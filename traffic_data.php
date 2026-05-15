<?php
require_once 'config.php';
requireAuth();

$db = getDBConnection();

// Get traffic data with filters
$where = ["1=1"];
$params = [];
$types = '';

if (isset($_GET['congestion']) && $_GET['congestion'] !== '') {
    $where[] = "td.congestion_level = ?";
    $params[] = $_GET['congestion'];
    $types .= 's';
}
if (isset($_GET['road']) && $_GET['road'] !== '' && is_numeric($_GET['road'])) {
    $where[] = "r.road_id = ?";
    $params[] = (int)$_GET['road'];
    $types .= 'i';
}

$where_clause = implode(" AND ", $where);

$query = "
    SELECT r.road_name, rs.segment_name, rs.segment_id, td.average_speed, td.congestion_level, 
           td.timestamp, td.data_source, td.vehicle_count
    FROM traffic_data td 
    JOIN road_segments rs ON td.segment_id = rs.segment_id 
    JOIN roads r ON rs.road_id = r.road_id 
    WHERE $where_clause AND td.timestamp >= NOW() - INTERVAL 2 HOUR
    ORDER BY td.timestamp DESC
    LIMIT 100
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$traffic_data = $stmt->get_result();

// Get all roads for filter
$roads = $db->query("SELECT * FROM roads ORDER BY road_name");

// Get statistics
$stats_query = "
    SELECT 
        AVG(average_speed) as avg_speed,
        COUNT(DISTINCT segment_id) as active_segments,
        SUM(CASE WHEN congestion_level = 'high' OR congestion_level = 'severe' THEN 1 ELSE 0 END) as congested_count
    FROM traffic_data 
    WHERE timestamp >= NOW() - INTERVAL 30 MINUTE
";
$stats = $db->query($stats_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Traffic Data - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .traffic-low { background: rgba(40, 167, 69, 0.1) !important; }
        .traffic-medium { background: rgba(255, 193, 7, 0.1) !important; }
        .traffic-high { background: rgba(253, 126, 20, 0.1) !important; }
        .traffic-severe { background: rgba(220, 53, 69, 0.1) !important; }
        
        .stat-card {
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .speed-indicator {
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light">
                        <i class="fas fa-car me-2"></i>Live Traffic Data
                        <span class="badge bg-success">Live</span>
                    </h2>
                    <button class="btn btn-primary" onclick="refreshData()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card cyber-card stat-card">
                            <i class="fas fa-tachometer-alt fa-2x text-primary mb-2"></i>
                            <div class="speed-indicator text-light"><?php echo round($stats['avg_speed'] ?? 0, 1); ?> km/h</div>
                            <small class="text-muted">Average Speed</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card cyber-card stat-card">
                            <i class="fas fa-road fa-2x text-info mb-2"></i>
                            <div class="speed-indicator text-light"><?php echo $stats['active_segments'] ?? 0; ?></div>
                            <small class="text-muted">Active Segments</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card cyber-card stat-card">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                            <div class="speed-indicator text-light"><?php echo $stats['congested_count'] ?? 0; ?></div>
                            <small class="text-muted">Congested Areas</small>
                        </div>
                    </div>
                </div>

                <div class="card cyber-card shadow">
                    <div class="card-header">
                        <h5 class="mb-0 text-light">
                            <i class="fas fa-filter me-2"></i>Filter Traffic Data
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-5">
                                <label class="form-label text-light">Road</label>
                                <select class="form-select" name="road">
                                    <option value="">All Roads</option>
                                    <?php 
                                    $roads->data_seek(0); // Reset pointer
                                    while ($road = $roads->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $road['road_id']; ?>" 
                                            <?php echo (isset($_GET['road']) && $_GET['road'] == $road['road_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($road['road_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-light">Congestion Level</label>
                                <select class="form-select" name="congestion">
                                    <option value="">All Levels</option>
                                    <option value="low" <?php echo (isset($_GET['congestion']) && $_GET['congestion'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo (isset($_GET['congestion']) && $_GET['congestion'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo (isset($_GET['congestion']) && $_GET['congestion'] == 'high') ? 'selected' : ''; ?>>High</option>
                                    <option value="severe" <?php echo (isset($_GET['congestion']) && $_GET['congestion'] == 'severe') ? 'selected' : ''; ?>>Severe</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100 me-2">Filter</button>
                                <a href="traffic_data.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>

                        <!-- Traffic Data Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Road</th>
                                        <th>Segment</th>
                                        <th>Speed (km/h)</th>
                                        <th>Vehicles</th>
                                        <th>Congestion</th>
                                        <th>Source</th>
                                        <th>Last Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($traffic_data->num_rows > 0): ?>
                                        <?php while ($row = $traffic_data->fetch_assoc()): ?>
                                            <tr class="align-middle traffic-<?php echo $row['congestion_level']; ?>">
                                                <td class="text-light">
                                                    <strong><?php echo htmlspecialchars($row['road_name']); ?></strong>
                                                </td>
                                                <td class="text-light">
                                                    <?php echo htmlspecialchars($row['segment_name'] ?? 'N/A'); ?>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-light">
                                                        <?php echo $row['average_speed']; ?>
                                                    </span>
                                                </td>
                                                <td class="text-light">
                                                    <?php echo $row['vehicle_count'] ?? 'N/A'; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getCongestionBadgeColor($row['congestion_level']); ?>">
                                                        <?php echo ucfirst($row['congestion_level']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst(str_replace('_', ' ', $row['data_source'])); ?>
                                                    </span>
                                                </td>
                                                <td class="text-light">
                                                    <?php 
                                                    $time_diff = time() - strtotime($row['timestamp']);
                                                    if ($time_diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($time_diff < 3600) {
                                                        echo floor($time_diff / 60) . ' min ago';
                                                    } else {
                                                        echo date('H:i', strtotime($row['timestamp']));
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                                                <p class="text-muted mb-0">No traffic data available for the selected filters</p>
                                                <small class="text-muted">Try adjusting your filters or check back later</small>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($traffic_data->num_rows > 0): ?>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing data from the last 2 hours. Auto-refreshes every 2 minutes.
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="darkveil.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body, {
                speed: 0.6,
                particleCount: 60,
                connectionDistance: 100,
                colors: ['#667eea', '#764ba2', '#4facfe', '#00f2fe']
            });
        });

        function refreshData() {
            location.reload();
        }
        
        // Auto-refresh every 2 minutes
        setInterval(refreshData, 120000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function getCongestionBadgeColor($level) {
    switch($level) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'danger';
        case 'severe': return 'dark';
        default: return 'secondary';
    }
}
?>