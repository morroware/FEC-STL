<?php
/**
 * Community 3D Model Vault - Database Operations
 * Hybrid MySQL/JSON storage with automatic fallback
 * Uses MySQL if configured, falls back to JSON otherwise
 */

require_once __DIR__ . '/config.php';

// Check if MySQL is configured and available
$useMySql = false;
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';

    // Test if MySQL is actually configured (not placeholder values)
    if (defined('DB_PASS') && DB_PASS !== 'your_password_here') {
        // Try to connect to verify MySQL is available
        try {
            @$testConn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$testConn->connect_error) {
                $useMySql = true;
                $testConn->close();
            }
        } catch (Exception $e) {
            // MySQL not available, will fall back to JSON
        }
    }
}

// Set storage mode constant
define('USE_MYSQL', $useMySql);

if (USE_MYSQL) {
    // ============================================================================
    // MYSQL IMPLEMENTATION
    // ============================================================================

    /**
     * User Operations
     */
    function getUsers(): array {
        $conn = getDbConnection();
        $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = formatUserRow($row);
        }

        return $users;
    }

    function getUser(string $id): ?array {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return formatUserRow($row);
        }

        return null;
    }

    function getUserByUsername(string $username): ?array {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return formatUserRow($row);
        }

        return null;
    }

    function getUserByEmail(string $email): ?array {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return formatUserRow($row);
        }

        return null;
    }

    function createUser(array $data): ?string {
        $conn = getDbConnection();
        $id = generateId();

        // Ensure approved column exists
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'approved'");
        if ($result->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN approved TINYINT(1) DEFAULT 1");
        }

        // Determine if user needs approval (skip for admins or if using invite code)
        $needsApproval = !empty($data['needs_approval']);
        $approved = $needsApproval ? 0 : 1;

        $stmt = $conn->prepare("INSERT INTO users (id, username, email, password, is_admin, approved, avatar, bio, location, model_count, download_count) VALUES (?, ?, ?, ?, 0, ?, NULL, '', '', 0, 0)");
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt->bind_param("ssssi", $id, $data['username'], $data['email'], $password, $approved);

        if ($stmt->execute()) {
            return $id;
        }

        return null;
    }

    function updateUser(string $id, array $data): bool {
        $conn = getDbConnection();

        // Build dynamic UPDATE query
        $fields = [];
        $values = [];
        $types = '';

        foreach ($data as $key => $value) {
            if ($key === 'id') continue; // Don't update ID
            $fields[] = "`$key` = ?";
            $values[] = $value;
            $types .= is_int($value) || is_bool($value) ? 'i' : 's';
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $types .= 's';

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);

        return $stmt->execute();
    }

    function deleteUser(string $id): bool {
        $conn = getDbConnection();
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }

    function authenticateUser(string $username, string $password): ?array {
        $user = getUserByUsername($username);
        if (!$user) $user = getUserByEmail($username);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }

    function toggleFavorite(string $userId, string $modelId): bool {
        $conn = getDbConnection();

        // Check if already favorited
        $stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND model_id = ?");
        $stmt->bind_param("ss", $userId, $modelId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Remove favorite
            $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND model_id = ?");
            $stmt->bind_param("ss", $userId, $modelId);
            return $stmt->execute();
        } else {
            // Add favorite
            $stmt = $conn->prepare("INSERT INTO favorites (user_id, model_id) VALUES (?, ?)");
            $stmt->bind_param("ss", $userId, $modelId);
            return $stmt->execute();
        }
    }

    function formatUserRow(array $row): array {
        // Get user's favorites
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT model_id FROM favorites WHERE user_id = ?");
        $stmt->bind_param("s", $row['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $favorites = [];
        while ($favRow = $result->fetch_assoc()) {
            $favorites[] = $favRow['model_id'];
        }

        $row['favorites'] = $favorites;
        $row['is_admin'] = (bool)$row['is_admin'];

        return $row;
    }

    /**
     * Category Operations
     */
    function getCategories(): array {
        $conn = getDbConnection();
        $result = $conn->query("SELECT * FROM categories ORDER BY name");

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        return $categories;
    }

    function getCategory(string $id): ?array {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row;
        }

        return null;
    }

    function createCategory(array $data): bool {
        $conn = getDbConnection();
        $id = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $data['name'])));

        // Ensure unique ID
        $baseId = $id;
        $counter = 1;
        while (getCategory($id)) {
            $id = $baseId . '-' . $counter++;
        }

        $icon = $data['icon'] ?? 'fa-cube';
        $description = $data['description'] ?? '';

        $stmt = $conn->prepare("INSERT INTO categories (id, name, icon, description, count) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("ssss", $id, $data['name'], $icon, $description);

        return $stmt->execute();
    }

    function updateCategory(string $id, array $data): bool {
        $conn = getDbConnection();

        // Build dynamic UPDATE query
        $fields = [];
        $values = [];
        $types = '';

        foreach ($data as $key => $value) {
            if ($key === 'id') continue; // Don't update ID
            $fields[] = "`$key` = ?";
            $values[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $types .= 's';

        $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);

        return $stmt->execute();
    }

    function deleteCategory(string $id): bool {
        $conn = getDbConnection();
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }

    function updateCategoryCount(string $categoryId, int $delta): void {
        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE categories SET count = GREATEST(0, count + ?) WHERE id = ?");
        $stmt->bind_param("is", $delta, $categoryId);
        $stmt->execute();
    }

    /**
     * Model Operations
     */
    function getModels(): array {
        $conn = getDbConnection();
        $result = $conn->query("SELECT * FROM models ORDER BY created_at DESC");

        $models = [];
        while ($row = $result->fetch_assoc()) {
            $models[] = formatModelRow($row);
        }

        return $models;
    }

    function getModel(string $id): ?array {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM models WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return formatModelRow($row);
        }

        return null;
    }

    function getModelsByUser(string $userId): array {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM models WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $models = [];
        while ($row = $result->fetch_assoc()) {
            $models[] = formatModelRow($row);
        }

        return $models;
    }

    function getModelsByCategory(string $categoryId): array {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM models WHERE category = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();

        $models = [];
        while ($row = $result->fetch_assoc()) {
            $models[] = formatModelRow($row);
        }

        return $models;
    }

    function searchModels(string $query, ?string $category = null, string $sort = 'newest'): array {
        $conn = getDbConnection();

        // Build query
        $sql = "SELECT * FROM models WHERE 1=1";
        $params = [];
        $types = '';

        // Search by query
        if ($query) {
            $sql .= " AND (title LIKE ? OR description LIKE ?)";
            $searchTerm = "%$query%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }

        // Filter by category
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= 's';
        }

        // Sorting
        switch ($sort) {
            case 'oldest':
                $sql .= " ORDER BY created_at ASC";
                break;
            case 'popular':
                $sql .= " ORDER BY downloads DESC";
                break;
            case 'likes':
                $sql .= " ORDER BY likes DESC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY created_at DESC";
        }

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $models = [];
        while ($row = $result->fetch_assoc()) {
            $models[] = formatModelRow($row);
        }

        return $models;
    }

    function createModel(array $data): ?string {
        $conn = getDbConnection();
        $id = generateId();

        // Support both single file (legacy) and multiple files
        $files = [];
        if (!empty($data['files'])) {
            $files = $data['files'];
        } elseif (!empty($data['filename'])) {
            $files = [[
                'filename' => $data['filename'],
                'filesize' => $data['filesize'],
                'original_name' => $data['original_name'] ?? $data['filename'],
                'extension' => pathinfo($data['filename'], PATHINFO_EXTENSION),
                'has_color' => false
            ]];
        }

        // Handle multiple photos
        $photos = [];
        if (!empty($data['photos'])) {
            $photos = $data['photos'];
        } elseif (!empty($data['photo'])) {
            $photos = [$data['photo']];
        }

        // Calculate totals
        $totalSize = array_sum(array_column($files, 'filesize'));
        $fileCount = count($files);
        $primaryFilename = $files[0]['filename'] ?? '';
        $primaryPhoto = $photos[0] ?? null;

        // Prepare JSON fields
        $tagsJson = json_encode($data['tags'] ?? []);
        $printSettingsJson = json_encode($data['print_settings'] ?? []);

        // Insert main model record
        $stmt = $conn->prepare("
            INSERT INTO models
            (id, user_id, title, description, category, tags, filename, filesize, file_count,
             thumbnail, photo, primary_display, license, print_settings,
             downloads, likes, views, featured)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0)
        ");

        $description = $data['description'] ?? '';
        $thumbnail = $data['thumbnail'] ?? null;
        $primaryDisplay = $data['primary_display'] ?? 'auto';
        $license = $data['license'] ?? 'CC BY-NC';

        $stmt->bind_param(
            "sssssssiisssss",
            $id,
            $data['user_id'],
            $data['title'],
            $description,
            $data['category'],
            $tagsJson,
            $primaryFilename,
            $totalSize,
            $fileCount,
            $thumbnail,
            $primaryPhoto,
            $primaryDisplay,
            $license,
            $printSettingsJson
        );

        if (!$stmt->execute()) {
            return null;
        }

        // Insert files
        if (!empty($files)) {
            $fileStmt = $conn->prepare("
                INSERT INTO model_files
                (model_id, filename, filesize, original_name, extension, has_color, file_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($files as $index => $file) {
                $hasColor = (int)($file['has_color'] ?? false);
                $filesize = (int)$file['filesize'];
                $fileOrder = (int)$index;
                $fileStmt->bind_param(
                    "ssissii",
                    $id,
                    $file['filename'],
                    $filesize,
                    $file['original_name'],
                    $file['extension'],
                    $hasColor,
                    $fileOrder
                );
                $fileStmt->execute();
            }
        }

        // Insert photos
        if (!empty($photos)) {
            $photoStmt = $conn->prepare("
                INSERT INTO model_photos
                (model_id, filename, is_primary, photo_order)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($photos as $index => $photo) {
                $isPrimary = (int)($index === 0);
                $photoOrder = (int)$index;
                $photoStmt->bind_param("ssii", $id, $photo, $isPrimary, $photoOrder);
                $photoStmt->execute();
            }
        }

        // Update category count and user model count
        updateCategoryCount($data['category'], 1);
        $conn->query("UPDATE users SET model_count = model_count + 1 WHERE id = '{$data['user_id']}'");

        return $id;
    }

    function updateModel(string $id, array $data): bool {
        $conn = getDbConnection();
        $model = getModel($id);
        if (!$model) return false;

        $oldCategory = $model['category'];

        // Build dynamic UPDATE query
        $fields = [];
        $values = [];
        $types = '';

        foreach ($data as $key => $value) {
            if ($key === 'id' || $key === 'files' || $key === 'photos') continue;

            if ($key === 'tags' || $key === 'print_settings') {
                $value = json_encode($value);
            }

            $fields[] = "`$key` = ?";
            $values[] = $value;
            $types .= (is_int($value) || is_bool($value)) ? 'i' : 's';
        }

        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        $types .= 's';

        $sql = "UPDATE models SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();

        // Update category counts if category changed
        if (isset($data['category']) && $data['category'] !== $oldCategory) {
            updateCategoryCount($oldCategory, -1);
            updateCategoryCount($data['category'], 1);
        }

        return $result;
    }

    function deleteModel(string $id): bool {
        $model = getModel($id);
        if (!$model) return false;

        $conn = getDbConnection();

        // Delete model (cascade will delete files and photos)
        $stmt = $conn->prepare("DELETE FROM models WHERE id = ?");
        $stmt->bind_param("s", $id);

        if ($stmt->execute()) {
            updateCategoryCount($model['category'], -1);

            // Delete physical files
            foreach ($model['files'] as $file) {
                $filepath = UPLOADS_DIR . $file['filename'];
                if (file_exists($filepath)) unlink($filepath);
            }

            foreach ($model['photos'] as $photo) {
                $photopath = UPLOADS_DIR . $photo;
                if (file_exists($photopath)) unlink($photopath);
            }

            return true;
        }

        return false;
    }

    /**
     * Add a file to an existing model
     */
    function addModelFile(string $modelId, array $fileData): bool {
        $conn = getDbConnection();

        // Get current max file_order
        $stmt = $conn->prepare("SELECT MAX(file_order) as max_order FROM model_files WHERE model_id = ?");
        $stmt->bind_param("s", $modelId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $nextOrder = ($result['max_order'] ?? -1) + 1;

        // Insert new file
        $stmt = $conn->prepare("
            INSERT INTO model_files (model_id, filename, filesize, original_name, extension, has_color, file_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $hasColor = (int)($fileData['has_color'] ?? false);
        $filesize = (int)$fileData['filesize'];

        $stmt->bind_param(
            "ssissii",
            $modelId,
            $fileData['filename'],
            $filesize,
            $fileData['original_name'],
            $fileData['extension'],
            $hasColor,
            $nextOrder
        );

        if ($stmt->execute()) {
            // Update model's total filesize and file_count
            $conn->query("UPDATE models SET
                filesize = (SELECT COALESCE(SUM(filesize), 0) FROM model_files WHERE model_id = '$modelId'),
                file_count = (SELECT COUNT(*) FROM model_files WHERE model_id = '$modelId'),
                updated_at = NOW()
                WHERE id = '$modelId'");
            return true;
        }

        return false;
    }

    /**
     * Remove a file from a model
     */
    function removeModelFile(string $modelId, string $filename): bool {
        $conn = getDbConnection();

        // Check if model has more than one file (can't remove the last file)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM model_files WHERE model_id = ?");
        $stmt->bind_param("s", $modelId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];

        if ($count <= 1) {
            return false; // Can't remove the last file
        }

        // Delete from database
        $stmt = $conn->prepare("DELETE FROM model_files WHERE model_id = ? AND filename = ?");
        $stmt->bind_param("ss", $modelId, $filename);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Delete physical file
            $filepath = UPLOADS_DIR . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Update model's total filesize and file_count
            $conn->query("UPDATE models SET
                filesize = (SELECT COALESCE(SUM(filesize), 0) FROM model_files WHERE model_id = '$modelId'),
                file_count = (SELECT COUNT(*) FROM model_files WHERE model_id = '$modelId'),
                updated_at = NOW()
                WHERE id = '$modelId'");

            // Re-order remaining files
            $stmt = $conn->prepare("SELECT filename FROM model_files WHERE model_id = ? ORDER BY file_order");
            $stmt->bind_param("s", $modelId);
            $stmt->execute();
            $result = $stmt->get_result();

            $order = 0;
            while ($row = $result->fetch_assoc()) {
                $conn->query("UPDATE model_files SET file_order = $order WHERE model_id = '$modelId' AND filename = '{$row['filename']}'");
                $order++;
            }

            return true;
        }

        return false;
    }

    /**
     * Add a photo to an existing model
     */
    function addModelPhoto(string $modelId, string $filename): bool {
        $conn = getDbConnection();

        // Get current max photo_order
        $stmt = $conn->prepare("SELECT MAX(photo_order) as max_order FROM model_photos WHERE model_id = ?");
        $stmt->bind_param("s", $modelId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $nextOrder = ($result['max_order'] ?? -1) + 1;

        // Check if this is the first photo (will be primary)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM model_photos WHERE model_id = ?");
        $stmt->bind_param("s", $modelId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        $isPrimary = ($count === 0) ? 1 : 0;

        // Insert new photo
        $stmt = $conn->prepare("INSERT INTO model_photos (model_id, filename, is_primary, photo_order) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $modelId, $filename, $isPrimary, $nextOrder);

        if ($stmt->execute()) {
            // Update model's photo field if this is the first/primary photo
            if ($isPrimary) {
                $conn->query("UPDATE models SET photo = '$filename', updated_at = NOW() WHERE id = '$modelId'");
            } else {
                $conn->query("UPDATE models SET updated_at = NOW() WHERE id = '$modelId'");
            }
            return true;
        }

        return false;
    }

    /**
     * Remove a photo from a model
     */
    function removeModelPhoto(string $modelId, string $filename): bool {
        $conn = getDbConnection();

        // Check if this photo was primary
        $stmt = $conn->prepare("SELECT is_primary FROM model_photos WHERE model_id = ? AND filename = ?");
        $stmt->bind_param("ss", $modelId, $filename);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $wasPrimary = $result ? (bool)$result['is_primary'] : false;

        // Delete from database
        $stmt = $conn->prepare("DELETE FROM model_photos WHERE model_id = ? AND filename = ?");
        $stmt->bind_param("ss", $modelId, $filename);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Delete physical file
            $filepath = UPLOADS_DIR . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Re-order remaining photos and set new primary if needed
            $stmt = $conn->prepare("SELECT filename FROM model_photos WHERE model_id = ? ORDER BY photo_order");
            $stmt->bind_param("s", $modelId);
            $stmt->execute();
            $result = $stmt->get_result();

            $order = 0;
            $newPrimary = null;
            while ($row = $result->fetch_assoc()) {
                $isPrimary = ($order === 0) ? 1 : 0;
                if ($order === 0) $newPrimary = $row['filename'];
                $conn->query("UPDATE model_photos SET photo_order = $order, is_primary = $isPrimary WHERE model_id = '$modelId' AND filename = '{$row['filename']}'");
                $order++;
            }

            // Update model's photo field
            if ($newPrimary) {
                $conn->query("UPDATE models SET photo = '$newPrimary', updated_at = NOW() WHERE id = '$modelId'");
            } else {
                $conn->query("UPDATE models SET photo = NULL, updated_at = NOW() WHERE id = '$modelId'");
            }

            return true;
        }

        return false;
    }

    function incrementModelStat(string $id, string $stat): bool {
        $conn = getDbConnection();
        $allowedStats = ['downloads', 'likes', 'views'];

        if (!in_array($stat, $allowedStats)) return false;

        $stmt = $conn->prepare("UPDATE models SET `$stat` = `$stat` + 1 WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }

    function formatModelRow(array $row): array {
        // Decode JSON fields
        $row['tags'] = json_decode($row['tags'] ?? '[]', true) ?? [];
        $row['print_settings'] = json_decode($row['print_settings'] ?? '{}', true) ?? [];

        // Get files
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM model_files WHERE model_id = ? ORDER BY file_order");
        $stmt->bind_param("s", $row['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $files = [];
        while ($fileRow = $result->fetch_assoc()) {
            unset($fileRow['id'], $fileRow['model_id']);
            $fileRow['has_color'] = (bool)$fileRow['has_color'];
            $files[] = $fileRow;
        }
        $row['files'] = $files;

        // Get photos
        $stmt = $conn->prepare("SELECT filename FROM model_photos WHERE model_id = ? ORDER BY photo_order");
        $stmt->bind_param("s", $row['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $photos = [];
        while ($photoRow = $result->fetch_assoc()) {
            $photos[] = $photoRow['filename'];
        }
        $row['photos'] = $photos;

        // Legacy compatibility
        $row['images'] = $photos; // Alias for backwards compat
        $row['featured'] = (bool)$row['featured'];

        return $row;
    }

    /**
     * Statistics
     */
    function getStats(): array {
        $conn = getDbConnection();

        $totalModels = $conn->query("SELECT COUNT(*) as count FROM models")->fetch_assoc()['count'];
        $totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
        $totalDownloads = $conn->query("SELECT SUM(downloads) as total FROM models")->fetch_assoc()['total'] ?? 0;
        $totalCategories = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];

        return [
            'total_models' => $totalModels,
            'total_users' => $totalUsers,
            'total_downloads' => $totalDownloads,
            'total_categories' => $totalCategories
        ];
    }

    /**
     * Settings Operations (MySQL)
     */
    function getSettings(): array {
        $conn = getDbConnection();

        // Check if settings table exists
        $result = $conn->query("SHOW TABLES LIKE 'settings'");
        if ($result->num_rows === 0) {
            // Create settings table
            $conn->query("
                CREATE TABLE IF NOT EXISTS settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT,
                    setting_type VARCHAR(20) DEFAULT 'string',
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            // Initialize with defaults
            initializeSettings();
        }

        $result = $conn->query("SELECT * FROM settings");
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = castSettingValue($row['setting_value'], $row['setting_type']);
        }

        return array_merge(getDefaultSettings(), $settings);
    }

    function getSetting(string $key, $default = null) {
        $settings = getSettings();
        return $settings[$key] ?? $default;
    }

    function setSetting(string $key, $value): bool {
        $conn = getDbConnection();
        $type = getSettingType($value);
        $stringValue = convertSettingToString($value, $type);

        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?
        ");
        $stmt->bind_param("sssss", $key, $stringValue, $type, $stringValue, $type);
        return $stmt->execute();
    }

    function setSettings(array $settings): bool {
        foreach ($settings as $key => $value) {
            if (!setSetting($key, $value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Invite Code Operations (MySQL)
     */
    function ensureInvitesTable(): void {
        $conn = getDbConnection();
        $result = $conn->query("SHOW TABLES LIKE 'invites'");
        if ($result->num_rows === 0) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS invites (
                    id VARCHAR(32) PRIMARY KEY,
                    code VARCHAR(20) UNIQUE NOT NULL,
                    created_by VARCHAR(32) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL,
                    max_uses INT DEFAULT 1,
                    uses INT DEFAULT 0,
                    note VARCHAR(255) DEFAULT '',
                    active TINYINT(1) DEFAULT 1,
                    INDEX (code),
                    INDEX (active)
                )
            ");
        }
    }

    function getInvites(): array {
        ensureInvitesTable();
        $conn = getDbConnection();
        $result = $conn->query("SELECT * FROM invites ORDER BY created_at DESC");
        $invites = [];
        while ($row = $result->fetch_assoc()) {
            $row['active'] = (bool)$row['active'];
            $invites[] = $row;
        }
        return $invites;
    }

    function getInvite(string $id): ?array {
        ensureInvitesTable();
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM invites WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $row['active'] = (bool)$row['active'];
            return $row;
        }
        return null;
    }

    function getInviteByCode(string $code): ?array {
        ensureInvitesTable();
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM invites WHERE code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $row['active'] = (bool)$row['active'];
            return $row;
        }
        return null;
    }

    function createInvite(array $data): ?string {
        ensureInvitesTable();
        $conn = getDbConnection();
        $id = generateId();
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $expiresAt = null;
        if (!empty($data['expires_days'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$data['expires_days'] . ' days'));
        }

        $stmt = $conn->prepare("
            INSERT INTO invites (id, code, created_by, expires_at, max_uses, note, active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $maxUses = (int)($data['max_uses'] ?? 1);
        $note = $data['note'] ?? '';
        $stmt->bind_param("ssssis", $id, $code, $data['created_by'], $expiresAt, $maxUses, $note);

        return $stmt->execute() ? $code : null;
    }

    function useInviteCode(string $code): bool {
        $invite = getInviteByCode($code);
        if (!$invite || !isInviteValid($invite)) {
            return false;
        }

        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE invites SET uses = uses + 1 WHERE code = ?");
        $stmt->bind_param("s", $code);
        return $stmt->execute();
    }

    function deleteInvite(string $id): bool {
        $conn = getDbConnection();
        $stmt = $conn->prepare("DELETE FROM invites WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }

    function toggleInvite(string $id): bool {
        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE invites SET active = NOT active WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }

    /**
     * Pending Users Operations (MySQL)
     */
    function getPendingUsers(): array {
        $conn = getDbConnection();
        // Check if approved column exists
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'approved'");
        if ($result->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN approved TINYINT(1) DEFAULT 1");
            return []; // No pending users if column just added
        }

        $result = $conn->query("SELECT * FROM users WHERE approved = 0 ORDER BY created_at DESC");
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = formatUserRow($row);
        }
        return $users;
    }

    function approveUser(string $id): bool {
        $conn = getDbConnection();
        $stmt = $conn->prepare("UPDATE users SET approved = 1 WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }

    function rejectUser(string $id): bool {
        // Delete the user and their data
        return deleteUser($id);
    }

    function isUserApproved(string $id): bool {
        $conn = getDbConnection();
        // Check if approved column exists
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'approved'");
        if ($result->num_rows === 0) {
            return true; // No approval system = all approved
        }

        $stmt = $conn->prepare("SELECT approved FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return (bool)$row['approved'];
        }
        return false;
    }

} else {
    // ============================================================================
    // JSON FALLBACK IMPLEMENTATION
    // ============================================================================

    // File paths
    define('USERS_FILE', DATA_DIR . 'users.json');
    define('MODELS_FILE', DATA_DIR . 'models.json');
    define('CATEGORIES_FILE', DATA_DIR . 'categories.json');

    /**
     * Generic JSON file operations
     */
    function readJsonFile(string $file): array {
        if (!file_exists($file)) return [];
        $content = file_get_contents($file);
        return json_decode($content, true) ?? [];
    }

    function writeJsonFile(string $file, array $data): bool {
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Initialize default data
     */
    function initializeData(): void {
        // Default categories
        if (!file_exists(CATEGORIES_FILE)) {
            $categories = [
                ['id' => 'arcade-parts', 'name' => 'Arcade Parts', 'icon' => 'fa-gamepad', 'description' => 'Buttons, joysticks, bezels, and arcade cabinet components', 'count' => 0],
                ['id' => 'redemption', 'name' => 'Redemption Games', 'icon' => 'fa-ticket', 'description' => 'Parts for ticket and prize redemption machines', 'count' => 0],
                ['id' => 'signage', 'name' => 'Signage & Displays', 'icon' => 'fa-sign', 'description' => 'Signs, toppers, marquees, and display pieces', 'count' => 0],
                ['id' => 'coin-op', 'name' => 'Coin-Op & Tokens', 'icon' => 'fa-coins', 'description' => 'Coin mechanisms, token holders, and cash handling', 'count' => 0],
                ['id' => 'maintenance', 'name' => 'Maintenance Tools', 'icon' => 'fa-wrench', 'description' => 'Tools and jigs for maintenance and repairs', 'count' => 0],
                ['id' => 'prizes', 'name' => 'Prize Displays', 'icon' => 'fa-gift', 'description' => 'Prize shelving, holders, and display units', 'count' => 0],
                ['id' => 'accessories', 'name' => 'Accessories', 'icon' => 'fa-puzzle-piece', 'description' => 'Cup holders, phone stands, and misc accessories', 'count' => 0],
                ['id' => 'other', 'name' => 'Other', 'icon' => 'fa-cube', 'description' => 'Miscellaneous 3D printable models', 'count' => 0],
            ];
            writeJsonFile(CATEGORIES_FILE, $categories);
        }

        // Default admin user
        if (!file_exists(USERS_FILE)) {
            $users = [
                [
                    'id' => 'admin',
                    'username' => 'admin',
                    'email' => 'admin@example.com',
                    'password' => password_hash('admin123', PASSWORD_DEFAULT),
                    'is_admin' => true,
                    'avatar' => null,
                    'bio' => 'Site Administrator',
                    'location' => 'HQ',
                    'created_at' => date('Y-m-d H:i:s'),
                    'model_count' => 0,
                    'download_count' => 0,
                    'favorites' => []
                ]
            ];
            writeJsonFile(USERS_FILE, $users);
        }

        // Empty models file
        if (!file_exists(MODELS_FILE)) {
            writeJsonFile(MODELS_FILE, []);
        }
    }

    // Initialize on include
    initializeData();

    /**
     * User Operations
     */
    function getUsers(): array {
        return readJsonFile(USERS_FILE);
    }

    function getUser(string $id): ?array {
        $users = getUsers();
        foreach ($users as $user) {
            if ($user['id'] === $id) return $user;
        }
        return null;
    }

    function getUserByUsername(string $username): ?array {
        $users = getUsers();
        foreach ($users as $user) {
            if (strtolower($user['username']) === strtolower($username)) return $user;
        }
        return null;
    }

    function getUserByEmail(string $email): ?array {
        $users = getUsers();
        foreach ($users as $user) {
            if (strtolower($user['email']) === strtolower($email)) return $user;
        }
        return null;
    }

    function createUser(array $data): ?string {
        $users = getUsers();
        $id = generateId();

        // Determine if user needs approval
        $needsApproval = !empty($data['needs_approval']);

        $user = [
            'id' => $id,
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_admin' => false,
            'approved' => !$needsApproval,
            'avatar' => null,
            'bio' => '',
            'location' => '',
            'website' => '',
            'twitter' => '',
            'github' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'model_count' => 0,
            'download_count' => 0,
            'favorites' => []
        ];

        $users[] = $user;
        return writeJsonFile(USERS_FILE, $users) ? $id : null;
    }

    function updateUser(string $id, array $data): bool {
        $users = getUsers();
        foreach ($users as &$user) {
            if ($user['id'] === $id) {
                $user = array_merge($user, $data);
                return writeJsonFile(USERS_FILE, $users);
            }
        }
        return false;
    }

    function deleteUser(string $id): bool {
        $users = getUsers();
        $users = array_filter($users, fn($u) => $u['id'] !== $id);
        return writeJsonFile(USERS_FILE, array_values($users));
    }

    function authenticateUser(string $username, string $password): ?array {
        $user = getUserByUsername($username);
        if (!$user) $user = getUserByEmail($username);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }

    function toggleFavorite(string $userId, string $modelId): bool {
        $users = getUsers();
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                if (!isset($user['favorites'])) $user['favorites'] = [];
                $key = array_search($modelId, $user['favorites']);
                if ($key !== false) {
                    unset($user['favorites'][$key]);
                    $user['favorites'] = array_values($user['favorites']);
                } else {
                    $user['favorites'][] = $modelId;
                }
                return writeJsonFile(USERS_FILE, $users);
            }
        }
        return false;
    }

    /**
     * Category Operations
     */
    function getCategories(): array {
        return readJsonFile(CATEGORIES_FILE);
    }

    function getCategory(string $id): ?array {
        $categories = getCategories();
        foreach ($categories as $cat) {
            if ($cat['id'] === $id) return $cat;
        }
        return null;
    }

    function createCategory(array $data): bool {
        $categories = getCategories();
        $id = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $data['name'])));

        // Ensure unique ID
        $baseId = $id;
        $counter = 1;
        while (getCategory($id)) {
            $id = $baseId . '-' . $counter++;
        }

        $categories[] = [
            'id' => $id,
            'name' => $data['name'],
            'icon' => $data['icon'] ?? 'fa-cube',
            'description' => $data['description'] ?? '',
            'count' => 0
        ];

        return writeJsonFile(CATEGORIES_FILE, $categories);
    }

    function updateCategory(string $id, array $data): bool {
        $categories = getCategories();
        foreach ($categories as &$cat) {
            if ($cat['id'] === $id) {
                $cat = array_merge($cat, $data);
                $cat['id'] = $id; // Preserve ID
                return writeJsonFile(CATEGORIES_FILE, $categories);
            }
        }
        return false;
    }

    function deleteCategory(string $id): bool {
        $categories = getCategories();
        $categories = array_filter($categories, fn($c) => $c['id'] !== $id);
        return writeJsonFile(CATEGORIES_FILE, array_values($categories));
    }

    function updateCategoryCount(string $categoryId, int $delta): void {
        $categories = getCategories();
        foreach ($categories as &$cat) {
            if ($cat['id'] === $categoryId) {
                $cat['count'] = max(0, ($cat['count'] ?? 0) + $delta);
                writeJsonFile(CATEGORIES_FILE, $categories);
                return;
            }
        }
    }

    /**
     * Model Operations
     */
    function getModels(): array {
        return readJsonFile(MODELS_FILE);
    }

    function getModel(string $id): ?array {
        $models = getModels();
        foreach ($models as $model) {
            if ($model['id'] === $id) return $model;
        }
        return null;
    }

    function getModelsByUser(string $userId): array {
        $models = getModels();
        return array_filter($models, fn($m) => $m['user_id'] === $userId);
    }

    function getModelsByCategory(string $categoryId): array {
        $models = getModels();
        return array_filter($models, fn($m) => $m['category'] === $categoryId);
    }

    function searchModels(string $query, ?string $category = null, string $sort = 'newest'): array {
        $models = getModels();
        $query = strtolower($query);

        // Filter by search query
        if ($query) {
            $models = array_filter($models, function($m) use ($query) {
                return strpos(strtolower($m['title']), $query) !== false ||
                       strpos(strtolower($m['description'] ?? ''), $query) !== false ||
                       in_array($query, array_map('strtolower', $m['tags'] ?? []));
            });
        }

        // Filter by category
        if ($category) {
            $models = array_filter($models, fn($m) => $m['category'] === $category);
        }

        // Sort
        $models = array_values($models);
        switch ($sort) {
            case 'oldest':
                usort($models, fn($a, $b) => strtotime($a['created_at']) - strtotime($b['created_at']));
                break;
            case 'popular':
                usort($models, fn($a, $b) => ($b['downloads'] ?? 0) - ($a['downloads'] ?? 0));
                break;
            case 'likes':
                usort($models, fn($a, $b) => ($b['likes'] ?? 0) - ($a['likes'] ?? 0));
                break;
            case 'newest':
            default:
                usort($models, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        }

        return $models;
    }

    function createModel(array $data): ?string {
        $models = getModels();
        $id = generateId();

        // Support both single file (legacy) and multiple files
        $files = [];
        if (!empty($data['files'])) {
            // Multiple files format
            $files = $data['files'];
        } elseif (!empty($data['filename'])) {
            // Legacy single file - convert to files array
            $files = [[
                'filename' => $data['filename'],
                'filesize' => $data['filesize'],
                'original_name' => $data['original_name'] ?? $data['filename']
            ]];
        }

        // Handle multiple photos
        $photos = [];
        if (!empty($data['photos'])) {
            $photos = $data['photos'];
        } elseif (!empty($data['photo'])) {
            // Single photo - convert to array
            $photos = [$data['photo']];
        }

        $model = [
            'id' => $id,
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'category' => $data['category'],
            'tags' => $data['tags'] ?? [],
            'files' => $files,
            'filename' => $files[0]['filename'] ?? '', // Primary file for backwards compat
            'filesize' => array_sum(array_column($files, 'filesize')), // Total size
            'file_count' => count($files),
            'thumbnail' => $data['thumbnail'] ?? null,
            'images' => $data['images'] ?? [],
            'photos' => $photos, // Array of photo filenames
            'photo' => $photos[0] ?? null, // Primary photo for backwards compat
            'primary_display' => $data['primary_display'] ?? 'auto', // 'auto', 'photo', or file index
            'license' => $data['license'] ?? 'CC BY-NC',
            'print_settings' => $data['print_settings'] ?? [],
            'downloads' => 0,
            'likes' => 0,
            'views' => 0,
            'featured' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $models[] = $model;
        if (writeJsonFile(MODELS_FILE, $models)) {
            updateCategoryCount($data['category'], 1);
            updateUser($data['user_id'], ['model_count' => (getUser($data['user_id'])['model_count'] ?? 0) + 1]);
            return $id;
        }
        return null;
    }

    function updateModel(string $id, array $data): bool {
        $models = getModels();
        foreach ($models as &$model) {
            if ($model['id'] === $id) {
                $oldCategory = $model['category'];
                $model = array_merge($model, $data);
                $model['id'] = $id;
                $model['updated_at'] = date('Y-m-d H:i:s');

                if (isset($data['category']) && $data['category'] !== $oldCategory) {
                    updateCategoryCount($oldCategory, -1);
                    updateCategoryCount($data['category'], 1);
                }

                return writeJsonFile(MODELS_FILE, $models);
            }
        }
        return false;
    }

    function deleteModel(string $id): bool {
        $model = getModel($id);
        if (!$model) return false;

        $models = getModels();
        $models = array_filter($models, fn($m) => $m['id'] !== $id);

        if (writeJsonFile(MODELS_FILE, array_values($models))) {
            updateCategoryCount($model['category'], -1);

            // Delete file
            $filepath = UPLOADS_DIR . $model['filename'];
            if (file_exists($filepath)) unlink($filepath);

            return true;
        }
        return false;
    }

    /**
     * Add a file to an existing model (JSON storage)
     */
    function addModelFile(string $modelId, array $fileData): bool {
        $models = getModels();
        foreach ($models as &$model) {
            if ($model['id'] === $modelId) {
                if (!isset($model['files'])) {
                    $model['files'] = [];
                }

                $model['files'][] = [
                    'filename' => $fileData['filename'],
                    'filesize' => (int)$fileData['filesize'],
                    'original_name' => $fileData['original_name'],
                    'extension' => $fileData['extension'],
                    'has_color' => $fileData['has_color'] ?? false,
                    'file_order' => count($model['files'])
                ];

                // Update totals
                $model['filesize'] = array_sum(array_column($model['files'], 'filesize'));
                $model['file_count'] = count($model['files']);
                $model['updated_at'] = date('Y-m-d H:i:s');

                return writeJsonFile(MODELS_FILE, $models);
            }
        }
        return false;
    }

    /**
     * Remove a file from a model (JSON storage)
     */
    function removeModelFile(string $modelId, string $filename): bool {
        $models = getModels();
        foreach ($models as &$model) {
            if ($model['id'] === $modelId) {
                if (!isset($model['files']) || count($model['files']) <= 1) {
                    return false; // Can't remove the last file
                }

                // Find and remove the file
                $found = false;
                $model['files'] = array_values(array_filter($model['files'], function($f) use ($filename, &$found) {
                    if ($f['filename'] === $filename) {
                        $found = true;
                        return false;
                    }
                    return true;
                }));

                if (!$found) return false;

                // Delete physical file
                $filepath = UPLOADS_DIR . $filename;
                if (file_exists($filepath)) unlink($filepath);

                // Update totals and re-order
                $model['filesize'] = array_sum(array_column($model['files'], 'filesize'));
                $model['file_count'] = count($model['files']);
                $model['filename'] = $model['files'][0]['filename'] ?? '';
                foreach ($model['files'] as $i => &$f) {
                    $f['file_order'] = $i;
                }
                $model['updated_at'] = date('Y-m-d H:i:s');

                return writeJsonFile(MODELS_FILE, $models);
            }
        }
        return false;
    }

    /**
     * Add a photo to an existing model (JSON storage)
     */
    function addModelPhoto(string $modelId, string $filename): bool {
        $models = getModels();
        foreach ($models as &$model) {
            if ($model['id'] === $modelId) {
                if (!isset($model['photos'])) {
                    $model['photos'] = [];
                }

                $model['photos'][] = $filename;

                // Update primary photo if this is the first
                if (count($model['photos']) === 1) {
                    $model['photo'] = $filename;
                }

                $model['updated_at'] = date('Y-m-d H:i:s');

                return writeJsonFile(MODELS_FILE, $models);
            }
        }
        return false;
    }

    /**
     * Remove a photo from a model (JSON storage)
     */
    function removeModelPhoto(string $modelId, string $filename): bool {
        $models = getModels();
        foreach ($models as &$model) {
            if ($model['id'] === $modelId) {
                if (!isset($model['photos'])) return false;

                // Find and remove the photo
                $found = false;
                $model['photos'] = array_values(array_filter($model['photos'], function($p) use ($filename, &$found) {
                    if ($p === $filename) {
                        $found = true;
                        return false;
                    }
                    return true;
                }));

                if (!$found) return false;

                // Delete physical file
                $filepath = UPLOADS_DIR . $filename;
                if (file_exists($filepath)) unlink($filepath);

                // Update primary photo
                $model['photo'] = $model['photos'][0] ?? null;
                $model['updated_at'] = date('Y-m-d H:i:s');

                return writeJsonFile(MODELS_FILE, $models);
            }
        }
        return false;
    }

    function incrementModelStat(string $id, string $stat): bool {
        $models = getModels();
        foreach ($models as &$model) {
            if ($model['id'] === $id) {
                $model[$stat] = ($model[$stat] ?? 0) + 1;
                return writeJsonFile(MODELS_FILE, $models);
            }
        }
        return false;
    }

    function getStats(): array {
        $models = getModels();
        $users = getUsers();

        $totalDownloads = array_sum(array_column($models, 'downloads'));

        return [
            'total_models' => count($models),
            'total_users' => count($users),
            'total_downloads' => $totalDownloads,
            'total_categories' => count(getCategories())
        ];
    }

    /**
     * Settings Operations (JSON)
     */
    define('SETTINGS_FILE', DATA_DIR . 'settings.json');

    function getSettings(): array {
        if (!file_exists(SETTINGS_FILE)) {
            initializeSettings();
        }
        $settings = readJsonFile(SETTINGS_FILE);
        return array_merge(getDefaultSettings(), $settings);
    }

    function getSetting(string $key, $default = null) {
        $settings = getSettings();
        return $settings[$key] ?? $default;
    }

    function setSetting(string $key, $value): bool {
        $settings = getSettings();
        $settings[$key] = $value;
        return writeJsonFile(SETTINGS_FILE, $settings);
    }

    function setSettings(array $newSettings): bool {
        $settings = getSettings();
        $settings = array_merge($settings, $newSettings);
        return writeJsonFile(SETTINGS_FILE, $settings);
    }

    /**
     * Invite Code Operations (JSON)
     */
    define('INVITES_FILE', DATA_DIR . 'invites.json');

    function getInvites(): array {
        if (!file_exists(INVITES_FILE)) {
            writeJsonFile(INVITES_FILE, []);
        }
        return readJsonFile(INVITES_FILE);
    }

    function getInvite(string $id): ?array {
        $invites = getInvites();
        foreach ($invites as $invite) {
            if ($invite['id'] === $id) return $invite;
        }
        return null;
    }

    function getInviteByCode(string $code): ?array {
        $invites = getInvites();
        foreach ($invites as $invite) {
            if ($invite['code'] === $code) return $invite;
        }
        return null;
    }

    function createInvite(array $data): ?string {
        $invites = getInvites();
        $id = generateId();
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $expiresAt = null;
        if (!empty($data['expires_days'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$data['expires_days'] . ' days'));
        }

        $invite = [
            'id' => $id,
            'code' => $code,
            'created_by' => $data['created_by'],
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'max_uses' => (int)($data['max_uses'] ?? 1),
            'uses' => 0,
            'note' => $data['note'] ?? '',
            'active' => true
        ];

        $invites[] = $invite;
        return writeJsonFile(INVITES_FILE, $invites) ? $code : null;
    }

    function useInviteCode(string $code): bool {
        $invites = getInvites();
        foreach ($invites as &$invite) {
            if ($invite['code'] === $code && isInviteValid($invite)) {
                $invite['uses']++;
                return writeJsonFile(INVITES_FILE, $invites);
            }
        }
        return false;
    }

    function deleteInvite(string $id): bool {
        $invites = getInvites();
        $invites = array_filter($invites, fn($i) => $i['id'] !== $id);
        return writeJsonFile(INVITES_FILE, array_values($invites));
    }

    function toggleInvite(string $id): bool {
        $invites = getInvites();
        foreach ($invites as &$invite) {
            if ($invite['id'] === $id) {
                $invite['active'] = !$invite['active'];
                return writeJsonFile(INVITES_FILE, $invites);
            }
        }
        return false;
    }

    /**
     * Pending Users Operations (JSON)
     */
    function getPendingUsers(): array {
        $users = getUsers();
        return array_filter($users, fn($u) => isset($u['approved']) && $u['approved'] === false);
    }

    function approveUser(string $id): bool {
        return updateUser($id, ['approved' => true]);
    }

    function rejectUser(string $id): bool {
        return deleteUser($id);
    }

    function isUserApproved(string $id): bool {
        $user = getUser($id);
        if (!$user) return false;
        // If no approved field, user is approved (legacy)
        return !isset($user['approved']) || $user['approved'] === true;
    }
}

/**
 * Shared Invite Helper Functions
 */
function isInviteValid(array $invite): bool {
    if (!$invite['active']) return false;
    if ($invite['max_uses'] > 0 && $invite['uses'] >= $invite['max_uses']) return false;
    if ($invite['expires_at'] && strtotime($invite['expires_at']) < time()) return false;
    return true;
}

function validateInviteCode(string $code): array {
    $invite = getInviteByCode($code);
    if (!$invite) {
        return ['valid' => false, 'error' => 'Invalid invite code'];
    }
    if (!$invite['active']) {
        return ['valid' => false, 'error' => 'This invite code has been deactivated'];
    }
    if ($invite['max_uses'] > 0 && $invite['uses'] >= $invite['max_uses']) {
        return ['valid' => false, 'error' => 'This invite code has reached its usage limit'];
    }
    if ($invite['expires_at'] && strtotime($invite['expires_at']) < time()) {
        return ['valid' => false, 'error' => 'This invite code has expired'];
    }
    return ['valid' => true, 'invite' => $invite];
}

/**
 * Shared Settings Helper Functions
 */
function getDefaultSettings(): array {
    return [
        // Site Configuration
        'site_name' => 'Community 3D Model Vault',
        'site_tagline' => 'Share. Print. Play.',
        'site_description' => 'A community-driven platform for sharing 3D printable models',
        'contact_email' => 'admin@example.com',
        'maintenance_mode' => false,
        'maintenance_message' => 'We are currently performing maintenance. Please check back soon.',

        // Registration & Users
        'allow_registration' => true,
        'require_admin_approval' => false,
        'require_email_verification' => false,
        'default_user_role' => 'user',

        // Upload Settings
        'max_file_size' => 50, // MB
        'max_files_per_model' => 10,
        'max_photos_per_model' => 5,
        'allowed_extensions' => 'stl,obj',

        // Feature Toggles
        'enable_downloads' => true,
        'enable_likes' => true,
        'enable_favorites' => true,
        'enable_comments' => false,
        'enable_user_profiles' => true,

        // Display Settings
        'items_per_page' => 12,
        'default_license' => 'CC BY-NC',
        'default_sort' => 'newest',
        'show_download_count' => true,
        'show_like_count' => true,
        'show_view_count' => true,

        // 3D Viewer Settings
        'default_model_color' => '#00ffff',
        'enable_auto_rotate' => false,
        'enable_wireframe_toggle' => true,
        'enable_grid' => true,
    ];
}

function initializeSettings(): void {
    $defaults = getDefaultSettings();
    if (USE_MYSQL) {
        $conn = getDbConnection();
        foreach ($defaults as $key => $value) {
            $type = getSettingType($value);
            $stringValue = convertSettingToString($value, $type);
            $stmt = $conn->prepare("
                INSERT IGNORE INTO settings (setting_key, setting_value, setting_type)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("sss", $key, $stringValue, $type);
            $stmt->execute();
        }
    } else {
        if (!defined('SETTINGS_FILE')) {
            define('SETTINGS_FILE', DATA_DIR . 'settings.json');
        }
        if (!file_exists(SETTINGS_FILE)) {
            file_put_contents(SETTINGS_FILE, json_encode($defaults, JSON_PRETTY_PRINT));
        }
    }
}

function getSettingType($value): string {
    if (is_bool($value)) return 'boolean';
    if (is_int($value)) return 'integer';
    if (is_float($value)) return 'float';
    if (is_array($value)) return 'json';
    return 'string';
}

function convertSettingToString($value, string $type): string {
    switch ($type) {
        case 'boolean':
            return $value ? '1' : '0';
        case 'json':
            return json_encode($value);
        default:
            return (string)$value;
    }
}

function castSettingValue(string $value, string $type) {
    switch ($type) {
        case 'boolean':
            return $value === '1' || $value === 'true';
        case 'integer':
            return (int)$value;
        case 'float':
            return (float)$value;
        case 'json':
            return json_decode($value, true);
        default:
            return $value;
    }
}

/**
 * Get all settings organized by category for admin UI
 */
function getSettingsSchema(): array {
    return [
        'site' => [
            'label' => 'Site Configuration',
            'icon' => 'fa-globe',
            'settings' => [
                'site_name' => ['label' => 'Site Name', 'type' => 'text', 'description' => 'The name of your site'],
                'site_tagline' => ['label' => 'Tagline', 'type' => 'text', 'description' => 'A short slogan or tagline'],
                'site_description' => ['label' => 'Description', 'type' => 'textarea', 'description' => 'Site description for SEO'],
                'contact_email' => ['label' => 'Contact Email', 'type' => 'email', 'description' => 'Primary contact email'],
                'maintenance_mode' => ['label' => 'Maintenance Mode', 'type' => 'toggle', 'description' => 'Put the site in maintenance mode'],
                'maintenance_message' => ['label' => 'Maintenance Message', 'type' => 'textarea', 'description' => 'Message shown during maintenance'],
            ]
        ],
        'users' => [
            'label' => 'Users & Registration',
            'icon' => 'fa-users',
            'settings' => [
                'allow_registration' => ['label' => 'Allow Registration', 'type' => 'toggle', 'description' => 'Allow new users to register publicly'],
                'require_admin_approval' => ['label' => 'Require Admin Approval', 'type' => 'toggle', 'description' => 'New users must be approved by admin before accessing the site'],
                'require_email_verification' => ['label' => 'Require Email Verification', 'type' => 'toggle', 'description' => 'Require email verification before login'],
                'enable_user_profiles' => ['label' => 'Enable User Profiles', 'type' => 'toggle', 'description' => 'Allow users to have public profiles'],
            ]
        ],
        'uploads' => [
            'label' => 'Upload Settings',
            'icon' => 'fa-upload',
            'settings' => [
                'max_file_size' => ['label' => 'Max File Size (MB)', 'type' => 'number', 'min' => 1, 'max' => 500, 'description' => 'Maximum upload size per file'],
                'max_files_per_model' => ['label' => 'Max Files per Model', 'type' => 'number', 'min' => 1, 'max' => 50, 'description' => 'Maximum 3D files per model upload'],
                'max_photos_per_model' => ['label' => 'Max Photos per Model', 'type' => 'number', 'min' => 1, 'max' => 20, 'description' => 'Maximum photos per model'],
                'allowed_extensions' => ['label' => 'Allowed File Types', 'type' => 'text', 'description' => 'Comma-separated list (e.g., stl,obj,3mf)'],
            ]
        ],
        'features' => [
            'label' => 'Features',
            'icon' => 'fa-toggle-on',
            'settings' => [
                'enable_downloads' => ['label' => 'Enable Downloads', 'type' => 'toggle', 'description' => 'Allow users to download models'],
                'enable_likes' => ['label' => 'Enable Likes', 'type' => 'toggle', 'description' => 'Allow users to like models'],
                'enable_favorites' => ['label' => 'Enable Favorites', 'type' => 'toggle', 'description' => 'Allow users to favorite models'],
                'enable_comments' => ['label' => 'Enable Comments', 'type' => 'toggle', 'description' => 'Allow comments on models (coming soon)'],
            ]
        ],
        'display' => [
            'label' => 'Display Settings',
            'icon' => 'fa-desktop',
            'settings' => [
                'items_per_page' => ['label' => 'Items Per Page', 'type' => 'select', 'options' => [6, 12, 24, 48], 'description' => 'Number of models shown per page'],
                'default_sort' => ['label' => 'Default Sort', 'type' => 'select', 'options' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'popular' => 'Most Downloaded', 'likes' => 'Most Liked'], 'description' => 'Default sort order for model listings'],
                'default_license' => ['label' => 'Default License', 'type' => 'select', 'options' => ['CC BY' => 'CC BY', 'CC BY-SA' => 'CC BY-SA', 'CC BY-NC' => 'CC BY-NC', 'CC BY-NC-SA' => 'CC BY-NC-SA', 'CC0' => 'CC0', 'MIT' => 'MIT', 'GPL' => 'GPL'], 'description' => 'Default license for new uploads'],
                'show_download_count' => ['label' => 'Show Download Count', 'type' => 'toggle', 'description' => 'Display download counts on models'],
                'show_like_count' => ['label' => 'Show Like Count', 'type' => 'toggle', 'description' => 'Display like counts on models'],
                'show_view_count' => ['label' => 'Show View Count', 'type' => 'toggle', 'description' => 'Display view counts on models'],
            ]
        ],
        'viewer' => [
            'label' => '3D Viewer',
            'icon' => 'fa-cube',
            'settings' => [
                'default_model_color' => ['label' => 'Default Model Color', 'type' => 'color', 'description' => 'Default color for 3D models'],
                'enable_auto_rotate' => ['label' => 'Auto-Rotate by Default', 'type' => 'toggle', 'description' => 'Enable auto-rotation on model viewer'],
                'enable_wireframe_toggle' => ['label' => 'Wireframe Toggle', 'type' => 'toggle', 'description' => 'Show wireframe toggle button'],
                'enable_grid' => ['label' => 'Show Grid', 'type' => 'toggle', 'description' => 'Show grid in 3D viewer by default'],
            ]
        ],
    ];
}
?>
