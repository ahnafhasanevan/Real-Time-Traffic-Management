<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if ($user['user_type'] !== 'admin' && $user['user_type'] !== 'traffic_manager') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $emergency_id = (int)$_POST['emergency_id'];
    $is_responding = (int)$_POST['is_responding'];
    
    $stmt = $db->prepare("UPDATE emergency_vehicles SET is_responding = ? WHERE emergency_id = ?");
    $stmt->bind_param("ii", $is_responding, $emergency_id);
    
    if ($stmt->execute()) {
        $success = "Status updated successfully!";
    }
}

// Get all emergency vehicles
$vehicles = $db->query("
    SELECT ev.*, v.license_plate, v.make, v.model 
    FROM emergency_vehicles ev
    LEFT JOIN vehicles v ON ev.vehicle_id = v.vehicle_id
    ORDER BY ev.is_responding DESC, ev.emergency_id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Vehicles - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .vehicle-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .vehicle-card.responding {
            border-left-color: #e74c3c;
            animation: pulse 2s infinite;
        }
        .vehicle-card.standby {
            border-left-color: #27ae60;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 10px rgba(231, 76, 60, 0.5); }
            50% { box-shadow: 0 0 20px rgba(231, 76, 60, 0.8); }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container mt-4">
                <h2 class="text-light mb-4"><i class="fas fa-ambulance me-2"></i>Emergency Vehicles</h2>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($vehicles->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card cyber-card vehicle-card <?php echo $vehicle['is_responding'] ? 'responding' : 'standby'; ?> h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title text-light">
                                                    <?php echo htmlspecialchars($vehicle['license_plate'] ?? 'N/A'); ?>
                                                </h5>
                                                <span class="badge bg-<?php echo $vehicle['is_responding'] ? 'danger' : 'success'; ?>">
                                                    <?php echo $vehicle['is_responding'] ? 'RESPONDING' : 'STANDBY'; ?>
                                                </span>
                                            </div>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($vehicle['emergency_type']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="text-light mb-2">
                                                <i class="fas fa-map-marker-alt me-2 text-success"></i>
                                                <strong>Current:</strong> <?php echo htmlspecialchars($vehicle['current_location']); ?>
                                            </div>
                                            <?php if ($vehicle['is_responding']): ?>
                                                <div class="text-light">
                                                    <i class="fas fa-flag-checkered me-2 text-danger"></i>
                                                    <strong>Destination:</strong> <?php echo htmlspecialchars($vehicle['destination']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form method="POST" class="d-grid">
                                            <input type="hidden" name="emergency_id" value="<?php echo $vehicle['emergency_id']; ?>">
                                            <input type="hidden" name="is_responding" value="<?php echo $vehicle['is_responding'] ? 0 : 1; ?>">
                                            <button type="submit" name="update_status" class="btn btn-<?php echo $vehicle['is_responding'] ? 'success' : 'danger'; ?>">
                                                <i class="fas fa-<?php echo $vehicle['is_responding'] ? 'check' : 'bell'; ?> me-2"></i>
                                                <?php echo $vehicle['is_responding'] ? 'Mark as Standby' : 'Dispatch Now'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card cyber-card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-ambulance fa-3x text-muted mb-3"></i>
                            <h4 class="text-light">No emergency vehicles registered</h4>
                            <p class="text-muted">Contact system administrator to add vehicles</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="darkveil.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body);
        });
        
        // Auto-refresh every 30 seconds
        setInterval(() => location.reload(), 30000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>