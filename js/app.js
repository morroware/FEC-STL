/**
 * FEC STL Vault - Main JavaScript
 * 3D Viewer with Multi-Format Support & Enhanced Visuals
 */

// ============================================================================
// SUPPORTED 3D FILE FORMATS FOR 3D PRINTING
// ============================================================================
const SUPPORTED_FORMATS = {
    'stl': { name: 'STL', description: 'Stereolithography', hasColor: false },
    'obj': { name: 'OBJ', description: 'Wavefront OBJ', hasColor: true },
    'ply': { name: 'PLY', description: 'Polygon File Format', hasColor: true },
    'gltf': { name: 'GLTF', description: 'GL Transmission Format', hasColor: true },
    'glb': { name: 'GLB', description: 'GL Binary', hasColor: true },
    '3mf': { name: '3MF', description: '3D Manufacturing Format', hasColor: true }
};

// ============================================================================
// MATERIAL PRESETS FOR 3D PRINTING VISUALIZATION
// ============================================================================
const MATERIAL_PRESETS = {
    'default': {
        name: 'Default',
        color: 0x00f0ff,
        metalness: 0.1,
        roughness: 0.5,
        clearcoat: 0,
        envMapIntensity: 0.5
    },
    'pla': {
        name: 'PLA',
        color: 0x00f0ff,
        metalness: 0.0,
        roughness: 0.6,
        clearcoat: 0.1,
        envMapIntensity: 0.3
    },
    'abs': {
        name: 'ABS',
        color: 0x00f0ff,
        metalness: 0.0,
        roughness: 0.5,
        clearcoat: 0.2,
        envMapIntensity: 0.4
    },
    'petg': {
        name: 'PETG',
        color: 0x00f0ff,
        metalness: 0.05,
        roughness: 0.3,
        clearcoat: 0.4,
        envMapIntensity: 0.6
    },
    'resin': {
        name: 'Resin',
        color: 0x00f0ff,
        metalness: 0.1,
        roughness: 0.15,
        clearcoat: 0.8,
        envMapIntensity: 0.8
    },
    'metal': {
        name: 'Metal',
        color: 0xc0c0c0,
        metalness: 0.9,
        roughness: 0.2,
        clearcoat: 0.0,
        envMapIntensity: 1.0
    },
    'silk': {
        name: 'Silk PLA',
        color: 0x00f0ff,
        metalness: 0.6,
        roughness: 0.3,
        clearcoat: 0.3,
        envMapIntensity: 0.7
    },
    'matte': {
        name: 'Matte',
        color: 0x00f0ff,
        metalness: 0.0,
        roughness: 0.9,
        clearcoat: 0.0,
        envMapIntensity: 0.2
    },
    'glow': {
        name: 'Glow Effect',
        color: 0x00ff88,
        metalness: 0.0,
        roughness: 0.4,
        clearcoat: 0.5,
        envMapIntensity: 0.5,
        emissive: 0x00ff88,
        emissiveIntensity: 0.3
    }
};

// ============================================================================
// COLOR PALETTE - EXPANDED WITH GRADIENT PRESETS
// ============================================================================
const COLOR_PALETTE = [
    // Neon Colors
    { name: 'Neon Cyan', hex: '#00f0ff', value: 0x00f0ff },
    { name: 'Neon Magenta', hex: '#ff00aa', value: 0xff00aa },
    { name: 'Neon Yellow', hex: '#f0ff00', value: 0xf0ff00 },
    { name: 'Neon Green', hex: '#00ff88', value: 0x00ff88 },
    { name: 'Electric Blue', hex: '#0066ff', value: 0x0066ff },
    { name: 'Hot Pink', hex: '#ff1493', value: 0xff1493 },
    // Standard Colors
    { name: 'Orange', hex: '#ff6b35', value: 0xff6b35 },
    { name: 'Purple', hex: '#a855f7', value: 0xa855f7 },
    { name: 'Red', hex: '#ff3333', value: 0xff3333 },
    { name: 'Coral', hex: '#ff7f50', value: 0xff7f50 },
    // Neutrals
    { name: 'White', hex: '#ffffff', value: 0xffffff },
    { name: 'Light Gray', hex: '#cccccc', value: 0xcccccc },
    { name: 'Gray', hex: '#888888', value: 0x888888 },
    { name: 'Dark Gray', hex: '#444444', value: 0x444444 },
    { name: 'Black', hex: '#1a1a1a', value: 0x1a1a1a },
    // Metallic
    { name: 'Gold', hex: '#ffd700', value: 0xffd700 },
    { name: 'Silver', hex: '#c0c0c0', value: 0xc0c0c0 },
    { name: 'Bronze', hex: '#cd7f32', value: 0xcd7f32 },
    { name: 'Copper', hex: '#b87333', value: 0xb87333 },
    { name: 'Rose Gold', hex: '#b76e79', value: 0xb76e79 }
];

// ============================================================================
// ENHANCED 3D VIEWER CLASS
// ============================================================================

class ModelViewer {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            backgroundColor: 0x0a0a0f,
            modelColor: 0x00f0ff,
            wireframe: false,
            autoRotate: true,
            materialPreset: 'default',
            showGrid: false,
            enableEnvironment: true,
            ...options
        };

        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.mesh = null;
        this.model = null;
        this.gridHelper = null;
        this.animationId = null;
        this.envMap = null;
        this.currentMaterial = null;
        this.originalMaterials = null;
        this.modelFormat = 'stl';
        this.hasVertexColors = false;

        this.init();
    }

    init() {
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;

        // Scene
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(this.options.backgroundColor);

        // Add fog for depth
        this.scene.fog = new THREE.Fog(this.options.backgroundColor, 100, 500);

        // Camera
        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
        this.camera.position.set(0, 0, 100);

        // Renderer with enhanced settings
        this.renderer = new THREE.WebGLRenderer({
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance'
        });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.outputEncoding = THREE.sRGBEncoding;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.2;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.container.appendChild(this.renderer.domElement);

        // Controls
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.05;
        this.controls.autoRotate = this.options.autoRotate;
        this.controls.autoRotateSpeed = 1;
        this.controls.minDistance = 1;
        this.controls.maxDistance = 500;

        // Lighting
        this.setupLighting();

        // Environment map for reflections
        if (this.options.enableEnvironment) {
            this.createEnvironmentMap();
        }

        // Optional grid
        if (this.options.showGrid) {
            this.addGrid();
        }

        // Handle resize
        this.handleResize = () => this.onResize();
        window.addEventListener('resize', this.handleResize);

        // Start animation
        this.animate();
    }

    setupLighting() {
        // Ambient light - softer overall illumination
        const ambient = new THREE.AmbientLight(0xffffff, 0.3);
        this.scene.add(ambient);

        // Hemisphere light for natural sky/ground lighting
        const hemi = new THREE.HemisphereLight(0x00f0ff, 0xff00aa, 0.2);
        this.scene.add(hemi);

        // Key light (main light with shadows)
        const keyLight = new THREE.DirectionalLight(0xffffff, 0.8);
        keyLight.position.set(50, 100, 50);
        keyLight.castShadow = true;
        keyLight.shadow.mapSize.width = 2048;
        keyLight.shadow.mapSize.height = 2048;
        keyLight.shadow.camera.near = 0.5;
        keyLight.shadow.camera.far = 500;
        this.scene.add(keyLight);

        // Fill light - cyan accent
        const fillLight = new THREE.DirectionalLight(0x00f0ff, 0.4);
        fillLight.position.set(-50, 0, 50);
        this.scene.add(fillLight);

        // Rim light - magenta accent for edge definition
        const rimLight = new THREE.DirectionalLight(0xff00aa, 0.3);
        rimLight.position.set(0, -50, -50);
        this.scene.add(rimLight);

        // Top light for highlights
        const topLight = new THREE.DirectionalLight(0xffffff, 0.3);
        topLight.position.set(0, 100, 0);
        this.scene.add(topLight);

        // Point lights for extra pop
        const pointLight1 = new THREE.PointLight(0x00f0ff, 0.5, 150);
        pointLight1.position.set(30, 30, 30);
        this.scene.add(pointLight1);

        const pointLight2 = new THREE.PointLight(0xff00aa, 0.3, 150);
        pointLight2.position.set(-30, -10, 20);
        this.scene.add(pointLight2);
    }

    createEnvironmentMap() {
        // Create a procedural environment map for reflections
        const pmremGenerator = new THREE.PMREMGenerator(this.renderer);
        pmremGenerator.compileEquirectangularShader();

        // Create a simple gradient environment
        const envScene = new THREE.Scene();
        const envGeometry = new THREE.SphereGeometry(500, 32, 32);

        // Gradient shader for environment
        const envMaterial = new THREE.ShaderMaterial({
            side: THREE.BackSide,
            uniforms: {
                topColor: { value: new THREE.Color(0x0a0a1a) },
                bottomColor: { value: new THREE.Color(0x000000) },
                offset: { value: 20 },
                exponent: { value: 0.6 }
            },
            vertexShader: `
                varying vec3 vWorldPosition;
                void main() {
                    vec4 worldPosition = modelMatrix * vec4(position, 1.0);
                    vWorldPosition = worldPosition.xyz;
                    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                }
            `,
            fragmentShader: `
                uniform vec3 topColor;
                uniform vec3 bottomColor;
                uniform float offset;
                uniform float exponent;
                varying vec3 vWorldPosition;
                void main() {
                    float h = normalize(vWorldPosition + offset).y;
                    gl_FragColor = vec4(mix(bottomColor, topColor, max(pow(max(h, 0.0), exponent), 0.0)), 1.0);
                }
            `
        });

        const envMesh = new THREE.Mesh(envGeometry, envMaterial);
        envScene.add(envMesh);

        // Add some bright spots for reflections
        const spotGeom = new THREE.SphereGeometry(5, 16, 16);
        const spotMat1 = new THREE.MeshBasicMaterial({ color: 0x00f0ff });
        const spotMat2 = new THREE.MeshBasicMaterial({ color: 0xff00aa });
        const spotMat3 = new THREE.MeshBasicMaterial({ color: 0xffffff });

        const spot1 = new THREE.Mesh(spotGeom, spotMat1);
        spot1.position.set(100, 100, 100);
        envScene.add(spot1);

        const spot2 = new THREE.Mesh(spotGeom, spotMat2);
        spot2.position.set(-100, 50, -100);
        envScene.add(spot2);

        const spot3 = new THREE.Mesh(spotGeom, spotMat3);
        spot3.position.set(0, 150, 0);
        envScene.add(spot3);

        // Generate environment map
        const renderTarget = pmremGenerator.fromScene(envScene, 0.04);
        this.envMap = renderTarget.texture;

        pmremGenerator.dispose();
    }

    addGrid() {
        this.gridHelper = new THREE.GridHelper(100, 20, 0x00f0ff, 0x1a1a25);
        this.gridHelper.material.opacity = 0.3;
        this.gridHelper.material.transparent = true;
        this.gridHelper.rotation.x = Math.PI / 2;
        this.scene.add(this.gridHelper);
    }

    // Get file extension from URL
    getFileExtension(url) {
        const path = url.split('?')[0];
        const ext = path.split('.').pop().toLowerCase();
        return ext;
    }

    // Load model based on file format
    async loadModel(url, format = null) {
        this.modelFormat = format || this.getFileExtension(url);

        // Remove existing model
        this.clearModel();

        try {
            switch (this.modelFormat) {
                case 'stl':
                    await this.loadSTL(url);
                    break;
                case 'obj':
                    await this.loadOBJ(url);
                    break;
                case 'ply':
                    await this.loadPLY(url);
                    break;
                case 'gltf':
                case 'glb':
                    await this.loadGLTF(url);
                    break;
                case '3mf':
                    await this.load3MF(url);
                    break;
                default:
                    // Default to STL
                    await this.loadSTL(url);
            }

            return true;
        } catch (error) {
            console.error('Failed to load model:', error);
            throw error;
        }
    }

    // Load STL file
    loadSTL(url) {
        return new Promise((resolve, reject) => {
            const loader = new THREE.STLLoader();

            loader.load(
                url,
                (geometry) => {
                    geometry.computeBoundingBox();
                    geometry.center();
                    geometry.computeVertexNormals();

                    // Create enhanced material
                    this.currentMaterial = this.createMaterial(this.options.modelColor);

                    this.mesh = new THREE.Mesh(geometry, this.currentMaterial);
                    this.mesh.castShadow = true;
                    this.mesh.receiveShadow = true;
                    this.model = this.mesh;
                    this.scene.add(this.mesh);

                    this.fitCameraToModel();
                    this.alignGridToModel();
                    this.hasVertexColors = false;

                    resolve(geometry);
                },
                (progress) => {
                    if (progress.total > 0) {
                        const percent = (progress.loaded / progress.total * 100).toFixed(0);
                        this.container.dispatchEvent(new CustomEvent('loadProgress', { detail: percent }));
                    }
                },
                (error) => reject(error)
            );
        });
    }

    // Load OBJ file (with optional MTL for materials/colors)
    loadOBJ(url) {
        return new Promise((resolve, reject) => {
            // Check for MTL file
            const mtlUrl = url.replace(/\.obj$/i, '.mtl');

            // Try to load with MTL first
            const mtlLoader = new THREE.MTLLoader();
            mtlLoader.load(
                mtlUrl,
                (materials) => {
                    materials.preload();

                    const objLoader = new THREE.OBJLoader();
                    objLoader.setMaterials(materials);

                    objLoader.load(
                        url,
                        (object) => {
                            this.processLoadedModel(object, true);
                            resolve(object);
                        },
                        (progress) => this.handleProgress(progress),
                        (error) => reject(error)
                    );
                },
                undefined,
                () => {
                    // No MTL file, load OBJ with default material
                    const objLoader = new THREE.OBJLoader();
                    objLoader.load(
                        url,
                        (object) => {
                            this.processLoadedModel(object, false);
                            resolve(object);
                        },
                        (progress) => this.handleProgress(progress),
                        (error) => reject(error)
                    );
                }
            );
        });
    }

    // Load PLY file (supports vertex colors)
    loadPLY(url) {
        return new Promise((resolve, reject) => {
            const loader = new THREE.PLYLoader();

            loader.load(
                url,
                (geometry) => {
                    geometry.computeBoundingBox();
                    geometry.center();
                    geometry.computeVertexNormals();

                    // Check if geometry has vertex colors
                    const hasColors = geometry.hasAttribute('color');
                    this.hasVertexColors = hasColors;

                    let material;
                    if (hasColors) {
                        // Use vertex colors from the file
                        material = new THREE.MeshStandardMaterial({
                            vertexColors: true,
                            metalness: 0.1,
                            roughness: 0.5,
                            envMap: this.envMap,
                            envMapIntensity: 0.5
                        });
                    } else {
                        material = this.createMaterial(this.options.modelColor);
                    }

                    this.currentMaterial = material;
                    this.mesh = new THREE.Mesh(geometry, material);
                    this.mesh.castShadow = true;
                    this.mesh.receiveShadow = true;
                    this.model = this.mesh;
                    this.scene.add(this.mesh);

                    this.fitCameraToModel();
                    this.alignGridToModel();

                    resolve(geometry);
                },
                (progress) => this.handleProgress(progress),
                (error) => reject(error)
            );
        });
    }

    // Load GLTF/GLB file (full material support)
    loadGLTF(url) {
        return new Promise((resolve, reject) => {
            const loader = new THREE.GLTFLoader();

            loader.load(
                url,
                (gltf) => {
                    const model = gltf.scene;

                    // Center the model
                    const box = new THREE.Box3().setFromObject(model);
                    const center = box.getCenter(new THREE.Vector3());
                    model.position.sub(center);

                    // Store original materials and add env map
                    this.originalMaterials = [];
                    model.traverse((child) => {
                        if (child.isMesh) {
                            child.castShadow = true;
                            child.receiveShadow = true;

                            if (child.material) {
                                this.originalMaterials.push({
                                    mesh: child,
                                    material: child.material.clone()
                                });

                                // Add environment map
                                if (this.envMap && child.material.envMap !== undefined) {
                                    child.material.envMap = this.envMap;
                                    child.material.envMapIntensity = 0.5;
                                    child.material.needsUpdate = true;
                                }
                            }
                        }
                    });

                    this.model = model;
                    this.hasVertexColors = true; // GLTF has its own colors
                    this.scene.add(model);

                    this.fitCameraToModel();
                    this.alignGridToModel();

                    resolve(gltf);
                },
                (progress) => this.handleProgress(progress),
                (error) => reject(error)
            );
        });
    }

    // Load 3MF file (3D Manufacturing Format)
    load3MF(url) {
        return new Promise((resolve, reject) => {
            const loader = new THREE.ThreeMFLoader();

            loader.load(
                url,
                (object) => {
                    this.processLoadedModel(object, true);
                    resolve(object);
                },
                (progress) => this.handleProgress(progress),
                (error) => reject(error)
            );
        });
    }

    // Process loaded model (for OBJ, 3MF)
    processLoadedModel(object, hasMaterials) {
        // Center the model
        const box = new THREE.Box3().setFromObject(object);
        const center = box.getCenter(new THREE.Vector3());
        object.position.sub(center);

        // Store original materials and setup
        this.originalMaterials = [];
        object.traverse((child) => {
            if (child.isMesh) {
                child.castShadow = true;
                child.receiveShadow = true;

                if (hasMaterials && child.material) {
                    this.originalMaterials.push({
                        mesh: child,
                        material: child.material.clone()
                    });

                    // Add environment map
                    if (this.envMap && child.material.envMap !== undefined) {
                        child.material.envMap = this.envMap;
                        child.material.envMapIntensity = 0.5;
                        child.material.needsUpdate = true;
                    }
                } else {
                    // Apply default material
                    const material = this.createMaterial(this.options.modelColor);
                    child.material = material;
                }
            }
        });

        this.hasVertexColors = hasMaterials;
        this.model = object;
        this.scene.add(object);

        this.fitCameraToModel();
        this.alignGridToModel();
    }

    // Handle loading progress
    handleProgress(progress) {
        if (progress.total > 0) {
            const percent = (progress.loaded / progress.total * 100).toFixed(0);
            this.container.dispatchEvent(new CustomEvent('loadProgress', { detail: percent }));
        }
    }

    // Create material based on preset
    createMaterial(color, presetName = null) {
        const preset = MATERIAL_PRESETS[presetName || this.options.materialPreset] || MATERIAL_PRESETS.default;

        const materialOptions = {
            color: color,
            metalness: preset.metalness,
            roughness: preset.roughness,
            envMap: this.envMap,
            envMapIntensity: preset.envMapIntensity
        };

        // Add clearcoat if supported
        if (preset.clearcoat > 0) {
            materialOptions.clearcoat = preset.clearcoat;
            materialOptions.clearcoatRoughness = 0.1;
        }

        // Add emissive for glow effect
        if (preset.emissive) {
            materialOptions.emissive = preset.emissive;
            materialOptions.emissiveIntensity = preset.emissiveIntensity || 0.2;
        }

        return new THREE.MeshPhysicalMaterial(materialOptions);
    }

    // Clear existing model
    clearModel() {
        if (this.model) {
            this.scene.remove(this.model);

            // Dispose geometries and materials
            this.model.traverse((child) => {
                if (child.isMesh) {
                    if (child.geometry) child.geometry.dispose();
                    if (child.material) {
                        if (Array.isArray(child.material)) {
                            child.material.forEach(mat => mat.dispose());
                        } else {
                            child.material.dispose();
                        }
                    }
                }
            });

            this.model = null;
            this.mesh = null;
        }

        this.originalMaterials = null;
        this.currentMaterial = null;
    }

    fitCameraToModel() {
        if (!this.model) return;

        const box = new THREE.Box3().setFromObject(this.model);
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const fov = this.camera.fov * (Math.PI / 180);
        const distance = maxDim / (2 * Math.tan(fov / 2)) * 1.5;

        this.camera.position.set(distance * 0.5, distance * 0.5, distance);
        this.camera.lookAt(0, 0, 0);
        this.controls.target.set(0, 0, 0);
        this.controls.update();
    }

    alignGridToModel() {
        if (!this.model || !this.gridHelper) return;

        const box = new THREE.Box3().setFromObject(this.model);
        this.gridHelper.position.z = box.min.z;
    }

    // Set model color
    setColor(color) {
        if (!this.model) return;

        // For models with vertex colors, offer option to override or preserve
        this.model.traverse((child) => {
            if (child.isMesh && child.material) {
                if (Array.isArray(child.material)) {
                    child.material.forEach(mat => {
                        mat.color.setHex(color);
                        mat.vertexColors = false;
                        mat.needsUpdate = true;
                    });
                } else {
                    child.material.color.setHex(color);
                    child.material.vertexColors = false;
                    child.material.needsUpdate = true;
                }
            }
        });

        this.options.modelColor = color;
    }

    // Restore original materials (for colored models)
    restoreOriginalColors() {
        if (!this.originalMaterials || !this.model) return;

        this.originalMaterials.forEach(({ mesh, material }) => {
            mesh.material = material.clone();
            if (this.envMap && mesh.material.envMap !== undefined) {
                mesh.material.envMap = this.envMap;
                mesh.material.needsUpdate = true;
            }
        });

        this.hasVertexColors = true;
    }

    // Set material preset
    setMaterialPreset(presetName) {
        if (!MATERIAL_PRESETS[presetName] || !this.model) return;

        const preset = MATERIAL_PRESETS[presetName];
        this.options.materialPreset = presetName;

        this.model.traverse((child) => {
            if (child.isMesh && child.material) {
                const materials = Array.isArray(child.material) ? child.material : [child.material];

                materials.forEach(mat => {
                    // Keep current color unless preset has specific color
                    const color = presetName === 'metal' ? preset.color : (mat.color ? mat.color.getHex() : this.options.modelColor);

                    mat.metalness = preset.metalness;
                    mat.roughness = preset.roughness;
                    mat.envMapIntensity = preset.envMapIntensity;

                    if (mat.clearcoat !== undefined) {
                        mat.clearcoat = preset.clearcoat || 0;
                    }

                    if (preset.emissive) {
                        mat.emissive = new THREE.Color(preset.emissive);
                        mat.emissiveIntensity = preset.emissiveIntensity || 0.2;
                    } else {
                        mat.emissive = new THREE.Color(0x000000);
                        mat.emissiveIntensity = 0;
                    }

                    mat.needsUpdate = true;
                });
            }
        });
    }

    setWireframe(enabled) {
        if (!this.model) return;

        this.model.traverse((child) => {
            if (child.isMesh && child.material) {
                if (Array.isArray(child.material)) {
                    child.material.forEach(mat => mat.wireframe = enabled);
                } else {
                    child.material.wireframe = enabled;
                }
            }
        });
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
        window.removeEventListener('resize', this.handleResize);

        this.clearModel();

        if (this.envMap) {
            this.envMap.dispose();
        }

        this.renderer.dispose();
        this.container.innerHTML = '';
    }
}

// ============================================================================
// LEGACY STL VIEWER (Wrapper for backward compatibility)
// ============================================================================

class STLViewer extends ModelViewer {
    constructor(container, options = {}) {
        super(container, options);
    }

    loadSTL(url) {
        return this.loadModel(url, 'stl');
    }
}

// ============================================================================
// THUMBNAIL GENERATOR (Mini 3D Preview)
// ============================================================================

class ThumbnailViewer {
    constructor(container, url) {
        this.container = container;
        this.url = url;
        this.init();
    }

    init() {
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;

        this.container.innerHTML = '';

        // Scene
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0a0a0f);

        // Camera
        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);

        // Renderer
        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.outputEncoding = THREE.sRGBEncoding;
        this.container.appendChild(this.renderer.domElement);

        // Lighting
        const ambient = new THREE.AmbientLight(0xffffff, 0.4);
        this.scene.add(ambient);

        const directional = new THREE.DirectionalLight(0xffffff, 0.8);
        directional.position.set(50, 50, 50);
        this.scene.add(directional);

        const fillLight = new THREE.DirectionalLight(0x00f0ff, 0.3);
        fillLight.position.set(-30, 0, 30);
        this.scene.add(fillLight);

        // Load model
        this.loadModel().catch(() => {
            this.container.innerHTML = '<i class="fas fa-cube"></i>';
        });

        // Animate
        this.animate();
    }

    getFileExtension(url) {
        const path = url.split('?')[0];
        return path.split('.').pop().toLowerCase();
    }

    async loadModel() {
        const ext = this.getFileExtension(this.url);

        switch (ext) {
            case 'stl':
                return this.loadSTL();
            case 'obj':
                return this.loadOBJ();
            case 'ply':
                return this.loadPLY();
            case 'gltf':
            case 'glb':
                return this.loadGLTF();
            default:
                return this.loadSTL();
        }
    }

    loadSTL() {
        return new Promise((resolve, reject) => {
            const loader = new THREE.STLLoader();
            loader.load(
                this.url,
                (geometry) => {
                    geometry.computeBoundingBox();
                    geometry.center();
                    geometry.computeVertexNormals();

                    const material = new THREE.MeshPhysicalMaterial({
                        color: 0x00f0ff,
                        metalness: 0.1,
                        roughness: 0.5
                    });

                    this.mesh = new THREE.Mesh(geometry, material);
                    this.scene.add(this.mesh);
                    this.positionCamera();
                    resolve();
                },
                undefined,
                reject
            );
        });
    }

    loadOBJ() {
        return new Promise((resolve, reject) => {
            const loader = new THREE.OBJLoader();
            loader.load(
                this.url,
                (object) => {
                    this.processModel(object);
                    resolve();
                },
                undefined,
                reject
            );
        });
    }

    loadPLY() {
        return new Promise((resolve, reject) => {
            const loader = new THREE.PLYLoader();
            loader.load(
                this.url,
                (geometry) => {
                    geometry.computeBoundingBox();
                    geometry.center();
                    geometry.computeVertexNormals();

                    const hasColors = geometry.hasAttribute('color');
                    const material = new THREE.MeshPhysicalMaterial({
                        color: hasColors ? 0xffffff : 0x00f0ff,
                        vertexColors: hasColors,
                        metalness: 0.1,
                        roughness: 0.5
                    });

                    this.mesh = new THREE.Mesh(geometry, material);
                    this.scene.add(this.mesh);
                    this.positionCamera();
                    resolve();
                },
                undefined,
                reject
            );
        });
    }

    loadGLTF() {
        return new Promise((resolve, reject) => {
            const loader = new THREE.GLTFLoader();
            loader.load(
                this.url,
                (gltf) => {
                    this.processModel(gltf.scene);
                    resolve();
                },
                undefined,
                reject
            );
        });
    }

    processModel(object) {
        const box = new THREE.Box3().setFromObject(object);
        const center = box.getCenter(new THREE.Vector3());
        object.position.sub(center);

        object.traverse((child) => {
            if (child.isMesh && !child.material.map) {
                child.material = new THREE.MeshPhysicalMaterial({
                    color: child.material.color || 0x00f0ff,
                    metalness: 0.1,
                    roughness: 0.5
                });
            }
        });

        this.model = object;
        this.scene.add(object);

        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const distance = maxDim * 2;

        this.camera.position.set(distance * 0.5, distance * 0.3, distance * 0.5);
        this.camera.lookAt(0, 0, 0);
    }

    positionCamera() {
        if (!this.mesh) return;

        const box = new THREE.Box3().setFromObject(this.mesh);
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        const distance = maxDim * 2;

        this.camera.position.set(distance * 0.5, distance * 0.3, distance * 0.5);
        this.camera.lookAt(0, 0, 0);
    }

    animate() {
        requestAnimationFrame(() => this.animate());

        if (this.mesh) {
            this.mesh.rotation.y += 0.005;
        }
        if (this.model) {
            this.model.rotation.y += 0.005;
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

// Supported file extensions for 3D printing
const ALLOWED_EXTENSIONS = ['stl', 'obj', 'ply', 'gltf', 'glb', '3mf'];

class FileUploader {
    constructor(dropzone, options = {}) {
        this.dropzone = dropzone;
        this.options = {
            accept: ALLOWED_EXTENSIONS.map(ext => '.' + ext).join(','),
            maxSize: 50 * 1024 * 1024, // 50MB
            onFile: () => {},
            onFiles: () => {},
            multiple: true,
            ...options
        };
        this.init();
    }

    init() {
        const input = this.dropzone.querySelector('input[type="file"]');

        // Update accept attribute
        if (input) {
            input.accept = this.options.accept;
        }

        // File input change
        input.addEventListener('change', (e) => {
            if (e.target.files.length) {
                this.handleFiles(Array.from(e.target.files));
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
                this.handleFiles(Array.from(e.dataTransfer.files));
            }
        });
    }

    handleFiles(files) {
        const validFiles = [];

        files.forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();

            // Validate extension
            if (!ALLOWED_EXTENSIONS.includes(ext)) {
                Toast.error(`Unsupported format: ${file.name}. Allowed: ${ALLOWED_EXTENSIONS.join(', ').toUpperCase()}`);
                return;
            }

            // Validate size
            if (file.size > this.options.maxSize) {
                Toast.error(`File too large (max 50MB): ${file.name}`);
                return;
            }

            validFiles.push(file);
        });

        if (validFiles.length > 0) {
            if (this.options.multiple) {
                this.options.onFiles(validFiles);
            } else {
                this.options.onFile(validFiles[0]);
            }
        }
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
                    const ext = file.name.split('.').pop().toUpperCase();
                    info.innerHTML = `
                        <strong>${file.name}</strong><br>
                        <small>${(file.size / 1024 / 1024).toFixed(2)} MB • ${ext}</small>
                    `;
                }

                // Store file reference
                el.dataset.file = file.name;
                el._file = file;
            }
        });
    });

    // Initialize 3D viewers with format detection
    document.querySelectorAll('[data-stl-viewer], [data-model-viewer]').forEach(container => {
        const url = container.dataset.stlUrl || container.dataset.modelUrl;
        if (url) {
            const viewer = new ModelViewer(container);
            viewer.loadModel(url).then(() => {
                // Hide loading indicator
                const loading = container.closest('.viewer-container')?.querySelector('.viewer-loading');
                if (loading) loading.style.display = 'none';
            }).catch(err => {
                console.error('Failed to load model:', err);
                Toast.error('Failed to load 3D model');
            });

            // Store reference
            container._viewer = viewer;
        }
    });

    // Initialize thumbnail viewers
    document.querySelectorAll('[data-stl-thumb], [data-model-thumb]').forEach(container => {
        const url = container.dataset.stlThumb || container.dataset.modelThumb;
        if (url) {
            new ThumbnailViewer(container, url);
        }
    });

    // Viewer controls
    document.querySelectorAll('[data-viewer-control]').forEach(btn => {
        btn.addEventListener('click', () => {
            const container = btn.closest('.viewer-container').querySelector('[data-stl-viewer], [data-model-viewer]');
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

function getFileFormatInfo(extension) {
    return SUPPORTED_FORMATS[extension.toLowerCase()] || { name: extension.toUpperCase(), hasColor: false };
}

// Export for use in pages
window.ModelViewer = ModelViewer;
window.STLViewer = STLViewer;
window.ThumbnailViewer = ThumbnailViewer;
window.API = API;
window.Toast = Toast;
window.Modal = Modal;
window.TagsInput = TagsInput;
window.FileUploader = FileUploader;
window.MATERIAL_PRESETS = MATERIAL_PRESETS;
window.COLOR_PALETTE = COLOR_PALETTE;
window.SUPPORTED_FORMATS = SUPPORTED_FORMATS;
window.ALLOWED_EXTENSIONS = ALLOWED_EXTENSIONS;
