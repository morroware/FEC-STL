<?php
/**
 * Community 3D Model Vault - Admin Dashboard
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Require admin
if (!isAdmin()) {
    redirect('index.php');
}

$currentUser = getCurrentUser();
$section = $_GET['section'] ?? 'dashboard';

// Get data
$stats = getStats();
$categories = getCategories();
$users = getUsers();
$models = getModels();

// Handle actions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_category':
            $name = trim($_POST['name'] ?? '');
            $icon = $_POST['icon'] ?? 'fa-cube';
            $description = $_POST['description'] ?? '';
            
            if (!$name) {
                $error = 'Category name is required';
            } elseif (createCategory(['name' => $name, 'icon' => $icon, 'description' => $description])) {
                $success = 'Category created successfully';
                $categories = getCategories(); // Refresh
            } else {
                $error = 'Failed to create category';
            }
            break;
            
        case 'update_category':
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $icon = $_POST['icon'] ?? 'fa-cube';
            $description = $_POST['description'] ?? '';
            
            if (updateCategory($id, ['name' => $name, 'icon' => $icon, 'description' => $description])) {
                $success = 'Category updated successfully';
                $categories = getCategories();
            } else {
                $error = 'Failed to update category';
            }
            break;
            
        case 'delete_category':
            $id = $_POST['id'] ?? '';
            $catModels = getModelsByCategory($id);
            
            if (count($catModels) > 0) {
                $error = 'Cannot delete category with models. Move or delete the models first.';
            } elseif (deleteCategory($id)) {
                $success = 'Category deleted successfully';
                $categories = getCategories();
            } else {
                $error = 'Failed to delete category';
            }
            break;
            
        case 'toggle_admin':
            $id = $_POST['id'] ?? '';
            $user = getUser($id);
            
            if ($id === $_SESSION['user_id']) {
                $error = 'Cannot modify your own admin status';
            } elseif ($user && updateUser($id, ['is_admin' => !($user['is_admin'] ?? false)])) {
                $success = 'User updated successfully';
                $users = getUsers();
            } else {
                $error = 'Failed to update user';
            }
            break;
            
        case 'delete_user':
            $id = $_POST['id'] ?? '';
            
            if ($id === $_SESSION['user_id']) {
                $error = 'Cannot delete your own account';
            } else {
                // Delete user's models first
                $userModels = getModelsByUser($id);
                foreach ($userModels as $model) {
                    deleteModel($model['id']);
                }
                
                if (deleteUser($id)) {
                    $success = 'User deleted successfully';
                    $users = getUsers();
                    $models = getModels();
                } else {
                    $error = 'Failed to delete user';
                }
            }
            break;
            
        case 'delete_model':
            $id = $_POST['id'] ?? '';
            if (deleteModel($id)) {
                $success = 'Model deleted successfully';
                $models = getModels();
            } else {
                $error = 'Failed to delete model';
            }
            break;

        case 'save_settings':
            $settingsToSave = [];
            $schema = getSettingsSchema();

            foreach ($schema as $category => $categoryData) {
                foreach ($categoryData['settings'] as $key => $config) {
                    if ($config['type'] === 'toggle') {
                        // Checkboxes are only present when checked
                        $settingsToSave[$key] = isset($_POST[$key]);
                    } elseif ($config['type'] === 'number') {
                        $settingsToSave[$key] = (int)($_POST[$key] ?? 0);
                    } else {
                        if (isset($_POST[$key])) {
                            $settingsToSave[$key] = $_POST[$key];
                        }
                    }
                }
            }

            if (setSettings($settingsToSave)) {
                clearSettingsCache(); // Clear cache so new values are loaded
                $success = 'Settings saved successfully';
            } else {
                $error = 'Failed to save settings';
            }
            break;

        case 'approve_user':
            $id = $_POST['id'] ?? '';
            if (approveUser($id)) {
                $success = 'User approved successfully';
            } else {
                $error = 'Failed to approve user';
            }
            break;

        case 'reject_user':
            $id = $_POST['id'] ?? '';
            if (rejectUser($id)) {
                $success = 'User rejected and removed';
            } else {
                $error = 'Failed to reject user';
            }
            break;

        case 'create_invite':
            $maxUses = (int)($_POST['max_uses'] ?? 1);
            $expiresDays = (int)($_POST['expires_days'] ?? 0);
            $note = trim($_POST['note'] ?? '');

            $code = createInvite([
                'created_by' => $_SESSION['user_id'],
                'max_uses' => $maxUses,
                'expires_days' => $expiresDays > 0 ? $expiresDays : null,
                'note' => $note
            ]);

            if ($code) {
                $success = "Invite code created: <strong>$code</strong>";
            } else {
                $error = 'Failed to create invite code';
            }
            break;

        case 'toggle_invite':
            $id = $_POST['id'] ?? '';
            if (toggleInvite($id)) {
                $success = 'Invite status updated';
            } else {
                $error = 'Failed to update invite';
            }
            break;

        case 'delete_invite':
            $id = $_POST['id'] ?? '';
            if (deleteInvite($id)) {
                $success = 'Invite deleted';
            } else {
                $error = 'Failed to delete invite';
            }
            break;
    }
}

// Sort models by date
usort($models, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

// Font Awesome icons for category selector
$faIcons = [
    'fa-gamepad', 'fa-ticket', 'fa-sign', 'fa-coins', 'fa-wrench', 'fa-gift', 
    'fa-puzzle-piece', 'fa-cube', 'fa-cog', 'fa-tools', 'fa-box', 'fa-trophy',
    'fa-star', 'fa-bolt', 'fa-lightbulb', 'fa-key', 'fa-shield-alt', 'fa-gem'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= getSiteName() ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class="fas fa-cube"></i></div>
                <span><?= getSiteName() ?></span>
            </a>
            
            <div class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="browse.php" class="nav-link">Browse</a>
                <a href="browse.php?sort=popular" class="nav-link">Popular</a>
            </div>
            
            <div class="nav-actions">
                <a href="upload.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-upload"></i> Upload
                </a>
                <a href="profile.php?id=<?= $currentUser['id'] ?>" class="nav-user-btn">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <img src="uploads/<?= sanitize($currentUser['avatar']) ?>" alt="<?= sanitize($currentUser['username']) ?>" class="nav-avatar">
                    <?php else: ?>
                        <div class="nav-avatar-placeholder"><?= strtoupper(substr($currentUser['username'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <span><?= sanitize($currentUser['username']) ?></span>
                </a>
                <a href="admin.php" class="btn btn-outline btn-sm active">
                    <i class="fas fa-cog"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="page-content">
        <div class="container">
            <div class="admin-layout">
                <!-- Sidebar -->
                <aside class="admin-sidebar">
                    <h3 style="margin-bottom: 16px; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase;">
                        Admin Panel
                    </h3>
                    <ul class="admin-nav">
                        <li>
                            <a href="admin.php?section=dashboard" class="<?= $section === 'dashboard' ? 'active' : '' ?>">
                                <i class="fas fa-chart-line"></i> Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="admin.php?section=categories" class="<?= $section === 'categories' ? 'active' : '' ?>">
                                <i class="fas fa-folder"></i> Categories
                            </a>
                        </li>
                        <li>
                            <a href="admin.php?section=users" class="<?= $section === 'users' ? 'active' : '' ?>">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                        <li>
                            <a href="admin.php?section=models" class="<?= $section === 'models' ? 'active' : '' ?>">
                                <i class="fas fa-cube"></i> Models
                            </a>
                        </li>
                        <li>
                            <a href="admin.php?section=settings" class="<?= $section === 'settings' ? 'active' : '' ?>">
                                <i class="fas fa-sliders-h"></i> Settings
                            </a>
                        </li>
                        <?php
                        $pendingUsers = getPendingUsers();
                        $pendingCount = count($pendingUsers);
                        ?>
                        <?php if (setting('require_admin_approval', false) || $pendingCount > 0): ?>
                        <li>
                            <a href="admin.php?section=pending" class="<?= $section === 'pending' ? 'active' : '' ?>">
                                <i class="fas fa-user-clock"></i> Pending
                                <?php if ($pendingCount > 0): ?>
                                    <span class="badge"><?= $pendingCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="admin.php?section=invites" class="<?= $section === 'invites' ? 'active' : '' ?>">
                                <i class="fas fa-ticket-alt"></i> Invites
                            </a>
                        </li>
                    </ul>
                    
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
                        <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;">
                            <i class="fas fa-arrow-left"></i> Back to Site
                        </a>
                    </div>
                </aside>

                <!-- Content -->
                <main class="admin-content">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= sanitize($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= sanitize($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($section === 'dashboard'): ?>
                        <!-- Dashboard -->
                        <div class="admin-header">
                            <h2>Dashboard</h2>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px;">
                            <div class="card" style="padding: 24px; text-align: center;">
                                <div class="model-stat-value" style="font-size: 2rem;"><?= $stats['total_models'] ?></div>
                                <div class="model-stat-label">Total Models</div>
                            </div>
                            <div class="card" style="padding: 24px; text-align: center;">
                                <div class="model-stat-value" style="font-size: 2rem;"><?= $stats['total_users'] ?></div>
                                <div class="model-stat-label">Total Users</div>
                            </div>
                            <div class="card" style="padding: 24px; text-align: center;">
                                <div class="model-stat-value" style="font-size: 2rem;"><?= number_format($stats['total_downloads']) ?></div>
                                <div class="model-stat-label">Downloads</div>
                            </div>
                            <div class="card" style="padding: 24px; text-align: center;">
                                <div class="model-stat-value" style="font-size: 2rem;"><?= $stats['total_categories'] ?></div>
                                <div class="model-stat-label">Categories</div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <h3 style="margin-bottom: 16px;">Recent Uploads</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Model</th>
                                    <th>Author</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($models, 0, 10) as $model): ?>
                                    <?php 
                                    $author = getUser($model['user_id']);
                                    $cat = getCategory($model['category']);
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="model.php?id=<?= $model['id'] ?>"><?= sanitize($model['title']) ?></a>
                                        </td>
                                        <td><?= $author ? sanitize($author['username']) : 'Unknown' ?></td>
                                        <td><?= $cat ? sanitize($cat['name']) : '-' ?></td>
                                        <td><?= timeAgo($model['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php elseif ($section === 'categories'): ?>
                        <!-- Categories Management -->
                        <div class="admin-header">
                            <h2>Categories</h2>
                            <button class="btn btn-primary btn-sm" onclick="Modal.show('add-category-modal')">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </div>
                        
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Icon</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Models</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>
                                            <div class="category-icon" style="width: 36px; height: 36px; font-size: 1rem;">
                                                <i class="fas <?= sanitize($cat['icon']) ?>"></i>
                                            </div>
                                        </td>
                                        <td><strong><?= sanitize($cat['name']) ?></strong></td>
                                        <td style="color: var(--text-secondary);"><?= sanitize($cat['description']) ?></td>
                                        <td><?= $cat['count'] ?? 0 ?></td>
                                        <td class="actions">
                                            <button class="btn btn-secondary btn-sm" 
                                                    onclick="editCategory('<?= $cat['id'] ?>', '<?= sanitize($cat['name']) ?>', '<?= sanitize($cat['icon']) ?>', '<?= sanitize($cat['description']) ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Delete this category?');">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        <?= ($cat['count'] ?? 0) > 0 ? 'disabled title="Has models"' : '' ?>>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php elseif ($section === 'users'): ?>
                        <!-- Users Management -->
                        <div class="admin-header">
                            <h2>Users</h2>
                        </div>

                        <!-- Search Bar -->
                        <div class="search-filter-bar" style="margin-bottom: 20px;">
                            <div class="search-input-wrapper" style="position: relative; max-width: 400px;">
                                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" id="user-search" class="form-input" placeholder="Search by username or email..." style="padding-left: 40px;">
                            </div>
                        </div>

                        <table class="data-table" id="users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Models</th>
                                    <th>Joined</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr data-username="<?= strtolower(sanitize($user['username'])) ?>" data-email="<?= strtolower(sanitize($user['email'])) ?>">
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="author-avatar">
                                                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                                </div>
                                                <a href="profile.php?id=<?= $user['id'] ?>">
                                                    <?= sanitize($user['username']) ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?= sanitize($user['email']) ?></td>
                                        <td><?= $user['model_count'] ?? 0 ?></td>
                                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <?php if ($user['is_admin'] ?? false): ?>
                                                <span style="color: var(--neon-magenta);">
                                                    <i class="fas fa-shield-alt"></i> Admin
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_admin">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" 
                                                            title="<?= ($user['is_admin'] ?? false) ? 'Remove Admin' : 'Make Admin' ?>">
                                                        <i class="fas fa-shield-alt"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Delete this user and all their models?');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">You</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php elseif ($section === 'models'): ?>
                        <!-- Models Management -->
                        <div class="admin-header">
                            <h2>Models</h2>
                        </div>

                        <!-- Search and Filter Bar -->
                        <div class="search-filter-bar" style="margin-bottom: 20px; display: flex; gap: 16px; flex-wrap: wrap; align-items: center;">
                            <div class="search-input-wrapper" style="position: relative; flex: 1; min-width: 250px; max-width: 400px;">
                                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" id="model-search" class="form-input" placeholder="Search by title or author..." style="padding-left: 40px;">
                            </div>
                            <select id="model-category-filter" class="form-select" style="min-width: 150px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= strtolower(sanitize($cat['name'])) ?>"><?= sanitize($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <table class="data-table" id="models-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Category</th>
                                    <th>Downloads</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($models as $model): ?>
                                    <?php
                                    $author = getUser($model['user_id']);
                                    $cat = getCategory($model['category']);
                                    $authorName = $author ? $author['username'] : 'Unknown';
                                    $catName = $cat ? $cat['name'] : '';
                                    ?>
                                    <tr data-title="<?= strtolower(sanitize($model['title'])) ?>"
                                        data-author="<?= strtolower(sanitize($authorName)) ?>"
                                        data-category="<?= strtolower(sanitize($catName)) ?>">
                                        <td>
                                            <a href="model.php?id=<?= $model['id'] ?>"><?= sanitize($model['title']) ?></a>
                                        </td>
                                        <td><?= sanitize($authorName) ?></td>
                                        <td><?= $catName ?: '-' ?></td>
                                        <td><?= $model['downloads'] ?? 0 ?></td>
                                        <td><?= timeAgo($model['created_at']) ?></td>
                                        <td class="actions">
                                            <a href="model.php?id=<?= $model['id'] ?>" class="btn btn-secondary btn-sm" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-secondary btn-sm" title="Edit"
                                                    onclick="editModel('<?= $model['id'] ?>', <?= htmlspecialchars(json_encode([
                                                        'title' => $model['title'],
                                                        'description' => $model['description'] ?? '',
                                                        'category' => $model['category'] ?? '',
                                                        'license' => $model['license'] ?? 'CC BY-NC',
                                                        'tags' => $model['tags'] ?? [],
                                                        'primary_display' => $model['primary_display'] ?? 'auto',
                                                        'has_photos' => !empty($model['photos'] ?? ($model['photo'] ? [$model['photo']] : []))
                                                    ]), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Delete this model?');">
                                                <input type="hidden" name="action" value="delete_model">
                                                <input type="hidden" name="id" value="<?= $model['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php elseif ($section === 'settings'): ?>
                        <!-- Settings Management -->
                        <?php
                        $currentSettings = getSettings();
                        $settingsSchema = getSettingsSchema();
                        ?>
                        <div class="admin-header">
                            <h2>Settings</h2>
                        </div>

                        <form method="POST" action="admin.php?section=settings" class="settings-form">
                            <input type="hidden" name="action" value="save_settings">

                            <div class="settings-tabs">
                                <?php $first = true; foreach ($settingsSchema as $catKey => $category): ?>
                                    <button type="button" class="settings-tab <?= $first ? 'active' : '' ?>" data-tab="<?= $catKey ?>">
                                        <i class="fas <?= $category['icon'] ?>"></i>
                                        <span><?= sanitize($category['label']) ?></span>
                                    </button>
                                <?php $first = false; endforeach; ?>
                            </div>

                            <?php $first = true; foreach ($settingsSchema as $catKey => $category): ?>
                                <div class="settings-panel <?= $first ? 'active' : '' ?>" id="settings-<?= $catKey ?>">
                                    <div class="settings-panel-header">
                                        <i class="fas <?= $category['icon'] ?>"></i>
                                        <h3><?= sanitize($category['label']) ?></h3>
                                    </div>

                                    <div class="settings-grid">
                                        <?php foreach ($category['settings'] as $key => $config): ?>
                                            <div class="setting-item">
                                                <div class="setting-label">
                                                    <label for="<?= $key ?>"><?= sanitize($config['label']) ?></label>
                                                    <span class="setting-description"><?= sanitize($config['description']) ?></span>
                                                </div>
                                                <div class="setting-input">
                                                    <?php
                                                    $value = $currentSettings[$key] ?? '';
                                                    switch ($config['type']):
                                                        case 'toggle': ?>
                                                            <label class="toggle-switch">
                                                                <input type="checkbox" name="<?= $key ?>" id="<?= $key ?>" <?= $value ? 'checked' : '' ?>>
                                                                <span class="toggle-slider"></span>
                                                            </label>
                                                        <?php break;
                                                        case 'text':
                                                        case 'email': ?>
                                                            <input type="<?= $config['type'] ?>" name="<?= $key ?>" id="<?= $key ?>"
                                                                   class="form-input" value="<?= sanitize($value) ?>">
                                                        <?php break;
                                                        case 'textarea': ?>
                                                            <textarea name="<?= $key ?>" id="<?= $key ?>"
                                                                      class="form-textarea" rows="2"><?= sanitize($value) ?></textarea>
                                                        <?php break;
                                                        case 'number': ?>
                                                            <input type="number" name="<?= $key ?>" id="<?= $key ?>"
                                                                   class="form-input" value="<?= (int)$value ?>"
                                                                   <?= isset($config['min']) ? 'min="'.$config['min'].'"' : '' ?>
                                                                   <?= isset($config['max']) ? 'max="'.$config['max'].'"' : '' ?>>
                                                        <?php break;
                                                        case 'color': ?>
                                                            <input type="color" name="<?= $key ?>" id="<?= $key ?>"
                                                                   class="form-color" value="<?= sanitize($value) ?>">
                                                        <?php break;
                                                        case 'select': ?>
                                                            <select name="<?= $key ?>" id="<?= $key ?>" class="form-select">
                                                                <?php
                                                                $options = $config['options'];
                                                                if (is_array($options) && !array_keys($options) !== range(0, count($options) - 1)):
                                                                    // Associative array
                                                                    foreach ($options as $optVal => $optLabel): ?>
                                                                        <option value="<?= is_int($optVal) ? $optVal : sanitize($optVal) ?>"
                                                                            <?= $value == $optVal ? 'selected' : '' ?>>
                                                                            <?= sanitize($optLabel) ?>
                                                                        </option>
                                                                    <?php endforeach;
                                                                else:
                                                                    // Simple array
                                                                    foreach ($options as $opt): ?>
                                                                        <option value="<?= $opt ?>" <?= $value == $opt ? 'selected' : '' ?>>
                                                                            <?= $opt ?>
                                                                        </option>
                                                                    <?php endforeach;
                                                                endif; ?>
                                                            </select>
                                                        <?php break;
                                                    endswitch; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php $first = false; endforeach; ?>

                            <div class="settings-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="location.reload()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </form>

                    <?php elseif ($section === 'pending'): ?>
                        <!-- Pending Users -->
                        <?php $pendingUsers = getPendingUsers(); ?>
                        <div class="admin-header">
                            <h2>Pending Users</h2>
                            <span class="badge" style="font-size: 1rem; padding: 8px 16px;">
                                <?= count($pendingUsers) ?> awaiting approval
                            </span>
                        </div>

                        <?php if (empty($pendingUsers)): ?>
                            <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-user-check" style="font-size: 3rem; color: var(--neon-green); margin-bottom: 16px;"></i>
                                <h3>No Pending Users</h3>
                                <p style="color: var(--text-muted);">All user registrations have been processed.</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingUsers as $user): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="author-avatar">
                                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                                    </div>
                                                    <?= sanitize($user['username']) ?>
                                                </div>
                                            </td>
                                            <td><?= sanitize($user['email']) ?></td>
                                            <td><?= timeAgo($user['created_at']) ?></td>
                                            <td class="actions">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="approve_user">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm" title="Approve">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;"
                                                      onsubmit="return confirm('Reject and delete this user?');">
                                                    <input type="hidden" name="action" value="reject_user">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Reject">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <div class="card" style="margin-top: 24px; padding: 20px;">
                            <h4 style="margin-bottom: 8px;"><i class="fas fa-info-circle"></i> About Admin Approval</h4>
                            <p style="color: var(--text-secondary); margin: 0;">
                                When "Require Admin Approval" is enabled in Settings, new users must be approved before they can access the site.
                                Users with valid invite codes bypass the approval requirement.
                            </p>
                        </div>

                    <?php elseif ($section === 'invites'): ?>
                        <!-- Invite Codes -->
                        <?php $invites = getInvites(); ?>
                        <div class="admin-header">
                            <h2>Invite Codes</h2>
                            <button class="btn btn-primary btn-sm" onclick="Modal.show('create-invite-modal')">
                                <i class="fas fa-plus"></i> Create Invite
                            </button>
                        </div>

                        <div class="card" style="margin-bottom: 24px; padding: 16px; background: var(--bg-elevated);">
                            <p style="color: var(--text-secondary); margin: 0;">
                                <i class="fas fa-lightbulb" style="color: var(--neon-yellow);"></i>
                                Invite codes allow users to register even when public registration is disabled.
                                Share codes privately with people you want to invite.
                            </p>
                        </div>

                        <?php if (empty($invites)): ?>
                            <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-ticket-alt" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 16px;"></i>
                                <h3>No Invite Codes</h3>
                                <p style="color: var(--text-muted);">Create an invite code to allow someone to register.</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Uses</th>
                                        <th>Expires</th>
                                        <th>Note</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invites as $invite):
                                        $isValid = isInviteValid($invite);
                                        $creator = getUser($invite['created_by']);
                                    ?>
                                        <tr style="<?= !$isValid ? 'opacity: 0.6;' : '' ?>">
                                            <td>
                                                <code class="invite-code" style="font-family: monospace; font-size: 1.1rem; color: var(--neon-cyan); cursor: pointer;"
                                                      onclick="copyToClipboard('<?= $invite['code'] ?>')" title="Click to copy">
                                                    <?= $invite['code'] ?>
                                                </code>
                                            </td>
                                            <td>
                                                <?= $invite['uses'] ?> / <?= $invite['max_uses'] > 0 ? $invite['max_uses'] : '∞' ?>
                                            </td>
                                            <td>
                                                <?php if ($invite['expires_at']): ?>
                                                    <?php if (strtotime($invite['expires_at']) < time()): ?>
                                                        <span style="color: var(--error);">Expired</span>
                                                    <?php else: ?>
                                                        <?= date('M j, Y', strtotime($invite['expires_at'])) ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color: var(--text-secondary);">
                                                <?= $invite['note'] ? sanitize($invite['note']) : '-' ?>
                                            </td>
                                            <td>
                                                <?php if (!$invite['active']): ?>
                                                    <span class="status-badge status-inactive">Inactive</span>
                                                <?php elseif ($invite['max_uses'] > 0 && $invite['uses'] >= $invite['max_uses']): ?>
                                                    <span class="status-badge status-used">Used Up</span>
                                                <?php elseif ($invite['expires_at'] && strtotime($invite['expires_at']) < time()): ?>
                                                    <span class="status-badge status-expired">Expired</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-active">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_invite">
                                                    <input type="hidden" name="id" value="<?= $invite['id'] ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm"
                                                            title="<?= $invite['active'] ? 'Deactivate' : 'Activate' ?>">
                                                        <i class="fas fa-<?= $invite['active'] ? 'pause' : 'play' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;"
                                                      onsubmit="return confirm('Delete this invite code?');">
                                                    <input type="hidden" name="action" value="delete_invite">
                                                    <input type="hidden" name="id" value="<?= $invite['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal-overlay" id="add-category-modal">
        <div class="modal">
            <div class="modal-header">
                <h2>Add Category</h2>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="admin.php?section=categories">
                <input type="hidden" name="action" value="create_category">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon</label>
                        <select name="icon" class="form-select">
                            <?php foreach ($faIcons as $icon): ?>
                                <option value="<?= $icon ?>">
                                    <?= ucwords(str_replace(['fa-', '-'], ['', ' '], $icon)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Font Awesome icon class</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.hide('add-category-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal-overlay" id="edit-category-modal">
        <div class="modal">
            <div class="modal-header">
                <h2>Edit Category</h2>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="admin.php?section=categories">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="id" id="edit-cat-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" id="edit-cat-name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon</label>
                        <select name="icon" id="edit-cat-icon" class="form-select">
                            <?php foreach ($faIcons as $icon): ?>
                                <option value="<?= $icon ?>">
                                    <?= ucwords(str_replace(['fa-', '-'], ['', ' '], $icon)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit-cat-desc" class="form-textarea" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.hide('edit-category-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Model Modal -->
    <div class="modal-overlay" id="edit-model-modal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Edit Model</h2>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <form id="edit-model-form" onsubmit="submitModelEdit(event)">
                <input type="hidden" name="id" id="edit-model-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label required">Title</label>
                        <input type="text" name="title" id="edit-model-title" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit-model-description" class="form-textarea" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" id="edit-model-category" class="form-select">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">License</label>
                        <select name="license" id="edit-model-license" class="form-select">
                            <option value="CC BY">CC BY (Attribution)</option>
                            <option value="CC BY-SA">CC BY-SA (Attribution-ShareAlike)</option>
                            <option value="CC BY-NC">CC BY-NC (Attribution-NonCommercial)</option>
                            <option value="CC BY-NC-SA">CC BY-NC-SA (Attribution-NonCommercial-ShareAlike)</option>
                            <option value="CC0">CC0 (Public Domain)</option>
                            <option value="MIT">MIT License</option>
                            <option value="GPL">GPL License</option>
                            <option value="All Rights Reserved">All Rights Reserved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" id="edit-model-tags" class="form-input" placeholder="Enter tags separated by commas">
                        <div class="form-hint">Separate tags with commas (e.g., gaming, miniature, terrain)</div>
                    </div>
                    <div class="form-group" id="primary-display-group">
                        <label class="form-label">Default Display</label>
                        <select name="primary_display" id="edit-model-primary-display" class="form-select">
                            <option value="0">3D Model - Show 3D preview in listings</option>
                            <option value="photo">Photo - Show photo in listings</option>
                            <option value="auto">Auto - Photo if available, else 3D</option>
                        </select>
                        <div class="form-hint">Choose what to display as the cover image in browse/search results</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.hide('edit-model-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Invite Modal -->
    <div class="modal-overlay" id="create-invite-modal">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <h2>Create Invite Code</h2>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="admin.php?section=invites">
                <input type="hidden" name="action" value="create_invite">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Max Uses</label>
                        <input type="number" name="max_uses" class="form-input" value="1" min="1" max="100">
                        <div class="form-hint">How many times this code can be used (1 = single use)</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expires In (days)</label>
                        <input type="number" name="expires_days" class="form-input" value="7" min="0" max="365">
                        <div class="form-hint">Leave at 0 for no expiration</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Note (optional)</label>
                        <input type="text" name="note" class="form-input" placeholder="e.g., For John">
                        <div class="form-hint">Private note to remember who this is for</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.hide('create-invite-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-ticket-alt"></i> Create Code
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="browse.php">Browse</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?= date('Y') ?> <?= getSiteName() ?>. A community-driven platform.
                </div>
            </div>
        </div>
    </footer>

    <script src="js/app.js"></script>
    <script>
        // Category edit
        function editCategory(id, name, icon, description) {
            document.getElementById('edit-cat-id').value = id;
            document.getElementById('edit-cat-name').value = name;
            document.getElementById('edit-cat-icon').value = icon;
            document.getElementById('edit-cat-desc').value = description;
            Modal.show('edit-category-modal');
        }

        // Model edit
        function editModel(id, data) {
            document.getElementById('edit-model-id').value = id;
            document.getElementById('edit-model-title').value = data.title || '';
            document.getElementById('edit-model-description').value = data.description || '';
            document.getElementById('edit-model-category').value = data.category || '';
            document.getElementById('edit-model-license').value = data.license || 'CC BY-NC';
            document.getElementById('edit-model-tags').value = (data.tags || []).join(', ');
            document.getElementById('edit-model-primary-display').value = data.primary_display || '0';

            // Show/hide photo option based on whether model has photos
            const photoOption = document.querySelector('#edit-model-primary-display option[value="photo"]');
            if (photoOption) {
                photoOption.style.display = data.has_photos ? '' : 'none';
            }

            Modal.show('edit-model-modal');
        }

        async function submitModelEdit(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'update_model');

            // Convert tags string to JSON array
            const tagsInput = formData.get('tags');
            const tagsArray = tagsInput ? tagsInput.split(',').map(t => t.trim()).filter(t => t) : [];
            formData.set('tags', JSON.stringify(tagsArray));

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Toast.success('Model updated successfully!');
                    Modal.hide('edit-model-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Toast.error(result.error || 'Failed to update model');
                }
            } catch (err) {
                Toast.error('Failed to update model');
            }
        }

        // Copy to clipboard function for invite codes
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                Toast.success('Invite code copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                Toast.success('Invite code copied to clipboard!');
            });
        }

        // User search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const userSearch = document.getElementById('user-search');
            if (userSearch) {
                userSearch.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('#users-table tbody tr');

                    rows.forEach(row => {
                        const username = row.dataset.username || '';
                        const email = row.dataset.email || '';
                        const matches = username.includes(query) || email.includes(query);
                        row.style.display = matches ? '' : 'none';
                    });
                });
            }

            // Model search and filter functionality
            const modelSearch = document.getElementById('model-search');
            const categoryFilter = document.getElementById('model-category-filter');

            function filterModels() {
                const query = modelSearch ? modelSearch.value.toLowerCase().trim() : '';
                const category = categoryFilter ? categoryFilter.value.toLowerCase() : '';
                const rows = document.querySelectorAll('#models-table tbody tr');

                rows.forEach(row => {
                    const title = row.dataset.title || '';
                    const author = row.dataset.author || '';
                    const rowCategory = row.dataset.category || '';

                    const matchesSearch = !query || title.includes(query) || author.includes(query);
                    const matchesCategory = !category || rowCategory === category;

                    row.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
                });
            }

            if (modelSearch) {
                modelSearch.addEventListener('input', filterModels);
            }
            if (categoryFilter) {
                categoryFilter.addEventListener('change', filterModels);
            }

            // Settings tabs functionality
            const settingsTabs = document.querySelectorAll('.settings-tab');
            const settingsPanels = document.querySelectorAll('.settings-panel');

            settingsTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.dataset.tab;

                    // Update active states
                    settingsTabs.forEach(t => t.classList.remove('active'));
                    settingsPanels.forEach(p => p.classList.remove('active'));

                    this.classList.add('active');
                    document.getElementById('settings-' + targetTab).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
