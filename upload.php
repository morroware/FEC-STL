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
    } elseif (!isset($_FILES['model_files']) || !is_array($_FILES['model_files']['name']) || empty($_FILES['model_files']['name'][0])) {
        $error = 'Please upload at least one 3D model file';
    } else {
        $uploadedFiles = [];
        $hasError = false;

        // Supported 3D printing file formats
        $allowedExtensions = ['stl', 'obj', 'ply', 'gltf', 'glb', '3mf'];

        // Process multiple files
        $fileCount = count($_FILES['model_files']['name']);
        $totalSize = 0;

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['model_files']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $originalName = $_FILES['model_files']['name'][$i];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $size = $_FILES['model_files']['size'][$i];
            $tmpName = $_FILES['model_files']['tmp_name'][$i];

            if (!in_array($ext, $allowedExtensions)) {
                $error = 'Unsupported format: ' . $originalName . '. Allowed: ' . strtoupper(implode(', ', $allowedExtensions));
                $hasError = true;
                break;
            }

            if ($size > MAX_FILE_SIZE) {
                $error = 'File too large (max 50MB): ' . $originalName;
                $hasError = true;
                break;
            }

            $totalSize += $size;

            // Generate unique filename
            $filename = generateId() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
            $filepath = UPLOADS_DIR . $filename;

            if (move_uploaded_file($tmpName, $filepath)) {
                $uploadedFiles[] = [
                    'filename' => $filename,
                    'filesize' => $size,
                    'original_name' => pathinfo($originalName, PATHINFO_FILENAME),
                    'extension' => $ext,
                    'has_color' => in_array($ext, ['obj', 'ply', 'gltf', 'glb', '3mf'])
                ];
            } else {
                $error = 'Failed to upload: ' . $originalName;
                $hasError = true;
                break;
            }
        }

        if (!$hasError && !empty($uploadedFiles)) {
            // Handle multiple photo uploads
            $uploadedPhotos = [];
            if (isset($_FILES['model_photos']) && is_array($_FILES['model_photos']['name'])) {
                $allowedPhotoExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $photoCount = count($_FILES['model_photos']['name']);

                for ($p = 0; $p < $photoCount && $p < 5; $p++) { // Max 5 photos
                    if ($_FILES['model_photos']['error'][$p] !== UPLOAD_ERR_OK) continue;

                    $photoExt = strtolower(pathinfo($_FILES['model_photos']['name'][$p], PATHINFO_EXTENSION));
                    if (!in_array($photoExt, $allowedPhotoExt)) continue;
                    if ($_FILES['model_photos']['size'][$p] > 10 * 1024 * 1024) continue;

                    $photoFilename = 'photo_' . generateId() . '.' . $photoExt;
                    $photoPath = UPLOADS_DIR . $photoFilename;
                    if (move_uploaded_file($_FILES['model_photos']['tmp_name'][$p], $photoPath)) {
                        $uploadedPhotos[] = $photoFilename;
                    }
                }
            }

            // Fallback: check single photo upload (backwards compat)
            if (empty($uploadedPhotos) && isset($_FILES['model_photo']) && $_FILES['model_photo']['error'] === UPLOAD_ERR_OK) {
                $photoExt = strtolower(pathinfo($_FILES['model_photo']['name'], PATHINFO_EXTENSION));
                $allowedPhotoExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($photoExt, $allowedPhotoExt) && $_FILES['model_photo']['size'] <= 10 * 1024 * 1024) {
                    $photoFilename = 'photo_' . generateId() . '.' . $photoExt;
                    $photoPath = UPLOADS_DIR . $photoFilename;
                    if (move_uploaded_file($_FILES['model_photo']['tmp_name'], $photoPath)) {
                        $uploadedPhotos[] = $photoFilename;
                    }
                }
            }

            // Get primary display preference
            $primaryDisplay = $_POST['primary_display'] ?? 'auto';

            $modelId = createModel([
                'user_id' => $_SESSION['user_id'],
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'tags' => $tags,
                'files' => $uploadedFiles,
                'license' => $license,
                'print_settings' => $printSettings,
                'photos' => $uploadedPhotos,
                'primary_display' => $primaryDisplay
            ]);

            if ($modelId) {
                redirect('model.php?id=' . $modelId);
            } else {
                // Cleanup uploaded files on failure
                foreach ($uploadedFiles as $file) {
                    @unlink(UPLOADS_DIR . $file['filename']);
                }
                $error = 'Failed to create model. Please try again.';
            }
        } elseif ($hasError && !empty($uploadedFiles)) {
            // Cleanup any successfully uploaded files if there was an error
            foreach ($uploadedFiles as $file) {
                @unlink(UPLOADS_DIR . $file['filename']);
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
            <div class="section-header upload-header" style="margin-bottom: 24px;">
                <div class="section-header-content">
                    <h1 class="section-title">
                        <i class="fas fa-upload"></i>
                        Upload Model
                    </h1>
                    <p class="section-subtitle">Share your design in three simple steps. Upload your files, add details, and optionally include extras like print settings or a photo.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="card upload-card">
                <!-- Multi-File Upload -->
                <div class="upload-step">
                    <div class="upload-step-header">
                        <div class="upload-step-number">1</div>
                        <div>
                            <h2 class="upload-step-title">Add your files</h2>
                            <p class="upload-step-description">Drag and drop your 3D model files, or click to browse. You can upload multiple files at once.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">3D model files</label>
                        <div class="file-upload" id="file-dropzone">
                            <input type="file" name="model_files[]" accept=".stl,.obj,.ply,.gltf,.glb,.3mf" multiple required>
                            <div class="file-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="file-upload-text">
                                <strong>Click to choose files</strong> or drag and drop<br>
                                <small>Formats: STL, OBJ, PLY, GLTF/GLB, 3MF (max 50MB each)</small>
                            </div>
                        </div>
                        <div class="form-hint" style="margin-top: 8px;">
                            Color-ready formats: OBJ, PLY, GLTF/GLB, and 3MF can include full color data.
                        </div>
                    </div>

                <!-- Upload Summary -->
                <div id="upload-summary" class="upload-summary" style="display: none;">
                    <div class="upload-summary-header">
                        <div class="upload-summary-title">
                            <i class="fas fa-check-circle"></i>
                            Files Ready to Upload
                            <span id="summary-count" class="upload-summary-count">0</span>
                        </div>
                        <div class="upload-status-badge">
                            <i class="fas fa-check"></i>
                            Ready
                        </div>
                    </div>
                    <div class="upload-summary-stats">
                        <div class="upload-stat">
                            <span class="upload-stat-label">Total Size</span>
                            <span id="summary-size" class="upload-stat-value">0 MB</span>
                        </div>
                        <div class="upload-stat">
                            <span class="upload-stat-label">Formats</span>
                            <span id="summary-formats" class="upload-stat-value">-</span>
                        </div>
                        <div class="upload-stat">
                            <span class="upload-stat-label">Color Models</span>
                            <span id="summary-color" class="upload-stat-value">0</span>
                        </div>
                    </div>
                    <div class="upload-summary-confirmation">
                        <p class="upload-summary-note">All selected files are queued and ready. Review each file below to confirm.</p>
                        <ul id="summary-files" class="upload-summary-files"></ul>
                    </div>
                </div>

                <!-- File List -->
                <div id="file-list-container" class="form-group" style="display: none;">
                    <div class="file-list-header">
                        <label class="form-label">
                            <i class="fas fa-list" style="color: var(--neon-cyan);"></i>
                            Uploaded Files
                            <span id="file-list-count" class="file-count-badge">0</span>
                        </label>
                        <div class="file-list-actions">
                            <button type="button" class="btn btn-outline btn-sm" id="clear-files">
                                <i class="fas fa-trash"></i> Clear all
                            </button>
                        </div>
                    </div>
                    <div id="file-list" class="file-list"></div>
                    <div id="file-empty" class="file-empty-state">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Select files to see them listed here.</p>
                    </div>
                </div>

                <!-- 3D Preview -->
                <div id="preview-container" class="form-group" style="display: none;">
                    <div class="preview-header">
                        <div class="preview-title">
                            <i class="fas fa-cube"></i>
                            3D Preview
                        </div>
                        <span id="preview-filename" class="preview-filename"></span>
                        <div class="preview-actions">
                            <button type="button" class="btn btn-sm btn-outline" id="toggle-autorotate">
                                <i class="fas fa-sync-alt"></i> Auto-rotate
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" id="reset-view">
                                <i class="fas fa-expand"></i> Reset view
                            </button>
                        </div>
                    </div>
                    <div id="file-tabs" class="file-tabs"></div>
                    <div class="viewer-container" style="min-height: 350px; position: relative;">
                        <div class="viewer-loading" id="preview-loading" style="display: none;">
                            <div class="spinner"></div>
                        </div>
                        <div class="viewer-canvas" id="upload-preview" style="width: 100%; height: 350px;"></div>
                    </div>
                    <div class="form-hint">Use the tabs or file list to preview each file in your upload.</div>
                </div>

                </div>

                <div class="upload-step">
                    <div class="upload-step-header">
                        <div class="upload-step-number">2</div>
                        <div>
                            <h2 class="upload-step-title">Describe your model</h2>
                            <p class="upload-step-description">Help others find and understand your design with a clear title, category, and description.</p>
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
                </div>

                <div class="upload-step">
                    <div class="upload-step-header">
                        <div class="upload-step-number">3</div>
                        <div>
                            <h2 class="upload-step-title">Optional extras</h2>
                            <p class="upload-step-description">Add a photo, tags, print settings, and licensing to help others print your model.</p>
                        </div>
                    </div>

                    <!-- Optional Photo Upload (Multiple) -->
                    <div class="form-group">
                        <label class="form-label">Photos of the printed model (optional)</label>
                        <div class="photo-upload-section" id="photo-dropzone">
                            <label for="model-photos" id="photo-upload-label">
                                <div class="upload-icon"><i class="fas fa-camera"></i></div>
                                <div class="upload-title">Add photos of your print</div>
                                <div class="upload-hint">
                                    JPG, PNG, GIF, or WebP (max 10MB each, up to 5 photos)
                                </div>
                                <input type="file" name="model_photos[]" id="model-photos" accept=".jpg,.jpeg,.png,.gif,.webp" multiple>
                            </label>
                            <div id="photo-gallery-preview" class="photo-gallery-preview"></div>
                        </div>
                    </div>

                    <!-- Primary Display Selector -->
                    <div class="form-group" id="primary-display-group" style="display: none;">
                        <label class="form-label">Cover Image</label>
                        <p class="form-hint" style="margin-bottom: 12px;">Choose what to display as the main preview in listings.</p>
                        <div class="primary-display-options" id="primary-display-options">
                            <label class="display-option active" data-value="auto">
                                <input type="radio" name="primary_display" value="auto" checked>
                                <div class="display-option-content">
                                    <i class="fas fa-magic"></i>
                                    <span>Auto</span>
                                    <small>Photo if available, else 3D</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <div data-tags-input data-name="tags"></div>
                        <div class="form-hint">Press Enter or comma to add tags (max 10).</div>
                    </div>

                    <!-- Print Settings -->
                    <div class="form-group">
                        <label class="form-label">Recommended print settings</label>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                            <div>
                                <label class="form-label" style="font-size: 0.85rem;">Layer height</label>
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
                        <div class="form-hint">Choose how others can use your model.</div>
                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/OBJLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/MTLLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/PLYLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/3MFLoader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Multi-file upload with preview
        const dropzone = document.getElementById('file-dropzone');
        const fileInput = dropzone.querySelector('input[type="file"]');
        const previewContainer = document.getElementById('preview-container');
        const previewCanvas = document.getElementById('upload-preview');
        const fileListContainer = document.getElementById('file-list-container');
        const fileList = document.getElementById('file-list');
        const fileTabs = document.getElementById('file-tabs');
        const previewFilename = document.getElementById('preview-filename');
        const fileListCount = document.getElementById('file-list-count');
        const fileEmptyState = document.getElementById('file-empty');
        const clearFilesButton = document.getElementById('clear-files');
        const autoRotateButton = document.getElementById('toggle-autorotate');
        const resetViewButton = document.getElementById('reset-view');
        const dropzoneText = dropzone.querySelector('.file-upload-text');
        const defaultDropzoneHtml = dropzoneText.innerHTML;

        // Upload summary elements
        const uploadSummary = document.getElementById('upload-summary');
        const summaryCount = document.getElementById('summary-count');
        const summarySize = document.getElementById('summary-size');
        const summaryFormats = document.getElementById('summary-formats');
        const summaryColor = document.getElementById('summary-color');
        const summaryFiles = document.getElementById('summary-files');

        let viewer = null;
        let uploadedFiles = [];
        let currentPreviewIndex = 0;

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
                handleFiles(Array.from(files));
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                handleFiles(Array.from(e.target.files));
            }
        });

        // Supported file extensions for 3D printing
        const allowedExtensions = window.ALLOWED_EXTENSIONS || ['stl', 'obj', 'ply', 'gltf', 'glb', '3mf'];
        const colorFormats = ['obj', 'ply', 'gltf', 'glb', '3mf'];

        function handleFiles(files) {
            uploadedFiles = [];
            let validFiles = [];

            files.forEach(file => {
                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(ext)) {
                    Toast.error(`Unsupported format: ${file.name}. Allowed: ${allowedExtensions.join(', ').toUpperCase()}`);
                    return;
                }
                if (file.size > 50 * 1024 * 1024) {
                    Toast.error(`File too large (max 50MB): ${file.name}`);
                    return;
                }
                validFiles.push(file);
            });

            if (validFiles.length === 0) return;

            uploadedFiles = validFiles;
            currentPreviewIndex = 0;
            syncFileInput();

            updateUploadSummary();
            updateFileList();
            updateFileTabs();
            showPreview(0);

            // Update dropzone to show success state
            dropzone.classList.add('has-files');
            const fileText = validFiles.length === 1 ? '1 file' : `${validFiles.length} files`;
            dropzone.querySelector('.file-upload-text').innerHTML = `
                <div class="file-upload-success">
                    <div class="file-upload-success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="file-upload-success-text">${fileText} selected</div>
                    <div class="file-upload-success-details">
                        ${formatBytes(getTotalSize())} total
                    </div>
                    <div class="file-upload-change">Click to change files</div>
                </div>
            `;

            Toast.success(`${validFiles.length} file(s) ready to upload`);
        }

        function updateFileList() {
            fileListContainer.style.display = 'block';
            fileListCount.textContent = uploadedFiles.length;

            if (uploadedFiles.length === 0) {
                fileList.innerHTML = '';
                fileEmptyState.style.display = 'flex';
                return;
            }

            fileEmptyState.style.display = 'none';
            fileList.innerHTML = uploadedFiles.map((file, i) => {
                const ext = file.name.split('.').pop().toLowerCase();
                const hasColor = colorFormats.includes(ext);
                return `
                <div class="file-list-item${i === currentPreviewIndex ? ' selected' : ''}" data-index="${i}">
                    <div class="file-list-item-info">
                        <i class="fas ${hasColor ? 'fa-palette' : 'fa-cube'}" style="color: ${hasColor ? 'var(--neon-magenta)' : 'var(--neon-cyan)'};"></i>
                        <span class="file-list-item-name">${file.name}</span>
                        <span class="format-badge-mini ${hasColor ? 'format-color' : ''}">${ext.toUpperCase()}</span>
                        <span class="file-list-item-size">${formatBytes(file.size)}</span>
                        <span class="file-list-item-status"><i class="fas fa-check-circle"></i> Ready</span>
                    </div>
                    <div class="file-list-item-actions">
                        <button type="button" class="file-list-item-preview btn btn-sm btn-outline${i === currentPreviewIndex ? ' active' : ''}" data-action="preview" data-index="${i}">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" data-action="remove" data-index="${i}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `}).join('');
        }

        function updateFileTabs() {
            if (uploadedFiles.length <= 1) {
                fileTabs.style.display = 'none';
                return;
            }

            fileTabs.style.display = 'flex';
            fileTabs.innerHTML = uploadedFiles.map((file, i) => {
                const nameParts = file.name.split('.');
                const baseName = nameParts.length > 1 ? nameParts.slice(0, -1).join('.') : file.name;
                const shortName = baseName.length > 19 ? `${baseName.substring(0, 15)}...` : baseName;
                return `
                <button type="button" class="file-tab ${i === currentPreviewIndex ? 'active' : ''}"
                        data-index="${i}">
                    ${shortName}
                </button>
            `;
            }).join('');
        }

        function showPreview(index) {
            if (!uploadedFiles[index]) {
                previewContainer.style.display = 'none';
                return;
            }

            currentPreviewIndex = index;
            previewContainer.style.display = 'block';

            // Update tab active state
            document.querySelectorAll('.file-tab').forEach((tab, i) => {
                tab.classList.toggle('active', i === index);
            });

            // Update file list active state and selection
            document.querySelectorAll('.file-list-item').forEach((item, i) => {
                item.classList.toggle('active', i === index);
                item.classList.toggle('selected', i === index);
            });

            // Update preview buttons
            document.querySelectorAll('.file-list-item-preview').forEach((btn, i) => {
                btn.classList.toggle('active', i === index);
            });

            const file = uploadedFiles[index];
            const url = URL.createObjectURL(file);
            const ext = file.name.split('.').pop().toLowerCase();

            // Update preview filename display
            previewFilename.textContent = file.name;

            // Show loading indicator
            const loadingEl = document.getElementById('preview-loading');
            if (loadingEl) loadingEl.style.display = 'flex';

            // Dispose of old viewer
            if (viewer) {
                viewer.dispose();
                viewer = null;
            }

            // Use requestAnimationFrame to ensure container is rendered before initializing viewer
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    try {
                        viewer = new ModelViewer(previewCanvas, { autoRotate: true });
                        viewer.loadModel(url, ext).then(() => {
                            // Success - hide loading
                            if (loadingEl) loadingEl.style.display = 'none';
                        }).catch(err => {
                            if (loadingEl) loadingEl.style.display = 'none';
                            Toast.error('Failed to preview model');
                            console.error(err);
                        });
                    } catch (err) {
                        if (loadingEl) loadingEl.style.display = 'none';
                        console.error('Viewer init error:', err);
                    }
                });
            });

            updatePreviewButtons();
        }

        function updateUploadSummary() {
            if (uploadedFiles.length === 0) {
                uploadSummary.style.display = 'none';
                return;
            }

            const formats = new Set();
            let colorCount = 0;
            uploadedFiles.forEach(file => {
                const ext = file.name.split('.').pop().toLowerCase();
                formats.add(ext.toUpperCase());
                if (colorFormats.includes(ext)) colorCount++;
            });

            uploadSummary.style.display = 'block';
            summaryCount.textContent = uploadedFiles.length;
            summarySize.textContent = formatBytes(getTotalSize());
            summaryFormats.textContent = Array.from(formats).join(', ');
            summaryColor.textContent = colorCount;
            summaryFiles.innerHTML = uploadedFiles.map((file) => `
                <li class="upload-summary-file">
                    <i class="fas fa-check-circle"></i>
                    <span class="upload-summary-file-name">${file.name}</span>
                    <span class="upload-summary-file-size">${formatBytes(file.size)}</span>
                </li>
            `).join('');
        }

        function getTotalSize() {
            return uploadedFiles.reduce((sum, file) => sum + file.size, 0);
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 MB';
            const mb = bytes / 1024 / 1024;
            return `${mb.toFixed(2)} MB`;
        }

        function resetUploadUI() {
            uploadedFiles = [];
            currentPreviewIndex = 0;
            if (viewer) {
                viewer.dispose();
                viewer = null;
            }

            uploadSummary.style.display = 'none';
            summaryFiles.innerHTML = '';
            fileListContainer.style.display = 'none';
            previewContainer.style.display = 'none';
            fileList.innerHTML = '';
            fileTabs.innerHTML = '';
            fileListCount.textContent = '0';
            fileEmptyState.style.display = 'flex';
            dropzone.classList.remove('has-files');
            dropzoneText.innerHTML = defaultDropzoneHtml;
            fileInput.value = '';
            updatePrimaryDisplayOptions();
        }

        function syncFileInput() {
            const dt = new DataTransfer();
            uploadedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        function updatePreviewButtons() {
            if (!viewer) return;
            autoRotateButton.classList.toggle('active', viewer.controls.autoRotate);
        }

        if (clearFilesButton) {
            clearFilesButton.addEventListener('click', () => {
                resetUploadUI();
                Toast.info('Cleared selected files');
            });
        }

        if (autoRotateButton) {
            autoRotateButton.addEventListener('click', () => {
                if (!viewer) return;
                const nextState = !viewer.controls.autoRotate;
                viewer.setAutoRotate(nextState);
                updatePreviewButtons();
            });
        }

        if (resetViewButton) {
            resetViewButton.addEventListener('click', () => {
                if (!viewer) return;
                viewer.resetView();
            });
        }

        fileTabs.addEventListener('click', (event) => {
            const button = event.target.closest('.file-tab');
            if (!button) return;
            const index = Number(button.dataset.index);
            showPreview(index);
        });

        fileList.addEventListener('click', (event) => {
            const actionButton = event.target.closest('button[data-action]');
            if (!actionButton) return;
            const index = Number(actionButton.dataset.index);
            if (Number.isNaN(index)) return;

            if (actionButton.dataset.action === 'preview') {
                showPreview(index);
                return;
            }

            if (actionButton.dataset.action === 'remove') {
                uploadedFiles.splice(index, 1);
                syncFileInput();

                if (uploadedFiles.length === 0) {
                    resetUploadUI();
                    Toast.info('All files removed');
                    return;
                }

                if (currentPreviewIndex >= uploadedFiles.length) {
                    currentPreviewIndex = uploadedFiles.length - 1;
                } else if (index <= currentPreviewIndex) {
                    currentPreviewIndex = Math.max(0, currentPreviewIndex - 1);
                }

                updateUploadSummary();
                updateFileList();
                updateFileTabs();
                showPreview(currentPreviewIndex);
            }
        });

        // Multi-photo upload handling
        const photoInput = document.getElementById('model-photos');
        const photoGalleryPreview = document.getElementById('photo-gallery-preview');
        const photoDropzone = document.getElementById('photo-dropzone');
        const photoUploadLabel = document.getElementById('photo-upload-label');
        const primaryDisplayGroup = document.getElementById('primary-display-group');
        const primaryDisplayOptions = document.getElementById('primary-display-options');

        let uploadedPhotos = [];

        if (photoInput) {
            photoInput.addEventListener('change', (e) => {
                if (e.target.files.length) {
                    handlePhotoFiles(Array.from(e.target.files));
                }
            });
        }

        // Photo drag and drop
        if (photoDropzone) {
            photoDropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                photoDropzone.style.borderColor = 'var(--neon-magenta)';
            });

            photoDropzone.addEventListener('dragleave', () => {
                photoDropzone.style.borderColor = '';
            });

            photoDropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                photoDropzone.style.borderColor = '';
                const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
                if (files.length) {
                    handlePhotoFiles(files);
                    syncPhotoInput();
                }
            });
        }

        function handlePhotoFiles(files) {
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            let added = 0;

            files.forEach(file => {
                if (uploadedPhotos.length >= 5) return;
                if (!allowedTypes.includes(file.type)) {
                    Toast.error(`Invalid format: ${file.name}`);
                    return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    Toast.error(`Photo too large: ${file.name}`);
                    return;
                }

                uploadedPhotos.push(file);
                added++;
            });

            if (added > 0) {
                renderPhotoGallery();
                updatePrimaryDisplayOptions();
                syncPhotoInput();
                Toast.success(`${added} photo${added > 1 ? 's' : ''} added`);
            }
        }

        function renderPhotoGallery() {
            if (uploadedPhotos.length === 0) {
                photoGalleryPreview.innerHTML = '';
                photoGalleryPreview.style.display = 'none';
                photoUploadLabel.style.display = 'block';
                photoDropzone.classList.remove('has-photo');
                return;
            }

            photoUploadLabel.style.display = 'none';
            photoDropzone.classList.add('has-photo');
            photoGalleryPreview.style.display = 'grid';

            photoGalleryPreview.innerHTML = uploadedPhotos.map((file, i) => {
                const url = URL.createObjectURL(file);
                return `
                    <div class="photo-thumb" data-index="${i}">
                        <img src="${url}" alt="Photo ${i + 1}">
                        <button type="button" class="remove-photo-btn" data-index="${i}">
                            <i class="fas fa-times"></i>
                        </button>
                        ${i === 0 ? '<span class="photo-primary-badge"><i class="fas fa-star"></i></span>' : ''}
                    </div>
                `;
            }).join('') + `
                ${uploadedPhotos.length < 5 ? `
                    <label class="photo-add-more" for="model-photos">
                        <i class="fas fa-plus"></i>
                        <span>Add more</span>
                    </label>
                ` : ''}
            `;

            // Add remove listeners
            photoGalleryPreview.querySelectorAll('.remove-photo-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const index = parseInt(btn.dataset.index);
                    uploadedPhotos.splice(index, 1);
                    renderPhotoGallery();
                    updatePrimaryDisplayOptions();
                    syncPhotoInput();
                    Toast.info('Photo removed');
                });
            });
        }

        function syncPhotoInput() {
            const dt = new DataTransfer();
            uploadedPhotos.forEach(file => dt.items.add(file));
            photoInput.files = dt.files;
        }

        function updatePrimaryDisplayOptions() {
            const hasPhotos = uploadedPhotos.length > 0;
            const hasFiles = uploadedFiles.length > 0;

            if (!hasPhotos && !hasFiles) {
                primaryDisplayGroup.style.display = 'none';
                return;
            }

            primaryDisplayGroup.style.display = 'block';

            // Build options HTML
            let optionsHtml = `
                <label class="display-option active" data-value="auto">
                    <input type="radio" name="primary_display" value="auto" checked>
                    <div class="display-option-content">
                        <i class="fas fa-magic"></i>
                        <span>Auto</span>
                        <small>${hasPhotos ? 'Uses photo' : 'Uses 3D preview'}</small>
                    </div>
                </label>
            `;

            if (hasPhotos) {
                optionsHtml += `
                    <label class="display-option" data-value="photo">
                        <input type="radio" name="primary_display" value="photo">
                        <div class="display-option-content">
                            <i class="fas fa-camera"></i>
                            <span>Photo</span>
                            <small>Show photo in listings</small>
                        </div>
                    </label>
                `;
            }

            if (hasFiles) {
                optionsHtml += `
                    <label class="display-option" data-value="3d">
                        <input type="radio" name="primary_display" value="0">
                        <div class="display-option-content">
                            <i class="fas fa-cube"></i>
                            <span>3D Model</span>
                            <small>Show 3D preview in listings</small>
                        </div>
                    </label>
                `;
            }

            primaryDisplayOptions.innerHTML = optionsHtml;

            // Add click handlers
            primaryDisplayOptions.querySelectorAll('.display-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    primaryDisplayOptions.querySelectorAll('.display-option').forEach(o => o.classList.remove('active'));
                    opt.classList.add('active');
                    opt.querySelector('input').checked = true;
                });
            });
        }

        // Update primary display when files change
        const originalHandleFiles = handleFiles;
        handleFiles = function(files) {
            originalHandleFiles(files);
            updatePrimaryDisplayOptions();
        };
    </script>
</body>
</html>
