<?php
require_once 'config.php';
require_once 'maptiler_config.php';
requireAuth();
$user = getCurrentUser();
$db = getDBConnection();

// Handle route planning form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_route'])) {
    $start_point = trim($_POST['start_point']);
    $end_point = trim($_POST['end_point']);
    $preferred_route = $_POST['preferred_route'];
    $avoid_tolls = isset($_POST['avoid_tolls']) ? 1 : 0;
    $avoid_highways = isset($_POST['avoid_highways']) ? 1 : 0;
    
    $stmt = $db->prepare("INSERT INTO route_planning (user_id, start_point, end_point, preferred_route, avoid_tolls, avoid_highways) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssii", $user['user_id'], $start_point, $end_point, $preferred_route, $avoid_tolls, $avoid_highways);
    
    if ($stmt->execute()) {
        $route_id = $stmt->insert_id;
        $success = "Route planned successfully!";
        
        // Log the action
        $log_stmt = $db->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'plan_route', ?, ?)");
        $desc = "Planned route from $start_point to $end_point";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("iss", $user['user_id'], $desc, $ip);
        $log_stmt->execute();
    } else {
        $error = "Error planning route";
    }
}

// Get user's saved routes
$routes_query = $db->prepare("
    SELECT * FROM route_planning 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$routes_query->bind_param("i", $user['user_id']);
$routes_query->execute();
$saved_routes = $routes_query->get_result();

// Get active incidents for route planning
$incidents = $db->query("
    SELECT utr.*, r.road_name, utr.latitude, utr.longitude, utr.place_name
    FROM user_traffic_reports utr 
    JOIN road_segments rs ON utr.segment_id = rs.segment_id 
    JOIN roads r ON rs.road_id = r.road_id 
    WHERE utr.status != 'resolved' 
    AND utr.report_time >= NOW() - INTERVAL 24 HOUR
    AND utr.latitude IS NOT NULL 
    AND utr.longitude IS NOT NULL
");

// Get traffic predictions
$predictions = $db->query("
    SELECT tp.*, rs.segment_name, r.road_name
    FROM traffic_predictions tp
    JOIN road_segments rs ON tp.segment_id = rs.segment_id
    JOIN roads r ON rs.road_id = r.road_id
    WHERE tp.prediction_time >= NOW()
    AND tp.confidence_level > 0.7
    ORDER BY tp.predicted_congestion DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Planner - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="darkveil.css">
    <style>
        #map {
            height: 600px;
            width: 100%;
            border-radius: 10px;
            border: 2px solid rgba(100, 100, 255, 0.3);
        }
        .route-info-box {
            background: rgba(25, 30, 50, 0.95);
            border: 2px solid rgba(102, 126, 234, 0.5);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .eta-display {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .traffic-legend {
            background: rgba(25, 30, 50, 0.9);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(100, 100, 255, 0.3);
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .traffic-legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .traffic-color-box {
            width: 30px;
            height: 4px;
            margin-right: 10px;
            border-radius: 2px;
        }
        .location-input {
            position: relative;
        }
        .location-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(25, 30, 50, 0.98);
            border: 1px solid rgba(100, 100, 255, 0.3);
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .location-suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid rgba(100, 100, 255, 0.1);
            color: #e0e0ff;
        }
        .location-suggestion-item:hover {
            background: rgba(102, 126, 234, 0.2);
        }
        .route-option {
            border: 2px solid rgba(100, 100, 255, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .route-option:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        .route-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.2);
        }
        .traffic-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            border-radius: 10px;
        }
        .incident-marker {
            border: 2px solid white;
            border-radius: 50%;
        }
        .route-line {
            stroke-dasharray: 10, 10;
            animation: dash 1s linear infinite;
        }
        @keyframes dash {
            to {
                stroke-dashoffset: -20;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container-fluid mt-4">
                <h2 class="text-light mb-4">
                    <i class="fas fa-route me-2"></i>Live Route Planner
                    <span class="badge bg-success ms-2">Real Road Network</span>
                </h2>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Route Planning Form -->
                    <div class="col-lg-4 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light">
                                    <i class="fas fa-map-marked-alt me-2"></i>Plan Your Route
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="routeForm">
                                    <div class="mb-3 location-input">
                                        <label class="form-label">
                                            <i class="fas fa-map-marker-alt text-success me-2"></i>Starting Point *
                                        </label>
                                        <input type="text" class="form-control" id="startPoint" required 
                                               placeholder="Enter starting location">
                                        <div class="location-suggestions" id="startSuggestions"></div>
                                        <input type="hidden" id="startLat">
                                        <input type="hidden" id="startLng">
                                    </div>
                                    
                                    <div class="mb-3 location-input">
                                        <label class="form-label">
                                            <i class="fas fa-flag-checkered text-danger me-2"></i>Destination *
                                        </label>
                                        <input type="text" class="form-control" id="endPoint" required 
                                               placeholder="Enter destination">
                                        <div class="location-suggestions" id="endSuggestions"></div>
                                        <input type="hidden" id="endLat">
                                        <input type="hidden" id="endLng">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Route Preferences</label>
                                        <select class="form-select" id="preferredRoute">
                                            <option value="driving">Fastest Route</option>
                                            <option value="driving-traffic">Avoid Traffic</option>
                                            <option value="walking">Walking</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="avoidTolls">
                                            <label class="form-check-label" for="avoidTolls">
                                                Avoid Toll Roads
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="avoidHighways">
                                            <label class="form-check-label" for="avoidHighways">
                                                Avoid Highways
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="avoidIncidents" checked>
                                            <label class="form-check-label" for="avoidIncidents">
                                                Avoid Traffic Incidents
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100 mb-2">
                                        <i class="fas fa-search me-2"></i>Find Route
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary w-100" id="clearRoute">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Route Options -->
                        <div class="card cyber-card mt-3" id="routeOptionsCard" style="display: none;">
                            <div class="card-header">
                                <h6 class="mb-0 text-light">
                                    <i class="fas fa-road me-2"></i>Route Details
                                </h6>
                            </div>
                            <div class="card-body" id="routeOptions">
                                <!-- Route details will be populated here -->
                            </div>
                        </div>

                        <!-- Active Incidents -->
                        <div class="card cyber-card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0 text-light">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Active Incidents
                                </h6>
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php if ($incidents->num_rows > 0): ?>
                                    <?php while ($incident = $incidents->fetch_assoc()): ?>
                                        <div class="border-start border-3 border-<?php echo getSeverityColor($incident['severity']); ?> ps-3 mb-3">
                                            <strong class="text-light"><?php echo ucfirst($incident['report_type']); ?></strong>
                                            <div class="text-muted small"><?php echo htmlspecialchars($incident['place_name']); ?></div>
                                            <small class="text-<?php echo getSeverityColor($incident['severity']); ?>">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                <?php echo ucfirst($incident['severity']); ?> severity
                                            </small>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-2 mb-0">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        No active incidents
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Map and Route Info -->
                    <div class="col-lg-8 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-light">
                                    <i class="fas fa-map me-2"></i>Bangladesh Road Network
                                </h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" id="centerMap">
                                        <i class="fas fa-crosshairs"></i> Center
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" id="refreshData">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0 position-relative">
                                <div id="mapLoading" class="loading-overlay" style="display: none;">
                                    <div class="text-center">
                                        <div class="spinner-border text-primary mb-3"></div>
                                        <p class="text-light">Calculating optimal route...</p>
                                    </div>
                                </div>
                                <div id="map"></div>
                                
                                <!-- Traffic Legend -->
                                <div class="traffic-legend">
                                    <div class="text-light mb-2"><strong>Incident Types</strong></div>
                                    <div class="traffic-legend-item">
                                        <div class="traffic-color-box" style="background: #ef4444;"></div>
                                        <small class="text-light">Accident</small>
                                    </div>
                                    <div class="traffic-legend-item">
                                        <div class="traffic-color-box" style="background: #f59e0b;"></div>
                                        <small class="text-light">Congestion</small>
                                    </div>
                                    <div class="traffic-legend-item">
                                        <div class="traffic-color-box" style="background: #10b981;"></div>
                                        <small class="text-light">Road Work</small>
                                    </div>
                                    <div class="traffic-legend-item">
                                        <div class="traffic-color-box" style="background: #8b5cf6;"></div>
                                        <small class="text-light">Hazard</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Route Information -->
                        <div id="routeInfo" style="display: none;">
                            <div class="route-info-box">
                                <div class="row">
                                    <div class="col-md-3 text-center border-end">
                                        <div class="eta-display" id="etaDisplay">--</div>
                                        <small class="text-muted">Estimated Time</small>
                                    </div>
                                    <div class="col-md-3 text-center border-end">
                                        <h3 class="text-light" id="distanceDisplay">--</h3>
                                        <small class="text-muted">Total Distance</small>
                                    </div>
                                    <div class="col-md-3 text-center border-end">
                                        <h3 class="text-light" id="trafficDisplay">--</h3>
                                        <small class="text-muted">Traffic Delay</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3 class="text-light" id="arrivalDisplay">--</h3>
                                        <small class="text-muted">Est. Arrival</small>
                                    </div>
                                </div>
                                <div class="mt-3" id="routeSteps">
                                    <!-- Turn by turn directions will appear here -->
                                </div>
                            </div>
                        </div>

                        <!-- Saved Routes -->
                        <?php if ($saved_routes->num_rows > 0): ?>
                        <div class="card cyber-card mt-4">
                            <div class="card-header">
                                <h6 class="mb-0 text-light">
                                    <i class="fas fa-history me-2"></i>Recent Routes
                                </h6>
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php while ($route = $saved_routes->fetch_assoc()): ?>
                                    <div class="border-bottom pb-2 mb-2 saved-route" 
                                         data-start="<?php echo htmlspecialchars($route['start_point']); ?>"
                                         data-end="<?php echo htmlspecialchars($route['end_point']); ?>"
                                         style="cursor: pointer;">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="text-light">
                                                    <i class="fas fa-map-marker-alt text-success me-2"></i>
                                                    <?php echo htmlspecialchars($route['start_point']); ?>
                                                </div>
                                                <div class="text-light">
                                                    <i class="fas fa-flag-checkered text-danger me-2"></i>
                                                    <?php echo htmlspecialchars($route['end_point']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo ucfirst($route['preferred_route']); ?>
                                                    <?php if ($route['avoid_tolls']): ?>
                                                        <span class="badge bg-secondary">No Tolls</span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j', strtotime($route['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="darkveil.js"></script>
    <script>
        // Initialize map centered on Bangladesh
        const map = L.map('map').setView([23.8103, 90.4125], 10);
        
        // MapTiler Layer
        L.tileLayer(`https://api.maptiler.com/maps/streets/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>`, {
            attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a>',
            tileSize: 512,
            zoomOffset: -1
        }).addTo(map);

        // Store map elements
        let routeLayer = null;
        let startMarker = null;
        let endMarker = null;
        let incidentLayers = [];
        let currentRoute = null;

        // Incident data from PHP
        const incidentsData = <?php 
            $incidents_array = [];
            $incidents->data_seek(0);
            while ($inc = $incidents->fetch_assoc()) {
                $incidents_array[] = $inc;
            }
            echo json_encode($incidents_array);
        ?>;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body);
            displayIncidentsOnMap();
            setupEventListeners();
            setupLocationAutocomplete('startPoint', 'startSuggestions', 'startLat', 'startLng');
            setupLocationAutocomplete('endPoint', 'endSuggestions', 'endLat', 'endLng');
        });

        // Display incidents on map with different colors
        function displayIncidentsOnMap() {
            incidentsData.forEach(incident => {
                if (incident.latitude && incident.longitude) {
                    const color = getIncidentColor(incident.report_type);
                    const icon = L.divIcon({
                        html: `
                            <div style="
                                background: ${color};
                                width: 24px;
                                height: 24px;
                                border-radius: 50%;
                                border: 3px solid white;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                            ">
                                <i class="fas fa-${getIncidentIcon(incident.report_type)}" style="color: white; font-size: 10px;"></i>
                            </div>
                        `,
                        className: 'incident-marker',
                        iconSize: [30, 30]
                    });

                    const marker = L.marker([incident.latitude, incident.longitude], { icon: icon })
                        .addTo(map)
                        .bindPopup(`
                            <strong>${incident.report_type.toUpperCase()}</strong><br>
                            ${incident.place_name}<br>
                            Severity: <span class="badge bg-${getSeverityColor(incident.severity)}">${incident.severity}</span><br>
                            <small>${incident.description || 'No details'}</small>
                        `);

                    incidentLayers.push(marker);
                }
            });
        }

        // Get incident color based on type
        function getIncidentColor(type) {
            const colors = {
                'accident': '#ef4444',
                'congestion': '#f59e0b',
                'road_work': '#10b981',
                'hazard': '#8b5cf6',
                'police': '#3b82f6'
            };
            return colors[type] || '#6b7280';
        }

        // Get incident icon
        function getIncidentIcon(type) {
            const icons = {
                'accident': 'car-crash',
                'congestion': 'traffic-light',
                'road_work': 'road',
                'hazard': 'exclamation-triangle',
                'police': 'shield-alt'
            };
            return icons[type] || 'exclamation-circle';
        }

        // Get severity color
        function getSeverityColor(severity) {
            const colors = {
                'low': 'success',
                'medium': 'warning',
                'high': 'danger'
            };
            return colors[severity] || 'secondary';
        }

        // Setup event listeners
        function setupEventListeners() {
            // Route form submission
            document.getElementById('routeForm').addEventListener('submit', function(e) {
                e.preventDefault();
                calculateRealRoute();
            });

            // Clear route button
            document.getElementById('clearRoute').addEventListener('click', clearRoute);

            // Center map button
            document.getElementById('centerMap').addEventListener('click', () => {
                map.setView([23.8103, 90.4125], 10);
            });

            // Refresh data button
            document.getElementById('refreshData').addEventListener('click', () => {
                location.reload();
            });

            // Click saved routes to load them
            document.querySelectorAll('.saved-route').forEach(route => {
                route.addEventListener('click', function() {
                    document.getElementById('startPoint').value = this.dataset.start;
                    document.getElementById('endPoint').value = this.dataset.end;
                    // Trigger geocoding for these locations
                    geocodeAndSetLocation(this.dataset.start, 'start');
                    geocodeAndSetLocation(this.dataset.end, 'end');
                });
            });
        }

        // Setup location autocomplete with geocoding
        function setupLocationAutocomplete(inputId, suggestionsId, latId, lngId) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);
            const latInput = document.getElementById(latId);
            const lngInput = document.getElementById(lngId);
            
            input.addEventListener('input', async function() {
                const value = this.value.trim();
                
                if (value.length < 3) {
                    suggestions.style.display = 'none';
                    return;
                }

                try {
                    const results = await forwardGeocode(value);
                    
                    if (results && results.length > 0) {
                        suggestions.innerHTML = results.map(result => `
                            <div class="location-suggestion-item" 
                                 data-lat="${result.latitude}" 
                                 data-lng="${result.longitude}"
                                 data-place="${result.place_name}">
                                <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                ${result.place_name}
                            </div>
                        `).join('');
                        
                        suggestions.style.display = 'block';
                        
                        // Add click handlers to suggestions
                        suggestions.querySelectorAll('.location-suggestion-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const lat = parseFloat(this.dataset.lat);
                                const lng = parseFloat(this.dataset.lng);
                                const placeName = this.dataset.place;
                                
                                input.value = placeName;
                                latInput.value = lat;
                                lngInput.value = lng;
                                
                                // Update map marker
                                if (inputId === 'startPoint') {
                                    updateStartMarker(lat, lng, placeName);
                                } else {
                                    updateEndMarker(lat, lng, placeName);
                                }
                                
                                suggestions.style.display = 'none';
                            });
                        });
                    } else {
                        suggestions.innerHTML = `
                            <div class="location-suggestion-item text-muted">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                No locations found
                            </div>
                        `;
                        suggestions.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Geocoding error:', error);
                    suggestions.innerHTML = `
                        <div class="location-suggestion-item text-muted">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Search unavailable
                        </div>
                    `;
                    suggestions.style.display = 'block';
                }
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
        }

        // Forward geocode using our PHP endpoint
        async function forwardGeocode(query) {
            const formData = new FormData();
            formData.append('action', 'forward_geocode');
            formData.append('query', query);
            
            const response = await fetch('ajax_geocoding.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success && data.results) {
                return data.results;
            } else {
                throw new Error(data.error || 'Geocoding failed');
            }
        }

        // Update start marker
        function updateStartMarker(lat, lng, placeName) {
            if (startMarker) map.removeLayer(startMarker);
            
            const startIcon = L.divIcon({
                html: '<div style="background: #22c55e; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-play" style="color: white; font-size: 12px;"></i></div>',
                className: '',
                iconSize: [30, 30]
            });
            
            startMarker = L.marker([lat, lng], { icon: startIcon })
                .addTo(map)
                .bindPopup(`<strong>Start: ${placeName}</strong>`);
        }

        // Update end marker
        function updateEndMarker(lat, lng, placeName) {
            if (endMarker) map.removeLayer(endMarker);
            
            const endIcon = L.divIcon({
                html: '<div style="background: #ef4444; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-flag" style="color: white; font-size: 12px;"></i></div>',
                className: '',
                iconSize: [30, 30]
            });
            
            endMarker = L.marker([lat, lng], { icon: endIcon })
                .addTo(map)
                .bindPopup(`<strong>Destination: ${placeName}</strong>`);
        }

        // Calculate real route using OSRM
        async function calculateRealRoute() {
            const startLat = document.getElementById('startLat').value;
            const startLng = document.getElementById('startLng').value;
            const endLat = document.getElementById('endLat').value;
            const endLng = document.getElementById('endLng').value;

            if (!startLat || !startLng || !endLat || !endLng) {
                alert('Please select valid start and end locations from the suggestions');
                return;
            }

            document.getElementById('mapLoading').style.display = 'flex';

            try {
                // Use OSRM for routing (follows actual roads)
                const profile = document.getElementById('preferredRoute').value;
                const url = `https://router.project-osrm.org/route/v1/${profile}/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.code === 'Ok') {
                    const route = data.routes[0];
                    displayRealRoute(route, startLat, startLng, endLat, endLng);
                } else {
                    throw new Error('No route found');
                }
                
            } catch (error) {
                console.error('Routing error:', error);
                alert('Error calculating route. Please try different locations.');
            } finally {
                document.getElementById('mapLoading').style.display = 'none';
            }
        }

        // Display real route on map
        function displayRealRoute(route, startLat, startLng, endLat, endLng) {
            currentRoute = route;
            
            // Clear existing route
            if (routeLayer) map.removeLayer(routeLayer);

            // Draw route line
            routeLayer = L.geoJSON(route.geometry, {
                style: {
                    color: '#667eea',
                    weight: 6,
                    opacity: 0.8,
                    lineCap: 'round',
                    lineJoin: 'round'
                }
            }).addTo(map);

            // Add start and end markers if not already present
            if (!startMarker) {
                updateStartMarker(startLat, startLng, 'Start Point');
            }
            if (!endMarker) {
                updateEndMarker(endLat, endLng, 'Destination');
            }

            // Fit map to route bounds
            map.fitBounds(routeLayer.getBounds().pad(0.1));

            // Update route info
            updateRouteInfo(route);
        }

        // Update route information
        function updateRouteInfo(route) {
            const duration = Math.round(route.duration / 60); // Convert to minutes
            const distance = (route.distance / 1000).toFixed(1); // Convert to km
            
            // Calculate traffic delay based on incidents along route
            const trafficDelay = calculateTrafficDelay(route);
            const totalTime = duration + trafficDelay;
            const arrival = new Date(Date.now() + totalTime * 60000);
            
            document.getElementById('etaDisplay').textContent = `${totalTime} min`;
            document.getElementById('distanceDisplay').textContent = `${distance} km`;
            document.getElementById('trafficDisplay').textContent = `+${trafficDelay} min`;
            document.getElementById('arrivalDisplay').textContent = arrival.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });

            // Show route info
            document.getElementById('routeInfo').style.display = 'block';
            document.getElementById('routeOptionsCard').style.display = 'block';
            
            // Update route options
            document.getElementById('routeOptions').innerHTML = `
                <div class="alert alert-info">
                    <strong>Route Calculated Successfully</strong><br>
                    <small>Based on real road network data</small>
                </div>
                <div class="text-light">
                    <i class="fas fa-road me-2"></i>Following actual roads<br>
                    <i class="fas fa-clock me-2"></i>Includes traffic delays<br>
                    <i class="fas fa-map me-2"></i>Real-time routing
                </div>
            `;
        }

        // Calculate traffic delay based on incidents near the route
        function calculateTrafficDelay(route) {
            let delay = 0;
            
            incidentsData.forEach(incident => {
                if (isIncidentNearRoute(incident, route.geometry)) {
                    // Add delay based on incident severity
                    switch(incident.severity) {
                        case 'low': delay += 2; break;
                        case 'medium': delay += 5; break;
                        case 'high': delay += 10; break;
                    }
                }
            });
            
            return Math.min(delay, 30); // Cap at 30 minutes
        }

        // Check if incident is near the route
        function isIncidentNearRoute(incident, routeGeometry) {
            if (!incident.latitude || !incident.longitude) return false;
            
            // Simple distance calculation (in production, use proper GIS functions)
            const incidentPoint = [incident.latitude, incident.longitude];
            const routePoints = routeGeometry.coordinates.map(coord => [coord[1], coord[0]]);
            
            for (let point of routePoints) {
                const distance = calculateDistance(incidentPoint, point);
                if (distance < 2) { // Within 2 km
                    return true;
                }
            }
            return false;
        }

        // Calculate distance between two points in km
        function calculateDistance(point1, point2) {
            const [lat1, lon1] = point1;
            const [lat2, lon2] = point2;
            
            const R = 6371; // Earth's radius in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        // Clear route
        function clearRoute() {
            if (routeLayer) map.removeLayer(routeLayer);
            if (startMarker) map.removeLayer(startMarker);
            if (endMarker) map.removeLayer(endMarker);
            
            routeLayer = null;
            startMarker = null;
            endMarker = null;
            currentRoute = null;

            document.getElementById('startPoint').value = '';
            document.getElementById('endPoint').value = '';
            document.getElementById('startLat').value = '';
            document.getElementById('startLng').value = '';
            document.getElementById('endLat').value = '';
            document.getElementById('endLng').value = '';
            document.getElementById('routeInfo').style.display = 'none';
            document.getElementById('routeOptionsCard').style.display = 'none';
            
            map.setView([23.8103, 90.4125], 10);
        }

        // Geocode and set location for saved routes
        async function geocodeAndSetLocation(query, type) {
            try {
                const results = await forwardGeocode(query);
                if (results && results.length > 0) {
                    const bestMatch = results[0];
                    if (type === 'start') {
                        document.getElementById('startLat').value = bestMatch.latitude;
                        document.getElementById('startLng').value = bestMatch.longitude;
                        updateStartMarker(bestMatch.latitude, bestMatch.longitude, bestMatch.place_name);
                    } else {
                        document.getElementById('endLat').value = bestMatch.latitude;
                        document.getElementById('endLng').value = bestMatch.longitude;
                        updateEndMarker(bestMatch.latitude, bestMatch.longitude, bestMatch.place_name);
                    }
                }
            } catch (error) {
                console.error('Geocoding error for saved route:', error);
            }
        }

        // Auto-refresh incidents every 5 minutes
        setInterval(() => {
            console.log('Refreshing incident data...');
            // In production, fetch updated incidents via AJAX
        }, 300000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Helper function for severity color
function getSeverityColor($severity) {
    switch($severity) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'danger';
        default: return 'secondary';
    }
}
?>