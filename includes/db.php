<?php
/**
 * FEC STL Share - Database Operations
 * JSON-based storage for PoC
 */

require_once __DIR__ . '/config.php';

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
