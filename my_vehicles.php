<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();
$db = getDBConnection();

// Handle vehicle addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $license_plate = strtoupper(trim($_POST['license_plate']));
    $vehicle_type = $_POST['vehicle_type'];
    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $color = trim($_POST['color']);
    $year = (int)$_POST['year'];
    
    // Check if license plate already exists
    $check = $db->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate = ?");
    $check->bind_param("s", $license_plate);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "A vehicle with this license plate already exists";
    } else {
        $stmt = $db->prepare("INSERT INTO vehicles (user_id, license_plate, vehicle_type, make, model, color, year) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssi", $user['user_id'], $license_plate, $vehicle_type, $make, $model, $color, $year);
        
        if ($stmt->execute()) {
            $success = "Vehicle added successfully!";
            // Log the action
            $log_stmt = $db->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'add_vehicle', ?, ?)");
            $desc = "Added vehicle: $license_plate";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iss", $user['user_id'], $desc, $ip);
            $log_stmt->execute();
        } else {
            $error = "Error adding vehicle";
        }
    }
}

// Handle vehicle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $vehicle_id = (int)$_GET['delete'];
    
    // Verify ownership
    $check = $db->prepare("SELECT license_plate FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
    $check->bind_param("ii", $vehicle_id, $user['user_id']);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $vehicle = $result->fetch_assoc();
        $stmt = $db->prepare("DELETE FROM vehicles WHERE vehicle_id = ?");
        $stmt->bind_param("i", $vehicle_id);
        
        if ($stmt->execute()) {
            $success = "Vehicle deleted successfully!";
            // Log the action
            $log_stmt = $db->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'delete_vehicle', ?, ?)");
            $desc = "Deleted vehicle: " . $vehicle['license_plate'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iss", $user['user_id'], $desc, $ip);
            $log_stmt->execute();
        } else {
            $error = "Error deleting vehicle";
        }
    } else {
        $error = "Vehicle not found or you don't have permission";
    }
}

// Get user's vehicles with penalty information
$vehicles_query = $db->prepare("
    SELECT v.*, 
           COUNT(DISTINCT tp.penalty_id) as total_penalties,
           SUM(CASE WHEN tp.is_paid = FALSE THEN 1 ELSE 0 END) as unpaid_penalties,
           SUM(CASE WHEN tp.is_paid = FALSE THEN tp.fine_amount ELSE 0 END) as unpaid_amount
    FROM vehicles v
    LEFT JOIN traffic_penalties tp ON v.vehicle_id = tp.vehicle_id
    WHERE v.user_id = ?
    GROUP BY v.vehicle_id
    ORDER BY v.vehicle_id DESC
");
$vehicles_query->bind_param("i", $user['user_id']);
$vehicles_query->execute();
$vehicles = $vehicles_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicles - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light"><i class="fas fa-car-side me-2"></i>My Vehicles</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                        <i class="fas fa-plus me-2"></i>Add Vehicle
                    </button>
                </div>

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

                <?php if ($vehicles->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card cyber-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title text-light"><?php echo htmlspecialchars($vehicle['license_plate']); ?></h5>
                                                <p class="text-muted mb-0">
                                                    <?php echo htmlspecialchars($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                                                </p>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="?delete=<?php echo $vehicle['vehicle_id']; ?>" onclick="return confirm('Delete this vehicle?')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted">Type</small>
                                                <div class="text-light"><?php echo ucfirst($vehicle['vehicle_type']); ?></div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Color</small>
                                                <div class="text-light"><?php echo htmlspecialchars($vehicle['color']); ?></div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <h4 class="mb-0 text-<?php echo $vehicle['unpaid_penalties'] > 0 ? 'danger' : 'success'; ?>">
                                                    <?php echo $vehicle['unpaid_penalties']; ?>
                                                </h4>
                                                <small class="text-muted">Unpaid Penalties</small>
                                            </div>
                                            <div class="col-6">
                                                <h4 class="mb-0 text-info"><?php echo $vehicle['total_penalties']; ?></h4>
                                                <small class="text-muted">Total Penalties</small>
                                            </div>
                                        </div>
                                        
                                        <?php if ($vehicle['unpaid_amount'] > 0): ?>
                                        <div class="alert alert-warning mt-3 mb-0">
                                            <small><i class="fas fa-exclamation-triangle me-2"></i>
                                            Outstanding: ৳<?php echo number_format($vehicle['unpaid_amount'], 2); ?></small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card cyber-card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-car-side fa-3x text-muted mb-3"></i>
                            <h4 class="text-light">No vehicles registered</h4>
                            <p class="text-muted">Add your first vehicle to get started</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                                <i class="fas fa-plus me-2"></i>Add Vehicle
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Vehicle Modal -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: rgba(25, 30, 50, 0.95); color: #e0e0ff;">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Vehicle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">License Plate *</label>
                            <input type="text" class="form-control" name="license_plate" required placeholder="e.g., DHAKA-GA-11-2222">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Vehicle Type *</label>
                            <select class="form-select" name="vehicle_type" required>
                                <option value="">Select type</option>
                                <option value="car">Car</option>
                                <option value="motorcycle">Motorcycle</option>
                                <option value="truck">Truck</option>
                                <option value="bus">Bus</option>
                                <option value="van">Van</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Make *</label>
                                <input type="text" class="form-control" name="make" required placeholder="e.g., Toyota">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model *</label>
                                <input type="text" class="form-control" name="model" required placeholder="e.g., Corolla">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Color *</label>
                                <input type="text" class="form-control" name="color" required placeholder="e.g., Black">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year *</label>
                                <input type="number" class="form-control" name="year" required min="1900" max="2025" value="2023">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_vehicle" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Vehicle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="darkveil.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body, {
                speed: 0.5,
                particleCount: 60,
                connectionDistance: 100
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>