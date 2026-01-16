<?php
/**
 * Community 3D Model Vault - User Profile
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
    $website = $_POST['website'] ?? '';
    $twitter = preg_replace('/^@/', '', $_POST['twitter'] ?? '');
    $github = preg_replace('/^@/', '', $_POST['github'] ?? '');

    if (updateUser($userId, [
        'bio' => $bio,
        'location' => $location,
        'website' => $website,
        'twitter' => $twitter,
        'github' => $github
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
    <title><?= sanitize($profileUser['username']) ?> - <?= getSiteName() ?></title>
    
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
                <?php if (isLoggedIn()): ?>
                    <a href="upload.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload"></i> Upload
                    </a>
                    <a href="profile.php?id=<?= $currentUser['id'] ?>" class="nav-user-btn <?= $isOwnProfile ? 'active' : '' ?>">
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <img src="uploads/<?= sanitize($currentUser['avatar']) ?>" alt="<?= sanitize($currentUser['username']) ?>" class="nav-avatar">
                        <?php else: ?>
                            <div class="nav-avatar-placeholder"><?= strtoupper(substr($currentUser['username'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <span><?= sanitize($currentUser['username']) ?></span>
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
                <div class="profile-avatar-wrapper">
                    <?php if (!empty($profileUser['avatar'])): ?>
                        <img src="uploads/<?= sanitize($profileUser['avatar']) ?>" alt="<?= sanitize($profileUser['username']) ?>" class="profile-avatar-img">
                    <?php else: ?>
                        <div class="profile-avatar">
                            <?= strtoupper(substr($profileUser['username'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($isOwnProfile): ?>
                        <button class="avatar-edit-btn" onclick="Modal.show('avatar-modal')" title="Change avatar">
                            <i class="fas fa-camera"></i>
                        </button>
                    <?php endif; ?>
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
                        <p class="profile-bio"><?= nl2br(sanitize($profileUser['bio'])) ?></p>
                    <?php endif; ?>
                    <div class="profile-meta">
                        <?php if (!empty($profileUser['location'])): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= sanitize($profileUser['location']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($profileUser['website'])): ?>
                            <a href="<?= sanitize($profileUser['website']) ?>" target="_blank" rel="noopener">
                                <i class="fas fa-globe"></i> Website
                            </a>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar"></i> Joined <?= date('M Y', strtotime($profileUser['created_at'])) ?></span>
                    </div>
                    <?php if (!empty($profileUser['twitter']) || !empty($profileUser['github'])): ?>
                    <div class="profile-social">
                        <?php if (!empty($profileUser['twitter'])): ?>
                            <a href="https://twitter.com/<?= sanitize($profileUser['twitter']) ?>" target="_blank" rel="noopener" class="social-link twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($profileUser['github'])): ?>
                            <a href="https://github.com/<?= sanitize($profileUser['github']) ?>" target="_blank" rel="noopener" class="social-link github">
                                <i class="fab fa-github"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
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
                            <?php
                            $category = getCategory($model['category']);
                            $photos = $model['photos'] ?? ($model['photo'] ? [$model['photo']] : []);
                            $hasPhotos = !empty($photos);
                            $primaryDisplay = $model['primary_display'] ?? 'auto';
                            $showPhoto = ($primaryDisplay === 'photo' || $primaryDisplay === 'auto') && $hasPhotos;
                            ?>
                            <div class="card model-card">
                                <a href="model.php?id=<?= $model['id'] ?>" class="model-card-preview <?= $showPhoto ? 'has-photo' : '' ?>" style="display: block; cursor: pointer;">
                                    <?php if ($showPhoto): ?>
                                    <div class="preview-photo">
                                        <img src="uploads/<?= sanitize($photos[0]) ?>" alt="<?= sanitize($model['title']) ?>">
                                    </div>
                                    <?php else: ?>
                                    <div class="preview-placeholder" data-model-thumb="uploads/<?= sanitize($model['filename']) ?>">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="model-card-overlay">
                                        <div class="model-card-actions">
                                            <span class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </span>
                                        </div>
                                    </div>
                                </a>
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
                                    <?php if ($isOwnProfile): ?>
                                    <div class="model-card-actions-owner" style="display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-color);">
                                        <button class="btn btn-secondary btn-sm" style="flex: 1;"
                                                onclick="editModelFromProfile('<?= $model['id'] ?>', <?= htmlspecialchars(json_encode([
                                                    'title' => $model['title'],
                                                    'description' => $model['description'] ?? '',
                                                    'category' => $model['category'] ?? '',
                                                    'license' => $model['license'] ?? 'CC BY-NC',
                                                    'tags' => $model['tags'] ?? []
                                                ]), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" style="flex: 1;"
                                                onclick="deleteModelFromProfile('<?= $model['id'] ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                    <?php endif; ?>
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
                                <?php
                                $category = getCategory($model['category']);
                                $photos = $model['photos'] ?? ($model['photo'] ? [$model['photo']] : []);
                                $hasPhotos = !empty($photos);
                                $primaryDisplay = $model['primary_display'] ?? 'auto';
                                $showPhoto = ($primaryDisplay === 'photo' || $primaryDisplay === 'auto') && $hasPhotos;
                                ?>
                                <div class="card model-card">
                                    <a href="model.php?id=<?= $model['id'] ?>" class="model-card-preview <?= $showPhoto ? 'has-photo' : '' ?>" style="display: block; cursor: pointer;">
                                        <?php if ($showPhoto): ?>
                                        <div class="preview-photo">
                                            <img src="uploads/<?= sanitize($photos[0]) ?>" alt="<?= sanitize($model['title']) ?>">
                                        </div>
                                        <?php else: ?>
                                        <div class="preview-placeholder" data-model-thumb="uploads/<?= sanitize($model['filename']) ?>">
                                            <i class="fas fa-cube"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div class="model-card-overlay">
                                            <div class="model-card-actions">
                                                <span class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </span>
                                            </div>
                                        </div>
                                    </a>
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
                                            <a href="profile.php?id=<?= $model['user_id'] ?>"><?= sanitize($model['author']) ?></a>
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
            <div class="modal modal-lg">
                <div class="modal-header">
                    <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
                    <button class="modal-close" onclick="Modal.hide('edit-profile-modal')"><i class="fas fa-times"></i></button>
                </div>
                <form method="POST" action="profile.php?id=<?= $userId ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">About Me</label>
                            <textarea name="bio" class="form-textarea" rows="4"
                                      placeholder="Tell us about yourself, your interests, what kind of models you create..."><?= sanitize($profileUser['bio'] ?? '') ?></textarea>
                            <small class="form-hint">Share your background, interests, or what inspires your 3D printing projects.</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Location</label>
                                <input type="text" name="location" class="form-input"
                                       placeholder="City, State or Country"
                                       value="<?= sanitize($profileUser['location'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-globe"></i> Website</label>
                                <input type="url" name="website" class="form-input"
                                       placeholder="https://yoursite.com"
                                       value="<?= sanitize($profileUser['website'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-divider">
                            <span>Social Links</span>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-twitter"></i> Twitter</label>
                                <div class="input-with-prefix">
                                    <span class="input-prefix">@</span>
                                    <input type="text" name="twitter" class="form-input"
                                           placeholder="username"
                                           value="<?= sanitize($profileUser['twitter'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-github"></i> GitHub</label>
                                <div class="input-with-prefix">
                                    <span class="input-prefix">@</span>
                                    <input type="text" name="github" class="form-input"
                                           placeholder="username"
                                           value="<?= sanitize($profileUser['github'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="Modal.hide('edit-profile-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Model Modal -->
        <?php $allCategories = getCategories(); ?>
        <div class="modal-overlay" id="edit-model-modal">
            <div class="modal" style="max-width: 600px;">
                <div class="modal-header">
                    <h2>Edit Model</h2>
                    <button class="modal-close"><i class="fas fa-times"></i></button>
                </div>
                <form id="edit-model-form" onsubmit="submitModelEditFromProfile(event)">
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
                                <?php foreach ($allCategories as $cat): ?>
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

        <!-- Avatar Upload Modal -->
        <div class="modal-overlay" id="avatar-modal">
            <div class="modal">
                <div class="modal-header">
                    <h2><i class="fas fa-camera"></i> Change Profile Picture</h2>
                    <button class="modal-close" onclick="Modal.hide('avatar-modal')"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="avatar-upload-area" id="avatar-upload-area">
                        <div class="avatar-preview" id="avatar-preview">
                            <?php if (!empty($profileUser['avatar'])): ?>
                                <img src="uploads/<?= sanitize($profileUser['avatar']) ?>" alt="Current avatar" id="avatar-preview-img">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="avatar-upload-info">
                            <p>Click or drag to upload a new profile picture</p>
                            <small>JPG, PNG, GIF or WebP. Max 2MB.</small>
                        </div>
                        <input type="file" id="avatar-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.hide('avatar-modal')">Cancel</button>
                    <button type="button" class="btn btn-primary" id="upload-avatar-btn" disabled>
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
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
                    &copy; <?= date('Y') ?> <?= getSiteName() ?>. A community-driven platform.
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/STLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/OBJLoader.js"></script>
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

        <?php if ($isOwnProfile): ?>
        // Model edit/delete functions
        function editModelFromProfile(id, data) {
            document.getElementById('edit-model-id').value = id;
            document.getElementById('edit-model-title').value = data.title || '';
            document.getElementById('edit-model-description').value = data.description || '';
            document.getElementById('edit-model-category').value = data.category || '';
            document.getElementById('edit-model-license').value = data.license || 'CC BY-NC';
            document.getElementById('edit-model-tags').value = (data.tags || []).join(', ');
            Modal.show('edit-model-modal');
        }

        async function submitModelEditFromProfile(e) {
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

        async function deleteModelFromProfile(id) {
            if (!confirm('Are you sure you want to delete this model? This cannot be undone.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_model');
                formData.append('id', id);

                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Toast.success('Model deleted successfully!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Toast.error(result.error || 'Failed to delete model');
                }
            } catch (err) {
                Toast.error('Failed to delete model');
            }
        }

        // Avatar upload functionality
        document.addEventListener('DOMContentLoaded', () => {
            const avatarUploadArea = document.getElementById('avatar-upload-area');
            const avatarInput = document.getElementById('avatar-input');
            const avatarPreview = document.getElementById('avatar-preview');
            const uploadBtn = document.getElementById('upload-avatar-btn');
            let selectedFile = null;

            if (avatarUploadArea && avatarInput) {
                // Click to select file
                avatarUploadArea.addEventListener('click', () => avatarInput.click());

                // Drag and drop
                avatarUploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    avatarUploadArea.classList.add('dragover');
                });

                avatarUploadArea.addEventListener('dragleave', () => {
                    avatarUploadArea.classList.remove('dragover');
                });

                avatarUploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    avatarUploadArea.classList.remove('dragover');
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        handleFileSelect(files[0]);
                    }
                });

                // File input change
                avatarInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        handleFileSelect(e.target.files[0]);
                    }
                });

                function handleFileSelect(file) {
                    // Validate file type
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!validTypes.includes(file.type)) {
                        Toast.error('Invalid file type. Please use JPG, PNG, GIF, or WebP.');
                        return;
                    }

                    // Validate file size (2MB max)
                    if (file.size > 2 * 1024 * 1024) {
                        Toast.error('File too large. Maximum size is 2MB.');
                        return;
                    }

                    selectedFile = file;

                    // Preview the image
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        avatarPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" id="avatar-preview-img">`;
                    };
                    reader.readAsDataURL(file);

                    // Enable upload button
                    uploadBtn.disabled = false;
                }

                // Upload button click
                uploadBtn.addEventListener('click', async () => {
                    if (!selectedFile) return;

                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

                    const formData = new FormData();
                    formData.append('action', 'upload_avatar');
                    formData.append('avatar', selectedFile);

                    try {
                        const response = await fetch('api.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            Toast.success('Profile picture updated!');
                            // Update avatar on page
                            const avatarWrapper = document.querySelector('.profile-avatar-wrapper');
                            if (avatarWrapper) {
                                const existingAvatar = avatarWrapper.querySelector('.profile-avatar');
                                const existingImg = avatarWrapper.querySelector('.profile-avatar-img');
                                if (existingAvatar) existingAvatar.remove();
                                if (existingImg) existingImg.remove();
                                const newImg = document.createElement('img');
                                newImg.src = 'uploads/' + data.avatar;
                                newImg.alt = 'Avatar';
                                newImg.className = 'profile-avatar-img';
                                avatarWrapper.insertBefore(newImg, avatarWrapper.firstChild);
                            }
                            Modal.hide('avatar-modal');
                        } else {
                            Toast.error(data.error || 'Upload failed');
                        }
                    } catch (err) {
                        Toast.error('Upload failed. Please try again.');
                    } finally {
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
                    }
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
