<?php
/**
 * Community 3D Model Vault - Browse Models
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Get parameters
$query = $_GET['q'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 16;

// Get data
$categories = getCategories();
$models = searchModels($query, $categoryFilter ?: null, $sort);

// Pagination
$total = count($models);
$totalPages = ceil($total / $limit);
$offset = ($page - 1) * $limit;
$models = array_slice($models, $offset, $limit);

// Add author info
foreach ($models as $index => $model) {
    $user = getUser($model['user_id']);
    $models[$index]['author'] = $user ? $user['username'] : 'Unknown';
}

// Page title
$pageTitle = 'Browse Models';
if ($categoryFilter) {
    $cat = getCategory($categoryFilter);
    if ($cat) $pageTitle = $cat['name'];
}
if ($query) {
    $pageTitle = 'Search: ' . $query;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - <?= SITE_NAME ?></title>
    
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
                <a href="browse.php" class="nav-link active">Browse</a>
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
            <!-- Page Header -->
            <div class="section-header" style="margin-bottom: 24px;">
                <h1 class="section-title">
                    <?php if ($categoryFilter && $cat = getCategory($categoryFilter)): ?>
                        <i class="fas <?= sanitize($cat['icon']) ?>"></i>
                        <?= sanitize($cat['name']) ?>
                    <?php elseif ($query): ?>
                        <i class="fas fa-search"></i>
                        Search Results
                    <?php else: ?>
                        <i class="fas fa-cube"></i>
                        All Models
                    <?php endif; ?>
                </h1>
                <span style="color: var(--text-muted);"><?= $total ?> models found</span>
            </div>

            <!-- Filters Bar -->
            <div class="filters-bar">
                <!-- Search -->
                <form action="browse.php" method="GET" style="display: flex; gap: 8px; flex: 1; max-width: 400px;">
                    <input type="text" name="q" value="<?= sanitize($query) ?>" 
                           placeholder="Search models..." class="form-input" style="padding: 10px 14px;">
                    <?php if ($categoryFilter): ?>
                        <input type="hidden" name="category" value="<?= sanitize($categoryFilter) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                
                <!-- Category Filter -->
                <div class="filter-group">
                    <label>Category:</label>
                    <select class="filter-select" onchange="applyFilter('category', this.value)">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter === $cat['id'] ? 'selected' : '' ?>>
                                <?= sanitize($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sort -->
                <div class="filter-group">
                    <label>Sort:</label>
                    <select class="filter-select" onchange="applyFilter('sort', this.value)">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Downloads</option>
                        <option value="likes" <?= $sort === 'likes' ? 'selected' : '' ?>>Most Liked</option>
                    </select>
                </div>
            </div>

            <!-- Models Grid -->
            <?php if (empty($models)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No models found</h3>
                    <p>Try adjusting your search or filters</p>
                    <a href="browse.php" class="btn btn-secondary" style="margin-top: 20px;">
                        Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="model-grid">
                    <?php foreach ($models as $model): ?>
                        <?php
                        $category = getCategory($model['category']);
                        $fileCount = $model['file_count'] ?? 1;

                        // Get photos array (support both formats)
                        $photos = $model['photos'] ?? ($model['photo'] ? [$model['photo']] : []);
                        $hasPhotos = !empty($photos);
                        $primaryDisplay = $model['primary_display'] ?? 'auto';

                        // Determine what to show as preview
                        $showPhoto = false;
                        if ($primaryDisplay === 'photo' && $hasPhotos) {
                            $showPhoto = true;
                        } elseif ($primaryDisplay === 'auto' && $hasPhotos) {
                            $showPhoto = true;
                        }
                        // If primary_display is a number (file index), show 3D
                        ?>
                        <div class="card model-card">
                            <a href="model.php?id=<?= $model['id'] ?>" class="model-card-preview <?= $showPhoto ? 'has-photo' : '' ?>" style="display: block; cursor: pointer;">
                                <?php if ($showPhoto): ?>
                                <!-- Show uploaded photo as thumbnail -->
                                <div class="preview-photo">
                                    <img src="uploads/<?= sanitize($photos[0]) ?>" alt="<?= sanitize($model['title']) ?>" loading="lazy">
                                </div>
                                <div class="photo-indicator">
                                    <i class="fas fa-camera"></i> Photo
                                </div>
                                <?php else: ?>
                                <!-- Show 3D preview placeholder -->
                                <div class="preview-placeholder" data-model-thumb="uploads/<?= sanitize($model['filename']) ?>">
                                    <i class="fas fa-cube"></i>
                                </div>
                                <?php endif; ?>
                                <?php if ($fileCount > 1): ?>
                                <div style="position: absolute; top: 12px; right: 12px; z-index: 3;">
                                    <span class="file-count-badge"><?= $fileCount ?> files</span>
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
                                    <span><i class="fas fa-clock"></i> <?= timeAgo($model['created_at']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-btn" aria-label="Previous page">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                                <span class="sr-only">Previous</span>
                            </a>
                        <?php endif; ?>

                        <div class="pagination-numbers">
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="pagination-number active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="pagination-number"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-btn" aria-label="Next page">
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                <span class="sr-only">Next</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. A community-driven platform.
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
        function applyFilter(name, value) {
            const url = new URL(window.location);
            if (value) {
                url.searchParams.set(name, value);
            } else {
                url.searchParams.delete(name);
            }
            url.searchParams.delete('page'); // Reset to page 1
            window.location = url;
        }
    </script>
</body>
</html>
