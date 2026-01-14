<?php
/**
 * FEC STL Vault - User Profile
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$userId = $_GET['id'] ?? '';
$profileUser = getUser($userId);

if (!$profileUser) {
    redirect('index.php');
}

// Get user's models
$userModels = array_values(getModelsByUser($userId));
usort($userModels, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

// Check if viewing own profile
$isOwnProfile = isLoggedIn() && $_SESSION['user_id'] === $userId;

// Handle profile update
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile) {
    $bio = $_POST['bio'] ?? '';
    $location = $_POST['location'] ?? '';
    
    if (updateUser($userId, [
        'bio' => $bio,
        'location' => $location
    ])) {
        $success = 'Profile updated successfully';
        $profileUser = getUser($userId); // Refresh
    } else {
        $error = 'Failed to update profile';
    }
}

// Get favorites if own profile
$favorites = [];
if ($isOwnProfile && !empty($profileUser['favorites'])) {
    foreach ($profileUser['favorites'] as $modelId) {
        $model = getModel($modelId);
        if ($model) {
            $author = getUser($model['user_id']);
            $model['author'] = $author ? $author['username'] : 'Unknown';
            $favorites[] = $model;
        }
    }
}

$currentUser = isLoggedIn() ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($profileUser['username']) ?> - <?= SITE_NAME ?></title>
    
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
                <?php if (isLoggedIn()): ?>
                    <a href="upload.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload"></i> Upload
                    </a>
                    <a href="profile.php?id=<?= $currentUser['id'] ?>" class="btn btn-secondary btn-sm <?= $isOwnProfile ? 'active' : '' ?>">
                        <i class="fas fa-user"></i> <?= sanitize($currentUser['username']) ?>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="admin.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-cog"></i>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary btn-sm">Sign In</a>
                    <a href="login.php?register=1" class="btn btn-primary btn-sm">Join Now</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="page-content">
        <div class="container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($profileUser['username'], 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h1>
                        <?= sanitize($profileUser['username']) ?>
                        <?php if ($profileUser['is_admin'] ?? false): ?>
                            <span style="font-size: 0.6em; color: var(--neon-magenta); margin-left: 8px;">
                                <i class="fas fa-shield-alt"></i> Admin
                            </span>
                        <?php endif; ?>
                    </h1>
                    <?php if (!empty($profileUser['bio'])): ?>
                        <p><?= sanitize($profileUser['bio']) ?></p>
                    <?php endif; ?>
                    <div style="display: flex; gap: 16px; margin-top: 8px; color: var(--text-muted); font-size: 0.9rem;">
                        <?php if (!empty($profileUser['location'])): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= sanitize($profileUser['location']) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar"></i> Joined <?= date('M Y', strtotime($profileUser['created_at'])) ?></span>
                    </div>
                </div>
                <div class="profile-stats">
                    <div class="model-stat">
                        <div class="model-stat-value"><?= count($userModels) ?></div>
                        <div class="model-stat-label">Models</div>
                    </div>
                    <div class="model-stat">
                        <div class="model-stat-value"><?= number_format($profileUser['download_count'] ?? 0) ?></div>
                        <div class="model-stat-label">Downloads</div>
                    </div>
                    <?php if ($isOwnProfile): ?>
                        <div class="model-stat">
                            <div class="model-stat-value"><?= count($profileUser['favorites'] ?? []) ?></div>
                            <div class="model-stat-label">Favorites</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

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

            <!-- Profile Actions -->
            <?php if ($isOwnProfile): ?>
                <div style="display: flex; gap: 12px; margin-bottom: 32px;">
                    <button class="btn btn-secondary btn-sm" onclick="Modal.show('edit-profile-modal')">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                    <a href="logout.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Sign Out
                    </a>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="auth-tabs" style="max-width: 400px; margin-bottom: 24px;">
                <div class="auth-tab active" onclick="showTab('models')">
                    <i class="fas fa-cube"></i> Models (<?= count($userModels) ?>)
                </div>
                <?php if ($isOwnProfile): ?>
                    <div class="auth-tab" onclick="showTab('favorites')">
                        <i class="fas fa-heart"></i> Favorites (<?= count($favorites) ?>)
                    </div>
                <?php endif; ?>
            </div>

            <!-- Models Tab -->
            <div id="models-tab">
                <?php if (empty($userModels)): ?>
                    <div class="empty-state">
                        <i class="fas fa-cube"></i>
                        <h3>No models yet</h3>
                        <?php if ($isOwnProfile): ?>
                            <p>Upload your first model to get started!</p>
                            <a href="upload.php" class="btn btn-primary" style="margin-top: 20px;">
                                <i class="fas fa-upload"></i> Upload Model
                            </a>
                        <?php else: ?>
                            <p>This user hasn't uploaded any models yet.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="model-grid">
                        <?php foreach ($userModels as $model): ?>
                            <?php $category = getCategory($model['category']); ?>
                            <div class="card model-card">
                                <div class="model-card-preview">
                                    <div class="preview-placeholder" data-stl-thumb="uploads/<?= sanitize($model['filename']) ?>">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                    <div class="model-card-overlay">
                                        <div class="model-card-actions">
                                            <a href="model.php?id=<?= $model['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="model-card-body">
                                    <?php if ($category): ?>
                                        <span class="model-card-category">
                                            <i class="fas <?= sanitize($category['icon']) ?>"></i>
                                            <?= sanitize($category['name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <h3 class="model-card-title">
                                        <a href="model.php?id=<?= $model['id'] ?>"><?= sanitize($model['title']) ?></a>
                                    </h3>
                                    <div class="model-card-meta">
                                        <span><i class="fas fa-download"></i> <?= $model['downloads'] ?? 0 ?></span>
                                        <span><i class="fas fa-heart"></i> <?= $model['likes'] ?? 0 ?></span>
                                        <span><i class="fas fa-clock"></i> <?= timeAgo($model['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Favorites Tab -->
            <?php if ($isOwnProfile): ?>
                <div id="favorites-tab" style="display: none;">
                    <?php if (empty($favorites)): ?>
                        <div class="empty-state">
                            <i class="fas fa-heart"></i>
                            <h3>No favorites yet</h3>
                            <p>Save models you like to find them easily later!</p>
                            <a href="browse.php" class="btn btn-secondary" style="margin-top: 20px;">
                                Browse Models
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="model-grid">
                            <?php foreach ($favorites as $model): ?>
                                <?php $category = getCategory($model['category']); ?>
                                <div class="card model-card">
                                    <div class="model-card-preview">
                                        <div class="preview-placeholder" data-stl-thumb="uploads/<?= sanitize($model['filename']) ?>">
                                            <i class="fas fa-cube"></i>
                                        </div>
                                        <div class="model-card-overlay">
                                            <div class="model-card-actions">
                                                <a href="model.php?id=<?= $model['id'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="model-card-body">
                                        <?php if ($category): ?>
                                            <span class="model-card-category">
                                                <i class="fas <?= sanitize($category['icon']) ?>"></i>
                                                <?= sanitize($category['name']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <h3 class="model-card-title">
                                            <a href="model.php?id=<?= $model['id'] ?>"><?= sanitize($model['title']) ?></a>
                                        </h3>
                                        <div class="model-card-author">
                                            <div class="author-avatar">
                                                <?= strtoupper(substr($model['author'], 0, 1)) ?>
                                            </div>
                                            <span><?= sanitize($model['author']) ?></span>
                                        </div>
                                        <div class="model-card-meta">
                                            <span><i class="fas fa-download"></i> <?= $model['downloads'] ?? 0 ?></span>
                                            <span><i class="fas fa-heart"></i> <?= $model['likes'] ?? 0 ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <?php if ($isOwnProfile): ?>
        <div class="modal-overlay" id="edit-profile-modal">
            <div class="modal">
                <div class="modal-header">
                    <h2>Edit Profile</h2>
                    <button class="modal-close"><i class="fas fa-times"></i></button>
                </div>
                <form method="POST" action="profile.php?id=<?= $userId ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-textarea" rows="3" 
                                      placeholder="Tell us about yourself..."><?= sanitize($profileUser['bio'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-input" 
                                   placeholder="City, State or Country"
                                   value="<?= sanitize($profileUser['location'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="Modal.hide('edit-profile-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/STLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/OBJLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/MTLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/PLYLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/3MFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="js/app.js"></script>
    <script>
        function showTab(tab) {
            // Hide all tabs
            document.getElementById('models-tab').style.display = 'none';
            const favTab = document.getElementById('favorites-tab');
            if (favTab) favTab.style.display = 'none';
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tab + '-tab').style.display = 'block';
            
            // Add active class to clicked tab
            event.target.closest('.auth-tab').classList.add('active');
        }
    </script>
</body>
</html>
