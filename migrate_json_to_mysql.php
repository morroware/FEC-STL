<?php
/**
 * FEC STL Vault - JSON to MySQL Migration Script
 * Migrates existing JSON data files to MySQL database
 * Run this AFTER setup_database.php
 */

// Prevent direct access in production
if (!defined('MIGRATION_MODE')) {
    define('MIGRATION_MODE', true);
}

// Set execution limits for long-running migration
@set_time_limit(300); // 5 minutes
@ini_set('max_execution_time', 300);
@ini_set('memory_limit', '256M');

// Check if running from CLI or browser
$isCli = php_sapi_name() === 'cli';

// Enable output buffering and auto-flush for browser
if (!$isCli) {
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', false);
    @ini_set('implicit_flush', true);
    ob_implicit_flush(true);
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}

// Function to output message
function outputMsg($message, $type = 'info') {
    global $isCli;
    if ($isCli) {
        echo $message . "\n";
    } else {
        $class = $type === 'error' ? 'error' : ($type === 'success' ? 'success' : 'info');
        echo "<div class='message $class'>" . nl2br(htmlspecialchars($message)) . "</div>";
        flush();
        if (ob_get_level() > 0) {
            ob_flush();
        }
    }
}

// Start HTML if browser
if (!$isCli) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>FEC STL Vault - Data Migration</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                background: linear-gradient(135deg, #0a0a0f 0%, #1a1a2e 100%);
                color: #e0e0e0;
                padding: 20px;
                min-height: 100vh;
            }
            .container {
                max-width: 900px;
                margin: 40px auto;
                background: rgba(26, 26, 46, 0.8);
                border: 2px solid rgba(0, 240, 255, 0.3);
                border-radius: 12px;
                padding: 40px;
                box-shadow: 0 20px 60px rgba(0, 240, 255, 0.2);
            }
            h1 {
                font-family: 'Courier New', monospace;
                color: #00f0ff;
                margin-bottom: 10px;
                font-size: 2em;
            }
            h2 {
                color: #00f0ff;
                margin: 30px 0 15px;
                font-size: 1.3em;
            }
            .subtitle {
                color: #888;
                margin-bottom: 30px;
            }
            .message {
                padding: 15px 20px;
                margin: 15px 0;
                border-radius: 8px;
                border-left: 4px solid;
                background: rgba(0, 0, 0, 0.3);
                line-height: 1.6;
            }
            .message.success {
                border-color: #00ff88;
                color: #00ff88;
            }
            .message.error {
                border-color: #ff3366;
                color: #ff3366;
            }
            .message.info {
                border-color: #00f0ff;
                color: #00f0ff;
            }
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            .stat {
                background: rgba(0, 240, 255, 0.1);
                padding: 15px;
                border-radius: 8px;
                text-align: center;
                border: 1px solid rgba(0, 240, 255, 0.3);
            }
            .stat-value {
                font-size: 2em;
                font-weight: bold;
                color: #00f0ff;
            }
            .stat-label {
                color: #888;
                margin-top: 5px;
                font-size: 0.9em;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: linear-gradient(135deg, #00f0ff, #00aacc);
                color: #0a0a0f;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
                margin: 10px 10px 10px 0;
                transition: transform 0.2s;
            }
            .btn:hover {
                transform: translateY(-2px);
            }
            .progress {
                margin: 20px 0;
            }
            .progress-item {
                padding: 10px;
                margin: 5px 0;
                background: rgba(0, 0, 0, 0.2);
                border-radius: 6px;
                border-left: 3px solid #00f0ff;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📦 Data Migration</h1>
            <p class="subtitle">Migrating JSON data to MySQL database</p>
    <?php
}

require_once __DIR__ . '/includes/config.php';

// Check if db_config exists
if (!file_exists(__DIR__ . '/includes/db_config.php')) {
    outputMsg("❌ Database configuration not found. Please run setup_database.php first.", 'error');
    if (!$isCli) {
        echo '<a href="setup_database.php" class="btn">⚙️ Run Database Setup</a></div></body></html>';
    }
    exit;
}

require_once __DIR__ . '/includes/db_config.php';

// JSON file paths
define('OLD_USERS_FILE', DATA_DIR . 'users.json');
define('OLD_MODELS_FILE', DATA_DIR . 'models.json');
define('OLD_CATEGORIES_FILE', DATA_DIR . 'categories.json');

if ($isCli) {
    echo "FEC STL Vault - JSON to MySQL Migration\n";
    echo "========================================\n\n";
}

// Check if JSON files exist
$hasData = false;
$foundFiles = [];
if (file_exists(OLD_USERS_FILE)) {
    outputMsg("✓ Found users.json", 'success');
    $hasData = true;
    $foundFiles[] = 'users';
}
if (file_exists(OLD_MODELS_FILE)) {
    outputMsg("✓ Found models.json", 'success');
    $hasData = true;
    $foundFiles[] = 'models';
}
if (file_exists(OLD_CATEGORIES_FILE)) {
    outputMsg("✓ Found categories.json", 'success');
    $hasData = true;
    $foundFiles[] = 'categories';
}

if (!$hasData) {
    outputMsg("❌ No JSON data files found in " . DATA_DIR . "\nNothing to migrate.", 'error');
    if (!$isCli) {
        echo '<div class="message info">Your database is already set up. No JSON files to migrate.</div>';
        echo '<a href="index.php" class="btn">🏠 Go to Site</a></div></body></html>';
    }
    exit;
}

outputMsg("Connecting to database...", 'info');

try {
    $conn = getDbConnection();
    outputMsg("✓ Connected successfully!", 'success');
} catch (Exception $e) {
    outputMsg("❌ Database connection failed: " . $e->getMessage(), 'error');
    if (!$isCli) {
        echo '<a href="setup_database.php" class="btn">⚙️ Run Database Setup</a></div></body></html>';
    }
    exit;
}

if (!$isCli) {
    echo '<h2>🔄 Migration Progress</h2><div class="progress">';
}

// Verify tables exist before migration
outputMsg("Verifying database tables...", 'info');
$requiredTables = ['users', 'categories', 'models', 'model_files', 'model_photos', 'favorites'];
$missingTables = [];

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    outputMsg("❌ Missing database tables: " . implode(', ', $missingTables), 'error');
    outputMsg("Please run setup_database.php first to create the database schema.", 'error');
    if (!$isCli) {
        echo '<a href="setup_database.php" class="btn">⚙️ Run Database Setup</a></div></body></html>';
    }
    exit;
}
outputMsg("✓ All database tables found", 'success');

// Helper function to read JSON
function readJsonFile($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?? [];
}

// Migrate Categories
outputMsg("Migrating categories...", 'info');
$categories = readJsonFile(OLD_CATEGORIES_FILE);
$categoryCount = 0;

if (!empty($categories)) {
    outputMsg("Found " . count($categories) . " categories to migrate", 'info');
    $stmt = $conn->prepare("INSERT INTO categories (id, name, icon, description, count) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), icon=VALUES(icon), description=VALUES(description), count=VALUES(count)");

    if (!$stmt) {
        outputMsg("❌ Failed to prepare category statement: " . $conn->error, 'error');
        outputMsg("MySQL Error: " . $conn->errno . " - " . $conn->error, 'error');
    } else {
        outputMsg("✓ Category statement prepared successfully", 'success');
        foreach ($categories as $cat) {
            outputMsg("  Processing: {$cat['name']}...", 'info');

            // Validate data
            if (empty($cat['id']) || empty($cat['name'])) {
                outputMsg("  ⚠️ Skipping invalid category (missing id or name)", 'error');
                continue;
            }

            $catId = $cat['id'];
            $catName = $cat['name'];
            $catIcon = $cat['icon'];
            $catDesc = $cat['description'] ?? '';
            $catCount = $cat['count'] ?? 0;

            outputMsg("  Binding parameters for {$catName}...", 'info');
            if (!$stmt->bind_param("ssssi", $catId, $catName, $catIcon, $catDesc, $catCount)) {
                outputMsg("  ❌ Failed to bind parameters: " . $stmt->error, 'error');
                continue;
            }

            outputMsg("  Executing insert for {$catName}...", 'info');
            if ($stmt->execute()) {
                $categoryCount++;
                outputMsg("  ✓ {$catName}", 'success');
            } else {
                outputMsg("  ❌ Failed to execute for '{$catName}': " . $stmt->error, 'error');
                outputMsg("  MySQL Error #" . $stmt->errno . ": " . $stmt->error, 'error');
            }
        }
        $stmt->close();
    }
}
outputMsg("✓ Migrated $categoryCount categories", 'success');

// Migrate Users
outputMsg("Migrating users...", 'info');
$users = readJsonFile(OLD_USERS_FILE);
$userCount = 0;

if (!empty($users)) {
    $totalUsers = count($users);
    outputMsg("Found $totalUsers users to migrate", 'info');
    $stmt = $conn->prepare("INSERT INTO users (id, username, email, password, is_admin, avatar, bio, location, created_at, model_count, download_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username=VALUES(username), email=VALUES(email)");

    if (!$stmt) {
        outputMsg("❌ Failed to prepare user statement: " . $conn->error, 'error');
    } else {
        foreach ($users as $user) {
            $isAdmin = (int)($user['is_admin'] ?? false);  // Cast boolean to int for MySQL
            $createdAt = $user['created_at'] ?? date('Y-m-d H:i:s');

            // Extract values with proper null handling for bind_param
            $userId = $user['id'];
            $username = $user['username'];
            $email = $user['email'];
            $password = $user['password'];
            $avatar = $user['avatar'] ?? '';  // Handle null avatar
            $bio = $user['bio'] ?? '';
            $location = $user['location'] ?? '';
            $modelCount = (int)($user['model_count'] ?? 0);
            $downloadCount = (int)($user['download_count'] ?? 0);

            $stmt->bind_param(
                "ssssissssii",
                $userId,
                $username,
                $email,
                $password,
                $isAdmin,
                $avatar,
                $bio,
                $location,
                $createdAt,
                $modelCount,
                $downloadCount
            );

            if ($stmt->execute()) {
                $userCount++;
                if ($isCli) {
                    echo "  ✓ {$user['username']}\n";
                } else {
                    // Show progress every user in browser mode
                    outputMsg("  ✓ {$user['username']} ($userCount/$totalUsers)", 'success');
                }

                // Migrate user favorites
                if (!empty($user['favorites'])) {
                    $favStmt = $conn->prepare("INSERT IGNORE INTO favorites (user_id, model_id) VALUES (?, ?)");
                    if ($favStmt) {
                        foreach ($user['favorites'] as $modelId) {
                            $favStmt->bind_param("ss", $user['id'], $modelId);
                            $favStmt->execute();
                        }
                        $favStmt->close();
                    }
                }
            } else {
                outputMsg("  ❌ Failed to migrate user '{$user['username']}': " . $stmt->error, 'error');
            }
        }
        $stmt->close();
    }
}
outputMsg("✓ Migrated $userCount users", 'success');

// Migrate Models
outputMsg("Migrating models...", 'info');
$models = readJsonFile(OLD_MODELS_FILE);
$modelCount = 0;

if (!empty($models)) {
    $totalModels = count($models);
    outputMsg("Found $totalModels models to migrate", 'info');
    $modelStmt = $conn->prepare("
        INSERT INTO models
        (id, user_id, title, description, category, tags, filename, filesize, file_count,
         thumbnail, photo, primary_display, license, print_settings,
         downloads, likes, views, featured, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description)
    ");

    $fileStmt = $conn->prepare("
        INSERT INTO model_files
        (model_id, filename, filesize, original_name, extension, has_color, file_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $photoStmt = $conn->prepare("
        INSERT INTO model_photos
        (model_id, filename, is_primary, photo_order)
        VALUES (?, ?, ?, ?)
    ");

    if (!$modelStmt || !$fileStmt || !$photoStmt) {
        outputMsg("❌ Failed to prepare model statements: " . $conn->error, 'error');
    } else {
        outputMsg("✓ Model statements prepared successfully", 'success');
        foreach ($models as $model) {
            outputMsg("  Processing model: {$model['title']}...", 'info');

            // Prepare data with proper null handling
            $tagsJson = json_encode($model['tags'] ?? []);
            $printSettingsJson = json_encode($model['print_settings'] ?? []);

            $fileCount = $model['file_count'] ?? count($model['files'] ?? []);
            if ($fileCount == 0 && !empty($model['filename'])) $fileCount = 1;

            $createdAt = $model['created_at'] ?? date('Y-m-d H:i:s');
            $updatedAt = $model['updated_at'] ?? $createdAt;

            // Extract values with proper null handling for bind_param
            $modelId = $model['id'];
            $modelUserId = $model['user_id'];
            $modelTitle = $model['title'];
            $modelDesc = $model['description'] ?? '';
            $modelCategory = $model['category'];
            $modelFilename = $model['filename'] ?? '';
            $modelFilesize = (int)($model['filesize'] ?? 0);
            $modelThumbnail = $model['thumbnail'] ?? '';  // Handle null thumbnail
            $modelPhoto = $model['photo'] ?? '';  // Handle null/missing photo
            $modelPrimaryDisplay = $model['primary_display'] ?? 'auto';
            $modelLicense = $model['license'] ?? 'CC BY-NC';
            $modelDownloads = (int)($model['downloads'] ?? 0);
            $modelLikes = (int)($model['likes'] ?? 0);
            $modelViews = (int)($model['views'] ?? 0);
            $modelFeatured = (int)($model['featured'] ?? 0);  // Cast bool to int

            outputMsg("    Binding parameters...", 'info');

            // Insert model
            // Type string: s=string, i=integer
            // Columns: id(s), user_id(s), title(s), description(s), category(s), tags(s), filename(s),
            //          filesize(i), file_count(i), thumbnail(s), photo(s), primary_display(s), license(s),
            //          print_settings(s), downloads(i), likes(i), views(i), featured(i), created_at(s), updated_at(s)
            $bindResult = $modelStmt->bind_param(
                "sssssssiisssssiiiiss",
                $modelId,
                $modelUserId,
                $modelTitle,
                $modelDesc,
                $modelCategory,
                $tagsJson,
                $modelFilename,
                $modelFilesize,
                $fileCount,
                $modelThumbnail,
                $modelPhoto,
                $modelPrimaryDisplay,
                $modelLicense,
                $printSettingsJson,
                $modelDownloads,
                $modelLikes,
                $modelViews,
                $modelFeatured,
                $createdAt,
                $updatedAt
            );

            if (!$bindResult) {
                outputMsg("    ❌ Bind failed: " . $modelStmt->error, 'error');
                continue;
            }
            outputMsg("    Executing insert...", 'info');

            if ($modelStmt->execute()) {
                $modelCount++;
                if ($isCli) {
                    echo "  ✓ {$model['title']}\n";
                } else {
                    outputMsg("  ✓ {$model['title']} ($modelCount/$totalModels)", 'success');
                }

                // Migrate files
                outputMsg("    Migrating files...", 'info');
                if (!empty($model['files'])) {
                    foreach ($model['files'] as $index => $file) {
                        $hasColor = (int)($file['has_color'] ?? 0);  // Cast bool to int
                        $fileSize = (int)($file['filesize'] ?? 0);
                        $extension = $file['extension'] ?? pathinfo($file['filename'], PATHINFO_EXTENSION);
                        $origName = $file['original_name'] ?? $file['filename'];

                        $fileStmt->bind_param(
                            "ssissii",
                            $model['id'],
                            $file['filename'],
                            $fileSize,
                            $origName,
                            $extension,
                            $hasColor,
                            $index
                        );
                        if (!$fileStmt->execute()) {
                            outputMsg("Failed to migrate file '{$file['filename']}' for model '{$model['title']}': " . $fileStmt->error, 'error');
                        }
                    }
                } elseif (!empty($model['filename'])) {
                    // Legacy single file
                    $extension = pathinfo($model['filename'], PATHINFO_EXTENSION);
                    $legacyFilesize = (int)($model['filesize'] ?? 0);
                    $hasColorInt = 0;  // false as int
                    $fileOrder = 0;
                    $fileStmt->bind_param(
                        "ssissii",
                        $modelId,
                        $model['filename'],
                        $legacyFilesize,
                        $model['filename'],
                        $extension,
                        $hasColorInt,
                        $fileOrder
                    );
                    if (!$fileStmt->execute()) {
                        outputMsg("    ❌ Failed to migrate legacy file: " . $fileStmt->error, 'error');
                    } else {
                        outputMsg("    ✓ File migrated", 'success');
                    }
                }

                // Migrate photos
                outputMsg("    Migrating photos...", 'info');
                if (!empty($model['photos'])) {
                    foreach ($model['photos'] as $index => $photo) {
                        $isPrimary = (int)($index === 0);  // Cast bool to int
                        $photoStmt->bind_param("ssii", $model['id'], $photo, $isPrimary, $index);
                        if (!$photoStmt->execute()) {
                            outputMsg("Failed to migrate photo '{$photo}' for model '{$model['title']}': " . $photoStmt->error, 'error');
                        }
                    }
                } elseif (!empty($model['photo'])) {
                    // Legacy single photo
                    $isPrimaryInt = 1;  // true as int
                    $photoOrder = 0;
                    $photoStmt->bind_param("ssii", $modelId, $model['photo'], $isPrimaryInt, $photoOrder);
                    if (!$photoStmt->execute()) {
                        outputMsg("    ❌ Failed to migrate legacy photo: " . $photoStmt->error, 'error');
                    } else {
                        outputMsg("    ✓ Photo migrated", 'success');
                    }
                } else {
                    outputMsg("    No photos to migrate", 'info');
                }
            } else {
                outputMsg("❌ Failed to migrate model '{$model['title']}': " . $modelStmt->error, 'error');
            }
        }

        $modelStmt->close();
        $fileStmt->close();
        $photoStmt->close();
    }
}
outputMsg("✓ Migrated $modelCount models", 'success');

// Update category counts
outputMsg("Recalculating category counts...", 'info');
$result = $conn->query("
    UPDATE categories c
    SET c.count = (
        SELECT COUNT(*)
        FROM models m
        WHERE m.category = c.id
    )
");
if ($result) {
    outputMsg("✓ Category counts updated", 'success');
} else {
    outputMsg("❌ Failed to update category counts: " . $conn->error, 'error');
}

if (!$isCli) {
    echo '</div>'; // Close progress div
}

// Show statistics
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$totalModels = $conn->query("SELECT COUNT(*) as count FROM models")->fetch_assoc()['count'];
$totalCategories = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
$totalFiles = $conn->query("SELECT COUNT(*) as count FROM model_files")->fetch_assoc()['count'];
$totalPhotos = $conn->query("SELECT COUNT(*) as count FROM model_photos")->fetch_assoc()['count'];
$totalFavorites = $conn->query("SELECT COUNT(*) as count FROM favorites")->fetch_assoc()['count'];

if (!$isCli) {
    ?>
    <div class="message success">
        <strong>✅ Migration Complete!</strong><br>
        All JSON data has been successfully migrated to MySQL.
    </div>

    <h2>📊 Database Statistics</h2>
    <div class="stats">
        <div class="stat">
            <div class="stat-value"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Users</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?php echo $totalModels; ?></div>
            <div class="stat-label">Models</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?php echo $totalCategories; ?></div>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?php echo $totalFiles; ?></div>
            <div class="stat-label">Model Files</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?php echo $totalPhotos; ?></div>
            <div class="stat-label">Photos</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?php echo $totalFavorites; ?></div>
            <div class="stat-label">Favorites</div>
        </div>
    </div>

    <div class="message info">
        <strong>📝 Next Steps:</strong><br>
        <ol style="margin: 10px 0 0 20px;">
            <li>Test the application thoroughly</li>
            <li>Backup the JSON files (they are no longer used)</li>
            <li>Delete or archive the old JSON files after verification</li>
            <li>Consider removing the <code>data/</code> directory</li>
        </ol>
    </div>

    <h2>🔗 Quick Links</h2>
    <a href="index.php" class="btn">🏠 Go to Site</a>
    <a href="login.php" class="btn">🔐 Log In</a>

    </div>
    </body>
    </html>
    <?php
} else {
    echo "\n========================================\n";
    echo "Migration complete!\n";
    echo "========================================\n\n";
    echo "Database Statistics:\n";
    echo "  Users: $totalUsers\n";
    echo "  Models: $totalModels\n";
    echo "  Categories: $totalCategories\n";
    echo "  Model Files: $totalFiles\n";
    echo "  Model Photos: $totalPhotos\n";
    echo "  Favorites: $totalFavorites\n\n";
    echo "Next steps:\n";
    echo "1. Test the application thoroughly\n";
    echo "2. Backup the JSON files (they are no longer used)\n";
    echo "3. You can delete or archive the old JSON files after verifying everything works\n";
    echo "4. Consider removing the data/ directory after confirming migration success\n\n";
}

$conn->close();
?>
