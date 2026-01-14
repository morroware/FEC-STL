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
                    <?php
                    // Get files array (support both old single-file and new multi-file format)
                    $files = $model['files'] ?? [['filename' => $model['filename'], 'original_name' => pathinfo($model['filename'], PATHINFO_FILENAME), 'filesize' => $model['filesize']]];
                    $fileCount = count($files);
                    ?>

                    <?php if ($fileCount > 1): ?>
                    <!-- File Selector Tabs -->
                    <div class="file-selector" style="margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <span class="file-count-badge"><?= $fileCount ?> files</span>
                            <span style="color: var(--text-secondary); font-size: 0.9rem;">Click to switch between files</span>
                        </div>
                        <div class="file-tabs" id="model-file-tabs">
                            <?php foreach ($files as $index => $file): ?>
                            <button type="button" class="file-tab <?= $index === 0 ? 'active' : '' ?>"
                                    data-file-index="<?= $index ?>"
                                    data-file-url="uploads/<?= sanitize($file['filename']) ?>">
                                <i class="fas fa-cube"></i>
                                <?= sanitize($file['original_name'] ?? pathinfo($file['filename'], PATHINFO_FILENAME)) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="viewer-container">
                        <div class="viewer-loading">
                            <div class="spinner"></div>
                        </div>
                        <div class="screenshot-flash" id="screenshot-flash"></div>

                        <!-- Toolbar (top-left) -->
                        <div class="viewer-toolbar">
                            <!-- Color Picker -->
                            <div class="viewer-color-picker">
                                <button class="color-picker-btn" id="color-picker-btn" title="Change Color"></button>
                                <div class="color-palette-panel" id="color-palette">
                                    <div class="palette-header">
                                        <span class="palette-title"><i class="fas fa-palette"></i> Colors</span>
                                        <button class="restore-colors-btn" id="restore-colors-btn" title="Restore Original Colors">
                                            <i class="fas fa-undo"></i>
                                        </button>
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

                            <!-- Material Selector -->
                            <div class="viewer-material-picker">
                                <button class="material-picker-btn" id="material-picker-btn" title="Material Finish">
                                    <i class="fas fa-gem"></i>
                                </button>
                                <div class="material-panel" id="material-panel">
                                    <div class="palette-header">
                                        <span class="palette-title"><i class="fas fa-gem"></i> Material Finish</span>
                                    </div>
                                    <div class="material-grid">
                                        <button class="material-btn active" data-material="default" title="Default">
                                            <div class="material-preview default"></div>
                                            <span>Default</span>
                                        </button>
                                        <button class="material-btn" data-material="pla" title="PLA - Matte plastic">
                                            <div class="material-preview pla"></div>
                                            <span>PLA</span>
                                        </button>
                                        <button class="material-btn" data-material="abs" title="ABS - Semi-gloss plastic">
                                            <div class="material-preview abs"></div>
                                            <span>ABS</span>
                                        </button>
                                        <button class="material-btn" data-material="petg" title="PETG - Glossy clear plastic">
                                            <div class="material-preview petg"></div>
                                            <span>PETG</span>
                                        </button>
                                        <button class="material-btn" data-material="resin" title="Resin - Ultra smooth glossy">
                                            <div class="material-preview resin"></div>
                                            <span>Resin</span>
                                        </button>
                                        <button class="material-btn" data-material="silk" title="Silk PLA - Shiny metallic">
                                            <div class="material-preview silk"></div>
                                            <span>Silk</span>
                                        </button>
                                        <button class="material-btn" data-material="metal" title="Metal - Full metallic finish">
                                            <div class="material-preview metal"></div>
                                            <span>Metal</span>
                                        </button>
                                        <button class="material-btn" data-material="matte" title="Matte - No reflections">
                                            <div class="material-preview matte"></div>
                                            <span>Matte</span>
                                        </button>
                                        <button class="material-btn" data-material="glow" title="Glow - Emissive effect">
                                            <div class="material-preview glow"></div>
                                            <span>Glow</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="viewer-canvas" id="main-viewer"
                             data-stl-viewer
                             data-stl-url="uploads/<?= sanitize($files[0]['filename']) ?>"></div>

                        <!-- Controls (bottom-right) -->
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
                            <button class="btn btn-secondary btn-icon" data-viewer-control="screenshot" title="Screenshot">
                                <i class="fas fa-camera"></i>
                            </button>
                            <button class="btn btn-secondary btn-icon" data-viewer-control="fullscreen" title="Fullscreen">
                                <i class="fas fa-expand"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Keyboard Shortcuts Hint -->
                    <div class="keyboard-hints" style="margin-top: 12px; display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;">
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
                            <i class="fas fa-download"></i> Download STL
                        </button>
                        <?php endif; ?>
                        <div style="display: flex; gap: 12px; margin-top: 12px;">
                            <button class="btn btn-secondary" onclick="likeModel('<?= $modelId ?>')" id="like-btn" style="flex: 1;">
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
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/OBJLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/MTLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/PLYLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/3MFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Store viewer reference globally
        let mainViewer = null;
        let fullscreenViewer = null;
        let currentModelColor = 0x00f0ff;
        let currentStlUrl = '';

        // Files data for multi-file downloads
        const modelFiles = <?= json_encode(array_map(fn($f) => [
            'filename' => $f['filename'],
            'original_name' => $f['original_name'] ?? pathinfo($f['filename'], PATHINFO_FILENAME)
        ], $files)) ?>;

        // Initialize after DOM ready
        document.addEventListener('DOMContentLoaded', () => {
            const viewerContainer = document.getElementById('main-viewer');
            if (viewerContainer) {
                currentStlUrl = viewerContainer.dataset.stlUrl;
                mainViewer = viewerContainer._viewer;
            }

            // File tab switching (for multi-file models)
            const fileTabs = document.getElementById('model-file-tabs');
            if (fileTabs) {
                fileTabs.addEventListener('click', (e) => {
                    const tab = e.target.closest('.file-tab');
                    if (!tab) return;

                    // Update active state
                    fileTabs.querySelectorAll('.file-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    // Load new file
                    const url = tab.dataset.fileUrl;
                    currentStlUrl = url;
                    loadNewFile(url);
                });
            }

            // Color picker
            const colorBtn = document.getElementById('color-picker-btn');
            const colorPalette = document.getElementById('color-palette');
            const customColorInput = document.getElementById('custom-color-input');
            const applyCustomColor = document.getElementById('apply-custom-color');
            const restoreColorsBtn = document.getElementById('restore-colors-btn');

            // Material picker
            const materialBtn = document.getElementById('material-picker-btn');
            const materialPanel = document.getElementById('material-panel');

            if (colorBtn && colorPalette) {
                colorBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    colorPalette.classList.toggle('active');
                    materialPanel?.classList.remove('active');
                });

                colorPalette.addEventListener('click', (e) => {
                    const swatch = e.target.closest('.color-swatch');
                    if (swatch) {
                        const colorHex = parseInt(swatch.dataset.color);
                        currentModelColor = colorHex;
                        colorBtn.style.background = swatch.style.background;
                        customColorInput.value = swatch.style.background;

                        if (mainViewer) {
                            mainViewer.setColor(colorHex);
                        }
                        colorPalette.classList.remove('active');
                        Toast.success('Color applied!');
                    }
                });

                // Custom color picker
                if (applyCustomColor && customColorInput) {
                    applyCustomColor.addEventListener('click', () => {
                        const hex = customColorInput.value;
                        const colorHex = parseInt(hex.replace('#', '0x'));
                        currentModelColor = colorHex;
                        colorBtn.style.background = hex;

                        if (mainViewer) {
                            mainViewer.setColor(colorHex);
                        }
                        colorPalette.classList.remove('active');
                        Toast.success('Custom color applied!');
                    });
                }

                // Restore original colors
                if (restoreColorsBtn) {
                    restoreColorsBtn.addEventListener('click', () => {
                        if (mainViewer && mainViewer.restoreOriginalColors) {
                            mainViewer.restoreOriginalColors();
                            Toast.info('Original colors restored');
                        } else {
                            Toast.warning('No original colors to restore');
                        }
                        colorPalette.classList.remove('active');
                    });
                }

                // Close palette when clicking outside
                document.addEventListener('click', (e) => {
                    if (!colorPalette.contains(e.target) && e.target !== colorBtn) {
                        colorPalette.classList.remove('active');
                    }
                });
            }

            // Material picker
            if (materialBtn && materialPanel) {
                materialBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    materialPanel.classList.toggle('active');
                    colorPalette?.classList.remove('active');
                });

                materialPanel.querySelectorAll('.material-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const material = btn.dataset.material;

                        // Update active state
                        materialPanel.querySelectorAll('.material-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');

                        // Apply material
                        if (mainViewer && mainViewer.setMaterialPreset) {
                            mainViewer.setMaterialPreset(material);
                            Toast.success(`${btn.querySelector('span').textContent} finish applied!`);
                        }

                        materialPanel.classList.remove('active');
                    });
                });

                // Close panel when clicking outside
                document.addEventListener('click', (e) => {
                    if (!materialPanel.contains(e.target) && e.target !== materialBtn) {
                        materialPanel.classList.remove('active');
                    }
                });
            }

            // Screenshot functionality
            document.querySelectorAll('[data-viewer-control="screenshot"]').forEach(btn => {
                btn.addEventListener('click', takeScreenshot);
            });

            // Fullscreen functionality
            document.querySelectorAll('[data-viewer-control="fullscreen"]').forEach(btn => {
                btn.addEventListener('click', openFullscreen);
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Don't trigger if typing in an input
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

                const key = e.key.toLowerCase();
                const viewer = document.getElementById('main-viewer')?._viewer;

                switch (key) {
                    case 'escape':
                        closeFullscreen();
                        break;
                    case 'r':
                        if (viewer) {
                            const btn = document.querySelector('[data-viewer-control="rotate"]');
                            btn?.classList.toggle('active');
                            viewer.setAutoRotate(btn?.classList.contains('active'));
                        }
                        break;
                    case 'w':
                        if (viewer) {
                            const btn = document.querySelector('[data-viewer-control="wireframe"]');
                            btn?.classList.toggle('active');
                            viewer.setWireframe(btn?.classList.contains('active'));
                        }
                        break;
                    case 'f':
                        openFullscreen();
                        break;
                    case 's':
                        takeScreenshot();
                        break;
                }
            });
        });

        function loadNewFile(url) {
            const viewerContainer = document.getElementById('main-viewer');
            if (!viewerContainer) return;

            // Show loading
            const loading = viewerContainer.closest('.viewer-container')?.querySelector('.viewer-loading');
            if (loading) loading.style.display = 'flex';

            // Get or create viewer
            let viewer = viewerContainer._viewer;
            if (!viewer) {
                viewer = new ModelViewer(viewerContainer, { modelColor: currentModelColor });
                viewerContainer._viewer = viewer;
            }

            viewer.loadModel(url).then(() => {
                if (loading) loading.style.display = 'none';
                // Only set color if it's not a format with embedded colors
                const ext = url.split('.').pop().toLowerCase();
                if (!['gltf', 'glb', 'ply', 'obj', '3mf'].includes(ext) || !viewer.hasVertexColors) {
                    viewer.setColor(currentModelColor);
                }
                mainViewer = viewer;
            }).catch(err => {
                Toast.error('Failed to load model');
                console.error(err);
            });
        }

        function takeScreenshot() {
            const viewerContainer = document.getElementById('main-viewer');
            const viewer = viewerContainer?._viewer;
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
        }

        function openFullscreen() {
            const fsContainer = document.getElementById('fullscreen-viewer');
            const fsCanvas = document.getElementById('fullscreen-canvas');
            if (!fsContainer || !fsCanvas) return;

            fsContainer.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Create fullscreen viewer
            if (fullscreenViewer) {
                fullscreenViewer.dispose();
            }

            fullscreenViewer = new ModelViewer(fsCanvas, { modelColor: currentModelColor, autoRotate: true });
            fullscreenViewer.loadModel(currentStlUrl).then(() => {
                const ext = currentStlUrl.split('.').pop().toLowerCase();
                if (!['gltf', 'glb', 'ply', 'obj', '3mf'].includes(ext) || !fullscreenViewer.hasVertexColors) {
                    fullscreenViewer.setColor(currentModelColor);
                }
            });

            // Setup fullscreen controls
            document.querySelectorAll('[data-fs-control]').forEach(btn => {
                btn.onclick = () => {
                    const action = btn.dataset.fsControl;
                    if (!fullscreenViewer) return;

                    switch (action) {
                        case 'reset':
                            fullscreenViewer.resetView();
                            break;
                        case 'wireframe':
                            btn.classList.toggle('active');
                            fullscreenViewer.setWireframe(btn.classList.contains('active'));
                            break;
                        case 'rotate':
                            btn.classList.toggle('active');
                            fullscreenViewer.setAutoRotate(btn.classList.contains('active'));
                            break;
                    }
                };
            });
        }

        function closeFullscreen() {
            const fsContainer = document.getElementById('fullscreen-viewer');
            if (!fsContainer) return;

            fsContainer.classList.remove('active');
            document.body.style.overflow = '';

            if (fullscreenViewer) {
                fullscreenViewer.dispose();
                fullscreenViewer = null;
            }
        }

        // Download all files sequentially
        async function downloadAllFiles() {
            Toast.info(`Downloading ${modelFiles.length} files...`);

            for (let i = 0; i < modelFiles.length; i++) {
                await new Promise(resolve => setTimeout(resolve, 300)); // Small delay between downloads
                downloadSingleFile(i);
            }

            // Increment download count once
            try {
                await API.downloadModel('<?= $modelId ?>');
            } catch (e) {}
        }

        // Download a single file by index
        function downloadSingleFile(index) {
            const file = modelFiles[index];
            if (!file) return;

            const link = document.createElement('a');
            link.href = 'uploads/' + file.filename;
            link.download = file.original_name + '.stl';
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
