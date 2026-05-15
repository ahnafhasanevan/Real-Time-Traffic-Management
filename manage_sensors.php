<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if (!isManager()) {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Handle sensor actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_sensor'])) {
        $sensor_id = (int)$_POST['sensor_id'];
        $is_active = (int)$_POST['is_active'];
        $stmt = $db->prepare("UPDATE traffic_sensors SET is_active = ? WHERE sensor_id = ?");
        $stmt->bind_param("ii", $is_active, $sensor_id);
        if ($stmt->execute()) {
            $success = "Sensor status updated!";
            logAction($db, $user['user_id'], 'toggle_sensor', "Changed sensor ID $sensor_id status");
        }
    }
    
    if (isset($_POST['update_maintenance'])) {
        $sensor_id = (int)$_POST['sensor_id'];
        $stmt = $db->prepare("UPDATE traffic_sensors SET last_maintenance = NOW() WHERE sensor_id = ?");
        $stmt->bind_param("i", $sensor_id);
        if ($stmt->execute()) {
            $success = "Maintenance record updated!";
            logAction($db, $user['user_id'], 'sensor_maintenance', "Updated maintenance for sensor ID $sensor_id");
        }
    }
    
    if (isset($_POST['add_sensor'])) {
        $segment_id = (int)$_POST['segment_id'];
        $installation_date = $_POST['installation_date'];
        $stmt = $db->prepare("INSERT INTO traffic_sensors (segment_id, installation_date, last_maintenance, is_active) VALUES (?, ?, NOW(), TRUE)");
        $stmt->bind_param("is", $segment_id, $installation_date);
        if ($stmt->execute()) {
            $success = "New sensor added!";
            logAction($db, $user['user_id'], 'add_sensor', "Added new sensor for segment ID $segment_id");
        }
    }
}

// Get all sensors with road information
$sensors = $db->query("
    SELECT ts.*, rs.segment_name, r.road_name,
           (SELECT COUNT(*) FROM traffic_data WHERE segment_id = ts.segment_id AND timestamp >= NOW() - INTERVAL 24 HOUR) as data_count_24h
    FROM traffic_sensors ts
    JOIN road_segments rs ON ts.segment_id = rs.segment_id
    JOIN roads r ON rs.road_id = r.road_id
    ORDER BY ts.is_active DESC, ts.sensor_id DESC
");

// Get road segments for adding new sensor
$segments = $db->query("SELECT rs.segment_id, rs.segment_name, r.road_name FROM road_segments rs JOIN roads r ON rs.road_id = r.road_id ORDER BY r.road_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sensors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container-fluid mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light"><i class="fas fa-microchip me-2"></i>Sensor Management</h2>
                    <div>
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addSensorModal">
                            <i class="fas fa-plus me-2"></i>Add Sensor
                        </button>
                        <a href="<?php echo $user['user_type'] == 'admin' ? 'admin_dashboard.php' : 'index.php'; ?>" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card cyber-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Sensor ID</th>
                                        <th>Location</th>
                                        <th>Installation Date</th>
                                        <th>Last Maintenance</th>
                                        <th>24h Data Points</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sensor = $sensors->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-light">#<?php echo $sensor['sensor_id']; ?></td>
                                            <td class="text-light">
                                                <strong><?php echo h($sensor['road_name']); ?></strong>
                                                <?php if ($sensor['segment_name']): ?>
                                                    <br><small class="text-muted"><?php echo h($sensor['segment_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-light"><?php echo date('M j, Y', strtotime($sensor['installation_date'])); ?></td>
                                            <td class="text-light">
                                                <?php echo $sensor['last_maintenance'] ? date('M j, Y', strtotime($sensor['last_maintenance'])) : 'Never'; ?>
                                                <?php 
                                                $days_since = $sensor['last_maintenance'] ? 
                                                    floor((time() - strtotime($sensor['last_maintenance'])) / 86400) : 999;
                                                if ($days_since > 180): ?>
                                                    <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Maintenance Due</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $sensor['data_count_24h'] > 0 ? 'success' : 'danger'; ?>">
                                                    <?php echo $sensor['data_count_24h']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($sensor['is_active']): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="sensor_id" value="<?php echo $sensor['sensor_id']; ?>">
                                                        <input type="hidden" name="is_active" value="<?php echo $sensor['is_active'] ? 0 : 1; ?>">
                                                        <button type="submit" name="toggle_sensor" class="btn btn-sm btn-<?php echo $sensor['is_active'] ? 'warning' : 'success'; ?>">
                                                            <i class="fas fa-power-off"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="sensor_id" value="<?php echo $sensor['sensor_id']; ?>">
                                                        <button type="submit" name="update_maintenance" class="btn btn-sm btn-info" title="Record Maintenance">
                                                            <i class="fas fa-wrench"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Sensor Modal -->
    <div class="modal fade" id="addSensorModal">
        <div class="modal-dialog">
            <div class="modal-content" style="background: rgba(25,30,50,0.95); color: #e0e0ff;">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Sensor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Road Segment *</label>
                            <select class="form-select" name="segment_id" required>
                                <option value="">Select segment</option>
                                <?php while ($seg = $segments->fetch_assoc()): ?>
                                    <option value="<?php echo $seg['segment_id']; ?>">
                                        <?php echo h($seg['road_name']); ?> - <?php echo h($seg['segment_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Installation Date *</label>
                            <input type="date" class="form-control" name="installation_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_sensor" class="btn btn-primary">Add Sensor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="darkveil.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>