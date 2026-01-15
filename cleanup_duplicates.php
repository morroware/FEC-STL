<?php
/**
 * Community 3D Model Vault - Duplicate Cleanup Script
 * Removes duplicate entries from model_files and model_photos tables
 * Run this to fix duplicate models appearing in browse/search without losing all data
 */

// Prevent direct access in production
if (!defined('CLEANUP_MODE')) {
    define('CLEANUP_MODE', true);
}

// Check if running from CLI or browser
$isCli = php_sapi_name() === 'cli';

// Function to output message
function outputMsg($message, $type = 'info') {
    global $isCli;
    if ($isCli) {
        echo $message . "\n";
    } else {
        $class = $type === 'error' ? 'error' : ($type === 'success' ? 'success' : 'info');
        echo "<div class='message $class'>" . nl2br(htmlspecialchars($message)) . "</div>";
        flush();
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
        <title>Community 3D Model Vault - Duplicate Cleanup</title>
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
            .message.success { border-color: #00ff88; color: #00ff88; }
            .message.error { border-color: #ff3366; color: #ff3366; }
            .message.info { border-color: #00f0ff; color: #00f0ff; }
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
            .stat-value { font-size: 2em; font-weight: bold; color: #00f0ff; }
            .stat-label { color: #888; margin-top: 5px; font-size: 0.9em; }
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
            .btn:hover { transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🧹 Duplicate Cleanup</h1>
            <p class="subtitle">Removing duplicate entries from model_files and model_photos</p>
    <?php
}

// Load configuration
if (!file_exists(__DIR__ . '/includes/db_config.php')) {
    outputMsg("❌ Database configuration not found.", 'error');
    if (!$isCli) {
        echo '<a href="setup_database.php" class="btn">⚙️ Run Database Setup</a></div></body></html>';
    }
    exit;
}

require_once __DIR__ . '/includes/db_config.php';

if ($isCli) {
    echo "Community 3D Model Vault - Duplicate Cleanup\n";
    echo "=============================================\n\n";
}

// Connect to database
try {
    $conn = getDbConnection();
    outputMsg("✓ Connected to database", 'success');
} catch (Exception $e) {
    outputMsg("❌ Database connection failed: " . $e->getMessage(), 'error');
    if (!$isCli) {
        echo '</div></body></html>';
    }
    exit;
}

// Count duplicates before cleanup
outputMsg("Analyzing duplicate entries...", 'info');

// Count duplicate files (same model_id and filename)
$duplicateFilesQuery = "
    SELECT model_id, filename, COUNT(*) as cnt
    FROM model_files
    GROUP BY model_id, filename
    HAVING cnt > 1
";
$result = $conn->query($duplicateFilesQuery);
$duplicateFileCount = 0;
$duplicateFileModels = [];
while ($row = $result->fetch_assoc()) {
    $duplicateFileCount += $row['cnt'] - 1; // Count excess duplicates
    $duplicateFileModels[] = $row['model_id'];
}

// Count duplicate photos (same model_id and filename)
$duplicatePhotosQuery = "
    SELECT model_id, filename, COUNT(*) as cnt
    FROM model_photos
    GROUP BY model_id, filename
    HAVING cnt > 1
";
$result = $conn->query($duplicatePhotosQuery);
$duplicatePhotoCount = 0;
$duplicatePhotoModels = [];
while ($row = $result->fetch_assoc()) {
    $duplicatePhotoCount += $row['cnt'] - 1; // Count excess duplicates
    $duplicatePhotoModels[] = $row['model_id'];
}

outputMsg("Found $duplicateFileCount duplicate file entries", $duplicateFileCount > 0 ? 'error' : 'success');
outputMsg("Found $duplicatePhotoCount duplicate photo entries", $duplicatePhotoCount > 0 ? 'error' : 'success');

if ($duplicateFileCount === 0 && $duplicatePhotoCount === 0) {
    outputMsg("✓ No duplicates found! Your database is clean.", 'success');
} else {
    outputMsg("Starting cleanup...", 'info');

    // Remove duplicate files - keep only the first entry (lowest ID) for each model_id + filename combination
    $cleanupFilesQuery = "
        DELETE f1 FROM model_files f1
        INNER JOIN model_files f2
        WHERE f1.model_id = f2.model_id
          AND f1.filename = f2.filename
          AND f1.id > f2.id
    ";
    $conn->query($cleanupFilesQuery);
    $removedFiles = $conn->affected_rows;
    outputMsg("✓ Removed $removedFiles duplicate file entries", 'success');

    // Remove duplicate photos - keep only the first entry (lowest ID) for each model_id + filename combination
    $cleanupPhotosQuery = "
        DELETE p1 FROM model_photos p1
        INNER JOIN model_photos p2
        WHERE p1.model_id = p2.model_id
          AND p1.filename = p2.filename
          AND p1.id > p2.id
    ";
    $conn->query($cleanupPhotosQuery);
    $removedPhotos = $conn->affected_rows;
    outputMsg("✓ Removed $removedPhotos duplicate photo entries", 'success');

    // Also fix any duplicate file_order or photo_order within the same model
    outputMsg("Fixing duplicate ordering...", 'info');

    // Get all models and reorder their files
    $modelsResult = $conn->query("SELECT DISTINCT model_id FROM model_files");
    $reorderedModels = 0;
    while ($modelRow = $modelsResult->fetch_assoc()) {
        $modelId = $modelRow['model_id'];
        // Get files ordered by id and reassign file_order
        $filesResult = $conn->query("SELECT id FROM model_files WHERE model_id = '$modelId' ORDER BY id");
        $order = 0;
        while ($fileRow = $filesResult->fetch_assoc()) {
            $conn->query("UPDATE model_files SET file_order = $order WHERE id = " . $fileRow['id']);
            $order++;
        }
        if ($order > 0) $reorderedModels++;
    }

    // Get all models and reorder their photos
    $modelsResult = $conn->query("SELECT DISTINCT model_id FROM model_photos");
    while ($modelRow = $modelsResult->fetch_assoc()) {
        $modelId = $modelRow['model_id'];
        // Get photos ordered by id and reassign photo_order
        $photosResult = $conn->query("SELECT id FROM model_photos WHERE model_id = '$modelId' ORDER BY id");
        $order = 0;
        $firstPhoto = true;
        while ($photoRow = $photosResult->fetch_assoc()) {
            $isPrimary = $firstPhoto ? 1 : 0;
            $conn->query("UPDATE model_photos SET photo_order = $order, is_primary = $isPrimary WHERE id = " . $photoRow['id']);
            $order++;
            $firstPhoto = false;
        }
    }

    outputMsg("✓ Fixed ordering for $reorderedModels models", 'success');
}

// Final counts
$totalFiles = $conn->query("SELECT COUNT(*) as count FROM model_files")->fetch_assoc()['count'];
$totalPhotos = $conn->query("SELECT COUNT(*) as count FROM model_photos")->fetch_assoc()['count'];
$totalModels = $conn->query("SELECT COUNT(*) as count FROM models")->fetch_assoc()['count'];

if (!$isCli) {
    ?>
    <div class="message success">
        <strong>✓ Cleanup Complete!</strong>
    </div>

    <h2>📊 Database Statistics (After Cleanup)</h2>
    <div class="stats">
        <div class="stat">
            <div class="stat-value"><?php echo $totalModels; ?></div>
            <div class="stat-label">Models</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?php echo $totalFiles; ?></div>
            <div class="stat-label">Model Files</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?php echo $totalPhotos; ?></div>
            <div class="stat-label">Photos</div>
        </div>
    </div>

    <div class="message info">
        <strong>💡 Note:</strong> If duplicates persist, you may need to run
        <code>setup_database.php?reset=1</code> followed by <code>migrate_json_to_mysql.php</code>
        to completely rebuild the database from your JSON files.
    </div>

    <h2>🔗 Quick Links</h2>
    <a href="index.php" class="btn">🏠 Go to Site</a>
    <a href="browse.php" class="btn">📂 Browse Models</a>

    </div>
    </body>
    </html>
    <?php
} else {
    echo "\n=============================================\n";
    echo "Cleanup complete!\n";
    echo "=============================================\n\n";
    echo "Database Statistics:\n";
    echo "  Models: $totalModels\n";
    echo "  Model Files: $totalFiles\n";
    echo "  Photos: $totalPhotos\n\n";
}

$conn->close();
?>
