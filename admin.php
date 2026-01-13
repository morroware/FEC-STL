<?php
/**
 * FEC STL Vault - Admin Dashboard
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
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
    
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
                <span><?= SITE_NAME ?></span>
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
                <a href="profile.php?id=<?= $currentUser['id'] ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-user"></i> <?= sanitize($currentUser['username']) ?>
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
                        
                        <table class="data-table">
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
                                    <tr>
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
                        
                        <table class="data-table">
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
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="model.php?id=<?= $model['id'] ?>"><?= sanitize($model['title']) ?></a>
                                        </td>
                                        <td><?= $author ? sanitize($author['username']) : 'Unknown' ?></td>
                                        <td><?= $cat ? sanitize($cat['name']) : '-' ?></td>
                                        <td><?= $model['downloads'] ?? 0 ?></td>
                                        <td><?= timeAgo($model['created_at']) ?></td>
                                        <td class="actions">
                                            <a href="model.php?id=<?= $model['id'] ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Delete this model?');">
                                                <input type="hidden" name="action" value="delete_model">
                                                <input type="hidden" name="id" value="<?= $model['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="browse.php">Browse</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. Made for the FEC community.
                </div>
            </div>
        </div>
    </footer>

    <script src="js/app.js"></script>
    <script>
        function editCategory(id, name, icon, description) {
            document.getElementById('edit-cat-id').value = id;
            document.getElementById('edit-cat-name').value = name;
            document.getElementById('edit-cat-icon').value = icon;
            document.getElementById('edit-cat-desc').value = description;
            Modal.show('edit-category-modal');
        }
    </script>
</body>
</html>
