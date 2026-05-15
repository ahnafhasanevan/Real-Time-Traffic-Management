// welcome_prismatic-burst.js - GradientBlinds implementation
class GradientBlinds {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            gradientColors: options.gradientColors || ['#d6c8d6', '#0c003b'],
            angle: options.angle || 0,
            noise: options.noise || 0.3,
            blindCount: options.blindCount || 12,
            blindMinWidth: options.blindMinWidth || 50,
            spotlightRadius: options.spotlightRadius || 0.5,
            spotlightSoftness: options.spotlightSoftness || 1,
            spotlightOpacity: options.spotlightOpacity || 1,
            mouseDampening: options.mouseDampening || 0.15,
            distortAmount: options.distortAmount || 0,
            shineDirection: options.shineDirection || "left",
            mixBlendMode: options.mixBlendMode || "lighten",
            ...options
        };
        
        this.canvas = null;
        this.gl = null;
        this.program = null;
        this.animationId = null;
        this.startTime = Date.now();
        this.mouseTarget = [0.5, 0.5];
        this.mouseSmooth = [0.5, 0.5];
        this.isVisible = true;
        
        this.MAX_COLORS = 8;
        this.init();
    }
    
    hexToRGB(hex) {
        const c = hex.replace('#', '').padEnd(6, '0');
        const r = parseInt(c.slice(0, 2), 16) / 255;
        const g = parseInt(c.slice(2, 4), 16) / 255;
        const b = parseInt(c.slice(4, 6), 16) / 255;
        return [r, g, b];
    }
    
    prepStops(stops) {
        const base = (stops && stops.length ? stops : ['#d6c8d6', '#0c003b']).slice(0, this.MAX_COLORS);
        if (base.length === 1) base.push(base[0]);
        while (base.length < this.MAX_COLORS) base.push(base[base.length - 1]);
        const arr = [];
        for (let i = 0; i < this.MAX_COLORS; i++) arr.push(this.hexToRGB(base[i]));
        const count = Math.max(2, Math.min(this.MAX_COLORS, stops?.length ?? 2));
        return { arr, count };
    }
    
    init() {
        if (this.supportsWebGL()) {
            this.initWebGL();
        } else {
            this.initCSSFallback();
        }
    }
    
    supportsWebGL() {
        try {
            const canvas = document.createElement('canvas');
            return !!(window.WebGLRenderingContext && 
                     (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
        } catch (e) {
            return false;
        }
    }
    
    initWebGL() {
        this.createCanvas();
        this.setupWebGL();
        this.createShaders();
        this.setupBuffers();
        this.startAnimation();
        this.setupEventListeners();
    }
    
    createCanvas() {
        this.canvas = document.createElement('canvas');
        this.canvas.className = 'gradient-blinds-canvas';
        this.canvas.style.position = 'fixed';
        this.canvas.style.top = '0';
        this.canvas.style.left = '0';
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        this.canvas.style.zIndex = '-1';
        this.canvas.style.pointerEvents = 'none';
        this.canvas.style.mixBlendMode = this.options.mixBlendMode;
        
        this.container.appendChild(this.canvas);
        this.resize();
    }
    
    resize() {
        if (!this.canvas) return;
        
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        this.canvas.width = window.innerWidth * dpr;
        this.canvas.height = window.innerHeight * dpr;
        this.canvas.style.width = window.innerWidth + 'px';
        this.canvas.style.height = window.innerHeight + 'px';
        
        if (this.gl) {
            this.gl.viewport(0, 0, this.canvas.width, this.canvas.height);
            if (this.program) {
                this.gl.uniform2f(this.uniforms.iResolution, this.canvas.width, this.canvas.height);
                
                // Update blind count based on min width
                if (this.options.blindMinWidth && this.options.blindMinWidth > 0) {
                    const maxByMinWidth = Math.max(1, Math.floor(window.innerWidth / this.options.blindMinWidth));
                    const effective = this.options.blindCount ? 
                        Math.min(this.options.blindCount, maxByMinWidth) : maxByMinWidth;
                    this.gl.uniform1f(this.uniforms.uBlindCount, Math.max(1, effective));
                } else {
                    this.gl.uniform1f(this.uniforms.uBlindCount, Math.max(1, this.options.blindCount));
                }
            }
        }
    }
    
    setupWebGL() {
        this.gl = this.canvas.getContext('webgl') || this.canvas.getContext('experimental-webgl');
        if (!this.gl) {
            this.initCSSFallback();
            return;
        }
    }
    
    createShaders() {
        const vertexShaderSource = `
            attribute vec2 position;
            attribute vec2 uv;
            varying vec2 vUv;

            void main() {
                vUv = uv;
                gl_Position = vec4(position, 0.0, 1.0);
            }
        `;
        
        const fragmentShaderSource = `
            precision mediump float;

            uniform vec3  iResolution;
            uniform vec2  iMouse;
            uniform float iTime;

            uniform float uAngle;
            uniform float uNoise;
            uniform float uBlindCount;
            uniform float uSpotlightRadius;
            uniform float uSpotlightSoftness;
            uniform float uSpotlightOpacity;
            uniform float uMirror;
            uniform float uDistort;
            uniform float uShineFlip;
            uniform vec3  uColor0;
            uniform vec3  uColor1;
            uniform vec3  uColor2;
            uniform vec3  uColor3;
            uniform vec3  uColor4;
            uniform vec3  uColor5;
            uniform vec3  uColor6;
            uniform vec3  uColor7;
            uniform int   uColorCount;

            varying vec2 vUv;

            float rand(vec2 co){
                return fract(sin(dot(co, vec2(12.9898,78.233))) * 43758.5453);
            }

            vec2 rotate2D(vec2 p, float a){
                float c = cos(a);
                float s = sin(a);
                return mat2(c, -s, s, c) * p;
            }

            vec3 getGradientColor(float t){
                float tt = clamp(t, 0.0, 1.0);
                int count = uColorCount;
                if (count < 2) count = 2;
                float scaled = tt * float(count - 1);
                float seg = floor(scaled);
                float f = fract(scaled);

                if (seg < 1.0) return mix(uColor0, uColor1, f);
                if (seg < 2.0 && count > 2) return mix(uColor1, uColor2, f);
                if (seg < 3.0 && count > 3) return mix(uColor2, uColor3, f);
                if (seg < 4.0 && count > 4) return mix(uColor3, uColor4, f);
                if (seg < 5.0 && count > 5) return mix(uColor4, uColor5, f);
                if (seg < 6.0 && count > 6) return mix(uColor5, uColor6, f);
                if (seg < 7.0 && count > 7) return mix(uColor6, uColor7, f);
                if (count > 7) return uColor7;
                if (count > 6) return uColor6;
                if (count > 5) return uColor5;
                if (count > 4) return uColor4;
                if (count > 3) return uColor3;
                if (count > 2) return uColor2;
                return uColor1;
            }

            void main() {
                vec2 uv0 = vUv;

                float aspect = iResolution.x / iResolution.y;
                vec2 p = uv0 * 2.0 - 1.0;
                p.x *= aspect;
                vec2 pr = rotate2D(p, uAngle);
                pr.x /= aspect;
                vec2 uv = pr * 0.5 + 0.5;

                vec2 uvMod = uv;
                if (uDistort > 0.0) {
                    float a = uvMod.y * 6.0;
                    float b = uvMod.x * 6.0;
                    float w = 0.01 * uDistort;
                    uvMod.x += sin(a) * w;
                    uvMod.y += cos(b) * w;
                }
                float t = uvMod.x;
                if (uMirror > 0.5) {
                    t = 1.0 - abs(1.0 - 2.0 * fract(t));
                }
                vec3 base = getGradientColor(t);

                vec2 offset = vec2(iMouse.x/iResolution.x, iMouse.y/iResolution.y);
                float d = length(uv0 - offset);
                float r = max(uSpotlightRadius, 1e-4);
                float dn = d / r;
                float spot = (1.0 - 2.0 * pow(dn, uSpotlightSoftness)) * uSpotlightOpacity;
                vec3 cir = vec3(spot);
                float stripe = fract(uvMod.x * max(uBlindCount, 1.0));
                if (uShineFlip > 0.5) stripe = 1.0 - stripe;
                vec3 ran = vec3(stripe);

                vec3 col = cir + base - ran;
                col += (rand(gl_FragCoord.xy + iTime) - 0.5) * uNoise;

                gl_FragColor = vec4(col, 1.0);
            }
        `;

        const vertexShader = this.compileShader(this.gl.VERTEX_SHADER, vertexShaderSource);
        const fragmentShader = this.compileShader(this.gl.FRAGMENT_SHADER, fragmentShaderSource);
        
        if (!vertexShader || !fragmentShader) {
            this.initCSSFallback();
            return;
        }
        
        const { arr: colorArr, count: colorCount } = this.prepStops(this.options.gradientColors);
        
        this.program = this.gl.createProgram();
        this.gl.attachShader(this.program, vertexShader);
        this.gl.attachShader(this.program, fragmentShader);
        this.gl.linkProgram(this.program);
        
        if (!this.gl.getProgramParameter(this.program, this.gl.LINK_STATUS)) {
            console.error('WebGL program failed to link');
            this.initCSSFallback();
            return;
        }
        
        this.gl.useProgram(this.program);
        
        // Set up uniforms
        this.uniforms = {
            iResolution: this.gl.getUniformLocation(this.program, "iResolution"),
            iMouse: this.gl.getUniformLocation(this.program, "iMouse"),
            iTime: this.gl.getUniformLocation(this.program, "iTime"),
            uAngle: this.gl.getUniformLocation(this.program, "uAngle"),
            uNoise: this.gl.getUniformLocation(this.program, "uNoise"),
            uBlindCount: this.gl.getUniformLocation(this.program, "uBlindCount"),
            uSpotlightRadius: this.gl.getUniformLocation(this.program, "uSpotlightRadius"),
            uSpotlightSoftness: this.gl.getUniformLocation(this.program, "uSpotlightSoftness"),
            uSpotlightOpacity: this.gl.getUniformLocation(this.program, "uSpotlightOpacity"),
            uMirror: this.gl.getUniformLocation(this.program, "uMirror"),
            uDistort: this.gl.getUniformLocation(this.program, "uDistort"),
            uShineFlip: this.gl.getUniformLocation(this.program, "uShineFlip"),
            uColor0: this.gl.getUniformLocation(this.program, "uColor0"),
            uColor1: this.gl.getUniformLocation(this.program, "uColor1"),
            uColor2: this.gl.getUniformLocation(this.program, "uColor2"),
            uColor3: this.gl.getUniformLocation(this.program, "uColor3"),
            uColor4: this.gl.getUniformLocation(this.program, "uColor4"),
            uColor5: this.gl.getUniformLocation(this.program, "uColor5"),
            uColor6: this.gl.getUniformLocation(this.program, "uColor6"),
            uColor7: this.gl.getUniformLocation(this.program, "uColor7"),
            uColorCount: this.gl.getUniformLocation(this.program, "uColorCount")
        };
        
        // Set initial uniform values
        this.gl.uniform3f(this.uniforms.iResolution, this.canvas.width, this.canvas.height, 1);
        this.gl.uniform1f(this.uniforms.iTime, 0);
        this.gl.uniform2f(this.uniforms.iMouse, this.canvas.width/2, this.canvas.height/2);
        this.gl.uniform1f(this.uniforms.uAngle, (this.options.angle * Math.PI) / 180);
        this.gl.uniform1f(this.uniforms.uNoise, this.options.noise);
        this.gl.uniform1f(this.uniforms.uBlindCount, Math.max(1, this.options.blindCount));
        this.gl.uniform1f(this.uniforms.uSpotlightRadius, this.options.spotlightRadius);
        this.gl.uniform1f(this.uniforms.uSpotlightSoftness, this.options.spotlightSoftness);
        this.gl.uniform1f(this.uniforms.uSpotlightOpacity, this.options.spotlightOpacity);
        this.gl.uniform1f(this.uniforms.uMirror, 0);
        this.gl.uniform1f(this.uniforms.uDistort, this.options.distortAmount);
        this.gl.uniform1f(this.uniforms.uShineFlip, this.options.shineDirection === 'right' ? 1 : 0);
        this.gl.uniform3fv(this.uniforms.uColor0, colorArr[0]);
        this.gl.uniform3fv(this.uniforms.uColor1, colorArr[1]);
        this.gl.uniform3fv(this.uniforms.uColor2, colorArr[2]);
        this.gl.uniform3fv(this.uniforms.uColor3, colorArr[3]);
        this.gl.uniform3fv(this.uniforms.uColor4, colorArr[4]);
        this.gl.uniform3fv(this.uniforms.uColor5, colorArr[5]);
        this.gl.uniform3fv(this.uniforms.uColor6, colorArr[6]);
        this.gl.uniform3fv(this.uniforms.uColor7, colorArr[7]);
        this.gl.uniform1i(this.uniforms.uColorCount, colorCount);
    }
    
    compileShader(type, source) {
        const shader = this.gl.createShader(type);
        this.gl.shaderSource(shader, source);
        this.gl.compileShader(shader);
        
        if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) {
            console.error('Shader compilation error:', this.gl.getShaderInfoLog(shader));
            return null;
        }
        
        return shader;
    }
    
    setupBuffers() {
        // Create triangle geometry that covers the whole viewport
        const positions = new Float32Array([
            -1.0, -1.0, 0.0, 0.0,
             1.0, -1.0, 1.0, 0.0,
            -1.0,  1.0, 0.0, 1.0,
            -1.0,  1.0, 0.0, 1.0,
             1.0, -1.0, 1.0, 0.0,
             1.0,  1.0, 1.0, 1.0
        ]);
        
        this.positionBuffer = this.gl.createBuffer();
        this.gl.bindBuffer(this.gl.ARRAY_BUFFER, this.positionBuffer);
        this.gl.bufferData(this.gl.ARRAY_BUFFER, positions, this.gl.STATIC_DRAW);
        
        // Set up attributes
        this.positionAttribute = this.gl.getAttribLocation(this.program, "position");
        this.uvAttribute = this.gl.getAttribLocation(this.program, "uv");
        
        this.gl.enableVertexAttribArray(this.positionAttribute);
        this.gl.enableVertexAttribArray(this.uvAttribute);
    }
    
    setupEventListeners() {
        window.addEventListener('resize', () => this.resize());
        
        window.addEventListener('mousemove', (e) => {
            this.mouseTarget = [
                e.clientX,
                window.innerHeight - e.clientY
            ];
        });
        
        // Visibility handling
        document.addEventListener('visibilitychange', () => {
            this.isVisible = !document.hidden;
        });
    }
    
    startAnimation() {
        const animate = (currentTime) => {
            this.render(currentTime);
            this.animationId = requestAnimationFrame(animate);
        };
        this.animationId = requestAnimationFrame(animate);
    }
    
    render(currentTime) {
        if (!this.gl || !this.program || !this.isVisible) return;
        
        const time = (currentTime - this.startTime) * 0.001;
        
        // Smooth mouse movement
        if (this.options.mouseDampening > 0) {
            const tau = Math.max(1e-4, this.options.mouseDampening);
            const factor = 1 - Math.exp(-16.666 / 1000 / tau);
            const target = this.mouseTarget;
            const cur = [this.mouseSmooth[0], this.mouseSmooth[1]];
            this.mouseSmooth[0] += (target[0] - cur[0]) * factor;
            this.mouseSmooth[1] += (target[1] - cur[1]) * factor;
        } else {
            this.mouseSmooth = [...this.mouseTarget];
        }
        
        // Update uniforms
        this.gl.uniform1f(this.uniforms.iTime, time);
        this.gl.uniform2f(this.uniforms.iMouse, this.mouseSmooth[0], this.mouseSmooth[1]);
        
        // Render
        this.gl.bindBuffer(this.gl.ARRAY_BUFFER, this.positionBuffer);
        this.gl.vertexAttribPointer(this.positionAttribute, 2, this.gl.FLOAT, false, 16, 0);
        this.gl.vertexAttribPointer(this.uvAttribute, 2, this.gl.FLOAT, false, 16, 8);
        
        this.gl.drawArrays(this.gl.TRIANGLES, 0, 6);
    }
    
    initCSSFallback() {
        console.log('Using CSS fallback for GradientBlinds');
        
        const fallback = document.createElement('div');
        fallback.style.position = 'fixed';
        fallback.style.top = '0';
        fallback.style.left = '0';
        fallback.style.width = '100%';
        fallback.style.height = '100%';
        fallback.style.zIndex = '-1';
        fallback.style.background = `linear-gradient(45deg, ${this.options.gradientColors.join(', ')})`;
        fallback.style.backgroundSize = '400% 400%';
        fallback.style.animation = 'gradientAnimation 6s ease infinite';
        fallback.style.mixBlendMode = this.options.mixBlendMode;
        
        this.container.appendChild(fallback);
        
        const style = document.createElement('style');
        style.textContent = `
            @keyframes gradientAnimation {
                0% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }
        `;
        document.head.appendChild(style);
    }
    
    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
    }
}