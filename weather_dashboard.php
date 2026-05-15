<?php
require_once 'config.php';
require_once 'weather_config.php';
requireAuth();

$user = getCurrentUser();

// Handle city search
$current_city = DEFAULT_CITY;
if (isset($_GET['city']) && !empty($_GET['city'])) {
    $current_city = $_GET['city'];
}

// Get weather data
$weather_data = getWeatherData($current_city);

// Handle API errors
if (isset($weather_data['error'])) {
    $error_message = $weather_data['message'];
    $weather_data = [];
} else {
    $error_message = null;
    $weather_alert = getWeatherAlert($weather_data['current']);
    $traffic_impact = getTrafficImpact($weather_data['current']);
}

// Process forecast data
$weather_forecast = [];
if (isset($weather_data['forecast']) && is_array($weather_data['forecast'])) {
    foreach ($weather_data['forecast'] as $forecast) {
        $weather_forecast[] = [
            'datetime' => $forecast['dt_txt'],
            'day' => date('D', $forecast['dt']),
            'condition' => $forecast['weather'][0]['main'],
            'icon' => $forecast['weather'][0]['icon'],
            'temp_high' => round($forecast['main']['temp_max']),
            'temp_low' => round($forecast['main']['temp_min']),
            'description' => $forecast['weather'][0]['description']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather Dashboard - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Background Container */
        body {
            background: #0a0a0a;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            color: #e0e0ff;
        }

        .weather-background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        .weather-content {
            position: relative;
            z-index: 10;
        }

        /* Enhanced Card Styles with Dark Theme */
        .weather-card {
            background: rgba(15, 20, 30, 0.9) !important;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(100, 100, 255, 0.3) !important;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 20px rgba(100, 100, 255, 0.2) !important;
            border-radius: 15px !important;
            color: #e0e0ff !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .weather-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 0 30px rgba(100, 100, 255, 0.3) !important;
        }

        .card-header {
            background: rgba(102, 126, 234, 0.3) !important;
            border-bottom: 1px solid rgba(100, 100, 255, 0.3) !important;
            color: #ffffff !important;
        }

        .card-body {
            color: #e0e0ff !important;
        }

        /* Alert Styles with Enhanced Visibility */
        .alert {
            background: rgba(15, 20, 30, 0.95) !important;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(100, 100, 255, 0.3) !important;
            color: #e0e0ff !important;
        }

        .alert-danger {
            border-left: 5px solid #dc3545 !important;
            background: rgba(220, 53, 69, 0.2) !important;
        }

        .weather-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #667eea;
            text-shadow: 0 0 20px rgba(102, 126, 234, 0.6);
        }
        
        .temperature {
            font-size: 2.5rem;
            font-weight: bold;
            color: #ffffff;
            text-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }

        .weather-alert-high {
            border-left: 5px solid #dc3545;
            background: rgba(220, 53, 69, 0.3) !important;
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .weather-alert-medium {
            border-left: 5px solid #ffc107;
            background: rgba(255, 193, 7, 0.3) !important;
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .weather-alert-low {
            border-left: 5px solid #28a745;
            background: rgba(40, 167, 69, 0.3) !important;
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .impact-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .forecast-day {
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            background: rgba(25, 30, 50, 0.7);
            border: 1px solid rgba(100, 100, 255, 0.2);
            transition: all 0.3s ease;
            color: #e0e0ff;
        }
        
        .forecast-day:hover {
            background: rgba(30, 35, 60, 0.8);
            border-color: rgba(100, 100, 255, 0.4);
            transform: translateY(-3px);
        }
        
        .weather-bg {
            background: rgba(102, 126, 234, 0.3) !important;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(100, 100, 255, 0.3) !important;
            color: white !important;
        }
        
        .search-box {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(100, 100, 255, 0.3);
        }

        .search-box input {
            background: rgba(255, 255, 255, 0.1) !important;
            border: none !important;
            color: white !important;
        }

        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.6) !important;
        }

        .search-box .btn-light {
            background: rgba(102, 126, 234, 0.5) !important;
            border: none !important;
            color: white !important;
        }

        .search-box .btn-light:hover {
            background: rgba(102, 126, 234, 0.7) !important;
        }
        
        .api-status {
            font-size: 0.8rem;
        }

        /* Enhanced Badge Styles */
        .badge {
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
        }

        /* Statistics Cards */
        .bg-success, .bg-primary, .bg-warning, .bg-info {
            background: rgba(102, 126, 234, 0.4) !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(100, 100, 255, 0.3) !important;
            color: white !important;
        }

        /* List Group Items */
        .list-group-item {
            background: rgba(25, 30, 50, 0.7) !important;
            border: 1px solid rgba(100, 100, 255, 0.2) !important;
            color: #e0e0ff !important;
        }

        /* Text Colors */
        h1, h2, h3, h4, h5, h6 {
            color: #ffffff !important;
            text-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }

        .text-muted {
            color: rgba(200, 200, 255, 0.7) !important;
        }

        small {
            color: rgba(200, 200, 255, 0.8) !important;
        }

        /* Border Bottom */
        .border-bottom {
            border-bottom: 1px solid rgba(100, 100, 255, 0.3) !important;
        }

        /* Links */
        a {
            color: #a0a0ff !important;
        }

        a:hover {
            color: #667eea !important;
        }
    </style>
</head>
<body>
    <!-- Dynamic Background Container -->
    <div class="weather-background-container" id="weatherBackground"></div>

    <div class="weather-content">
        <?php include 'navbar.php'; ?>
        
        <div class="container-fluid mt-4">
            <div class="row">
                <main class="col-md-12 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">
                            <i class="fas fa-cloud-sun me-2"></i>Weather Dashboard
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <span class="badge bg-info fs-6">
                                <i class="fas fa-sync-alt me-1"></i>Live Data
                            </span>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Weather Data Unavailable</h5>
                        <?php echo $error_message; ?>
                        <hr>
                        <small class="mb-0">
                            To enable live weather data:
                            <ol class="mb-0 mt-2">
                                <li>Get a free API key from <a href="https://openweathermap.org/api" target="_blank">OpenWeatherMap</a></li>
                                <li>Replace 'your_openweather_api_key_here' in weather_config.php with your actual API key</li>
                            </ol>
                        </small>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- City Search -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card weather-bg">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h4 class="mb-0">Current Weather</h4>
                                            <p class="mb-0">
                                                Real-time weather conditions and traffic impact
                                                <?php if (!isset($error_message)): ?>
                                                <span class="api-status badge bg-light text-dark ms-2">
                                                    <i class="fas fa-check-circle text-success me-1"></i>API Connected
                                                </span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <form method="GET" class="search-box">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="city" 
                                                           placeholder="Enter city name..." 
                                                           value="<?php echo htmlspecialchars($current_city); ?>"
                                                           required>
                                                    <button type="submit" class="btn btn-light">
                                                        <i class="fas fa-search"></i> Search
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!isset($error_message)): ?>
                    <!-- Weather Alert -->
                    <?php if ($weather_alert['level'] !== 'low'): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card weather-alert-<?php echo $weather_alert['level']; ?>">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-1 text-center">
                                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                                        </div>
                                        <div class="col-md-9">
                                            <h5 class="card-title mb-1">Weather Alert</h5>
                                            <p class="card-text mb-0"><?php echo $weather_alert['message']; ?></p>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <span class="badge bg-<?php echo $weather_alert['level'] === 'high' ? 'danger' : ($weather_alert['level'] === 'medium' ? 'warning' : 'success'); ?> impact-badge">
                                                <?php echo ucfirst($weather_alert['level']); ?> Alert
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Current Weather & Impact -->
                    <div class="row mb-4">
                        <!-- Current Weather -->
                        <div class="col-md-6 mb-4">
                            <div class="card weather-card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-location-dot me-2"></i>
                                        <?php echo htmlspecialchars($weather_data['city']); ?>
                                    </h5>
                                </div>
                                <div class="card-body text-center">
                                    <div class="weather-icon">
                                        <i class="fas fa-<?php echo getWeatherIcon($weather_data['current']['weather'][0]['icon']); ?>"></i>
                                    </div>
                                    <div class="temperature">
                                        <?php echo round($weather_data['current']['main']['temp']); ?>°C
                                    </div>
                                    <h4 class="mb-3"><?php echo $weather_data['current']['weather'][0]['main']; ?></h4>
                                    <p class="text-muted"><?php echo ucfirst($weather_data['current']['weather'][0]['description']); ?></p>
                                    
                                    <div class="row mt-4">
                                        <div class="col-4">
                                            <i class="fas fa-temperature-low me-1"></i>
                                            <small>Feels like</small>
                                            <div><strong><?php echo round($weather_data['current']['main']['feels_like']); ?>°C</strong></div>
                                        </div>
                                        <div class="col-4">
                                            <i class="fas fa-tint me-1"></i>
                                            <small>Humidity</small>
                                            <div><strong><?php echo $weather_data['current']['main']['humidity']; ?>%</strong></div>
                                        </div>
                                        <div class="col-4">
                                            <i class="fas fa-wind me-1"></i>
                                            <small>Wind</small>
                                            <div><strong><?php echo $weather_data['current']['wind']['speed']; ?> m/s</strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Traffic Impact -->
                        <div class="col-md-6 mb-4">
                            <div class="card weather-card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-car-side me-2"></i>Traffic Impact Analysis
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <span class="badge bg-<?php echo $traffic_impact['color']; ?> impact-badge fs-6">
                                            <?php echo $traffic_impact['level']; ?> Impact
                                        </span>
                                    </div>
                                    
                                    <p class="card-text"><?php echo $traffic_impact['message']; ?></p>
                                    
                                    <div class="mt-4">
                                        <h6>Recommended Actions:</h6>
                                        <ul class="list-group list-group-flush">
                                            <?php echo getRecommendedActions($traffic_impact['level']); ?>
                                        </ul>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Last updated: <?php echo date('M j, g:i A', $weather_data['timestamp']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Weather Forecast -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow weather-card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-alt me-2"></i>5-Day Weather Forecast
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <?php foreach (array_slice($weather_forecast, 0, 5) as $day): ?>
                                            <div class="col">
                                                <div class="forecast-day">
                                                    <h6><?php echo $day['day']; ?></h6>
                                                    <div class="mb-2">
                                                        <i class="fas fa-<?php echo getWeatherIcon($day['icon']); ?> fa-2x" style="color: #667eea;"></i>
                                                    </div>
                                                    <div class="fw-bold"><?php echo $day['temp_high']; ?>°</div>
                                                    <div class="text-muted small"><?php echo $day['temp_low']; ?>°</div>
                                                    <small class="text-muted"><?php echo ucfirst($day['description']); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Weather Statistics -->
                    <div class="row mb-5">
                        <div class="col-md-4 mb-4">
                            <div class="card text-white bg-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-eye fa-2x mb-2"></i>
                                    <h4><?php echo number_format(($weather_data['current']['visibility'] ?? 10000) / 1000, 1); ?> km</h4>
                                    <p class="mb-0">Visibility</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card text-white bg-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-gauge-high fa-2x mb-2"></i>
                                    <h4><?php echo $weather_data['current']['main']['pressure']; ?> hPa</h4>
                                    <p class="mb-0">Pressure</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card text-white bg-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-percent fa-2x mb-2"></i>
                                    <h4><?php echo $weather_data['current']['main']['humidity']; ?>%</h4>
                                    <p class="mb-0">Humidity</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    </div>

    <!-- Enhanced DarkVeil Background Script -->
    <script>
        class EnhancedDarkVeil {
            constructor(container, options = {}) {
                this.container = container;
                this.options = {
                    speed: options.speed || 0.8,
                    particleCount: options.particleCount || 80,
                    connectionDistance: options.connectionDistance || 150,
                    colors: options.colors || ['#667eea', '#764ba2', '#f093fb', '#4facfe'],
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
                this.canvas.style.position = 'fixed';
                this.canvas.style.top = '0';
                this.canvas.style.left = '0';
                this.canvas.style.width = '100%';
                this.canvas.style.height = '100%';
                this.canvas.style.zIndex = '-1';
                this.canvas.style.pointerEvents = 'none';
                this.canvas.style.background = 'linear-gradient(135deg, #289045 0%, #beddc7 50%, #286291 100%)';
                
                this.container.appendChild(this.canvas);
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
                        z: Math.random() * 100,
                        vx: (Math.random() - 0.5) * 1.5,
                        vy: (Math.random() - 0.5) * 1.5,
                        vz: (Math.random() - 0.5) * 0.5,
                        radius: Math.random() * 2.5 + 1,
                        color: this.options.colors[Math.floor(Math.random() * this.options.colors.length)],
                        opacity: Math.random() * 0.6 + 0.3,
                        trail: []
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
                
                this.ctx.fillStyle = 'rgba(12, 12, 12, 0.08)';
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                
                this.particles.forEach(particle => {
                    particle.trail.push({x: particle.x, y: particle.y});
                    if (particle.trail.length > 8) {
                        particle.trail.shift();
                    }
                    
                    particle.x += particle.vx * this.options.speed;
                    particle.y += particle.vy * this.options.speed;
                    particle.z += particle.vz * this.options.speed;
                    
                    if (particle.x < 0 || particle.x > this.canvas.width) particle.vx *= -1;
                    if (particle.y < 0 || particle.y > this.canvas.height) particle.vy *= -1;
                    if (particle.z < 0 || particle.z > 100) particle.vz *= -1;
                    
                    particle.x = Math.max(0, Math.min(this.canvas.width, particle.x));
                    particle.y = Math.max(0, Math.min(this.canvas.height, particle.y));
                    particle.z = Math.max(0, Math.min(100, particle.z));
                    
                    const dx = this.mouse.x - particle.x;
                    const dy = this.mouse.y - particle.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < 150) {
                        const angle = Math.atan2(dy, dx);
                        const force = (150 - distance) / 150;
                        particle.vx -= Math.cos(angle) * force * 0.2;
                        particle.vy -= Math.sin(angle) * force * 0.2;
                    }
                    
                    if (particle.trail.length > 1) {
                        this.ctx.beginPath();
                        this.ctx.moveTo(particle.trail[0].x, particle.trail[0].y);
                        
                        for (let i = 1; i < particle.trail.length; i++) {
                            this.ctx.lineTo(particle.trail[i].x, particle.trail[i].y);
                        }
                        
                        const gradient = this.ctx.createLinearGradient(
                            particle.trail[0].x, particle.trail[0].y,
                            particle.trail[particle.trail.length-1].x, particle.trail[particle.trail.length-1].y
                        );
                        
                        const depthFactor = particle.z / 100;
                        const trailOpacity = 0.4 * (1 - depthFactor * 0.5);
                        
                        gradient.addColorStop(0, particle.color.replace(')', `, ${trailOpacity})`).replace('rgb', 'rgba'));
                        gradient.addColorStop(1, particle.color.replace(')', `, 0)`).replace('rgb', 'rgba'));
                        
                        this.ctx.strokeStyle = gradient;
                        this.ctx.lineWidth = particle.radius * 0.7;
                        this.ctx.stroke();
                    }
                    
                    const sizeFactor = 1 + (particle.z / 100) * 0.5;
                    this.ctx.beginPath();
                    this.ctx.arc(particle.x, particle.y, particle.radius * sizeFactor, 0, Math.PI * 2);
                    
                    const depthOpacity = particle.opacity * (1 - (particle.z / 100) * 0.3);
                    this.ctx.fillStyle = particle.color.replace(')', `, ${depthOpacity})`).replace('rgb', 'rgba');
                    this.ctx.fill();
                });
                
                this.ctx.lineWidth = 1;
                
                for (let i = 0; i < this.particles.length; i++) {
                    for (let j = i + 1; j < this.particles.length; j++) {
                        const dx = this.particles[i].x - this.particles[j].x;
                        const dy = this.particles[i].y - this.particles[j].y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        
                        if (distance < this.options.connectionDistance) {
                            const avgZ = (this.particles[i].z + this.particles[j].z) / 2;
                            const depthOpacity = 0.4 * (1 - (avgZ / 100) * 0.5);
                            
                            const opacity = (1 - (distance / this.options.connectionDistance)) * depthOpacity;
                            this.ctx.globalAlpha = opacity;
                            
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
                
                this.ctx.globalAlpha = 1;
                
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
            const bgContainer = document.getElementById('weatherBackground');
            new EnhancedDarkVeil(bgContainer, {
                speed: 1.2,
                particleCount: 80,
                connectionDistance: 150,
                colors: ['#667eea', '#764ba2', '#f093fb', '#4facfe']
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh weather data every 10 minutes
        setInterval(function() {
            window.location.reload();
        }, 600000);
    </script>
</body>
</html>

<?php
// Helper function to get recommended actions
function getRecommendedActions($impact) {
    $actions = [
        'Low' => [
            '<li class="list-group-item"><i class="fas fa-check text-success me-2"></i>Normal driving conditions</li>',
            '<li class="list-group-item"><i class="fas fa-check text-success me-2"></i>Standard travel times expected</li>',
            '<li class="list-group-item"><i class="fas fa-check text-success me-2"></i>No special precautions needed</li>'
        ],
        'Medium' => [
            '<li class="list-group-item"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Increase following distance</li>',
            '<li class="list-group-item"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Reduce speed by 10-20%</li>',
            '<li class="list-group-item"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Allow extra travel time</li>'
        ],
        'High' => [
            '<li class="list-group-item"><i class="fas fa-exclamation-circle text-danger me-2"></i>Significantly reduce speed</li>',
            '<li class="list-group-item"><i class="fas fa-exclamation-circle text-danger me-2"></i>Avoid non-essential travel</li>',
            '<li class="list-group-item"><i class="fas fa-exclamation-circle text-danger me-2"></i>Use headlights and hazard lights if needed</li>'
        ],
        'Severe' => [
            '<li class="list-group-item"><i class="fas fa-ban text-dark me-2"></i>Avoid all travel if possible</li>',
            '<li class="list-group-item"><i class="fas fa-ban text-dark me-2"></i>Follow emergency service advisories</li>',
            '<li class="list-group-item"><i class="fas fa-ban text-dark me-2"></i>Seek shelter if outdoors</li>'
        ]
    ];
    
    return implode('', $actions[$impact] ?? $actions['Low']);
}
?>