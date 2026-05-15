<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Traffic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .welcome-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        
        .welcome-card {
            background: rgba(15, 20, 30, 0.85) !important;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(100, 100, 255, 0.3) !important;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 30px rgba(100, 100, 255, 0.2) !important;
            border-radius: 16px !important;
            color: #e0e0ff !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .welcome-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 40px rgba(100, 100, 255, 0.3) !important;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #667eea;
            text-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }
        
        .system-title {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
            margin-bottom: 1rem;
        }
        
        .system-subtitle {
            font-size: 1.2rem;
            color: rgba(200, 200, 255, 0.8);
            margin-bottom: 2rem;
        }
        
        .action-buttons .btn {
            margin: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .feature-card {
            background: rgba(25, 30, 50, 0.7);
            border-radius: 12px;
            padding: 1.5rem;
            height: 100%;
            border: 1px solid rgba(100, 100, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            background: rgba(30, 35, 60, 0.8);
            transform: translateY(-3px);
            border-color: rgba(100, 100, 255, 0.4);
        }
        
        .feature-card h5 {
            color: #a0a0ff;
            margin-bottom: 1rem;
        }
        
        .feature-card p {
            color: rgba(200, 200, 255, 0.8);
        }
        
        .stats-container {
            background: rgba(20, 25, 40, 0.7);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(100, 100, 255, 0.2);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: rgba(200, 200, 255, 0.8);
            font-size: 0.9rem;
        }
        
        .glow-text {
            text-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="welcome-container">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <!-- Main Welcome Card -->
                            <div class="card welcome-card shadow-lg mb-5">
                                <div class="card-body p-5 text-center">
                                    <h1 class="system-title">
                                        <i class="fas fa-traffic-light me-3"></i>Traffic Management System
                                    </h1>
                                    <p class="system-subtitle">
                                        Advanced traffic monitoring and management solution for modern cities
                                    </p>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-4 mb-4">
                                            <div class="feature-card">
                                                <div class="feature-icon">
                                                    <i class="fas fa-chart-line"></i>
                                                </div>
                                                <h5>Real-time Analytics</h5>
                                                <p>Monitor traffic flow, congestion patterns, and incident reports in real-time with advanced analytics.</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-4">
                                            <div class="feature-card">
                                                <div class="feature-icon">
                                                    <i class="fas fa-camera"></i>
                                                </div>
                                                <h5>Smart Monitoring</h5>
                                                <p>AI-powered camera systems for automatic incident detection and traffic violation monitoring.</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-4">
                                            <div class="feature-card">
                                                <div class="feature-icon">
                                                    <i class="fas fa-road"></i>
                                                </div>
                                                <h5>Route Optimization</h5>
                                                <p>Dynamic route suggestions to reduce congestion and improve traffic flow across the city.</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="action-buttons mt-4">
                                        <a href="login.php" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                        </a>
                                        <a href="create_account.php" class="btn btn-outline-primary">
                                            <i class="fas fa-user-plus me-2"></i>Create Account
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stats Section -->
                            <div class="row mb-5">
                                <div class="col-md-3 col-6 mb-4">
                                    <div class="stats-container text-center">
                                        <div class="stat-number">24/7</div>
                                        <div class="stat-label">Monitoring</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-4">
                                    <div class="stats-container text-center">
                                        <div class="stat-number">500+</div>
                                        <div class="stat-label">Cameras</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-4">
                                    <div class="stats-container text-center">
                                        <div class="stat-number">15%</div>
                                        <div class="stat-label">Traffic Reduced</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-4">
                                    <div class="stats-container text-center">
                                        <div class="stat-number">99.9%</div>
                                        <div class="stat-label">Uptime</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Additional Info -->
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="feature-card">
                                        <h5 class="glow-text"><i class="fas fa-shield-alt me-2"></i>Secure & Reliable</h5>
                                        <p>Our system employs military-grade encryption and redundancy measures to ensure data security and system reliability.</p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="feature-card">
                                        <h5 class="glow-text"><i class="fas fa-mobile-alt me-2"></i>Mobile Ready</h5>
                                        <p>Access traffic information and system controls from any device with our responsive mobile interface. But still under development.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced DarkVeil Background -->
    <script>
        class EnhancedDarkVeil {
            constructor(container, options = {}) {
                this.container = container;
                this.options = {
                    speed: options.speed || 0.8, // Increased speed
                    particleCount: options.particleCount || 80,
                    connectionDistance: options.connectionDistance || 150,
                    colors: options.colors || ['#ADFF2F', '#008000', '#008B8B', '#008080'],
                    ...options
                };
                
                this.canvas = null;
                this.ctx = null;
                this.particles = [];
                this.animationId = null;
                this.mouse = { x: 0, y: 0 };
                
                this.init();
            }
            
            init() {
                this.createCanvas();
                this.createParticles();
                this.startAnimation();
                this.setupEventListeners();
            }
            
            createCanvas() {
                this.canvas = document.createElement('canvas');
                this.canvas.className = 'darkveil-canvas';
                this.canvas.style.position = 'fixed';
                this.canvas.style.top = '0';
                this.canvas.style.left = '0';
                this.canvas.style.width = '100%';
                this.canvas.style.height = '100%';
                this.canvas.style.zIndex = '-1';
                this.canvas.style.pointerEvents = 'none';
                this.canvas.style.background = 'linear-gradient(135deg, #289045 0%, #beddc7 50%, #286291 100%)';
                
                document.body.insertBefore(this.canvas, document.body.firstChild);
                this.resize();
            }
            
            resize() {
                if (!this.canvas) return;
                
                this.canvas.width = window.innerWidth;
                this.canvas.height = window.innerHeight;
            }
            
            createParticles() {
                this.particles = [];
                for (let i = 0; i < this.options.particleCount; i++) {
                    this.particles.push({
                        x: Math.random() * this.canvas.width,
                        y: Math.random() * this.canvas.height,
                        z: Math.random() * 100, // Added z-axis for 3D effect
                        vx: (Math.random() - 0.5) * 1.5, // Increased velocity
                        vy: (Math.random() - 0.5) * 1.5,
                        vz: (Math.random() - 0.5) * 0.5,
                        radius: Math.random() * 2.5 + 1,
                        color: this.options.colors[Math.floor(Math.random() * this.options.colors.length)],
                        opacity: Math.random() * 0.6 + 0.3, // Increased opacity
                        trail: [] // Store previous positions for trails
                    });
                }
            }
            
            setupEventListeners() {
                window.addEventListener('resize', () => {
                    this.resize();
                    this.createParticles();
                });
                
                window.addEventListener('mousemove', (e) => {
                    this.mouse.x = e.clientX;
                    this.mouse.y = e.clientY;
                });
            }
            
            startAnimation() {
                const animate = () => {
                    this.render();
                    this.animationId = requestAnimationFrame(animate);
                };
                animate();
            }
            
            render() {
                if (!this.ctx) {
                    this.ctx = this.canvas.getContext('2d');
                }
                
                // Clear with slight fade for trail effect
                this.ctx.fillStyle = 'rgba(12, 12, 12, 0.08)'; // Increased fade for more visible trails
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                
                // Update and draw particles
                this.particles.forEach(particle => {
                    // Store current position for trail
                    particle.trail.push({x: particle.x, y: particle.y});
                    if (particle.trail.length > 8) { // Increased trail length
                        particle.trail.shift();
                    }
                    
                    // Update position with 3D effect
                    particle.x += particle.vx * this.options.speed;
                    particle.y += particle.vy * this.options.speed;
                    particle.z += particle.vz * this.options.speed;
                    
                    // Bounce off walls
                    if (particle.x < 0 || particle.x > this.canvas.width) particle.vx *= -1;
                    if (particle.y < 0 || particle.y > this.canvas.height) particle.vy *= -1;
                    if (particle.z < 0 || particle.z > 100) particle.vz *= -1;
                    
                    // Keep within bounds
                    particle.x = Math.max(0, Math.min(this.canvas.width, particle.x));
                    particle.y = Math.max(0, Math.min(this.canvas.height, particle.y));
                    particle.z = Math.max(0, Math.min(100, particle.z));
                    
                    // Mouse interaction with stronger effect
                    const dx = this.mouse.x - particle.x;
                    const dy = this.mouse.y - particle.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < 150) { // Increased interaction radius
                        const angle = Math.atan2(dy, dx);
                        const force = (150 - distance) / 150;
                        particle.vx -= Math.cos(angle) * force * 0.2; // Stronger force
                        particle.vy -= Math.sin(angle) * force * 0.2;
                    }
                    
                    // Draw particle trail
                    if (particle.trail.length > 1) {
                        this.ctx.beginPath();
                        this.ctx.moveTo(particle.trail[0].x, particle.trail[0].y);
                        
                        for (let i = 1; i < particle.trail.length; i++) {
                            this.ctx.lineTo(particle.trail[i].x, particle.trail[i].y);
                        }
                        
                        // Gradient trail based on z-axis (3D effect)
                        const gradient = this.ctx.createLinearGradient(
                            particle.trail[0].x, particle.trail[0].y,
                            particle.trail[particle.trail.length-1].x, particle.trail[particle.trail.length-1].y
                        );
                        
                        // Adjust trail opacity based on z-position (3D depth)
                        const depthFactor = particle.z / 100;
                        const trailOpacity = 0.4 * (1 - depthFactor * 0.5); // Trails fade with depth
                        
                        gradient.addColorStop(0, particle.color.replace(')', `, ${trailOpacity})`).replace('rgb', 'rgba'));
                        gradient.addColorStop(1, particle.color.replace(')', `, 0)`).replace('rgb', 'rgba'));
                        
                        this.ctx.strokeStyle = gradient;
                        this.ctx.lineWidth = particle.radius * 0.7;
                        this.ctx.stroke();
                    }
                    
                    // Draw particle with 3D size effect
                    const sizeFactor = 1 + (particle.z / 100) * 0.5; // Particles appear larger when closer
                    this.ctx.beginPath();
                    this.ctx.arc(particle.x, particle.y, particle.radius * sizeFactor, 0, Math.PI * 2);
                    
                    // Adjust color based on z-position (3D depth)
                    const depthOpacity = particle.opacity * (1 - (particle.z / 100) * 0.3); // Particles fade with depth
                    this.ctx.fillStyle = particle.color.replace(')', `, ${depthOpacity})`).replace('rgb', 'rgba');
                    this.ctx.fill();
                });
                
                // Draw connections between nearby particles with 3D effect
                this.ctx.lineWidth = 1;
                
                for (let i = 0; i < this.particles.length; i++) {
                    for (let j = i + 1; j < this.particles.length; j++) {
                        const dx = this.particles[i].x - this.particles[j].x;
                        const dy = this.particles[i].y - this.particles[j].y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        
                        if (distance < this.options.connectionDistance) {
                            // Calculate average z-position for connection opacity
                            const avgZ = (this.particles[i].z + this.particles[j].z) / 2;
                            const depthOpacity = 0.4 * (1 - (avgZ / 100) * 0.5); // Connections fade with depth
                            
                            const opacity = (1 - (distance / this.options.connectionDistance)) * depthOpacity;
                            this.ctx.globalAlpha = opacity;
                            
                            // Create gradient for connection line
                            const gradient = this.ctx.createLinearGradient(
                                this.particles[i].x, this.particles[i].y,
                                this.particles[j].x, this.particles[j].y
                            );
                            
                            gradient.addColorStop(0, this.particles[i].color.replace(')', `, ${opacity})`).replace('rgb', 'rgba'));
                            gradient.addColorStop(1, this.particles[j].color.replace(')', `, ${opacity})`).replace('rgb', 'rgba'));
                            
                            this.ctx.strokeStyle = gradient;
                            this.ctx.beginPath();
                            this.ctx.moveTo(this.particles[i].x, this.particles[i].y);
                            this.ctx.lineTo(this.particles[j].x, this.particles[j].y);
                            this.ctx.stroke();
                        }
                    }
                }
                
                // Reset global alpha
                this.ctx.globalAlpha = 1;
                
                // Add subtle gradient overlay for enhanced 3D atmosphere
                const gradient = this.ctx.createRadialGradient(
                    this.canvas.width / 2,
                    this.canvas.height / 2,
                    0,
                    this.canvas.width / 2,
                    this.canvas.height / 2,
                    Math.max(this.canvas.width, this.canvas.height) / 2
                );
                gradient.addColorStop(0, 'rgba(102, 126, 234, 0.15)');
                gradient.addColorStop(0.7, 'rgba(118, 75, 162, 0.08)');
                gradient.addColorStop(1, 'rgba(15, 15, 30, 0.1)');
                
                this.ctx.globalCompositeOperation = 'overlay';
                this.ctx.fillStyle = gradient;
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.globalCompositeOperation = 'source-over';
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

        // Initialize the enhanced background
        document.addEventListener('DOMContentLoaded', function() {
            new EnhancedDarkVeil(document.body, {
                speed: 1.2,
                particleCount: 80,
                connectionDistance: 150,
                colors: ['#667eea', '#764ba2', '#f093fb', '#4facfe']
            });
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>