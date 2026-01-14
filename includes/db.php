<?php
/**
 * FEC STL Vault - Database Operations
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

        $stmt = $conn->prepare("INSERT INTO users (id, username, email, password, is_admin, avatar, bio, location, model_count, download_count) VALUES (?, ?, ?, ?, 0, NULL, '', '', 0, 0)");
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt->bind_param("ssss", $id, $data['username'], $data['email'], $password);

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
            "ssssssssissss",
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
                $hasColor = $file['has_color'] ?? false;
                $fileStmt->bind_param(
                    "ssissii",
                    $id,
                    $file['filename'],
                    $file['filesize'],
                    $file['original_name'],
                    $file['extension'],
                    $hasColor,
                    $index
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
                $isPrimary = ($index === 0);
                $photoStmt->bind_param("ssii", $id, $photo, $isPrimary, $index);
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
                ['id' => 'maintenance', 'name' => 'Maintenance Tools', 'icon' => 'fa-wrench', 'description' => 'Tools and jigs for FEC maintenance', 'count' => 0],
                ['id' => 'prizes', 'name' => 'Prize Displays', 'icon' => 'fa-gift', 'description' => 'Prize shelving, holders, and display units', 'count' => 0],
                ['id' => 'accessories', 'name' => 'Accessories', 'icon' => 'fa-puzzle-piece', 'description' => 'Cup holders, phone stands, and misc accessories', 'count' => 0],
                ['id' => 'other', 'name' => 'Other', 'icon' => 'fa-cube', 'description' => 'Miscellaneous FEC-related prints', 'count' => 0],
            ];
            writeJsonFile(CATEGORIES_FILE, $categories);
        }

        // Default admin user
        if (!file_exists(USERS_FILE)) {
            $users = [
                [
                    'id' => 'admin',
                    'username' => 'admin',
                    'email' => 'admin@fecvault.com',
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

        $user = [
            'id' => $id,
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_admin' => false,
            'avatar' => null,
            'bio' => '',
            'location' => '',
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
}
?>
