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

        // Add user info, category info, and time_ago
        foreach ($models as &$model) {
            $user = getUser($model['user_id']);
            $model['author'] = $user ? $user['username'] : 'Unknown';

            // Add category info for JS rendering
            $cat = getCategory($model['category']);
            $model['category_name'] = $cat ? $cat['name'] : '';
            $model['category_icon'] = $cat ? $cat['icon'] : 'fa-cube';

            // Add time_ago for JS rendering
            $model['time_ago'] = timeAgo($model['created_at']);
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
        if (isset($_POST['primary_display'])) $data['primary_display'] = $_POST['primary_display'];

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

    case 'add_model_file':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $modelId = $_POST['model_id'] ?? '';
        $model = getModel($modelId);

        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }

        // Check ownership or admin
        if ($model['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        $originalName = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $size = $_FILES['file']['size'];
        $tmpName = $_FILES['file']['tmp_name'];

        // Validate extension
        $allowedExtensions = ['stl', 'obj'];
        if (!in_array($extension, $allowedExtensions)) {
            jsonResponse(['success' => false, 'error' => 'Only STL and OBJ files allowed'], 400);
        }

        // Validate size (50MB max)
        if ($size > 50 * 1024 * 1024) {
            jsonResponse(['success' => false, 'error' => 'File too large (max 50MB)'], 400);
        }

        // Generate unique filename
        $newFilename = generateId() . '.' . $extension;
        $uploadPath = UPLOADS_DIR . $newFilename;

        if (move_uploaded_file($tmpName, $uploadPath)) {
            $fileData = [
                'filename' => $newFilename,
                'filesize' => $size,
                'original_name' => $originalName,
                'extension' => $extension,
                'has_color' => false
            ];

            if (addModelFile($modelId, $fileData)) {
                jsonResponse(['success' => true, 'file' => $fileData]);
            } else {
                unlink($uploadPath);
                jsonResponse(['success' => false, 'error' => 'Failed to add file to model'], 500);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to upload file'], 500);
        }
        break;

    case 'remove_model_file':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $modelId = $_POST['model_id'] ?? '';
        $filename = $_POST['filename'] ?? '';
        $model = getModel($modelId);

        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }

        // Check ownership or admin
        if ($model['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if (removeModelFile($modelId, $filename)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Cannot remove file (must have at least one)'], 400);
        }
        break;

    case 'add_model_photo':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $modelId = $_POST['model_id'] ?? '';
        $model = getModel($modelId);

        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }

        // Check ownership or admin
        if ($model['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'error' => 'No photo uploaded'], 400);
        }

        $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $size = $_FILES['photo']['size'];
        $tmpName = $_FILES['photo']['tmp_name'];

        // Validate extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            jsonResponse(['success' => false, 'error' => 'Only JPG, PNG, GIF, WebP allowed'], 400);
        }

        // Validate size (10MB max)
        if ($size > 10 * 1024 * 1024) {
            jsonResponse(['success' => false, 'error' => 'Photo too large (max 10MB)'], 400);
        }

        // Generate unique filename
        $newFilename = 'photo_' . generateId() . '.' . $extension;
        $uploadPath = UPLOADS_DIR . $newFilename;

        if (move_uploaded_file($tmpName, $uploadPath)) {
            if (addModelPhoto($modelId, $newFilename)) {
                jsonResponse(['success' => true, 'photo' => $newFilename]);
            } else {
                unlink($uploadPath);
                jsonResponse(['success' => false, 'error' => 'Failed to add photo to model'], 500);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to upload photo'], 500);
        }
        break;

    case 'remove_model_photo':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $modelId = $_POST['model_id'] ?? '';
        $filename = $_POST['filename'] ?? '';
        $model = getModel($modelId);

        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }

        // Check ownership or admin
        if ($model['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if (removeModelPhoto($modelId, $filename)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to remove photo'], 500);
        }
        break;

    case 'download_model':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Please log in to download'], 401);
        }

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
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Please log in to download'], 401);
        }

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
    // PRINTERS
    // ========================================================================

    case 'get_printers':
        jsonResponse(['success' => true, 'printers' => getPrinters()]);
        break;

    case 'get_printer':
        $id = $_GET['id'] ?? '';
        $printer = getPrinter($id);
        if ($printer) {
            jsonResponse(['success' => true, 'printer' => $printer]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Printer not found'], 404);
        }
        break;

    case 'create_printer':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }

        $data = [
            'name' => $_POST['name'] ?? '',
            'manufacturer' => $_POST['manufacturer'] ?? '',
            'build_volume_x' => (int)($_POST['build_volume_x'] ?? 200),
            'build_volume_y' => (int)($_POST['build_volume_y'] ?? 200),
            'build_volume_z' => (int)($_POST['build_volume_z'] ?? 200),
            'nozzle_diameter' => (float)($_POST['nozzle_diameter'] ?? 0.4),
            'description' => $_POST['description'] ?? ''
        ];

        if (!$data['name']) {
            jsonResponse(['success' => false, 'error' => 'Printer name required'], 400);
        }

        $id = createPrinter($data);
        if ($id) {
            jsonResponse(['success' => true, 'id' => $id]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to create printer'], 500);
        }
        break;

    case 'update_printer':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }

        $id = $_POST['id'] ?? '';
        $data = [];

        if (isset($_POST['name'])) $data['name'] = $_POST['name'];
        if (isset($_POST['manufacturer'])) $data['manufacturer'] = $_POST['manufacturer'];
        if (isset($_POST['build_volume_x'])) $data['build_volume_x'] = (int)$_POST['build_volume_x'];
        if (isset($_POST['build_volume_y'])) $data['build_volume_y'] = (int)$_POST['build_volume_y'];
        if (isset($_POST['build_volume_z'])) $data['build_volume_z'] = (int)$_POST['build_volume_z'];
        if (isset($_POST['nozzle_diameter'])) $data['nozzle_diameter'] = (float)$_POST['nozzle_diameter'];
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];

        if (updatePrinter($id, $data)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to update printer'], 500);
        }
        break;

    case 'delete_printer':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }

        $id = $_POST['id'] ?? '';
        if (deletePrinter($id)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to delete printer'], 500);
        }
        break;

    // ========================================================================
    // FILAMENTS
    // ========================================================================

    case 'get_filaments':
        jsonResponse(['success' => true, 'filaments' => getFilaments()]);
        break;

    case 'get_filament':
        $id = $_GET['id'] ?? '';
        $filament = getFilament($id);
        if ($filament) {
            jsonResponse(['success' => true, 'filament' => $filament]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Filament not found'], 404);
        }
        break;

    case 'create_filament':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }

        $data = [
            'name' => $_POST['name'] ?? '',
            'manufacturer' => $_POST['manufacturer'] ?? null,
            'color' => $_POST['color'] ?? null,
            'material_type' => $_POST['material_type'] ?? '',
            'description' => $_POST['description'] ?? ''
        ];

        if (!$data['name'] || !$data['material_type']) {
            jsonResponse(['success' => false, 'error' => 'Name and material type required'], 400);
        }

        $id = createFilament($data);
        if ($id) {
            jsonResponse(['success' => true, 'id' => $id]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to create filament'], 500);
        }
        break;

    case 'update_filament':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }

        $id = $_POST['id'] ?? '';
        $data = [];

        if (isset($_POST['name'])) $data['name'] = $_POST['name'];
        if (isset($_POST['manufacturer'])) $data['manufacturer'] = $_POST['manufacturer'];
        if (isset($_POST['color'])) $data['color'] = $_POST['color'];
        if (isset($_POST['material_type'])) $data['material_type'] = $_POST['material_type'];
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];

        if (updateFilament($id, $data)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to update filament'], 500);
        }
        break;

    case 'delete_filament':
        if (!isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
        }

        $id = $_POST['id'] ?? '';
        if (deleteFilament($id)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to delete filament'], 500);
        }
        break;

    // ========================================================================
    // PRINT PROFILES
    // ========================================================================

    case 'get_profiles':
        $modelId = $_GET['model_id'] ?? '';
        if (!$modelId) {
            jsonResponse(['success' => false, 'error' => 'Model ID required'], 400);
        }

        $filters = [];
        if (isset($_GET['verified'])) $filters['verified'] = (bool)$_GET['verified'];
        if (isset($_GET['printer_id'])) $filters['printer_id'] = $_GET['printer_id'];
        if (isset($_GET['material_type'])) $filters['material_type'] = $_GET['material_type'];
        if (isset($_GET['min_rating'])) $filters['min_rating'] = (float)$_GET['min_rating'];

        $sort = $_GET['sort'] ?? 'newest';

        $profiles = getPrintProfiles($modelId, $filters, $sort);
        jsonResponse(['success' => true, 'profiles' => $profiles]);
        break;

    case 'get_profile':
        $id = $_GET['id'] ?? '';
        $profile = getPrintProfile($id);
        if ($profile) {
            // Increment view count
            incrementProfileViews($id);
            jsonResponse(['success' => true, 'profile' => $profile]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Profile not found'], 404);
        }
        break;

    case 'upload_profile':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        require_once __DIR__ . '/includes/profile_parser.php';

        $modelId = $_POST['model_id'] ?? '';
        if (!$modelId) {
            jsonResponse(['success' => false, 'error' => 'Model ID required'], 400);
        }

        // Verify model exists
        $model = getModel($modelId);
        if (!$model) {
            jsonResponse(['success' => false, 'error' => 'Model not found'], 404);
        }

        // Check file upload
        if (!isset($_FILES['profile_file']) || $_FILES['profile_file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'error' => 'Please upload a .3mf file'], 400);
        }

        $file = $_FILES['profile_file'];

        // Validate file
        $validation = validate3mfFile($file['tmp_name']);
        if (!$validation['valid']) {
            jsonResponse(['success' => false, 'error' => $validation['error']], 400);
        }

        // Parse profile settings
        $parseResult = parse3mfFile($file['tmp_name']);
        if (isset($parseResult['error'])) {
            jsonResponse(['success' => false, 'error' => 'Failed to parse profile: ' . $parseResult['error']], 400);
        }

        // Generate unique filename
        $ext = '.3mf';
        $filename = generateId() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($file['name'], PATHINFO_FILENAME)) . $ext;
        $filepath = UPLOADS_DIR . 'profiles/' . $filename;

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            jsonResponse(['success' => false, 'error' => 'Failed to save file'], 500);
        }

        // Prepare profile data
        $data = [
            'model_id' => $modelId,
            'user_id' => $_SESSION['user_id'],
            'name' => $_POST['name'] ?? 'Print Profile',
            'description' => $_POST['description'] ?? '',
            'filename' => $filename,
            'filesize' => filesize($filepath),
            'settings' => $parseResult['settings'] ?? [],
            'printer_id' => $_POST['printer_id'] ?? null,
            'filament_id' => $_POST['filament_id'] ?? null,
            'compatible_printers' => isset($_POST['compatible_printers']) ? json_decode($_POST['compatible_printers'], true) : [],
            'compatible_materials' => isset($_POST['compatible_materials']) ? json_decode($_POST['compatible_materials'], true) : [],
            'layer_height' => $parseResult['settings']['layer_height'] ?? null,
            'infill_percentage' => $parseResult['settings']['infill_percentage'] ?? null,
            'supports_required' => $parseResult['settings']['supports_required'] ?? false,
            'print_time_minutes' => isset($_POST['print_time']) ? (int)$_POST['print_time'] : null,
            'material_used_grams' => isset($_POST['material_used']) ? (int)$_POST['material_used'] : null
        ];

        $profileId = createPrintProfile($data);
        if ($profileId) {
            jsonResponse(['success' => true, 'profile_id' => $profileId]);
        } else {
            @unlink($filepath);
            jsonResponse(['success' => false, 'error' => 'Failed to create profile'], 500);
        }
        break;

    case 'update_profile':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $id = $_POST['id'] ?? '';
        $profile = getPrintProfile($id);

        if (!$profile) {
            jsonResponse(['success' => false, 'error' => 'Profile not found'], 404);
        }

        // Check ownership or admin
        if ($profile['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Permission denied'], 403);
        }

        $data = [];
        if (isset($_POST['name'])) $data['name'] = $_POST['name'];
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];
        if (isset($_POST['printer_id'])) $data['printer_id'] = $_POST['printer_id'];
        if (isset($_POST['filament_id'])) $data['filament_id'] = $_POST['filament_id'];
        if (isset($_POST['compatible_printers'])) $data['compatible_printers'] = json_decode($_POST['compatible_printers'], true);
        if (isset($_POST['compatible_materials'])) $data['compatible_materials'] = json_decode($_POST['compatible_materials'], true);
        if (isset($_POST['layer_height'])) $data['layer_height'] = (float)$_POST['layer_height'];
        if (isset($_POST['infill_percentage'])) $data['infill_percentage'] = (int)$_POST['infill_percentage'];
        if (isset($_POST['supports_required'])) $data['supports_required'] = (bool)$_POST['supports_required'];

        // Admin-only fields
        if (isAdmin()) {
            if (isset($_POST['verified'])) $data['verified'] = (bool)$_POST['verified'];
            if (isset($_POST['verification_method'])) $data['verification_method'] = $_POST['verification_method'];
            if (isset($_POST['featured'])) $data['featured'] = (bool)$_POST['featured'];
        }

        if (updatePrintProfile($id, $data)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to update profile'], 500);
        }
        break;

    case 'delete_profile':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $id = $_POST['id'] ?? '';
        $profile = getPrintProfile($id);

        if (!$profile) {
            jsonResponse(['success' => false, 'error' => 'Profile not found'], 404);
        }

        // Check ownership or admin
        if ($profile['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            jsonResponse(['success' => false, 'error' => 'Permission denied'], 403);
        }

        if (deletePrintProfile($id)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to delete profile'], 500);
        }
        break;

    case 'download_profile':
        $id = $_GET['id'] ?? '';
        $profile = getPrintProfile($id);

        if (!$profile) {
            jsonResponse(['success' => false, 'error' => 'Profile not found'], 404);
        }

        $filepath = UPLOADS_DIR . 'profiles/' . $profile['filename'];
        if (!file_exists($filepath)) {
            jsonResponse(['success' => false, 'error' => 'Profile file not found'], 404);
        }

        // Increment download count
        incrementProfileDownloads($id);

        // Send file
        header('Content-Type: model/3mf');
        header('Content-Disposition: attachment; filename="' . basename($profile['filename']) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;

    case 'rate_profile':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $data = [
            'profile_id' => $_POST['profile_id'] ?? '',
            'user_id' => $_SESSION['user_id'],
            'rating' => (int)($_POST['rating'] ?? 0),
            'print_successful' => isset($_POST['print_successful']) ? (bool)$_POST['print_successful'] : null,
            'comment' => $_POST['comment'] ?? '',
            'printer_used' => $_POST['printer_used'] ?? null,
            'filament_used' => $_POST['filament_used'] ?? null
        ];

        if (!$data['profile_id'] || $data['rating'] < 1 || $data['rating'] > 5) {
            jsonResponse(['success' => false, 'error' => 'Valid profile ID and rating (1-5) required'], 400);
        }

        if (createProfileRating($data)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to save rating'], 500);
        }
        break;

    case 'get_profile_ratings':
        $profileId = $_GET['profile_id'] ?? '';
        if (!$profileId) {
            jsonResponse(['success' => false, 'error' => 'Profile ID required'], 400);
        }

        $ratings = getProfileRatings($profileId);
        jsonResponse(['success' => true, 'ratings' => $ratings]);
        break;

    // ========================================================================
    // USER EQUIPMENT
    // ========================================================================

    case 'add_user_printer':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $printerId = $_POST['printer_id'] ?? '';
        $nickname = $_POST['nickname'] ?? null;

        if (!$printerId) {
            jsonResponse(['success' => false, 'error' => 'Printer ID required'], 400);
        }

        if (addUserPrinter($_SESSION['user_id'], $printerId, $nickname)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to add printer'], 500);
        }
        break;

    case 'remove_user_printer':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $printerId = $_POST['printer_id'] ?? '';

        if (removeUserPrinter($_SESSION['user_id'], $printerId)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to remove printer'], 500);
        }
        break;

    case 'get_user_printers':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $printers = getUserPrinters($_SESSION['user_id']);
        jsonResponse(['success' => true, 'printers' => $printers]);
        break;

    case 'add_user_filament':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $filamentId = $_POST['filament_id'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 1);

        if (!$filamentId) {
            jsonResponse(['success' => false, 'error' => 'Filament ID required'], 400);
        }

        if (addUserFilament($_SESSION['user_id'], $filamentId, $quantity)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to add filament'], 500);
        }
        break;

    case 'remove_user_filament':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $filamentId = $_POST['filament_id'] ?? '';

        if (removeUserFilament($_SESSION['user_id'], $filamentId)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Failed to remove filament'], 500);
        }
        break;

    case 'get_user_filaments':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $filaments = getUserFilaments($_SESSION['user_id']);
        jsonResponse(['success' => true, 'filaments' => $filaments]);
        break;

    case 'get_compatible_profiles':
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Login required'], 401);
        }

        $modelId = $_GET['model_id'] ?? '';
        if (!$modelId) {
            jsonResponse(['success' => false, 'error' => 'Model ID required'], 400);
        }

        $profiles = getCompatibleProfiles($_SESSION['user_id'], $modelId);
        jsonResponse(['success' => true, 'profiles' => $profiles]);
        break;

    // ========================================================================
    // DEFAULT
    // ========================================================================

    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
