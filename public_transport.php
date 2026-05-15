<?php
require_once 'config.php';
require_once 'maptiler_config.php';
requireAuth();
$user = getCurrentUser();
$db = getDBConnection();

// Get all unique locations for autocomplete
$locations_query = $db->query("
    SELECT DISTINCT location_name, location_name_bangla 
    FROM bus_locations 
    ORDER BY location_name
");
$all_locations = [];
while ($loc = $locations_query->fetch_assoc()) {
    $all_locations[] = $loc;
}

// Process search request
$search_results = [];
$selected_bus = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_routes'])) {
    $start_location = trim($_POST['start_location']);
    $end_location = trim($_POST['end_location']);
    
    // Find buses that serve both locations
    $search_stmt = $db->prepare("
        SELECT DISTINCT b.bus_id, b.bus_name, b.bus_name_bangla, b.service_type, 
               b.start_time, b.end_time, b.route_description,
               GROUP_CONCAT(DISTINCT bl.location_name ORDER BY blp.stop_order SEPARATOR ' ⇄ ') as full_route
        FROM buses b
        JOIN bus_location_points blp ON b.bus_id = blp.bus_id
        JOIN bus_locations bl ON blp.location_id = bl.location_id
        WHERE b.bus_id IN (
            SELECT bus_id FROM bus_location_points blp1
            JOIN bus_locations bl1 ON blp1.location_id = bl1.location_id
            WHERE bl1.location_name LIKE ? OR bl1.location_name_bangla LIKE ?
        )
        AND b.bus_id IN (
            SELECT bus_id FROM bus_location_points blp2
            JOIN bus_locations bl2 ON blp2.location_id = bl2.location_id
            WHERE bl2.location_name LIKE ? OR bl2.location_name_bangla LIKE ?
        )
        GROUP BY b.bus_id
        HAVING COUNT(DISTINCT CASE WHEN bl.location_name LIKE ? OR bl.location_name_bangla LIKE ? THEN 1 END) > 0
           AND COUNT(DISTINCT CASE WHEN bl.location_name LIKE ? OR bl.location_name_bangla LIKE ? THEN 1 END) > 0
        ORDER BY b.bus_name
    ");
    
    $search_param = "%$start_location%";
    $search_param2 = "%$end_location%";
    $search_stmt->bind_param("ssssssss", 
        $search_param, $search_param, $search_param2, $search_param2,
        $search_param, $search_param, $search_param2, $search_param2
    );
    
    $search_stmt->execute();
    $search_results = $search_stmt->get_result();
}

// Get bus details if selected
if (isset($_GET['bus_id'])) {
    $bus_id = intval($_GET['bus_id']);
    $bus_stmt = $db->prepare("
        SELECT b.*, 
               GROUP_CONCAT(CONCAT(bl.location_name, '|', bl.location_name_bangla, '|', bl.latitude, '|', bl.longitude) 
                           ORDER BY blp.stop_order SEPARATOR ';') as route_points
        FROM buses b
        JOIN bus_location_points blp ON b.bus_id = blp.bus_id
        JOIN bus_locations bl ON blp.location_id = bl.location_id
        WHERE b.bus_id = ?
        GROUP BY b.bus_id
    ");
    $bus_stmt->bind_param("i", $bus_id);
    $bus_stmt->execute();
    $selected_bus = $bus_stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Transport Route Finder - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="darkveil.css">
    <style>
        #transportMap {
            height: 500px;
            width: 100%;
            border-radius: 10px;
            border: 2px solid rgba(100, 100, 255, 0.3);
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
        .bus-route-card {
            border: 2px solid rgba(100, 100, 255, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .bus-route-card:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        .bus-route-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.2);
        }
        .route-timeline {
            position: relative;
            padding-left: 20px;
            margin: 15px 0;
        }
        .route-timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #667eea;
        }
        .timeline-stop {
            position: relative;
            margin-bottom: 10px;
            padding: 5px 10px;
            background: rgba(25, 30, 50, 0.7);
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }
        .timeline-stop::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 2px solid rgba(25, 30, 50, 0.9);
        }
        .timeline-stop.current {
            border-left-color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
        }
        .timeline-stop.current::before {
            background: #22c55e;
        }
        .timeline-stop.destination {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        .timeline-stop.destination::before {
            background: #ef4444;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container-fluid mt-4">
                <h2 class="text-light mb-4">
                    <i class="fas fa-bus me-2"></i>Public Transport Route Finder
                    <span class="badge bg-success ms-2">Live Routes</span>
                </h2>

                <div class="row">
                    <!-- Search Panel -->
                    <div class="col-lg-4 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light">
                                    <i class="fas fa-search me-2"></i>Find Bus Routes
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="routeSearchForm">
                                    <div class="mb-3 location-input">
                                        <label class="form-label">
                                            <i class="fas fa-map-marker-alt text-success me-2"></i>Start Location *
                                        </label>
                                        <input type="text" class="form-control" id="startLocation" name="start_location" 
                                               placeholder="Enter starting location" required
                                               value="<?php echo isset($_POST['start_location']) ? htmlspecialchars($_POST['start_location']) : ''; ?>">
                                        <div class="location-suggestions" id="startSuggestions"></div>
                                    </div>
                                    
                                    <div class="mb-3 location-input">
                                        <label class="form-label">
                                            <i class="fas fa-flag-checkered text-danger me-2"></i>Destination *
                                        </label>
                                        <input type="text" class="form-control" id="endLocation" name="end_location" 
                                               placeholder="Enter destination" required
                                               value="<?php echo isset($_POST['end_location']) ? htmlspecialchars($_POST['end_location']) : ''; ?>">
                                        <div class="location-suggestions" id="endSuggestions"></div>
                                    </div>
                                    
                                    <button type="submit" name="search_routes" class="btn btn-primary w-100">
                                        <i class="fas fa-route me-2"></i>Find Bus Routes
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Search Results -->
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_routes'])): ?>
                        <div class="card cyber-card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0 text-light">
                                    <i class="fas fa-bus-alt me-2"></i>Available Buses
                                    <span class="badge bg-info"><?php echo $search_results->num_rows; ?> found</span>
                                </h6>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if ($search_results->num_rows > 0): ?>
                                    <?php while ($bus = $search_results->fetch_assoc()): ?>
                                        <div class="bus-route-card" 
                                             data-bus-id="<?php echo $bus['bus_id']; ?>"
                                             onclick="selectBusRoute(<?php echo $bus['bus_id']; ?>)">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="text-light mb-1"><?php echo htmlspecialchars($bus['bus_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($bus['bus_name_bangla']); ?></small>
                                                    <div class="mt-2">
                                                        <span class="badge bg-primary"><?php echo $bus['service_type']; ?></span>
                                                        <small class="text-muted ms-2">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('g:i A', strtotime($bus['start_time'])); ?> - 
                                                            <?php echo date('g:i A', strtotime($bus['end_time'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <i class="fas fa-chevron-right text-muted"></i>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                                        <p class="text-muted mb-0">No buses found for this route</p>
                                        <small class="text-muted">Try different locations</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Selected Bus Details -->
                        <?php if ($selected_bus): ?>
                        <div class="card cyber-card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0 text-light">
                                    <i class="fas fa-info-circle me-2"></i>Route Details
                                </h6>
                            </div>
                            <div class="card-body">
                                <h5 class="text-light"><?php echo htmlspecialchars($selected_bus['bus_name']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($selected_bus['bus_name_bangla']); ?></p>
                                
                                <div class="mb-3">
                                    <span class="badge bg-primary"><?php echo $selected_bus['service_type']; ?></span>
                                    <small class="text-muted ms-2">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('g:i A', strtotime($selected_bus['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($selected_bus['end_time'])); ?>
                                    </small>
                                </div>
                                
                                <div class="route-timeline" id="routeTimeline">
                                    <!-- Timeline will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Map and Route Info -->
                    <div class="col-lg-8 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-light">
                                    <i class="fas fa-map me-2"></i>Bus Route Map
                                </h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" id="centerMap">
                                        <i class="fas fa-crosshairs"></i> Center
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" id="refreshIncidents">
                                        <i class="fas fa-sync-alt"></i> Refresh Incidents
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0 position-relative">
                                <div id="transportMap"></div>
                            </div>
                        </div>
                        
                        <!-- Route Information -->
                        <?php if ($selected_bus): ?>
                        <div class="card cyber-card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0 text-light">
                                    <i class="fas fa-route me-2"></i>Complete Route
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-light"><?php echo htmlspecialchars($selected_bus['route_description']); ?></p>
                                <div class="alert alert-info">
                                    <small>
                                        <i class="fas fa-info-circle me-2"></i>
                                        This route follows actual bus paths with real-time traffic incident data.
                                    </small>
                                </div>
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
        // Initialize map
        const transportMap = L.map('transportMap').setView([23.8103, 90.4125], 12);
        
        // MapTiler Layer
        L.tileLayer(`https://api.maptiler.com/maps/streets/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>`, {
            attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a>',
            tileSize: 512,
            zoomOffset: -1
        }).addTo(transportMap);

        let routeLayer = null;
        let busStopsLayer = null;
        let incidentLayers = [];
        const allLocations = <?php echo json_encode($all_locations); ?>;
        const selectedBus = <?php echo $selected_bus ? json_encode($selected_bus) : 'null'; ?>;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body);
            setupLocationAutocomplete('startLocation', 'startSuggestions');
            setupLocationAutocomplete('endLocation', 'endSuggestions');
            loadIncidentsOnMap();
            
            if (selectedBus) {
                displayBusRouteOnMap(selectedBus);
                displayRouteTimeline(selectedBus);
            }
        });

        // Setup location autocomplete
        function setupLocationAutocomplete(inputId, suggestionsId) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);
            
            input.addEventListener('input', function() {
                const value = this.value.trim().toLowerCase();
                
                if (value.length < 2) {
                    suggestions.style.display = 'none';
                    return;
                }

                const filtered = allLocations.filter(loc => 
                    loc.location_name.toLowerCase().includes(value) || 
                    loc.location_name_bangla.includes(value)
                );
                
                if (filtered.length > 0) {
                    suggestions.innerHTML = filtered.map(loc => `
                        <div class="location-suggestion-item" 
                             data-location="${loc.location_name}">
                            <div>
                                <strong>${loc.location_name}</strong>
                                <div class="text-muted small">${loc.location_name_bangla}</div>
                            </div>
                        </div>
                    `).join('');
                    
                    suggestions.style.display = 'block';
                    
                    // Add click handlers
                    suggestions.querySelectorAll('.location-suggestion-item').forEach(item => {
                        item.addEventListener('click', function() {
                            input.value = this.dataset.location;
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
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
        }

        // Load incidents on map
        function loadIncidentsOnMap() {
            // This would typically fetch from your incidents API
            // For now, we'll use a simulated dataset
            const simulatedIncidents = [
                {
                    latitude: 23.750,
                    longitude: 90.400,
                    report_type: 'congestion',
                    place_name: 'Farmgate Area',
                    severity: 'medium',
                    description: 'Heavy traffic congestion'
                },
                {
                    latitude: 23.780,
                    longitude: 90.420,
                    report_type: 'accident',
                    place_name: 'Mirpur Road',
                    severity: 'high',
                    description: 'Road accident reported'
                }
            ];

            simulatedIncidents.forEach(incident => {
                const color = getIncidentColor(incident.report_type);
                const icon = L.divIcon({
                    html: `
                        <div style="
                            background: ${color};
                            width: 20px;
                            height: 20px;
                            border-radius: 50%;
                            border: 2px solid white;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                        ">
                            <i class="fas fa-${getIncidentIcon(incident.report_type)}" style="color: white; font-size: 8px;"></i>
                        </div>
                    `,
                    className: 'incident-marker',
                    iconSize: [24, 24]
                });

                const marker = L.marker([incident.latitude, incident.longitude], { icon: icon })
                    .addTo(transportMap)
                    .bindPopup(`
                        <strong>${incident.report_type.toUpperCase()}</strong><br>
                        ${incident.place_name}<br>
                        Severity: <span class="badge bg-${getSeverityColor(incident.severity)}">${incident.severity}</span><br>
                        <small>${incident.description}</small>
                    `);

                incidentLayers.push(marker);
            });
        }

        // Display bus route on map
        function displayBusRouteOnMap(bus) {
            // Clear existing layers
            if (routeLayer) transportMap.removeLayer(routeLayer);
            if (busStopsLayer) transportMap.removeLayer(busStopsLayer);

            // Parse route points
            if (bus.route_points) {
                const routeData = bus.route_points.split(';');
                const routeCoordinates = [];
                const busStops = [];

                routeData.forEach((point, index) => {
                    const [name, nameBn, lat, lng] = point.split('|');
                    if (lat && lng) {
                        routeCoordinates.push([parseFloat(lat), parseFloat(lng)]);
                        busStops.push({
                            name: name,
                            nameBn: nameBn,
                            lat: parseFloat(lat),
                            lng: parseFloat(lng),
                            isStart: index === 0,
                            isEnd: index === routeData.length - 1
                        });
                    }
                });

                // Draw route line
                if (routeCoordinates.length > 1) {
                    routeLayer = L.polyline(routeCoordinates, {
                        color: '#667eea',
                        weight: 6,
                        opacity: 0.7,
                        lineCap: 'round'
                    }).addTo(transportMap);

                    // Fit map to route bounds
                    transportMap.fitBounds(routeLayer.getBounds().pad(0.1));
                }

                // Add bus stops
                busStopsLayer = L.layerGroup().addTo(transportMap);
                busStops.forEach(stop => {
                    const icon = L.divIcon({
                        html: `
                            <div style="
                                background: ${stop.isStart ? '#22c55e' : stop.isEnd ? '#ef4444' : '#667eea'};
                                width: ${stop.isStart || stop.isEnd ? '16px' : '12px'};
                                height: ${stop.isStart || stop.isEnd ? '16px' : '12px'};
                                border-radius: 50%;
                                border: 2px solid white;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                            "></div>
                        `,
                        className: '',
                        iconSize: [stop.isStart || stop.isEnd ? 20 : 16, stop.isStart || stop.isEnd ? 20 : 16]
                    });

                    L.marker([stop.lat, stop.lng], { icon: icon })
                        .addTo(busStopsLayer)
                        .bindPopup(`
                            <strong>${stop.name}</strong><br>
                            <small>${stop.nameBn}</small><br>
                            <em>${stop.isStart ? 'Start Point' : stop.isEnd ? 'Destination' : 'Bus Stop'}</em>
                        `);
                });
            }
        }

        // Display route timeline
        function displayRouteTimeline(bus) {
            if (bus.route_points) {
                const routeData = bus.route_points.split(';');
                const timeline = document.getElementById('routeTimeline');
                
                timeline.innerHTML = routeData.map((point, index) => {
                    const [name, nameBn] = point.split('|');
                    const isStart = index === 0;
                    const isEnd = index === routeData.length - 1;
                    const isCurrent = !isStart && !isEnd;
                    
                    return `
                        <div class="timeline-stop ${isStart ? 'current' : ''} ${isEnd ? 'destination' : ''}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-light">${name}</strong>
                                    <div class="text-muted small">${nameBn}</div>
                                </div>
                                ${isStart ? '<span class="badge bg-success">Start</span>' : ''}
                                ${isEnd ? '<span class="badge bg-danger">End</span>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // Helper functions
        function getIncidentColor(type) {
            const colors = {
                'accident': '#ef4444',
                'congestion': '#f59e0b',
                'road_work': '#10b981',
                'hazard': '#8b5cf6'
            };
            return colors[type] || '#6b7280';
        }

        function getIncidentIcon(type) {
            const icons = {
                'accident': 'car-crash',
                'congestion': 'traffic-light',
                'road_work': 'road',
                'hazard': 'exclamation-triangle'
            };
            return icons[type] || 'exclamation-circle';
        }

        function getSeverityColor(severity) {
            const colors = {
                'low': 'success',
                'medium': 'warning',
                'high': 'danger'
            };
            return colors[severity] || 'secondary';
        }

        // Select bus route
        function selectBusRoute(busId) {
            window.location.href = `public_transport.php?bus_id=${busId}`;
        }

        // Map controls
        document.getElementById('centerMap').addEventListener('click', () => {
            transportMap.setView([23.8103, 90.4125], 12);
        });

        document.getElementById('refreshIncidents').addEventListener('click', () => {
            incidentLayers.forEach(layer => transportMap.removeLayer(layer));
            incidentLayers = [];
            loadIncidentsOnMap();
        });

        // Updated autocomplete function using AJAX
        function setupLocationAutocomplete(inputId, suggestionsId) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);
    
            input.addEventListener('input', async function() {
                const value = this.value.trim();
        
                if (value.length < 2) {
                    suggestions.style.display = 'none';
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('action', 'search_bus_locations');
                    formData.append('query', value);
            
                    const response = await fetch('ajax_geocoding.php', {
                        method: 'POST',
                        body: formData
                    });
            
                    const data = await response.json();
            
                    if (data.success && data.results.length > 0) {
                        suggestions.innerHTML = data.results.map(loc => `
                            <div class="location-suggestion-item" 
                                data-location="${loc.location_name}">
                                <div>
                                    <strong>${loc.location_name}</strong>
                                    <div class="text-muted small">${loc.location_name_bangla}</div>
                                </div>
                            </div>
                        `).join('');
                
                        suggestions.style.display = 'block';
                
                        // Add click handlers
                        suggestions.querySelectorAll('.location-suggestion-item').forEach(item => {
                            item.addEventListener('click', function() {
                                input.value = this.dataset.location;
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
                    console.error('Location search error:', error);
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



    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>