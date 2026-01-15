<?php
/**
 * Community 3D Model Vault - API Endpoint
 * Handles all AJAX requests
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Set JSON header
header('Content-Type: application/json');

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ========================================================================
    // AUTHENTICATION
    // ========================================================================
    
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!$username || !$password) {
            jsonResponse(['success' => false, 'error' => 'Please fill in all fields'], 400);
        }
        
        $user = authenticateUser($username, $password);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_admin'] = $user['is_admin'] ?? false;
            jsonResponse([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'is_admin' => $user['is_admin'] ?? false
                ]
            ]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Invalid credentials'], 401);
        }
        break;
        
    case 'register':
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validation
        if (!$username || !$email || !$password) {
            jsonResponse(['success' => false, 'error' => 'Please fill in all fields'], 400);
        }
        
        if (strlen($username) < 3 || strlen($username) > 20) {
            jsonResponse(['success' => false, 'error' => 'Username must be 3-20 characters'], 400);
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            jsonResponse(['success' => false, 'error' => 'Username can only contain letters, numbers, and underscores'], 400);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'error' => 'Invalid email address'], 400);
        }
        
        if (strlen($password) < 6) {
            jsonResponse(['success' => false, 'error' => 'Password must be at least 6 characters'], 400);
        }
        
        // Check existing
        if (getUserByUsername($username)) {
            jsonResponse(['success' => false, 'error' => 'Username already taken'], 400);
        }
        
        if (getUserByEmail($email)) {
            jsonResponse(['success' => false, 'error' => 'Email already registered'], 400);
        }
        
        // Create user
        $userId = createUser([
            'username' => $username,
            'email' => $email,
            'password' => $password
        ]);
        
        if ($userId) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['is_admin'] = false;
            jsonResponse(['success' => true, 'user_id' => $userId]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to create account'], 500);
        }
        break;
        
    case 'logout':
        session_destroy();
        jsonResponse(['success' => true]);
        break;
        
    case 'check_auth':
        if (isLoggedIn()) {
            $user = getCurrentUser();
            jsonResponse([
                'authenticated' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'is_admin' => $user['is_admin'] ?? false
                ]
            ]);
        } else {
            jsonResponse(['authenticated' => false]);
        }
        break;
    
    // ========================================================================
    // MODELS
    // ========================================================================
    
    case 'get_models':
        $query = $_POST['query'] ?? $_GET['query'] ?? '';
        $category = $_POST['category'] ?? $_GET['category'] ?? '';
        $sort = $_POST['sort'] ?? $_GET['sort'] ?? 'newest';
        $page = max(1, intval($_POST['page'] ?? $_GET['page'] ?? 1));
        $limit = min(50, max(1, intval($_POST['limit'] ?? $_GET['limit'] ?? 20)));
        
        $models = searchModels($query, $category ?: null, $sort);
        
        // Pagination
        $total = count($models);
        $totalPages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $models = array_slice($models, $offset, $limit);
        
        // Add user info
        foreach ($models as &$model) {
            $user = getUser($model['user_id']);
            $model['author'] = $user ? $user['username'] : 'Unknown';
        }
        
        jsonResponse([
            'success' => true,
            'models' => $models,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ]);
        break;
        
    case 'get_model':
        $id = $_POST['id'] ?? $_GET['id'] ?? '';
        $model = getModel($id);
        
        if ($model) {
            // Increment views
            incrementModelStat($id, 'views');
            $model['views']++;
            
            // Add user info
            $user = getUser($model['user_id']);
            $model['author'] = $user ? $user['username'] : 'Unknown';
            $model['author_avatar'] = $user['avatar'] ?? null;
            
            // Check if favorited by current user
            if (isLoggedIn()) {
                $currentUser = getCurrentUser();
                $model['is_favorited'] = in_array($id, $currentUser['favorites'] ?? []);
            }
            
            jsonResponse(['success' => true, 'model' => $model]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }
        break;
        
    case 'upload_model':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Please log in to upload'], 401);
        }
        
        // Validate required fields
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? '';
        
        if (!$title || !$category) {
            jsonResponse(['success' => false, 'error' => 'Title and category are required'], 400);
        }
        
        // Handle file upload
        $uploadKey = null;
        if (isset($_FILES['model_file']) && $_FILES['model_file']['error'] === UPLOAD_ERR_OK) {
            $uploadKey = 'model_file';
        } elseif (isset($_FILES['stl_file']) && $_FILES['stl_file']['error'] === UPLOAD_ERR_OK) {
            $uploadKey = 'stl_file';
        }

        if (!$uploadKey) {
            jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        $file = $_FILES[$uploadKey];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['stl', 'obj'];

        if (!in_array($ext, $allowedExtensions, true)) {
            jsonResponse(['success' => false, 'error' => 'Unsupported format. Allowed: ' . strtoupper(implode(', ', $allowedExtensions))], 400);
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            jsonResponse(['success' => false, 'error' => 'File too large (max 50MB)'], 400);
        }
        
        // Generate unique filename
        $filename = generateId() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
        $filepath = UPLOADS_DIR . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            jsonResponse(['success' => false, 'error' => 'Failed to save file'], 500);
        }
        
        // Parse tags
        $tags = [];
        if (!empty($_POST['tags'])) {
            $tags = json_decode($_POST['tags'], true) ?? [];
        }
        
        // Parse print settings
        $printSettings = [];
        if (!empty($_POST['print_settings'])) {
            $printSettings = json_decode($_POST['print_settings'], true) ?? [];
        }
        
        // Create model
        $modelId = createModel([
            'user_id' => $_SESSION['user_id'],
            'title' => $title,
            'description' => $_POST['description'] ?? '',
            'category' => $category,
            'tags' => $tags,
            'filename' => $filename,
            'filesize' => $file['size'],
            'license' => $_POST['license'] ?? 'CC BY-NC',
            'print_settings' => $printSettings
        ]);
        
        if ($modelId) {
            jsonResponse(['success' => true, 'model_id' => $modelId]);
        } else {
            unlink($filepath); // Clean up
            jsonResponse(['success' => false, 'error' => 'Failed to create model'], 500);
        }
        break;
        
    case 'update_model':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        
        $id = $_POST['id'] ?? '';
        $model = getModel($id);
        
        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }
        
        // Check ownership or admin
        if ($model['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        
        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];
        if (isset($_POST['category'])) $data['category'] = $_POST['category'];
        if (isset($_POST['tags'])) $data['tags'] = json_decode($_POST['tags'], true) ?? [];
        if (isset($_POST['license'])) $data['license'] = $_POST['license'];
        
        if (updateModel($id, $data)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to update'], 500);
        }
        break;
        
    case 'delete_model':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        
        $id = $_POST['id'] ?? '';
        $model = getModel($id);
        
        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }
        
        // Check ownership or admin
        if ($model['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        
        if (deleteModel($id)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to delete'], 500);
        }
        break;
        
    case 'download_model':
        $id = $_POST['id'] ?? $_GET['id'] ?? '';
        $model = getModel($id);

        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }

        incrementModelStat($id, 'downloads');

        // Update user download count
        $author = getUser($model['user_id']);
        if ($author) {
            updateUser($model['user_id'], ['download_count' => ($author['download_count'] ?? 0) + 1]);
        }

        $downloadFile = $model['filename'];
        if (!empty($model['files']) && is_array($model['files'])) {
            $downloadFile = $model['files'][0]['filename'] ?? $downloadFile;
        }
        $downloadExt = strtolower(pathinfo($downloadFile, PATHINFO_EXTENSION)) ?: 'stl';

        jsonResponse([
            'success' => true,
            'download_url' => 'uploads/' . $downloadFile,
            'filename' => $model['title'] . '.' . $downloadExt
        ]);
        break;

    case 'download_model_zip':
        $id = $_POST['id'] ?? $_GET['id'] ?? '';
        $model = getModel($id);

        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }

        // Get files array
        $files = $model['files'] ?? [['filename' => $model['filename'], 'original_name' => pathinfo($model['filename'], PATHINFO_FILENAME), 'extension' => pathinfo($model['filename'], PATHINFO_EXTENSION)]];

        if (empty($files)) {
            jsonResponse(['success' => false, 'error' => 'No files to download'], 400);
        }

        // Increment download stats
        incrementModelStat($id, 'downloads');
        $author = getUser($model['user_id']);
        if ($author) {
            updateUser($model['user_id'], ['download_count' => ($author['download_count'] ?? 0) + 1]);
        }

        // Create ZIP file
        $zipFilename = sys_get_temp_dir() . '/' . generateId() . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFilename, ZipArchive::CREATE) !== true) {
            jsonResponse(['success' => false, 'error' => 'Failed to create ZIP file'], 500);
        }

        // Add files to ZIP
        foreach ($files as $file) {
            $filePath = UPLOADS_DIR . $file['filename'];
            if (file_exists($filePath)) {
                $extension = $file['extension'] ?? pathinfo($file['filename'], PATHINFO_EXTENSION);
                $archiveName = ($file['original_name'] ?? pathinfo($file['filename'], PATHINFO_FILENAME)) . '.' . $extension;
                $zip->addFile($filePath, $archiveName);
            }
        }

        $zip->close();

        // Serve the ZIP file
        $safeTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $model['title']);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.zip"');
        header('Content-Length: ' . filesize($zipFilename));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($zipFilename);
        unlink($zipFilename); // Clean up temp file
        exit;
        break;
        
    case 'like_model':
        $id = $_POST['id'] ?? '';
        $model = getModel($id);

        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }

        // Track likes in session to prevent spam (allows one like per model per session)
        if (!isset($_SESSION['liked_models'])) {
            $_SESSION['liked_models'] = [];
        }

        if (in_array($id, $_SESSION['liked_models'])) {
            jsonResponse([
                'success' => false,
                'error' => 'Already liked',
                'likes' => $model['likes'] ?? 0,
                'already_liked' => true
            ]);
            break;
        }

        // Record the like
        $_SESSION['liked_models'][] = $id;
        incrementModelStat($id, 'likes');

        jsonResponse([
            'success' => true,
            'likes' => ($model['likes'] ?? 0) + 1,
            'already_liked' => false
        ]);
        break;
        
    case 'check_liked':
        $id = $_POST['id'] ?? $_GET['id'] ?? '';
        $isLiked = isset($_SESSION['liked_models']) && in_array($id, $_SESSION['liked_models']);
        jsonResponse(['success' => true, 'is_liked' => $isLiked]);
        break;

    case 'favorite_model':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Please log in'], 401);
        }
        
        $id = $_POST['id'] ?? '';
        $model = getModel($id);
        
        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }
        
        toggleFavorite($_SESSION['user_id'], $id);
        
        $user = getCurrentUser();
        $isFavorited = in_array($id, $user['favorites'] ?? []);
        
        jsonResponse(['success' => true, 'is_favorited' => $isFavorited]);
        break;
    
    // ========================================================================
    // CATEGORIES
    // ========================================================================
    
    case 'get_categories':
        jsonResponse(['success' => true, 'categories' => getCategories()]);
        break;
        
    case 'create_category':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }
        
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            jsonResponse(['success' => false, 'error' => 'Name is required'], 400);
        }
        
        if (createCategory([
            'name' => $name,
            'icon' => $_POST['icon'] ?? 'fa-cube',
            'description' => $_POST['description'] ?? ''
        ])) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to create category'], 500);
        }
        break;
        
    case 'update_category':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }
        
        $id = $_POST['id'] ?? '';
        if (!getCategory($id)) {
            jsonResponse(['success' => false, 'error' => 'Category not found'], 404);
        }
        
        $data = [];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['icon'])) $data['icon'] = $_POST['icon'];
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];
        
        if (updateCategory($id, $data)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to update'], 500);
        }
        break;
        
    case 'delete_category':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }
        
        $id = $_POST['id'] ?? '';
        
        // Check if category has models
        $models = getModelsByCategory($id);
        if (count($models) > 0) {
            jsonResponse(['success' => false, 'error' => 'Cannot delete category with models'], 400);
        }
        
        if (deleteCategory($id)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to delete'], 500);
        }
        break;
    
    // ========================================================================
    // USERS
    // ========================================================================
    
    case 'get_users':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }
        
        $users = getUsers();
        // Remove passwords
        $users = array_map(function($u) {
            unset($u['password']);
            return $u;
        }, $users);
        
        jsonResponse(['success' => true, 'users' => $users]);
        break;
        
    case 'get_user':
        $id = $_POST['id'] ?? $_GET['id'] ?? '';
        $user = getUser($id);
        
        if ($user) {
            unset($user['password']);
            unset($user['email']); // Privacy
            
            // Get user's models
            $models = array_values(getModelsByUser($id));
            $user['models'] = $models;
            
            jsonResponse(['success' => true, 'user' => $user]);
        } else {
            jsonResponse(['success' => false, 'error' => 'User not found'], 404);
        }
        break;
        
    case 'update_user':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $id = $_POST['id'] ?? '';

        // Check ownership or admin
        if ($id !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $data = [];
        if (isset($_POST['bio'])) $data['bio'] = $_POST['bio'];
        if (isset($_POST['location'])) $data['location'] = $_POST['location'];
        if (isset($_POST['website'])) $data['website'] = $_POST['website'];
        if (isset($_POST['twitter'])) $data['twitter'] = $_POST['twitter'];
        if (isset($_POST['github'])) $data['github'] = $_POST['github'];

        // Admin-only fields
        if (isAdmin()) {
            if (isset($_POST['is_admin'])) $data['is_admin'] = $_POST['is_admin'] === 'true';
        }

        if (updateUser($id, $data)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to update'], 500);
        }
        break;

    case 'upload_avatar':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Please log in'], 401);
        }

        $userId = $_SESSION['user_id'];

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        $file = $_FILES['avatar'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowedExtensions, true)) {
            jsonResponse(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'], 400);
        }

        // Max 2MB for avatar
        if ($file['size'] > 2 * 1024 * 1024) {
            jsonResponse(['success' => false, 'error' => 'File too large (max 2MB)'], 400);
        }

        // Generate unique filename
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $filepath = UPLOADS_DIR . $filename;

        // Delete old avatar if exists
        $user = getUser($userId);
        if (!empty($user['avatar'])) {
            $oldAvatar = UPLOADS_DIR . $user['avatar'];
            if (file_exists($oldAvatar)) {
                unlink($oldAvatar);
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            jsonResponse(['success' => false, 'error' => 'Failed to save file'], 500);
        }

        // Update user record
        if (updateUser($userId, ['avatar' => $filename])) {
            jsonResponse(['success' => true, 'avatar' => $filename]);
        } else {
            unlink($filepath);
            jsonResponse(['success' => false, 'error' => 'Failed to update profile'], 500);
        }
        break;
        
    case 'delete_user':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }
        
        $id = $_POST['id'] ?? '';
        
        // Don't allow deleting yourself
        if ($id === $_SESSION['user_id']) {
            jsonResponse(['success' => false, 'error' => 'Cannot delete your own account'], 400);
        }
        
        // Delete user's models first
        $models = getModelsByUser($id);
        foreach ($models as $model) {
            deleteModel($model['id']);
        }
        
        if (deleteUser($id)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to delete'], 500);
        }
        break;
    
    // ========================================================================
    // STATS
    // ========================================================================
    
    case 'get_stats':
        jsonResponse(['success' => true, 'stats' => getStats()]);
        break;
    
    // ========================================================================
    // DEFAULT
    // ========================================================================
    
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
