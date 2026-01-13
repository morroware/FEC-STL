/**
 * FEC STL Vault - Main JavaScript
 * 3D Viewer & Application Logic
 */

// ============================================================================
// STL 3D VIEWER CLASS
// ============================================================================

class STLViewer {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            backgroundColor: 0x0a0a0f,
            modelColor: 0x00f0ff,
            wireframe: false,
            autoRotate: true,
            ...options
        };
        
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.mesh = null;
        this.animationId = null;
        
        this.init();
    }
    
    init() {
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        
        // Scene
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(this.options.backgroundColor);
        
        // Camera
        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
        this.camera.position.set(0, 0, 100);
        
        // Renderer
        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.container.appendChild(this.renderer.domElement);
        
        // Controls
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.05;
        this.controls.autoRotate = this.options.autoRotate;
        this.controls.autoRotateSpeed = 1;
        
        // Lighting
        this.setupLighting();
        
        // Grid
        this.addGrid();
        
        // Handle resize
        window.addEventListener('resize', () => this.onResize());
        
        // Start animation
        this.animate();
    }
    
    setupLighting() {
        // Ambient light
        const ambient = new THREE.AmbientLight(0xffffff, 0.4);
        this.scene.add(ambient);
        
        // Key light
        const keyLight = new THREE.DirectionalLight(0xffffff, 0.8);
        keyLight.position.set(50, 50, 50);
        this.scene.add(keyLight);
        
        // Fill light
        const fillLight = new THREE.DirectionalLight(0x00f0ff, 0.3);
        fillLight.position.set(-50, 0, 50);
        this.scene.add(fillLight);
        
        // Rim light
        const rimLight = new THREE.DirectionalLight(0xff00aa, 0.2);
        rimLight.position.set(0, -50, -50);
        this.scene.add(rimLight);
    }
    
    addGrid() {
        const gridHelper = new THREE.GridHelper(100, 20, 0x2a2a3a, 0x1a1a25);
        gridHelper.rotation.x = Math.PI / 2;
        this.scene.add(gridHelper);
    }
    
    loadSTL(url) {
        return new Promise((resolve, reject) => {
            const loader = new THREE.STLLoader();
            
            loader.load(
                url,
                (geometry) => {
                    // Remove existing mesh
                    if (this.mesh) {
                        this.scene.remove(this.mesh);
                        this.mesh.geometry.dispose();
                        this.mesh.material.dispose();
                    }
                    
                    // Center geometry
                    geometry.computeBoundingBox();
                    geometry.center();
                    
                    // Compute vertex normals for better lighting
                    geometry.computeVertexNormals();
                    
                    // Create material
                    const material = new THREE.MeshPhongMaterial({
                        color: this.options.modelColor,
                        specular: 0x444444,
                        shininess: 50,
                        wireframe: this.options.wireframe
                    });
                    
                    // Create mesh
                    this.mesh = new THREE.Mesh(geometry, material);
                    this.scene.add(this.mesh);
                    
                    // Fit camera to model
                    this.fitCameraToModel();
                    
                    resolve(geometry);
                },
                (progress) => {
                    // Progress callback
                    const percent = (progress.loaded / progress.total * 100).toFixed(0);
                    this.container.dispatchEvent(new CustomEvent('loadProgress', { detail: percent }));
                },
                (error) => {
                    reject(error);
                }
            );
        });
    }
    
    fitCameraToModel() {
        if (!this.mesh) return;
        
        const box = new THREE.Box3().setFromObject(this.mesh);
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const fov = this.camera.fov * (Math.PI / 180);
        const distance = maxDim / (2 * Math.tan(fov / 2)) * 1.5;
        
        this.camera.position.set(distance * 0.5, distance * 0.5, distance);
        this.camera.lookAt(0, 0, 0);
        this.controls.target.set(0, 0, 0);
        this.controls.update();
    }
    
    setColor(color) {
        if (this.mesh) {
            this.mesh.material.color.setHex(color);
        }
    }
    
    setWireframe(enabled) {
        if (this.mesh) {
            this.mesh.material.wireframe = enabled;
        }
    }
    
    setAutoRotate(enabled) {
        this.controls.autoRotate = enabled;
    }
    
    resetView() {
        this.fitCameraToModel();
    }
    
    animate() {
        this.animationId = requestAnimationFrame(() => this.animate());
        this.controls.update();
        this.renderer.render(this.scene, this.camera);
    }
    
    onResize() {
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }
    
    dispose() {
        cancelAnimationFrame(this.animationId);
        window.removeEventListener('resize', this.onResize);
        
        if (this.mesh) {
            this.scene.remove(this.mesh);
            this.mesh.geometry.dispose();
            this.mesh.material.dispose();
        }
        
        this.renderer.dispose();
        this.container.innerHTML = '';
    }
}

// ============================================================================
// THUMBNAIL GENERATOR (Mini 3D Preview)
// ============================================================================

class ThumbnailViewer {
    constructor(container, stlUrl) {
        this.container = container;
        this.stlUrl = stlUrl;
        this.init();
    }
    
    init() {
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        
        // Scene
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0a0a0f);
        
        // Camera
        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
        
        // Renderer
        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.container.appendChild(this.renderer.domElement);
        
        // Lighting
        const ambient = new THREE.AmbientLight(0xffffff, 0.5);
        this.scene.add(ambient);
        
        const directional = new THREE.DirectionalLight(0xffffff, 0.8);
        directional.position.set(50, 50, 50);
        this.scene.add(directional);
        
        // Load STL
        this.loadSTL();
        
        // Animate
        this.animate();
    }
    
    loadSTL() {
        const loader = new THREE.STLLoader();
        loader.load(this.stlUrl, (geometry) => {
            geometry.computeBoundingBox();
            geometry.center();
            geometry.computeVertexNormals();
            
            const material = new THREE.MeshPhongMaterial({
                color: 0x00f0ff,
                specular: 0x444444,
                shininess: 50
            });
            
            this.mesh = new THREE.Mesh(geometry, material);
            this.scene.add(this.mesh);
            
            // Position camera
            const box = new THREE.Box3().setFromObject(this.mesh);
            const size = box.getSize(new THREE.Vector3());
            const maxDim = Math.max(size.x, size.y, size.z);
            const distance = maxDim * 2;
            
            this.camera.position.set(distance * 0.5, distance * 0.3, distance * 0.5);
            this.camera.lookAt(0, 0, 0);
        });
    }
    
    animate() {
        requestAnimationFrame(() => this.animate());
        if (this.mesh) {
            this.mesh.rotation.y += 0.005;
        }
        this.renderer.render(this.scene, this.camera);
    }
}

// ============================================================================
// API HELPER
// ============================================================================

const API = {
    async request(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        
        for (const [key, value] of Object.entries(data)) {
            if (value instanceof File) {
                formData.append(key, value);
            } else if (Array.isArray(value)) {
                formData.append(key, JSON.stringify(value));
            } else {
                formData.append(key, value);
            }
        }
        
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        
        return response.json();
    },
    
    // Auth
    login: (username, password) => API.request('login', { username, password }),
    register: (username, email, password) => API.request('register', { username, email, password }),
    logout: () => API.request('logout'),
    
    // Models
    getModels: (params = {}) => API.request('get_models', params),
    getModel: (id) => API.request('get_model', { id }),
    uploadModel: (data) => API.request('upload_model', data),
    deleteModel: (id) => API.request('delete_model', { id }),
    downloadModel: (id) => API.request('download_model', { id }),
    likeModel: (id) => API.request('like_model', { id }),
    favoriteModel: (id) => API.request('favorite_model', { id }),
    
    // Categories
    getCategories: () => API.request('get_categories'),
    createCategory: (data) => API.request('create_category', data),
    updateCategory: (id, data) => API.request('update_category', { id, ...data }),
    deleteCategory: (id) => API.request('delete_category', { id }),
    
    // Users
    getUsers: () => API.request('get_users'),
    updateUser: (id, data) => API.request('update_user', { id, ...data }),
    deleteUser: (id) => API.request('delete_user', { id })
};

// ============================================================================
// TOAST NOTIFICATIONS
// ============================================================================

const Toast = {
    container: null,
    
    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },
    
    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        const colors = {
            success: 'var(--success)',
            error: 'var(--error)',
            warning: 'var(--warning)',
            info: 'var(--neon-cyan)'
        };
        
        toast.innerHTML = `
            <i class="fas ${icons[type]}" style="color: ${colors[type]}"></i>
            <span>${message}</span>
        `;
        
        this.container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    success: (msg) => Toast.show(msg, 'success'),
    error: (msg) => Toast.show(msg, 'error'),
    warning: (msg) => Toast.show(msg, 'warning'),
    info: (msg) => Toast.show(msg, 'info')
};

// ============================================================================
// MODAL HELPER
// ============================================================================

const Modal = {
    show(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },
    
    hide(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },
    
    init() {
        // Close on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Close buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal-overlay');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        
        // ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = '';
            }
        });
    }
};

// ============================================================================
// TAGS INPUT
// ============================================================================

class TagsInput {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            maxTags: 10,
            placeholder: 'Add tag...',
            ...options
        };
        this.tags = [];
        this.init();
    }
    
    init() {
        this.container.classList.add('tags-input');
        this.container.innerHTML = `
            <input type="text" placeholder="${this.options.placeholder}">
        `;
        
        this.input = this.container.querySelector('input');
        this.hiddenInput = document.createElement('input');
        this.hiddenInput.type = 'hidden';
        this.hiddenInput.name = this.container.dataset.name || 'tags';
        this.container.appendChild(this.hiddenInput);
        
        this.input.addEventListener('keydown', (e) => this.handleKeydown(e));
        this.container.addEventListener('click', () => this.input.focus());
    }
    
    handleKeydown(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            this.addTag(this.input.value);
        } else if (e.key === 'Backspace' && !this.input.value) {
            this.removeLastTag();
        }
    }
    
    addTag(value) {
        value = value.trim().toLowerCase();
        if (!value || this.tags.includes(value) || this.tags.length >= this.options.maxTags) {
            this.input.value = '';
            return;
        }
        
        this.tags.push(value);
        this.render();
        this.input.value = '';
    }
    
    removeTag(tag) {
        this.tags = this.tags.filter(t => t !== tag);
        this.render();
    }
    
    removeLastTag() {
        this.tags.pop();
        this.render();
    }
    
    render() {
        // Remove existing tags
        this.container.querySelectorAll('.tag').forEach(el => el.remove());
        
        // Add tags before input
        this.tags.forEach(tag => {
            const tagEl = document.createElement('span');
            tagEl.className = 'tag';
            tagEl.innerHTML = `
                ${tag}
                <span class="tag-remove" data-tag="${tag}">&times;</span>
            `;
            this.container.insertBefore(tagEl, this.input);
        });
        
        // Update hidden input
        this.hiddenInput.value = JSON.stringify(this.tags);
        
        // Add remove listeners
        this.container.querySelectorAll('.tag-remove').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.removeTag(btn.dataset.tag);
            });
        });
    }
    
    getTags() {
        return this.tags;
    }
    
    setTags(tags) {
        this.tags = tags;
        this.render();
    }
}

// ============================================================================
// FILE UPLOAD HANDLER
// ============================================================================

class FileUploader {
    constructor(dropzone, options = {}) {
        this.dropzone = dropzone;
        this.options = {
            accept: '.stl',
            maxSize: 50 * 1024 * 1024, // 50MB
            onFile: () => {},
            ...options
        };
        this.init();
    }
    
    init() {
        const input = this.dropzone.querySelector('input[type="file"]');
        
        // File input change
        input.addEventListener('change', (e) => {
            if (e.target.files.length) {
                this.handleFile(e.target.files[0]);
            }
        });
        
        // Drag events
        this.dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.dropzone.classList.add('dragover');
        });
        
        this.dropzone.addEventListener('dragleave', () => {
            this.dropzone.classList.remove('dragover');
        });
        
        this.dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            this.dropzone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                this.handleFile(e.dataTransfer.files[0]);
            }
        });
    }
    
    handleFile(file) {
        // Validate extension
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'stl') {
            Toast.error('Only STL files are allowed');
            return;
        }
        
        // Validate size
        if (file.size > this.options.maxSize) {
            Toast.error('File too large (max 50MB)');
            return;
        }
        
        this.options.onFile(file);
    }
}

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize modals
    Modal.init();
    
    // Initialize toast
    Toast.init();
    
    // Initialize tags inputs
    document.querySelectorAll('[data-tags-input]').forEach(el => {
        new TagsInput(el);
    });
    
    // Initialize file uploaders
    document.querySelectorAll('.file-upload').forEach(el => {
        new FileUploader(el, {
            onFile: (file) => {
                // Show file info
                const info = el.querySelector('.file-upload-text');
                if (info) {
                    info.innerHTML = `
                        <strong>${file.name}</strong><br>
                        <small>${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    `;
                }
                
                // Store file reference
                el.dataset.file = file.name;
                el._file = file;
            }
        });
    });
    
    // Initialize 3D viewers
    document.querySelectorAll('[data-stl-viewer]').forEach(container => {
        const url = container.dataset.stlUrl;
        if (url) {
            const viewer = new STLViewer(container);
            viewer.loadSTL(url).then(() => {
                // Hide loading indicator
                const loading = container.querySelector('.viewer-loading');
                if (loading) loading.style.display = 'none';
            }).catch(err => {
                console.error('Failed to load STL:', err);
                Toast.error('Failed to load 3D model');
            });
            
            // Store reference
            container._viewer = viewer;
        }
    });
    
    // Initialize thumbnail viewers
    document.querySelectorAll('[data-stl-thumb]').forEach(container => {
        const url = container.dataset.stlThumb;
        if (url) {
            new ThumbnailViewer(container, url);
        }
    });
    
    // Viewer controls
    document.querySelectorAll('[data-viewer-control]').forEach(btn => {
        btn.addEventListener('click', () => {
            const container = btn.closest('.viewer-container').querySelector('[data-stl-viewer]');
            const viewer = container?._viewer;
            if (!viewer) return;
            
            const action = btn.dataset.viewerControl;
            switch (action) {
                case 'reset':
                    viewer.resetView();
                    break;
                case 'wireframe':
                    btn.classList.toggle('active');
                    viewer.setWireframe(btn.classList.contains('active'));
                    break;
                case 'rotate':
                    btn.classList.toggle('active');
                    viewer.setAutoRotate(btn.classList.contains('active'));
                    break;
            }
        });
    });
});

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
}

// Export for use in pages
window.STLViewer = STLViewer;
window.ThumbnailViewer = ThumbnailViewer;
window.API = API;
window.Toast = Toast;
window.Modal = Modal;
window.TagsInput = TagsInput;
window.FileUploader = FileUploader;
