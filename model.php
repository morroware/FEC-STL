<?php
/**
 * FEC STL Vault - Model Detail View
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$modelId = $_GET['id'] ?? '';
$model = getModel($modelId);

if (!$model) {
    header('Location: browse.php');
    exit;
}

// Increment view count
incrementModelStat($modelId, 'views');
$model['views']++;

// Get related data
$author = getUser($model['user_id']);
$category = getCategory($model['category']);

// Check if favorited
$isFavorited = false;
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    $isFavorited = in_array($modelId, $currentUser['favorites'] ?? []);
}

// Get related models (same category)
$relatedModels = array_slice(array_filter(
    searchModels('', $model['category'], 'popular'),
    fn($m) => $m['id'] !== $modelId
), 0, 4);

foreach ($relatedModels as $index => $rm) {
    $u = getUser($rm['user_id']);
    $relatedModels[$index]['author'] = $u ? $u['username'] : 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($model['title']) ?> - <?= SITE_NAME ?></title>
    
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
                    <?php $user = getCurrentUser(); ?>
                    <a href="upload.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload"></i> Upload
                    </a>
                    <a href="profile.php?id=<?= $user['id'] ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-user"></i> <?= sanitize($user['username']) ?>
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
            <!-- Breadcrumb -->
            <div style="margin-bottom: 24px;">
                <a href="browse.php" style="color: var(--text-muted);">
                    <i class="fas fa-arrow-left"></i> Back to Browse
                </a>
            </div>

            <div class="model-header">
                <!-- 3D Viewer Column -->
                <div>
                    <div class="viewer-container">
                        <div class="viewer-loading">
                            <div class="spinner"></div>
                        </div>
                        <div class="viewer-canvas" data-stl-viewer data-stl-url="uploads/<?= sanitize($model['filename']) ?>"></div>
                        <div class="viewer-controls">
                            <button class="btn btn-secondary btn-icon" data-viewer-control="reset" title="Reset View">
                                <i class="fas fa-home"></i>
                            </button>
                            <button class="btn btn-secondary btn-icon active" data-viewer-control="rotate" title="Auto Rotate">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn btn-secondary btn-icon" data-viewer-control="wireframe" title="Wireframe">
                                <i class="fas fa-border-all"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($model['description'])): ?>
                        <div class="card" style="margin-top: 24px; padding: 24px;">
                            <h3 style="margin-bottom: 12px;">Description</h3>
                            <p style="color: var(--text-secondary); white-space: pre-wrap;"><?= sanitize($model['description']) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Print Settings -->
                    <?php if (!empty($model['print_settings'])): ?>
                        <div class="card" style="margin-top: 24px; padding: 24px;">
                            <h3 style="margin-bottom: 12px;">Print Settings</h3>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <?php foreach ($model['print_settings'] as $key => $value): ?>
                                    <div>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;"><?= ucfirst(str_replace('_', ' ', $key)) ?></span>
                                        <div style="font-weight: 500;"><?= sanitize($value) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Info Sidebar -->
                <div class="model-info">
                    <?php if ($category): ?>
                        <a href="browse.php?category=<?= $category['id'] ?>" class="model-card-category" style="margin-bottom: 12px;">
                            <i class="fas <?= sanitize($category['icon']) ?>"></i>
                            <?= sanitize($category['name']) ?>
                        </a>
                    <?php endif; ?>
                    
                    <h1 class="model-title"><?= sanitize($model['title']) ?></h1>
                    
                    <!-- Author Info -->
                    <div class="model-author-info">
                        <div class="model-author-avatar">
                            <?= $author ? strtoupper(substr($author['username'], 0, 1)) : '?' ?>
                        </div>
                        <div>
                            <a href="profile.php?id=<?= $model['user_id'] ?>" style="font-weight: 600;">
                                <?= $author ? sanitize($author['username']) : 'Unknown' ?>
                            </a>
                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                <?= timeAgo($model['created_at']) ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="model-stats">
                        <div class="model-stat">
                            <div class="model-stat-value"><?= number_format($model['downloads'] ?? 0) ?></div>
                            <div class="model-stat-label">Downloads</div>
                        </div>
                        <div class="model-stat">
                            <div class="model-stat-value"><?= number_format($model['likes'] ?? 0) ?></div>
                            <div class="model-stat-label">Likes</div>
                        </div>
                        <div class="model-stat">
                            <div class="model-stat-value"><?= number_format($model['views'] ?? 0) ?></div>
                            <div class="model-stat-label">Views</div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="model-actions">
                        <button class="btn btn-primary btn-lg" onclick="downloadModel('<?= $modelId ?>')">
                            <i class="fas fa-download"></i> Download STL
                        </button>
                        <div style="display: flex; gap: 12px;">
                            <button class="btn btn-secondary" onclick="likeModel('<?= $modelId ?>')" id="like-btn">
                                <i class="fas fa-heart"></i> 
                                <span id="like-count"><?= $model['likes'] ?? 0 ?></span>
                            </button>
                            <?php if (isLoggedIn()): ?>
                                <button class="btn btn-secondary <?= $isFavorited ? 'active' : '' ?>" 
                                        onclick="favoriteModel('<?= $modelId ?>')" id="fav-btn"
                                        style="<?= $isFavorited ? 'border-color: var(--neon-magenta); color: var(--neon-magenta);' : '' ?>">
                                    <i class="fas fa-bookmark"></i> 
                                    <?= $isFavorited ? 'Saved' : 'Save' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- File Info -->
                    <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: var(--text-muted);">File Size</span>
                            <span><?= formatFileSize($model['filesize']) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: var(--text-muted);">License</span>
                            <span><?= sanitize($model['license'] ?? 'CC BY-NC') ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Uploaded</span>
                            <span><?= date('M j, Y', strtotime($model['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <!-- Tags -->
                    <?php if (!empty($model['tags'])): ?>
                        <div class="model-tags">
                            <?php foreach ($model['tags'] as $tag): ?>
                                <a href="browse.php?q=<?= urlencode($tag) ?>" class="tag"><?= sanitize($tag) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Owner Actions -->
                    <?php if (isLoggedIn() && ($model['user_id'] === $_SESSION['user_id'] || isAdmin())): ?>
                        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                            <button class="btn btn-danger btn-sm" onclick="deleteModel('<?= $modelId ?>')" style="width: 100%;">
                                <i class="fas fa-trash"></i> Delete Model
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Related Models -->
            <?php if (!empty($relatedModels)): ?>
                <section style="margin-top: 60px;">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-cube"></i>
                            Related Models
                        </h2>
                    </div>
                    
                    <div class="model-grid">
                        <?php foreach ($relatedModels as $rm): ?>
                            <div class="card model-card">
                                <div class="model-card-preview">
                                    <div class="preview-placeholder">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                    <div class="model-card-overlay">
                                        <div class="model-card-actions">
                                            <a href="model.php?id=<?= $rm['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="model-card-body">
                                    <h3 class="model-card-title">
                                        <a href="model.php?id=<?= $rm['id'] ?>"><?= sanitize($rm['title']) ?></a>
                                    </h3>
                                    <div class="model-card-author">
                                        <div class="author-avatar">
                                            <?= strtoupper(substr($rm['author'], 0, 1)) ?>
                                        </div>
                                        <span><?= sanitize($rm['author']) ?></span>
                                    </div>
                                    <div class="model-card-meta">
                                        <span><i class="fas fa-download"></i> <?= $rm['downloads'] ?? 0 ?></span>
                                        <span><i class="fas fa-heart"></i> <?= $rm['likes'] ?? 0 ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="browse.php">Browse</a>
                    <a href="login.php">Sign In</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. Made for the FEC community.
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/STLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="js/app.js"></script>
    <script>
        async function downloadModel(id) {
            try {
                const response = await API.downloadModel(id);
                if (response.success) {
                    // Create download link
                    const link = document.createElement('a');
                    link.href = response.download_url;
                    link.download = response.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    Toast.success('Download started!');
                } else {
                    Toast.error(response.error || 'Download failed');
                }
            } catch (err) {
                Toast.error('Download failed');
            }
        }
        
        async function likeModel(id) {
            try {
                const response = await API.likeModel(id);
                if (response.success) {
                    document.getElementById('like-count').textContent = response.likes;
                    Toast.success('Thanks for the like!');
                }
            } catch (err) {
                Toast.error('Failed to like');
            }
        }
        
        async function favoriteModel(id) {
            try {
                const response = await API.favoriteModel(id);
                if (response.success) {
                    const btn = document.getElementById('fav-btn');
                    if (response.is_favorited) {
                        btn.innerHTML = '<i class="fas fa-bookmark"></i> Saved';
                        btn.style.borderColor = 'var(--neon-magenta)';
                        btn.style.color = 'var(--neon-magenta)';
                        Toast.success('Added to favorites!');
                    } else {
                        btn.innerHTML = '<i class="fas fa-bookmark"></i> Save';
                        btn.style.borderColor = '';
                        btn.style.color = '';
                        Toast.info('Removed from favorites');
                    }
                }
            } catch (err) {
                Toast.error('Please log in to save');
            }
        }
        
        async function deleteModel(id) {
            if (!confirm('Are you sure you want to delete this model? This cannot be undone.')) {
                return;
            }
            
            try {
                const response = await API.deleteModel(id);
                if (response.success) {
                    Toast.success('Model deleted');
                    setTimeout(() => window.location = 'browse.php', 1000);
                } else {
                    Toast.error(response.error || 'Delete failed');
                }
            } catch (err) {
                Toast.error('Delete failed');
            }
        }
    </script>
</body>
</html>
