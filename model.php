<?php
/**
 * Community 3D Model Vault - Model Detail View
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Check maintenance mode (allow admins to bypass)
if (isMaintenanceMode() && !isAdmin()) {
    $maintenanceMessage = setting('maintenance_message', 'We are currently performing maintenance. Please check back soon.');
    include __DIR__ . '/includes/maintenance.php';
    exit;
}

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

// Check if liked (session-based)
$isLiked = isset($_SESSION['liked_models']) && in_array($modelId, $_SESSION['liked_models']);

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
    <title><?= sanitize($model['title']) ?> - <?= getSiteName() ?></title>
    
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
                    <?php $user = getCurrentUser(); ?>
                    <a href="upload.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload"></i> Upload
                    </a>
                    <a href="profile.php?id=<?= $user['id'] ?>" class="nav-user-btn">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="uploads/<?= sanitize($user['avatar']) ?>" alt="<?= sanitize($user['username']) ?>" class="nav-avatar">
                        <?php else: ?>
                            <div class="nav-avatar-placeholder"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <span><?= sanitize($user['username']) ?></span>
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
                    <?php
                    // Get files array (support both old single-file and new multi-file format)
                    $files = $model['files'] ?? [['filename' => $model['filename'], 'original_name' => pathinfo($model['filename'], PATHINFO_FILENAME), 'filesize' => $model['filesize']]];
                    $fileCount = count($files);

                    // Get photos array (support both old single-photo and new multi-photo format)
                    $photos = $model['photos'] ?? ($model['photo'] ? [$model['photo']] : []);
                    $hasPhotos = !empty($photos);
                    $photoCount = count($photos);
                    $primaryDisplay = $model['primary_display'] ?? 'auto';

                    // Build unified gallery items array (photos first, then 3D files)
                    $galleryItems = [];

                    // Add photos to gallery
                    foreach ($photos as $idx => $photo) {
                        $galleryItems[] = [
                            'type' => 'photo',
                            'url' => 'uploads/' . $photo,
                            'label' => 'Photo ' . ($idx + 1)
                        ];
                    }

                    // Add 3D model files to gallery
                    foreach ($files as $idx => $file) {
                        $galleryItems[] = [
                            'type' => '3d',
                            'url' => 'uploads/' . $file['filename'],
                            'label' => $file['original_name'] ?? pathinfo($file['filename'], PATHINFO_FILENAME)
                        ];
                    }

                    $totalItems = count($galleryItems);

                    // Determine initial slide: if photos exist and primary_display is 'photo' or 'auto', start with photo
                    $initialSlide = 0;
                    if (!$hasPhotos || ($primaryDisplay !== 'photo' && $primaryDisplay !== 'auto')) {
                        // Start with first 3D model
                        $initialSlide = $photoCount;
                    }
                    ?>

                    <!-- Unified Gallery Viewer -->
                    <div class="unified-gallery" id="unified-gallery" data-initial-slide="<?= $initialSlide ?>">
                        <!-- Navigation Arrows -->
                        <?php if ($totalItems > 1): ?>
                        <button type="button" class="gallery-nav gallery-nav-prev" id="gallery-prev">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="gallery-nav gallery-nav-next" id="gallery-next">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <?php endif; ?>

                        <!-- Gallery Slides Container -->
                        <div class="gallery-slides" id="gallery-slides">
                            <?php foreach ($galleryItems as $idx => $item): ?>
                            <div class="gallery-slide <?= $idx === $initialSlide ? 'active' : '' ?>"
                                 data-index="<?= $idx ?>"
                                 data-type="<?= $item['type'] ?>"
                                 data-url="<?= sanitize($item['url']) ?>">

                                <?php if ($item['type'] === 'photo'): ?>
                                <!-- Photo Slide -->
                                <div class="gallery-photo">
                                    <img src="<?= sanitize($item['url']) ?>" alt="<?= sanitize($model['title']) ?>">
                                </div>
                                <div class="gallery-slide-badge photo-badge-type">
                                    <i class="fas fa-camera"></i> Photo
                                </div>
                                <?php else: ?>
                                <!-- 3D Model Slide -->
                                <div class="gallery-3d-viewer"
                                     data-model-url="<?= sanitize($item['url']) ?>">
                                    <div class="viewer-loading">
                                        <div class="spinner"></div>
                                    </div>
                                </div>
                                <div class="gallery-slide-badge model-badge-type">
                                    <i class="fas fa-cube"></i> 3D Model
                                </div>
                                <!-- 3D Viewer Controls (only for 3D slides) -->
                                <div class="gallery-3d-controls">
                                    <button class="btn btn-secondary btn-icon" data-gallery-control="reset" title="Reset View">
                                        <i class="fas fa-home"></i>
                                    </button>
                                    <button class="btn btn-secondary btn-icon active" data-gallery-control="rotate" title="Auto Rotate">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <button class="btn btn-secondary btn-icon" data-gallery-control="wireframe" title="Wireframe">
                                        <i class="fas fa-border-all"></i>
                                    </button>
                                    <button class="btn btn-secondary btn-icon" data-gallery-control="screenshot" title="Screenshot">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                    <button class="btn btn-secondary btn-icon" data-gallery-control="fullscreen" title="Fullscreen">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Dot Indicators -->
                        <?php if ($totalItems > 1): ?>
                        <div class="gallery-dots" id="gallery-dots">
                            <?php foreach ($galleryItems as $idx => $item): ?>
                            <button type="button"
                                    class="gallery-dot <?= $idx === $initialSlide ? 'active' : '' ?> <?= $item['type'] === 'photo' ? 'dot-photo' : 'dot-3d' ?>"
                                    data-index="<?= $idx ?>"
                                    title="<?= $item['type'] === 'photo' ? $item['label'] : '3D: ' . $item['label'] ?>">
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Color Picker (for 3D models) -->
                        <div class="gallery-color-picker" id="gallery-color-picker">
                            <button class="color-picker-btn" id="color-picker-btn" title="Change Color"></button>
                            <div class="color-palette-panel" id="color-palette">
                                <div class="palette-header">
                                    <span class="palette-title"><i class="fas fa-palette"></i> Colors</span>
                                </div>
                                <div class="palette-section">
                                    <div class="palette-label">Custom Color</div>
                                    <div class="custom-color-row">
                                        <input type="color" id="custom-color-input" value="#00f0ff" class="custom-color-input">
                                        <button class="btn btn-sm btn-outline" id="apply-custom-color">Apply</button>
                                    </div>
                                </div>
                                <div class="palette-section">
                                    <div class="palette-label">Neon Colors</div>
                                    <div class="color-grid">
                                        <div class="color-swatch" style="background: #00f0ff;" data-color="0x00f0ff" title="Neon Cyan"></div>
                                        <div class="color-swatch" style="background: #ff00aa;" data-color="0xff00aa" title="Neon Magenta"></div>
                                        <div class="color-swatch" style="background: #f0ff00;" data-color="0xf0ff00" title="Neon Yellow"></div>
                                        <div class="color-swatch" style="background: #00ff88;" data-color="0x00ff88" title="Neon Green"></div>
                                        <div class="color-swatch" style="background: #0066ff;" data-color="0x0066ff" title="Electric Blue"></div>
                                        <div class="color-swatch" style="background: #ff1493;" data-color="0xff1493" title="Hot Pink"></div>
                                    </div>
                                </div>
                                <div class="palette-section">
                                    <div class="palette-label">Standard</div>
                                    <div class="color-grid">
                                        <div class="color-swatch" style="background: #ff6b35;" data-color="0xff6b35" title="Orange"></div>
                                        <div class="color-swatch" style="background: #a855f7;" data-color="0xa855f7" title="Purple"></div>
                                        <div class="color-swatch" style="background: #ff3333;" data-color="0xff3333" title="Red"></div>
                                        <div class="color-swatch" style="background: #ff7f50;" data-color="0xff7f50" title="Coral"></div>
                                        <div class="color-swatch" style="background: #ffffff;" data-color="0xffffff" title="White"></div>
                                        <div class="color-swatch" style="background: #888888;" data-color="0x888888" title="Gray"></div>
                                    </div>
                                </div>
                                <div class="palette-section">
                                    <div class="palette-label">Metallic</div>
                                    <div class="color-grid">
                                        <div class="color-swatch" style="background: #ffd700;" data-color="0xffd700" title="Gold"></div>
                                        <div class="color-swatch" style="background: #c0c0c0;" data-color="0xc0c0c0" title="Silver"></div>
                                        <div class="color-swatch" style="background: #cd7f32;" data-color="0xcd7f32" title="Bronze"></div>
                                        <div class="color-swatch" style="background: #b87333;" data-color="0xb87333" title="Copper"></div>
                                        <div class="color-swatch" style="background: #b76e79;" data-color="0xb76e79" title="Rose Gold"></div>
                                        <div class="color-swatch" style="background: #1a1a1a;" data-color="0x1a1a1a" title="Black"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="screenshot-flash" id="screenshot-flash"></div>
                    </div>

                    <!-- Keyboard Shortcuts Hint -->
                    <div class="keyboard-hints" style="margin-top: 12px; display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;">
                        <span class="kbd-hint"><kbd>←</kbd><kbd>→</kbd> Navigate</span>
                        <span class="kbd-hint"><kbd>R</kbd> Rotate</span>
                        <span class="kbd-hint"><kbd>W</kbd> Wireframe</span>
                        <span class="kbd-hint"><kbd>F</kbd> Fullscreen</span>
                        <span class="kbd-hint"><kbd>S</kbd> Screenshot</span>
                    </div>

                    <!-- Fullscreen Viewer -->
                    <div class="viewer-fullscreen" id="fullscreen-viewer">
                        <button class="btn btn-secondary btn-icon fullscreen-close" onclick="closeFullscreen()">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="viewer-canvas" id="fullscreen-canvas"></div>
                        <div class="viewer-controls">
                            <button class="btn btn-secondary btn-icon" data-fs-control="reset" title="Reset View">
                                <i class="fas fa-home"></i>
                            </button>
                            <button class="btn btn-secondary btn-icon active" data-fs-control="rotate" title="Auto Rotate">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn btn-secondary btn-icon" data-fs-control="wireframe" title="Wireframe">
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

                    <?php
                    $printSettings = [];
                    if (!empty($model['print_settings']) && is_array($model['print_settings'])) {
                        $printSettings = array_filter(
                            $model['print_settings'],
                            fn($key) => $key !== 'material',
                            ARRAY_FILTER_USE_KEY
                        );
                    }
                    ?>
                    <!-- Print Settings -->
                    <?php if (!empty($printSettings)): ?>
                        <div class="card" style="margin-top: 24px; padding: 24px;">
                            <h3 style="margin-bottom: 12px;">Print Settings</h3>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <?php foreach ($printSettings as $key => $value): ?>
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
                        <?php if (isLoggedIn()): ?>
                            <?php if ($fileCount > 1): ?>
                            <button class="btn btn-primary btn-lg" onclick="downloadAllFiles()" style="width: 100%;">
                                <i class="fas fa-file-archive"></i> Download All (<?= $fileCount ?> files)
                            </button>
                            <div class="download-individual" style="margin-top: 8px;">
                                <span style="font-size: 0.85rem; color: var(--text-muted);">Or download individually:</span>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                                    <?php foreach ($files as $idx => $f): ?>
                                    <button class="btn btn-outline btn-sm" onclick="downloadSingleFile(<?= $idx ?>)" title="<?= sanitize($f['original_name']) ?>">
                                        <i class="fas fa-download"></i> <?= sanitize(substr($f['original_name'], 0, 12)) ?><?= strlen($f['original_name']) > 12 ? '...' : '' ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <button class="btn btn-primary btn-lg" onclick="downloadModel('<?= $modelId ?>')" style="width: 100%;">
                                <i class="fas fa-download"></i> Download Model
                            </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-lg" style="width: 100%; text-align: center;">
                                <i class="fas fa-sign-in-alt"></i> Sign In to Download
                            </a>
                        <?php endif; ?>
                        <div style="display: flex; gap: 12px; margin-top: 12px;">
                            <button class="btn btn-secondary <?= $isLiked ? 'liked' : '' ?>" onclick="likeModel('<?= $modelId ?>')" id="like-btn" style="flex: 1;" <?= $isLiked ? 'disabled' : '' ?>>
                                <i class="fas fa-heart"></i>
                                <span id="like-count"><?= $model['likes'] ?? 0 ?></span>
                            </button>
                            <?php if (isLoggedIn()): ?>
                                <button class="btn btn-secondary <?= $isFavorited ? 'active' : '' ?>"
                                        onclick="favoriteModel('<?= $modelId ?>')" id="fav-btn" style="flex: 1;"
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
                            <button class="btn btn-secondary btn-sm" onclick="openEditModal()" style="width: 100%; margin-bottom: 10px;">
                                <i class="fas fa-edit"></i> Edit Model
                            </button>
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
                            <?php
                            $rmPhotos = $rm['photos'] ?? ($rm['photo'] ? [$rm['photo']] : []);
                            $rmHasPhotos = !empty($rmPhotos);
                            $rmPrimaryDisplay = $rm['primary_display'] ?? 'auto';
                            $rmShowPhoto = ($rmPrimaryDisplay === 'photo' || $rmPrimaryDisplay === 'auto') && $rmHasPhotos;
                            ?>
                            <div class="card model-card">
                                <a href="model.php?id=<?= $rm['id'] ?>" class="model-card-preview <?= $rmShowPhoto ? 'has-photo' : '' ?>" style="display: block; cursor: pointer;">
                                    <?php if ($rmShowPhoto): ?>
                                    <div class="preview-photo">
                                        <img src="uploads/<?= sanitize($rmPhotos[0]) ?>" alt="<?= sanitize($rm['title']) ?>">
                                    </div>
                                    <?php else: ?>
                                    <div class="preview-placeholder" data-model-thumb="uploads/<?= sanitize($rm['filename']) ?>">
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
                                    <h3 class="model-card-title">
                                        <a href="model.php?id=<?= $rm['id'] ?>"><?= sanitize($rm['title']) ?></a>
                                    </h3>
                                    <div class="model-card-author">
                                        <div class="author-avatar">
                                            <?= strtoupper(substr($rm['author'], 0, 1)) ?>
                                        </div>
                                        <a href="profile.php?id=<?= $rm['user_id'] ?>"><?= sanitize($rm['author']) ?></a>
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

    <!-- Edit Model Modal -->
    <?php if (isLoggedIn() && ($model['user_id'] === $_SESSION['user_id'] || isAdmin())): ?>
    <?php $allCategories = getCategories(); ?>
    <div class="modal-overlay" id="edit-model-modal">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h2>Edit Model</h2>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>

            <!-- Tabs -->
            <div class="auth-tabs" style="margin: 0; border-radius: 0;">
                <div class="auth-tab active" onclick="showEditTab('details')">
                    <i class="fas fa-info-circle"></i> Details
                </div>
                <div class="auth-tab" onclick="showEditTab('files')">
                    <i class="fas fa-file"></i> Files (<?= count($files) ?>)
                </div>
                <div class="auth-tab" onclick="showEditTab('photos')">
                    <i class="fas fa-camera"></i> Photos (<?= count($photos) ?>)
                </div>
            </div>

            <!-- Details Tab -->
            <div id="edit-tab-details" class="edit-tab-content">
                <form id="edit-model-form" onsubmit="submitModelEdit(event)">
                    <input type="hidden" name="id" value="<?= $modelId ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label required">Title</label>
                            <input type="text" name="title" id="edit-model-title" class="form-input" required
                                   value="<?= sanitize($model['title']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit-model-description" class="form-textarea" rows="3"><?= sanitize($model['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category" id="edit-model-category" class="form-select">
                                    <option value="">Select Category</option>
                                    <?php foreach ($allCategories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($model['category'] ?? '') === $cat['id'] ? 'selected' : '' ?>>
                                            <?= sanitize($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">License</label>
                                <select name="license" id="edit-model-license" class="form-select">
                                    <?php
                                    $licenses = [
                                        'CC BY' => 'CC BY (Attribution)',
                                        'CC BY-SA' => 'CC BY-SA (ShareAlike)',
                                        'CC BY-NC' => 'CC BY-NC (NonCommercial)',
                                        'CC BY-NC-SA' => 'CC BY-NC-SA',
                                        'CC0' => 'CC0 (Public Domain)',
                                        'MIT' => 'MIT License',
                                        'GPL' => 'GPL License',
                                        'All Rights Reserved' => 'All Rights Reserved'
                                    ];
                                    $currentLicense = $model['license'] ?? 'CC BY-NC';
                                    foreach ($licenses as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $currentLicense === $value ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tags</label>
                            <input type="text" name="tags" id="edit-model-tags" class="form-input"
                                   placeholder="Enter tags separated by commas"
                                   value="<?= sanitize(implode(', ', $model['tags'] ?? [])) ?>">
                            <div class="form-hint">Separate tags with commas</div>
                        </div>
                        <?php if (!empty($photos) || !empty($files)): ?>
                        <div class="form-group">
                            <label class="form-label">Default Display</label>
                            <select name="primary_display" id="edit-model-primary-display" class="form-select">
                                <option value="0" <?= ($model['primary_display'] ?? 'auto') === '0' ? 'selected' : '' ?>>
                                    3D Model - Show 3D preview in listings
                                </option>
                                <?php if (!empty($photos)): ?>
                                <option value="photo" <?= ($model['primary_display'] ?? '') === 'photo' ? 'selected' : '' ?>>
                                    Photo - Show photo in listings
                                </option>
                                <?php endif; ?>
                                <option value="auto" <?= ($model['primary_display'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>
                                    Auto - <?= !empty($photos) ? 'Photo if available, else 3D' : '3D preview' ?>
                                </option>
                            </select>
                            <div class="form-hint">Choose what to display as the cover image in browse/search results</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="Modal.hide('edit-model-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Files Tab -->
            <div id="edit-tab-files" class="edit-tab-content" style="display: none;">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Current Files</label>
                        <div id="current-files-list" class="file-list">
                            <?php foreach ($files as $idx => $file): ?>
                            <div class="file-item" data-filename="<?= sanitize($file['filename']) ?>">
                                <div class="file-info">
                                    <i class="fas fa-cube"></i>
                                    <span class="file-name"><?= sanitize($file['original_name'] ?? $file['filename']) ?></span>
                                    <span class="file-size">(<?= formatFileSize($file['filesize'] ?? 0) ?>)</span>
                                </div>
                                <?php if (count($files) > 1): ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeFile('<?= sanitize($file['filename']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php else: ?>
                                <span class="file-hint" style="color: var(--text-muted); font-size: 0.8rem;">Primary</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Add New File</label>
                        <div class="file-upload-area" id="file-upload-area">
                            <input type="file" id="new-file-input" accept=".stl,.obj" style="display: none;">
                            <div class="upload-placeholder" onclick="document.getElementById('new-file-input').click()">
                                <i class="fas fa-plus"></i>
                                <span>Click to add STL or OBJ file</span>
                            </div>
                        </div>
                        <div class="form-hint">Supported: STL, OBJ (max 50MB)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.hide('edit-model-modal')">Close</button>
                </div>
            </div>

            <!-- Photos Tab -->
            <div id="edit-tab-photos" class="edit-tab-content" style="display: none;">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Current Photos</label>
                        <div id="current-photos-list" class="photo-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                            <?php if (empty($photos)): ?>
                            <div class="no-photos" style="grid-column: 1/-1; text-align: center; padding: 20px; color: var(--text-muted);">
                                No photos yet
                            </div>
                            <?php else: ?>
                            <?php foreach ($photos as $idx => $photo): ?>
                            <div class="photo-item" data-filename="<?= sanitize($photo) ?>" style="position: relative;">
                                <img src="uploads/<?= sanitize($photo) ?>" alt="Photo" style="width: 100%; height: 100px; object-fit: cover; border-radius: 8px;">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removePhoto('<?= sanitize($photo) ?>')"
                                        style="position: absolute; top: 4px; right: 4px; padding: 4px 8px;">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php if ($idx === 0): ?>
                                <span style="position: absolute; bottom: 4px; left: 4px; background: var(--neon-cyan); color: #000; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">Primary</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Add New Photo</label>
                        <div class="file-upload-area" id="photo-upload-area">
                            <input type="file" id="new-photo-input" accept=".jpg,.jpeg,.png,.gif,.webp" style="display: none;">
                            <div class="upload-placeholder" onclick="document.getElementById('new-photo-input').click()">
                                <i class="fas fa-camera"></i>
                                <span>Click to add photo</span>
                            </div>
                        </div>
                        <div class="form-hint">Supported: JPG, PNG, GIF, WebP (max 10MB)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.hide('edit-model-modal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .file-list { display: flex; flex-direction: column; gap: 8px; }
        .file-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px; background: var(--bg-secondary); border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .file-info { display: flex; align-items: center; gap: 10px; }
        .file-info i { color: var(--neon-cyan); }
        .file-name { font-weight: 500; }
        .file-size { color: var(--text-muted); font-size: 0.85rem; }
        .file-upload-area, .photo-upload-area {
            border: 2px dashed var(--border-color); border-radius: 8px;
            padding: 20px; text-align: center; cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }
        .file-upload-area:hover, .photo-upload-area:hover {
            border-color: var(--neon-cyan); background: rgba(0, 240, 255, 0.05);
        }
        .upload-placeholder { display: flex; flex-direction: column; align-items: center; gap: 8px; color: var(--text-muted); }
        .upload-placeholder i { font-size: 1.5rem; }
        .edit-tab-content { max-height: 60vh; overflow-y: auto; }
    </style>
    <?php endif; ?>

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
        // Unified Gallery Controller
        const GalleryController = {
            gallery: null,
            slides: [],
            dots: [],
            viewers: {},  // Store 3D viewers by slide index
            currentIndex: 0,
            totalSlides: 0,
            currentModelColor: 0x00f0ff,
            fullscreenViewer: null,

            init() {
                this.gallery = document.getElementById('unified-gallery');
                if (!this.gallery) return;

                this.slides = Array.from(document.querySelectorAll('.gallery-slide'));
                this.dots = Array.from(document.querySelectorAll('.gallery-dot'));
                this.totalSlides = this.slides.length;
                this.currentIndex = parseInt(this.gallery.dataset.initialSlide) || 0;

                // Initialize navigation
                this.initNavigation();

                // Initialize color picker
                this.initColorPicker();

                // Initialize keyboard navigation
                this.initKeyboard();

                // Load the initial slide's 3D model if it's a 3D slide
                this.loadSlideViewer(this.currentIndex);

                // Update gallery data attribute for current type
                this.updateCurrentType();
            },

            initNavigation() {
                const prevBtn = document.getElementById('gallery-prev');
                const nextBtn = document.getElementById('gallery-next');

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => this.navigate(-1));
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', () => this.navigate(1));
                }

                // Dot navigation
                this.dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => this.goToSlide(index));
                });

                // Initialize 3D controls for each slide
                this.slides.forEach((slide, index) => {
                    if (slide.dataset.type === '3d') {
                        const controls = slide.querySelectorAll('[data-gallery-control]');
                        controls.forEach(btn => {
                            btn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                this.handleControl(btn.dataset.galleryControl, index);
                            });
                        });
                    }
                });
            },

            initColorPicker() {
                const colorBtn = document.getElementById('color-picker-btn');
                const colorPalette = document.getElementById('color-palette');
                const customColorInput = document.getElementById('custom-color-input');
                const applyCustomColor = document.getElementById('apply-custom-color');

                if (!colorBtn || !colorPalette) return;

                colorBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    colorPalette.classList.toggle('active');
                });

                colorPalette.addEventListener('click', (e) => {
                    const swatch = e.target.closest('.color-swatch');
                    if (swatch) {
                        const colorHex = parseInt(swatch.dataset.color);
                        this.applyColor(colorHex);
                        colorBtn.style.background = swatch.style.background;
                        if (customColorInput) customColorInput.value = swatch.style.background;
                        colorPalette.classList.remove('active');
                        Toast.success('Color applied!');
                    }
                });

                if (applyCustomColor && customColorInput) {
                    applyCustomColor.addEventListener('click', () => {
                        const hex = customColorInput.value;
                        const colorHex = parseInt(hex.replace('#', '0x'));
                        this.applyColor(colorHex);
                        colorBtn.style.background = hex;
                        colorPalette.classList.remove('active');
                        Toast.success('Custom color applied!');
                    });
                }

                // Close palette when clicking outside
                document.addEventListener('click', (e) => {
                    if (!colorPalette.contains(e.target) && e.target !== colorBtn) {
                        colorPalette.classList.remove('active');
                    }
                });
            },

            initKeyboard() {
                document.addEventListener('keydown', (e) => {
                    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

                    const key = e.key.toLowerCase();
                    const currentSlide = this.slides[this.currentIndex];
                    const is3D = currentSlide?.dataset.type === '3d';
                    const viewer = this.viewers[this.currentIndex];

                    switch (key) {
                        case 'arrowleft':
                            this.navigate(-1);
                            break;
                        case 'arrowright':
                            this.navigate(1);
                            break;
                        case 'escape':
                            this.closeFullscreen();
                            break;
                        case 'r':
                            if (is3D && viewer) {
                                const btn = currentSlide.querySelector('[data-gallery-control="rotate"]');
                                btn?.classList.toggle('active');
                                viewer.setAutoRotate(btn?.classList.contains('active'));
                            }
                            break;
                        case 'w':
                            if (is3D && viewer) {
                                const btn = currentSlide.querySelector('[data-gallery-control="wireframe"]');
                                btn?.classList.toggle('active');
                                viewer.setWireframe(btn?.classList.contains('active'));
                            }
                            break;
                        case 'f':
                            if (is3D) this.openFullscreen();
                            break;
                        case 's':
                            if (is3D) this.takeScreenshot();
                            break;
                    }
                });
            },

            navigate(direction) {
                let newIndex = this.currentIndex + direction;

                // Wrap around
                if (newIndex < 0) newIndex = this.totalSlides - 1;
                if (newIndex >= this.totalSlides) newIndex = 0;

                this.goToSlide(newIndex);
            },

            goToSlide(index) {
                if (index === this.currentIndex || index < 0 || index >= this.totalSlides) return;

                // Update slides
                this.slides[this.currentIndex].classList.remove('active');
                this.slides[index].classList.add('active');

                // Update dots
                if (this.dots.length) {
                    this.dots[this.currentIndex].classList.remove('active');
                    this.dots[index].classList.add('active');
                }

                this.currentIndex = index;

                // Load 3D viewer if needed
                this.loadSlideViewer(index);

                // Update current type attribute
                this.updateCurrentType();
            },

            updateCurrentType() {
                const currentSlide = this.slides[this.currentIndex];
                if (currentSlide && this.gallery) {
                    this.gallery.dataset.currentType = currentSlide.dataset.type;
                }
            },

            loadSlideViewer(index) {
                const slide = this.slides[index];
                if (!slide || slide.dataset.type !== '3d') return;
                if (this.viewers[index]) return; // Already loaded

                const viewerContainer = slide.querySelector('.gallery-3d-viewer');
                if (!viewerContainer) return;

                const modelUrl = viewerContainer.dataset.modelUrl;
                const loading = viewerContainer.querySelector('.viewer-loading');

                const viewer = new ModelViewer(viewerContainer, {
                    modelColor: this.currentModelColor,
                    autoRotate: true
                });

                viewer.loadModel(modelUrl).then(() => {
                    if (loading) loading.style.display = 'none';
                    viewer.setColor(this.currentModelColor);
                }).catch(err => {
                    console.error('Failed to load model:', err);
                    if (loading) {
                        loading.innerHTML = '<div style="text-align: center; color: var(--text-muted);"><i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i><br>Failed to load 3D model</div>';
                    }
                });

                this.viewers[index] = viewer;
                viewerContainer._viewer = viewer;
            },

            applyColor(colorHex) {
                this.currentModelColor = colorHex;

                // Apply to all loaded viewers
                Object.values(this.viewers).forEach(viewer => {
                    if (viewer) viewer.setColor(colorHex);
                });
            },

            handleControl(action, slideIndex) {
                const viewer = this.viewers[slideIndex];
                if (!viewer) return;

                const slide = this.slides[slideIndex];

                switch (action) {
                    case 'reset':
                        viewer.resetView();
                        break;
                    case 'rotate':
                        const rotateBtn = slide.querySelector('[data-gallery-control="rotate"]');
                        rotateBtn?.classList.toggle('active');
                        viewer.setAutoRotate(rotateBtn?.classList.contains('active'));
                        break;
                    case 'wireframe':
                        const wireBtn = slide.querySelector('[data-gallery-control="wireframe"]');
                        wireBtn?.classList.toggle('active');
                        viewer.setWireframe(wireBtn?.classList.contains('active'));
                        break;
                    case 'screenshot':
                        this.takeScreenshot();
                        break;
                    case 'fullscreen':
                        this.openFullscreen();
                        break;
                }
            },

            takeScreenshot() {
                const viewer = this.viewers[this.currentIndex];
                if (!viewer || !viewer.renderer) return;

                // Flash effect
                const flash = document.getElementById('screenshot-flash');
                if (flash) {
                    flash.classList.add('active');
                    setTimeout(() => flash.classList.remove('active'), 300);
                }

                // Capture
                viewer.renderer.render(viewer.scene, viewer.camera);
                const dataUrl = viewer.renderer.domElement.toDataURL('image/png');

                // Download
                const link = document.createElement('a');
                link.download = 'model-screenshot.png';
                link.href = dataUrl;
                link.click();

                Toast.success('Screenshot saved!');
            },

            openFullscreen() {
                const currentSlide = this.slides[this.currentIndex];
                if (currentSlide?.dataset.type !== '3d') return;

                const fsContainer = document.getElementById('fullscreen-viewer');
                const fsCanvas = document.getElementById('fullscreen-canvas');
                if (!fsContainer || !fsCanvas) return;

                fsContainer.classList.add('active');
                document.body.style.overflow = 'hidden';

                // Create fullscreen viewer
                if (this.fullscreenViewer) {
                    this.fullscreenViewer.dispose();
                }

                const modelUrl = currentSlide.dataset.url;
                this.fullscreenViewer = new ModelViewer(fsCanvas, {
                    modelColor: this.currentModelColor,
                    autoRotate: true
                });
                this.fullscreenViewer.loadModel(modelUrl).then(() => {
                    this.fullscreenViewer.setColor(this.currentModelColor);
                });

                // Setup fullscreen controls
                document.querySelectorAll('[data-fs-control]').forEach(btn => {
                    btn.onclick = () => {
                        const action = btn.dataset.fsControl;
                        if (!this.fullscreenViewer) return;

                        switch (action) {
                            case 'reset':
                                this.fullscreenViewer.resetView();
                                break;
                            case 'wireframe':
                                btn.classList.toggle('active');
                                this.fullscreenViewer.setWireframe(btn.classList.contains('active'));
                                break;
                            case 'rotate':
                                btn.classList.toggle('active');
                                this.fullscreenViewer.setAutoRotate(btn.classList.contains('active'));
                                break;
                        }
                    };
                });
            },

            closeFullscreen() {
                const fsContainer = document.getElementById('fullscreen-viewer');
                if (!fsContainer) return;

                fsContainer.classList.remove('active');
                document.body.style.overflow = '';

                if (this.fullscreenViewer) {
                    this.fullscreenViewer.dispose();
                    this.fullscreenViewer = null;
                }
            }
        };

        // Files data for multi-file downloads
        const modelFiles = <?= json_encode(array_map(fn($f) => [
            'filename' => $f['filename'],
            'original_name' => $f['original_name'] ?? pathinfo($f['filename'], PATHINFO_FILENAME),
            'extension' => $f['extension'] ?? pathinfo($f['filename'], PATHINFO_EXTENSION)
        ], $files)) ?>;

        // Initialize after DOM ready
        document.addEventListener('DOMContentLoaded', () => {
            GalleryController.init();
        });

        // Download all files as a single ZIP
        function downloadAllFiles() {
            Toast.info('Preparing ZIP download...');
            window.location.href = 'api.php?action=download_model_zip&id=<?= $modelId ?>';
        }

        // Download a single file by index
        function downloadSingleFile(index) {
            const file = modelFiles[index];
            if (!file) return;

            const link = document.createElement('a');
            link.href = 'uploads/' + file.filename;
            const extension = file.extension ? `.${file.extension}` : '';
            link.download = file.original_name + extension;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        async function downloadModel(id) {
            try {
                const response = await API.downloadModel(id);
                if (response.success) {
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
            const btn = document.getElementById('like-btn');

            const likedModels = JSON.parse(localStorage.getItem('liked_models') || '[]');
            if (likedModels.includes(id)) {
                Toast.info('You already liked this model');
                return;
            }

            try {
                btn.classList.add('liked');
                btn.disabled = true;
                const response = await API.likeModel(id);

                if (response.success) {
                    document.getElementById('like-count').textContent = response.likes;
                    likedModels.push(id);
                    localStorage.setItem('liked_models', JSON.stringify(likedModels));
                    Toast.success('Thanks for the like!');
                } else if (response.already_liked) {
                    Toast.info('You already liked this model');
                } else {
                    Toast.error(response.error || 'Failed to like');
                    btn.classList.remove('liked');
                    btn.disabled = false;
                }
            } catch (err) {
                Toast.error('Failed to like');
                btn.classList.remove('liked');
                btn.disabled = false;
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

        // Legacy function for fullscreen close button
        function closeFullscreen() {
            GalleryController.closeFullscreen();
        }

        // Edit model functions
        const modelId = '<?= $modelId ?>';

        function openEditModal() {
            Modal.show('edit-model-modal');
        }

        function showEditTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.edit-tab-content').forEach(tab => tab.style.display = 'none');
            // Remove active from all tab buttons
            document.querySelectorAll('#edit-model-modal .auth-tab').forEach(t => t.classList.remove('active'));
            // Show selected tab
            document.getElementById('edit-tab-' + tabName).style.display = 'block';
            // Add active to clicked tab
            event.target.closest('.auth-tab').classList.add('active');
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

            // primary_display is already in the form, no conversion needed

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

        // File management
        async function removeFile(filename) {
            if (!confirm('Remove this file from the model?')) return;

            const formData = new FormData();
            formData.append('action', 'remove_model_file');
            formData.append('model_id', modelId);
            formData.append('filename', filename);

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    Toast.success('File removed!');
                    document.querySelector(`.file-item[data-filename="${filename}"]`)?.remove();
                } else {
                    Toast.error(result.error || 'Failed to remove file');
                }
            } catch (err) {
                Toast.error('Failed to remove file');
            }
        }

        // Photo management
        async function removePhoto(filename) {
            if (!confirm('Remove this photo?')) return;

            const formData = new FormData();
            formData.append('action', 'remove_model_photo');
            formData.append('model_id', modelId);
            formData.append('filename', filename);

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    Toast.success('Photo removed!');
                    document.querySelector(`.photo-item[data-filename="${filename}"]`)?.remove();
                } else {
                    Toast.error(result.error || 'Failed to remove photo');
                }
            } catch (err) {
                Toast.error('Failed to remove photo');
            }
        }

        // File upload handler
        document.getElementById('new-file-input')?.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'add_model_file');
            formData.append('model_id', modelId);
            formData.append('file', file);

            Toast.info('Uploading file...');

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    Toast.success('File added!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Toast.error(result.error || 'Failed to upload file');
                }
            } catch (err) {
                Toast.error('Failed to upload file');
            }

            e.target.value = '';
        });

        // Photo upload handler
        document.getElementById('new-photo-input')?.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'add_model_photo');
            formData.append('model_id', modelId);
            formData.append('photo', file);

            Toast.info('Uploading photo...');

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    Toast.success('Photo added!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Toast.error(result.error || 'Failed to upload photo');
                }
            } catch (err) {
                Toast.error('Failed to upload photo');
            }

            e.target.value = '';
        });
    </script>
</body>
</html>
