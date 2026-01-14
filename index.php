<?php
/**
 * FEC STL Vault - Homepage
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$categories = getCategories();
$recentModels = searchModels('', null, 'newest');
$recentModels = array_slice($recentModels, 0, 8);

// Get trending/popular models (most downloads in recent models)
$trendingModels = searchModels('', null, 'popular');
$trendingModels = array_slice($trendingModels, 0, 4);

// Add author info to models
foreach ($recentModels as $index => $model) {
    $user = getUser($model['user_id']);
    $recentModels[$index]['author'] = $user ? $user['username'] : 'Unknown';
}

foreach ($trendingModels as $index => $model) {
    $user = getUser($model['user_id']);
    $trendingModels[$index]['author'] = $user ? $user['username'] : 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - <?= SITE_TAGLINE ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
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
                <a href="index.php" class="nav-link active">Home</a>
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
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <h1 class="hero-title">
                    <span class="text-gradient"><?= SITE_NAME ?></span>
                </h1>
                <p class="hero-subtitle">
                    Discover, download, and share the latest FEC-ready 3D models for parts, repairs, and upgrades.
                </p>
                
                <!-- Search Bar -->
                <form class="search-bar" action="browse.php" method="GET">
                    <input type="text" name="q" placeholder="Search for arcade parts, coin-op repairs, signage...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>

                <div class="hero-actions">
                    <a href="browse.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-compass"></i> Browse Models
                    </a>
                    <a href="#recent-models" class="btn btn-secondary btn-lg">
                        <i class="fas fa-clock"></i> Recent Uploads
                    </a>
                </div>
            </div>
        </section>

        <?php if (!empty($trendingModels)): ?>
        <!-- Trending/Featured Section -->
        <section class="container" style="margin-bottom: 60px;">
            <div class="featured-section">
                <div class="section-header" style="margin-bottom: 24px;">
                    <div>
                        <span class="featured-badge">
                            <i class="fas fa-fire"></i> Hot Right Now
                        </span>
                        <h2 class="section-title" style="margin-top: 12px;">
                            <i class="fas fa-chart-line"></i>
                            Trending Models
                        </h2>
                    </div>
                    <a href="browse.php?sort=popular" class="btn btn-outline btn-sm">View All Popular</a>
                </div>

                <div class="model-grid">
                    <?php foreach ($trendingModels as $tm): ?>
                        <?php $category = getCategory($tm['category']); ?>
                        <div class="card model-card">
                            <div class="model-card-preview">
                                <div class="preview-placeholder" data-stl-thumb="uploads/<?= sanitize($tm['filename']) ?>">
                                    <i class="fas fa-cube"></i>
                                </div>
                                <?php if (($tm['file_count'] ?? 1) > 1): ?>
                                <div style="position: absolute; top: 12px; right: 12px;">
                                    <span class="file-count-badge"><?= $tm['file_count'] ?> files</span>
                                </div>
                                <?php endif; ?>
                                <?php if (($tm['downloads'] ?? 0) > 0): ?>
                                <div style="position: absolute; top: 12px; left: 12px;">
                                    <span class="trending-indicator">
                                        <i class="fas fa-arrow-up"></i> <?= $tm['downloads'] ?> downloads
                                    </span>
                                </div>
                                <?php endif; ?>
                                <div class="model-card-overlay">
                                    <div class="model-card-actions">
                                        <a href="model.php?id=<?= $tm['id'] ?>" class="btn btn-primary btn-sm">
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
                                    <a href="model.php?id=<?= $tm['id'] ?>"><?= sanitize($tm['title']) ?></a>
                                </h3>
                                <div class="model-card-author">
                                    <div class="author-avatar">
                                        <?= strtoupper(substr($tm['author'], 0, 1)) ?>
                                    </div>
                                    <a href="profile.php?id=<?= $tm['user_id'] ?>"><?= sanitize($tm['author']) ?></a>
                                </div>
                                <div class="model-card-meta">
                                    <span><i class="fas fa-download"></i> <?= $tm['downloads'] ?? 0 ?></span>
                                    <span><i class="fas fa-heart"></i> <?= $tm['likes'] ?? 0 ?></span>
                                    <span><i class="fas fa-eye"></i> <?= $tm['views'] ?? 0 ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Categories Section -->
        <section class="container" style="margin-bottom: 60px;">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-folder"></i>
                    Browse Categories
                </h2>
                <a href="browse.php" class="btn btn-outline btn-sm">View All</a>
            </div>

            <div class="category-panel">
                <div class="category-panel-copy">
                    <span class="eyebrow">Find the perfect part fast</span>
                    <h3>Filter by arcade use-case</h3>
                    <p>Explore curated categories to spotlight the most essential models in the vault.</p>
                </div>
                <form class="category-form" action="browse.php" method="GET">
                    <label for="category-select">Select a category</label>
                    <div class="category-select">
                        <select id="category-select" name="category">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= sanitize($cat['name']) ?> (<?= $cat['count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </form>
            </div>
        </section>

        <!-- Recent Models Section -->
        <section class="container" id="recent-models">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-clock"></i>
                    Recent Uploads
                </h2>
                <a href="browse.php?sort=newest" class="btn btn-outline btn-sm">View All</a>
            </div>
            
            <?php if (empty($recentModels)): ?>
                <div class="empty-state">
                    <i class="fas fa-cube"></i>
                    <h3>No models yet</h3>
                    <p>Be the first to upload a model!</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="upload.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-upload"></i> Upload Model
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="model-grid">
                    <?php foreach ($recentModels as $model): ?>
                        <?php
                        $category = getCategory($model['category']);
                        $fileCount = $model['file_count'] ?? 1;
                        ?>
                        <div class="card model-card">
                            <div class="model-card-preview">
                                <div class="preview-placeholder" data-stl-thumb="uploads/<?= sanitize($model['filename']) ?>">
                                    <i class="fas fa-cube"></i>
                                </div>
                                <?php if ($fileCount > 1): ?>
                                <div style="position: absolute; top: 12px; right: 12px;">
                                    <span class="file-count-badge"><?= $fileCount ?> files</span>
                                </div>
                                <?php endif; ?>
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
            <?php endif; ?>
        </section>
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

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/STLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/OBJLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/MTLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/PLYLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/3MFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
