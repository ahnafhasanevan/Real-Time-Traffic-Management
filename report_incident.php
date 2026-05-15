<?php
require_once 'config.php';
requireAuth();

$db = getDBConnection();
$user = getCurrentUser();

// Get a default segment ID for reports without specific road segments
$default_segment = $db->query("SELECT segment_id FROM road_segments LIMIT 1")->fetch_assoc();
$default_segment_id = $default_segment ? $default_segment['segment_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use default segment if none provided, or get from form
    $segment_id = !empty($_POST['segment_id']) ? $_POST['segment_id'] : $default_segment_id;
    $place_name = trim($_POST['place_name']);
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $location_details = $_POST['location_details'] ?? '';
    $report_type = $_POST['report_type'];
    $severity = $_POST['severity'];
    $description = $_POST['description'];
    
    // Validate required fields
    if (empty($place_name)) {
        $error = "Place name is required. Please select a location on the map or search for a place name.";
    } elseif (empty($latitude) || empty($longitude)) {
        $error = "Location coordinates are required. Please select a location on the map.";
    } elseif (!$segment_id) {
        $error = "Unable to save report. No road segments available in system.";
    } else {
        $stmt = $db->prepare("INSERT INTO user_traffic_reports (user_id, segment_id, place_name, latitude, longitude, location_details, report_type, severity, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisddssss", $user['user_id'], $segment_id, $place_name, $latitude, $longitude, $location_details, $report_type, $severity, $description);
        
        if ($stmt->execute()) {
            $success = "Incident reported successfully!";
            // Clear form
            $_POST = array();
        } else {
            $error = "Error reporting incident: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Incident - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .form-section {
            background: rgba(25, 30, 50, 0.8);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(100, 100, 255, 0.2);
        }
        #incidentMap {
            height: 400px;
            border-radius: 8px;
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
        .coordinates-info {
            background: rgba(40, 45, 65, 0.8);
            border-radius: 6px;
            padding: 10px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .optional-section {
            border-left: 4px solid #6c757d;
            padding-left: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container mt-4">
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card cyber-card shadow-lg">
                            <div class="card-header">
                                <h4><i class="fas fa-exclamation-triangle me-2"></i>Report Traffic Incident</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo $success; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" id="incidentForm">
                                    <!-- Location Selection -->
                                    <div class="form-section">
                                        <h5><i class="fas fa-map-marker-alt me-2"></i>Incident Location</h5>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Place Name *</label>
                                            <div class="location-input position-relative">
                                                <input type="text" class="form-control" name="place_name" id="place_name" 
                                                       placeholder="Search for a location or click on the map..." 
                                                       value="<?php echo isset($_POST['place_name']) ? htmlspecialchars($_POST['place_name']) : ''; ?>" required>
                                                <div class="location-suggestions" id="placeSuggestions"></div>
                                            </div>
                                            <div class="form-text">Start typing to search for locations in Bangladesh, or click on the map below</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Specific Location Details (Optional)</label>
                                            <input type="text" class="form-control" name="location_details" 
                                                   placeholder="e.g., Near Main Intersection, Between 1st and 2nd Street, etc."
                                                   value="<?php echo isset($_POST['location_details']) ? htmlspecialchars($_POST['location_details']) : ''; ?>">
                                            <div class="form-text">Provide additional location details to help identify the exact spot</div>
                                        </div>

                                        <!-- Map Section -->
                                        <div class="mb-3">
                                            <label class="form-label">Select Location on Map *</label>
                                            <div id="incidentMap"></div>
                                            <div class="coordinates-info mt-2">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <span id="coordinatesDisplay">Click on the map to select incident location. Coordinates will appear here.</span>
                                            </div>
                                        </div>

                                        <input type="hidden" name="latitude" id="latitude" value="<?php echo isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : ''; ?>">
                                        <input type="hidden" name="longitude" id="longitude" value="<?php echo isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : ''; ?>">
                                        <input type="hidden" name="segment_id" id="segment_id" value="<?php echo $default_segment_id; ?>">
                                    </div>

                                    <!-- Incident Details -->
                                    <div class="form-section">
                                        <h5><i class="fas fa-clipboard-list me-2"></i>Incident Details</h5>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Incident Type *</label>
                                            <select class="form-select" name="report_type" required>
                                                <option value="">Select incident type</option>
                                                <option value="accident" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'accident') ? 'selected' : ''; ?>>Accident</option>
                                                <option value="congestion" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'congestion') ? 'selected' : ''; ?>>Traffic Congestion</option>
                                                <option value="road_work" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'road_work') ? 'selected' : ''; ?>>Road Work</option>
                                                <option value="hazard" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'hazard') ? 'selected' : ''; ?>>Road Hazard</option>
                                                <option value="police" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] == 'police') ? 'selected' : ''; ?>>Police Activity</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Severity Level *</label>
                                            <select class="form-select" name="severity" required>
                                                <option value="">Select severity</option>
                                                <option value="low" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'low') ? 'selected' : ''; ?>>Low - Minor impact</option>
                                                <option value="medium" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'medium') ? 'selected' : ''; ?>>Medium - Moderate impact</option>
                                                <option value="high" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'high') ? 'selected' : ''; ?>>High - Major impact</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Description *</label>
                                            <textarea class="form-control" name="description" rows="4" 
                                                      placeholder="Provide detailed description of the incident..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                            <div class="form-text">Be specific about what happened, vehicles involved, lane closures, etc.</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Optional Road Information -->
                                    <div class="form-section optional-section">
                                        <h6><i class="fas fa-road me-2"></i>Road Information (Optional)</h6>
                                        <p class="text-muted small">If you know the specific road, you can select it below</p>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Road Segment</label>
                                            <select class="form-select" name="optional_segment_id" id="optional_segment_id">
                                                <option value="">Not specified - use general location</option>
                                                <?php
                                                $roads = $db->query("SELECT rs.segment_id, r.road_name, rs.segment_name 
                                                                     FROM road_segments rs 
                                                                     JOIN roads r ON rs.road_id = r.road_id 
                                                                     ORDER BY r.road_name, rs.segment_name");
                                                while ($road = $roads->fetch_assoc()): ?>
                                                    <option value="<?php echo $road['segment_id']; ?>" 
                                                            <?php echo (isset($_POST['optional_segment_id']) && $_POST['optional_segment_id'] == $road['segment_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($road['road_name']); ?>
                                                        <?php if (!empty($road['segment_name'])): ?>
                                                             - <?php echo htmlspecialchars($road['segment_name']); ?>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="index.php" class="btn btn-secondary me-md-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>Submit Report
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DarkVeil Background -->
    <script src="darkveil.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize the DarkVeil background
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body, {
                speed: 0.5,
                particleCount: 70,
                connectionDistance: 110,
                colors: ['#667eea', '#764ba2', '#f093fb', '#4facfe']
            });
            
            initializeMap();
            setupLocationSearch();
            setupOptionalSegment();
        });

        let map;
        let incidentMarker = null;

        // Initialize map
        function initializeMap() {
            map = L.map('incidentMap').setView([23.8103, 90.4125], 12);
            
            // MapTiler Layer
            L.tileLayer(`https://api.maptiler.com/maps/streets/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>`, {
                attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a>',
                tileSize: 512,
                zoomOffset: -1
            }).addTo(map);
            
            // Add click event to map
            map.on('click', async function(e) {
                const { lat, lng } = e.latlng;
                
                // Show loading state
                document.getElementById('coordinatesDisplay').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Getting location name...';
                
                try {
                    // Reverse geocode to get place name
                    const placeName = await reverseGeocode(lat, lng);
                    
                    // Update form fields
                    document.getElementById('place_name').value = placeName;
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;
                    document.getElementById('coordinatesDisplay').innerHTML = 
                        `Location: ${placeName} | Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    
                    // Update marker
                    updateMapMarker(lat, lng, placeName);
                    
                } catch (error) {
                    document.getElementById('coordinatesDisplay').innerHTML = 
                        `<span class="text-danger">Error getting location name: ${error.message}</span>`;
                    console.error('Geocoding error:', error);
                }
            });
        }

        // Reverse geocode coordinates to place name
        async function reverseGeocode(lat, lng) {
            const formData = new FormData();
            formData.append('action', 'reverse_geocode');
            formData.append('lat', lat);
            formData.append('lng', lng);
            
            const response = await fetch('ajax_geocoding.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                return data.place_name;
            } else {
                throw new Error(data.error || 'Reverse geocoding failed');
            }
        }

        // Forward geocode place name to coordinates
        async function forwardGeocode(query) {
            const formData = new FormData();
            formData.append('action', 'forward_geocode');
            formData.append('query', query);
            
            const response = await fetch('ajax_geocoding.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            console.log('Forward Geocoding Response:', data); // Debug log
            
            if (data.success && data.results && Array.isArray(data.results)) {
                return data.results; // Return all results
            } else {
                throw new Error(data.error || 'Location not found');
            }
        }

        // Update map marker
        function updateMapMarker(lat, lng, placeName = 'Selected Location') {
            // Remove existing marker
            if (incidentMarker) {
                map.removeLayer(incidentMarker);
            }
            
            // Add new marker
            incidentMarker = L.marker([lat, lng]).addTo(map)
                .bindPopup(`<strong>${placeName}</strong>`)
                .openPopup();
            
            // Center map on marker
            map.setView([lat, lng], 15);
        }

        // Setup location search functionality
        function setupLocationSearch() {
            const placeInput = document.getElementById('place_name');
            const suggestions = document.getElementById('placeSuggestions');
            let searchTimeout;
            
            placeInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 3) {
                    suggestions.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(async () => {
                    try {
                        const results = await forwardGeocode(query);
                        
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
                                    
                                    placeInput.value = placeName;
                                    document.getElementById('latitude').value = lat;
                                    document.getElementById('longitude').value = lng;
                                    document.getElementById('coordinatesDisplay').innerHTML = 
                                        `Location: ${placeName} | Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                                    
                                    updateMapMarker(lat, lng, placeName);
                                    suggestions.style.display = 'none';
                                });
                            });
                        } else {
                            suggestions.innerHTML = `
                                <div class="location-suggestion-item text-muted">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    No locations found for "${query}"
                                </div>
                            `;
                            suggestions.style.display = 'block';
                        }
                        
                    } catch (error) {
                        suggestions.innerHTML = `
                            <div class="location-suggestion-item text-muted">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${error.message}
                            </div>
                        `;
                        suggestions.style.display = 'block';
                        console.error('Search error:', error);
                    }
                }, 500);
            });
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!placeInput.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
        }

        // Handle optional segment selection
        function setupOptionalSegment() {
            const optionalSegmentSelect = document.getElementById('optional_segment_id');
            const segmentIdInput = document.getElementById('segment_id');
            
            optionalSegmentSelect.addEventListener('change', function() {
                if (this.value) {
                    segmentIdInput.value = this.value;
                }
            });
        }

        // Form validation
        document.getElementById('incidentForm').addEventListener('submit', function(e) {
            const placeName = document.getElementById('place_name').value;
            const latitude = document.getElementById('latitude').value;
            const longitude = document.getElementById('longitude').value;
            
            if (!placeName || !latitude || !longitude) {
                e.preventDefault();
                alert('Please select a location on the map or search for a place name');
                return false;
            }
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>