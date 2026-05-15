<?php
require_once 'config.php';
require_once 'maptiler_config.php'; // Add this line for MapTiler
requireAuth();

$db = getDBConnection();
$user = getCurrentUser();

// Handle report deletion (admin/managers only)
if (isset($_POST['delete_report']) && ($user['user_type'] === 'admin' || $user['user_type'] === 'traffic_manager')) {
    $report_id = (int)$_POST['report_id'];
    
    $stmt = $db->prepare("DELETE FROM user_traffic_reports WHERE report_id = ?");
    $stmt->bind_param("i", $report_id);
    
    if ($stmt->execute()) {
        $success = "Report deleted successfully!";
    } else {
        $error = "Error deleting report: " . $stmt->error;
    }
}

// Handle status update (admin/managers only)
if (isset($_POST['update_status']) && ($user['user_type'] === 'admin' || $user['user_type'] === 'traffic_manager')) {
    $report_id = (int)$_POST['report_id'];
    $status = $_POST['status'];
    
    $stmt = $db->prepare("UPDATE user_traffic_reports SET status = ? WHERE report_id = ?");
    $stmt->bind_param("si", $status, $report_id);
    
    if ($stmt->execute()) {
        $success = "Report status updated successfully!";
    } else {
        $error = "Error updating status: " . $stmt->error;
    }
}

// Build WHERE conditions based on filters and user type
$where_conditions = [];
$params = [];
$types = '';

// Regular users can only see their own reports
if ($user['user_type'] === 'public_user') {
    $where_conditions[] = "utr.user_id = ?";
    $params[] = $user['user_id'];
    $types .= 'i';
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where_conditions[] = "utr.status = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

if (isset($_GET['severity']) && $_GET['severity'] !== '') {
    $where_conditions[] = "utr.severity = ?";
    $params[] = $_GET['severity'];
    $types .= 's';
}

if (isset($_GET['type']) && $_GET['type'] !== '') {
    $where_conditions[] = "utr.report_type = ?";
    $params[] = $_GET['type'];
    $types .= 's';
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Build query
$query = "
    SELECT utr.*, r.road_name, u.username, u.first_name, u.last_name, u.user_type 
    FROM user_traffic_reports utr 
    JOIN road_segments rs ON utr.segment_id = rs.segment_id 
    JOIN roads r ON rs.road_id = r.road_id 
    JOIN users u ON utr.user_id = u.user_id 
    $where_clause
    ORDER BY utr.report_time DESC
";

$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports = $stmt->get_result();

// Get reports data for map
$reports_for_map = [];
$reports->data_seek(0); // Reset pointer
while ($report = $reports->fetch_assoc()) {
    $reports_for_map[] = $report;
}
$reports->data_seek(0); // Reset pointer again for display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome@6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .report-card {
            border-left: 4px solid;
            transition: transform 0.2s;
            background: rgba(25, 30, 50, 0.9);
            border: 1px solid rgba(100, 100, 255, 0.3);
            margin-bottom: 1rem;
        }
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(100, 100, 255, 0.2);
        }
        .status-pending { border-left-color: #ffc107; }
        .status-investigating { border-left-color: #17a2b8; }
        .status-resolved { border-left-color: #28a745; }
        
        /* Improved text readability */
        .card-title, .card-text, .report-details {
            color: #e0e0ff !important;
        }
        
        .text-muted {
            color: rgba(200, 200, 255, 0.7) !important;
        }
        
        .segment-info {
            color: rgba(180, 180, 255, 0.9) !important;
        }
        
        .user-badge {
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }
        
        #reportsMap {
            border-radius: 8px;
            border: 2px solid rgba(100, 100, 255, 0.3);
        }
        
        .map-legend {
            background: rgba(25, 30, 50, 0.95);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(100, 100, 255, 0.3);
            position: absolute;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid white;
        }
        
        .nav-tabs .nav-link {
            color: rgba(200, 200, 255, 0.8);
            border: none;
        }
        
        .nav-tabs .nav-link.active {
            background: rgba(100, 100, 255, 0.2);
            color: #fff;
            border-bottom: 2px solid #667eea;
        }
        
        .nav-tabs .nav-link:hover {
            color: #fff;
            border: none;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light"><i class="fas fa-list me-2"></i>Traffic Incident Reports</h2>
                    <a href="report_incident.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>New Report
                    </a>
                </div>

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

                <!-- Filters and Map Tabs -->
                <div class="card cyber-card mb-4">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" href="#listView" data-bs-toggle="tab">
                                    <i class="fas fa-list me-1"></i>List View
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#mapView" data-bs-toggle="tab">
                                    <i class="fas fa-map me-1"></i>Map View
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#filtersView" data-bs-toggle="tab">
                                    <i class="fas fa-filter me-1"></i>Filters
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Filters Tab -->
                            <div class="tab-pane fade" id="filtersView">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status" onchange="this.form.submit()">
                                            <option value="">All Statuses</option>
                                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="investigating" <?php echo (isset($_GET['status']) && $_GET['status'] == 'investigating') ? 'selected' : ''; ?>>Investigating</option>
                                            <option value="resolved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Severity</label>
                                        <select class="form-select" name="severity" onchange="this.form.submit()">
                                            <option value="">All Severities</option>
                                            <option value="low" <?php echo (isset($_GET['severity']) && $_GET['severity'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo (isset($_GET['severity']) && $_GET['severity'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo (isset($_GET['severity']) && $_GET['severity'] == 'high') ? 'selected' : ''; ?>>High</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Type</label>
                                        <select class="form-select" name="type" onchange="this.form.submit()">
                                            <option value="">All Types</option>
                                            <option value="accident" <?php echo (isset($_GET['type']) && $_GET['type'] == 'accident') ? 'selected' : ''; ?>>Accident</option>
                                            <option value="congestion" <?php echo (isset($_GET['type']) && $_GET['type'] == 'congestion') ? 'selected' : ''; ?>>Congestion</option>
                                            <option value="road_work" <?php echo (isset($_GET['type']) && $_GET['type'] == 'road_work') ? 'selected' : ''; ?>>Road Work</option>
                                            <option value="hazard" <?php echo (isset($_GET['type']) && $_GET['type'] == 'hazard') ? 'selected' : ''; ?>>Hazard</option>
                                            <option value="police" <?php echo (isset($_GET['type']) && $_GET['type'] == 'police') ? 'selected' : ''; ?>>Police Activity</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <a href="view_reports.php" class="btn btn-secondary">Clear Filters</a>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- List View Tab -->
                            <div class="tab-pane fade show active" id="listView">
                                <!-- Reports List -->
                                <div class="row">
                                    <div class="col-12">
                                        <?php if ($reports->num_rows > 0): ?>
                                            <?php while ($report = $reports->fetch_assoc()): ?>
                                                <div class="card report-card status-<?php echo $report['status'] ?? 'pending'; ?>">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <h5 class="card-title mb-3">
                                                                    <i class="fas fa-<?php echo getReportIcon($report['report_type']); ?> me-2"></i>
                                                                    <?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?> - 
                                                                    <?php echo htmlspecialchars($report['road_name']); ?>
                                                                </h5>
                                                                
                                                                <div class="row mb-3">
                                                                    <div class="col-md-6">
                                                                        <div class="report-details">
                                                                            <i class="fas fa-user me-1"></i>
                                                                            Reported by: <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                                                            <span class="badge bg-info user-badge"><?php echo ucfirst(str_replace('_', ' ', $report['user_type'])); ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="report-details">
                                                                            <i class="fas fa-clock me-1"></i>
                                                                            <?php echo date('M j, Y g:i A', strtotime($report['report_time'])); ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <p class="card-text mb-3"><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                                                                
                                                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                                                    <span class="badge bg-<?php echo getSeverityColor($report['severity']); ?>">
                                                                        <i class="fas fa-exclamation-circle me-1"></i>
                                                                        <?php echo ucfirst($report['severity']); ?> Severity
                                                                    </span>
                                                                    
                                                                    <span class="badge bg-<?php echo getStatusColor($report['status'] ?? 'pending'); ?>">
                                                                        <i class="fas fa-<?php echo getStatusIcon($report['status'] ?? 'pending'); ?> me-1"></i>
                                                                        <?php echo ucfirst($report['status'] ?? 'pending'); ?>
                                                                    </span>
                                                                    
                                                                    <?php if (!empty($report['location_details'])): ?>
                                                                        <span class="badge bg-info">
                                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                                            <?php echo htmlspecialchars($report['location_details']); ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Action Buttons (Only for Admin and Traffic Manager) -->
                                                            <?php if ($user['user_type'] === 'admin' || $user['user_type'] === 'traffic_manager'): ?>
                                                                <div class="d-flex flex-column gap-2 ms-3">
                                                                    <!-- Status Update Form -->
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                                                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                                                            <option value="pending" <?php echo ($report['status'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                            <option value="investigating" <?php echo ($report['status'] ?? 'pending') == 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                                                                            <option value="resolved" <?php echo ($report['status'] ?? 'pending') == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                                        </select>
                                                                        <input type="hidden" name="update_status" value="1">
                                                                    </form>
                                                                    
                                                                    <!-- Delete Button -->
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                                                        <button type="submit" name="delete_report" class="btn btn-danger btn-sm" 
                                                                                onclick="return confirm('Are you sure you want to delete this report?')">
                                                                            <i class="fas fa-trash"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <div class="card cyber-card text-center py-5">
                                                <div class="card-body">
                                                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                                    <h4 class="text-light">No reports found</h4>
                                                    <p class="text-muted">There are no incident reports to display.</p>
                                                    <a href="report_incident.php" class="btn btn-primary">
                                                        <i class="fas fa-plus me-1"></i>Create First Report
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Map View Tab -->
                            <div class="tab-pane fade" id="mapView">
                                <div id="reportsMap" style="height: 600px;"></div>
                                <div class="map-legend">
                                    <div class="text-light mb-2"><strong>Report Severity</strong></div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #22c55e;"></div>
                                        <small class="text-light">Low</small>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #eab308;"></div>
                                        <small class="text-light">Medium</small>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background-color: #ef4444;"></div>
                                        <small class="text-light">High</small>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Click on markers to view report details. Clusters show multiple reports in the same area.
                                    </small>
                                </div>
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
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        // Initialize the DarkVeil background
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body, {
                speed: 0.4,
                particleCount: 50,
                connectionDistance: 90,
                colors: ['#667eea', '#764ba2', '#4facfe', '#00f2fe']
            });
            
            // Initialize reports map
            initializeReportsMap();
        });

        let reportsMap = null;
        let markerCluster = null;

        // Initialize reports map
        function initializeReportsMap() {
            reportsMap = L.map('reportsMap').setView([23.8103, 90.4125], 12);
            
            // MapTiler Layer
            L.tileLayer(`https://api.maptiler.com/maps/streets/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>`, {
                attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a>',
                tileSize: 512,
                zoomOffset: -1
            }).addTo(reportsMap);

            // Initialize marker cluster
            markerCluster = L.markerClusterGroup({
                chunkedLoading: true,
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: true,
                zoomToBoundsOnClick: true
            });

            // Add reports to map
            const reports = <?php echo json_encode($reports_for_map); ?>;
            
            reports.forEach(report => {
                // For demo purposes, generate coordinates around Dhaka
                // In production, you would use actual coordinates from your database
                const baseLat = 23.8103;
                const baseLng = 90.4125;
                const lat = baseLat + (Math.random() - 0.5) * 0.1;
                const lng = baseLng + (Math.random() - 0.5) * 0.1;
                
                const severityColor = getSeverityColorHex(report.severity);
                const statusIcon = getStatusIconClass(report.status);
                
                const icon = L.divIcon({
                    html: `
                        <div style="
                            background: ${severityColor}; 
                            border-radius: 50%; 
                            width: 24px; 
                            height: 24px; 
                            border: 3px solid white;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                        ">
                            <i class="fas fa-${getReportIconClass(report.report_type)}" style="color: white; font-size: 10px;"></i>
                        </div>
                    `,
                    className: 'report-marker',
                    iconSize: [30, 30]
                });
                
                const marker = L.marker([lat, lng], { icon: icon });
                
                const popupContent = `
                    <div style="min-width: 250px;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">${report.report_type.replace('_', ' ').toUpperCase()}</h6>
                            <span class="badge bg-${getSeverityColor(report.severity)}">${report.severity}</span>
                        </div>
                        <p class="mb-2"><strong>${report.road_name}</strong></p>
                        <p class="mb-2 small">${report.description.substring(0, 100)}${report.description.length > 100 ? '...' : ''}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i>${report.first_name}
                            </small>
                            <small class="text-muted">
                                ${new Date(report.report_time).toLocaleDateString()}
                            </small>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-${getStatusColor(report.status)}">
                                <i class="fas fa-${getStatusIconClass(report.status)} me-1"></i>
                                ${report.status}
                            </span>
                        </div>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                markerCluster.addLayer(marker);
            });
            
            reportsMap.addLayer(markerCluster);

            // Fit map to show all markers
            if (reports.length > 0) {
                const group = new L.featureGroup(markerCluster.getLayers());
                reportsMap.fitBounds(group.getBounds().pad(0.1));
            }

            // Reinitialize map when tab is shown
            document.querySelector('a[href="#mapView"]').addEventListener('shown.bs.tab', function() {
                setTimeout(() => {
                    reportsMap.invalidateSize();
                    if (reports.length > 0) {
                        const group = new L.featureGroup(markerCluster.getLayers());
                        reportsMap.fitBounds(group.getBounds().pad(0.1));
                    }
                }, 100);
            });
        }

        // Helper functions for map
        function getSeverityColorHex(severity) {
            switch(severity) {
                case 'low': return '#22c55e';
                case 'medium': return '#eab308';
                case 'high': return '#ef4444';
                default: return '#6b7280';
            }
        }

        function getReportIconClass(type) {
            switch(type) {
                case 'accident': return 'car-crash';
                case 'congestion': return 'traffic-light';
                case 'road_work': return 'road';
                case 'hazard': return 'exclamation-triangle';
                case 'police': return 'shield-alt';
                default: return 'exclamation-circle';
            }
        }

        function getStatusIconClass(status) {
            switch(status) {
                case 'pending': return 'clock';
                case 'investigating': return 'search';
                case 'resolved': return 'check';
                default: return 'question';
            }
        }

        function getSeverityColor(severity) {
            switch(severity) {
                case 'low': return 'success';
                case 'medium': return 'warning';
                case 'high': return 'danger';
                default: return 'secondary';
            }
        }

        function getStatusColor(status) {
            switch(status) {
                case 'pending': return 'warning';
                case 'investigating': return 'info';
                case 'resolved': return 'success';
                default: return 'secondary';
            }
        }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Helper functions
function getReportIcon($type) {
    switch($type) {
        case 'accident': return 'car-crash';
        case 'congestion': return 'traffic-light';
        case 'road_work': return 'road';
        case 'hazard': return 'exclamation-triangle';
        case 'police': return 'shield-alt';
        default: return 'exclamation-circle';
    }
}

function getSeverityColor($severity) {
    switch($severity) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'danger';
        default: return 'secondary';
    }
}

function getStatusColor($status) {
    switch($status) {
        case 'pending': return 'warning';
        case 'investigating': return 'info';
        case 'resolved': return 'success';
        default: return 'secondary';
    }
}

function getStatusIcon($status) {
    switch($status) {
        case 'pending': return 'clock';
        case 'investigating': return 'search';
        case 'resolved': return 'check';
        default: return 'question';
    }
}
?>