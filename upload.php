<?php
/**
 * FEC STL Vault - Upload Model
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Require login
if (!isLoggedIn()) {
    redirect('login.php?redirect=' . urlencode('upload.php'));
}

$categories = getCategories();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $license = $_POST['license'] ?? 'CC BY-NC';
    $tags = [];
    
    if (!empty($_POST['tags'])) {
        $tags = json_decode($_POST['tags'], true) ?? [];
    }
    
    // Print settings
    $printSettings = [];
    if (!empty($_POST['layer_height'])) $printSettings['layer_height'] = $_POST['layer_height'];
    if (!empty($_POST['infill'])) $printSettings['infill'] = $_POST['infill'];
    if (!empty($_POST['supports'])) $printSettings['supports'] = $_POST['supports'];
    if (!empty($_POST['material'])) $printSettings['material'] = $_POST['material'];
    
    // Validation
    if (!$title) {
        $error = 'Title is required';
    } elseif (!$category) {
        $error = 'Please select a category';
    } elseif (!isset($_FILES['stl_file']) || $_FILES['stl_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload an STL file';
    } else {
        $file = $_FILES['stl_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'stl') {
            $error = 'Only STL files are allowed';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $error = 'File too large (max 50MB)';
        } else {
            // Generate unique filename
            $filename = generateId() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $filepath = UPLOADS_DIR . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $modelId = createModel([
                    'user_id' => $_SESSION['user_id'],
                    'title' => $title,
                    'description' => $description,
                    'category' => $category,
                    'tags' => $tags,
                    'filename' => $filename,
                    'filesize' => $file['size'],
                    'license' => $license,
                    'print_settings' => $printSettings
                ]);
                
                if ($modelId) {
                    redirect('model.php?id=' . $modelId);
                } else {
                    unlink($filepath);
                    $error = 'Failed to create model. Please try again.';
                }
            } else {
                $error = 'Failed to upload file. Please try again.';
            }
        }
    }
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Model - <?= SITE_NAME ?></title>
    
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
                <a href="upload.php" class="btn btn-primary btn-sm active">
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
            </div>
        </div>
    </nav>

    <div class="page-content">
        <div class="container" style="max-width: 800px;">
            <div class="section-header" style="margin-bottom: 32px;">
                <h1 class="section-title">
                    <i class="fas fa-upload"></i>
                    Upload Model
                </h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="card" style="padding: 32px;">
                <!-- File Upload -->
                <div class="form-group">
                    <label class="form-label required">STL File</label>
                    <div class="file-upload" id="file-dropzone">
                        <input type="file" name="stl_file" accept=".stl" required>
                        <div class="file-upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="file-upload-text">
                            <strong>Click to upload</strong> or drag and drop<br>
                            <small>STL files only, max 50MB</small>
                        </div>
                    </div>
                </div>

                <!-- 3D Preview -->
                <div id="preview-container" class="form-group" style="display: none;">
                    <label class="form-label">Preview</label>
                    <div class="viewer-container" style="height: 300px;">
                        <div class="viewer-canvas" id="upload-preview"></div>
                    </div>
                </div>

                <!-- Title -->
                <div class="form-group">
                    <label class="form-label required">Title</label>
                    <input type="text" name="title" class="form-input" 
                           placeholder="Give your model a descriptive title"
                           value="<?= sanitize($_POST['title'] ?? '') ?>" 
                           maxlength="100" required>
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label class="form-label required">Category</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" 
                                    <?= ($_POST['category'] ?? '') === $cat['id'] ? 'selected' : '' ?>>
                                <?= sanitize($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" 
                              placeholder="Describe your model, what it's for, how to use it..."
                              rows="4"><?= sanitize($_POST['description'] ?? '') ?></textarea>
                </div>

                <!-- Tags -->
                <div class="form-group">
                    <label class="form-label">Tags</label>
                    <div data-tags-input data-name="tags"></div>
                    <div class="form-hint">Press Enter or comma to add tags (max 10)</div>
                </div>

                <!-- Print Settings -->
                <div class="form-group">
                    <label class="form-label">Recommended Print Settings</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div>
                            <label class="form-label" style="font-size: 0.85rem;">Layer Height</label>
                            <select name="layer_height" class="form-select">
                                <option value="">Not specified</option>
                                <option value="0.1mm">0.1mm (Fine)</option>
                                <option value="0.2mm">0.2mm (Standard)</option>
                                <option value="0.3mm">0.3mm (Draft)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.85rem;">Infill</label>
                            <select name="infill" class="form-select">
                                <option value="">Not specified</option>
                                <option value="10%">10% (Light)</option>
                                <option value="20%">20% (Standard)</option>
                                <option value="50%">50% (Strong)</option>
                                <option value="100%">100% (Solid)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.85rem;">Supports</label>
                            <select name="supports" class="form-select">
                                <option value="">Not specified</option>
                                <option value="None">None needed</option>
                                <option value="Touching buildplate">Touching buildplate</option>
                                <option value="Everywhere">Everywhere</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.85rem;">Material</label>
                            <select name="material" class="form-select">
                                <option value="">Not specified</option>
                                <option value="PLA">PLA</option>
                                <option value="PETG">PETG</option>
                                <option value="ABS">ABS</option>
                                <option value="TPU">TPU (Flexible)</option>
                                <option value="Resin">Resin</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- License -->
                <div class="form-group">
                    <label class="form-label">License</label>
                    <select name="license" class="form-select">
                        <option value="CC BY">CC BY - Attribution</option>
                        <option value="CC BY-SA">CC BY-SA - Attribution-ShareAlike</option>
                        <option value="CC BY-NC" selected>CC BY-NC - Attribution-NonCommercial</option>
                        <option value="CC BY-NC-SA">CC BY-NC-SA - Attribution-NonCommercial-ShareAlike</option>
                        <option value="CC0">CC0 - Public Domain</option>
                    </select>
                    <div class="form-hint">Choose how others can use your model</div>
                </div>

                <!-- Submit -->
                <div style="display: flex; gap: 16px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;">
                        <i class="fas fa-upload"></i> Upload Model
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg">Cancel</a>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/STLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Enhanced file upload with preview
        const dropzone = document.getElementById('file-dropzone');
        const fileInput = dropzone.querySelector('input[type="file"]');
        const previewContainer = document.getElementById('preview-container');
        const previewCanvas = document.getElementById('upload-preview');
        let viewer = null;
        
        // Drag and drop styling
        ['dragenter', 'dragover'].forEach(event => {
            dropzone.addEventListener(event, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        
        ['dragleave', 'drop'].forEach(event => {
            dropzone.addEventListener(event, (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        
        dropzone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length) {
                fileInput.files = files;
                handleFile(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                handleFile(e.target.files[0]);
            }
        });
        
        function handleFile(file) {
            // Validate
            if (!file.name.toLowerCase().endsWith('.stl')) {
                Toast.error('Only STL files are allowed');
                return;
            }
            
            if (file.size > 50 * 1024 * 1024) {
                Toast.error('File too large (max 50MB)');
                return;
            }
            
            // Update dropzone text
            dropzone.querySelector('.file-upload-text').innerHTML = `
                <strong>${file.name}</strong><br>
                <small>${(file.size / 1024 / 1024).toFixed(2)} MB</small>
            `;
            
            // Show preview
            previewContainer.style.display = 'block';
            
            // Create blob URL and load preview
            const url = URL.createObjectURL(file);
            
            if (viewer) {
                viewer.dispose();
            }
            
            viewer = new STLViewer(previewCanvas, { autoRotate: true });
            viewer.loadSTL(url).then(() => {
                Toast.success('File loaded successfully');
            }).catch(err => {
                Toast.error('Failed to preview STL');
                console.error(err);
            });
        }
    </script>
</body>
</html>
