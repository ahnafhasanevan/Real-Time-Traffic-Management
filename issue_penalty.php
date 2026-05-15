<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if ($user['user_type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Handle penalty creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_penalty'])) {
    $license_plate = trim($_POST['license_plate']);
    $fine_amount = (float)$_POST['fine_amount'];
    $violation_type = $_POST['violation_type'];
    $due_date = $_POST['due_date'];
    
    // Find user by vehicle license plate
    $target_user_id = null;
    $vehicle_id = null;
    
    if (!empty($license_plate)) {
        $find_vehicle = $db->prepare("
            SELECT v.vehicle_id, v.user_id, v.vehicle_type, v.make, v.model, v.color,
                   u.username, u.first_name, u.last_name, u.phone_number, u.email 
            FROM vehicles v 
            JOIN users u ON v.user_id = u.user_id 
            WHERE v.license_plate = ? AND u.is_active = TRUE
        ");
        $find_vehicle->bind_param("s", $license_plate);
        $find_vehicle->execute();
        $vehicle_result = $find_vehicle->get_result();
        
        if ($vehicle_result->num_rows > 0) {
            $vehicle_data = $vehicle_result->fetch_assoc();
            $target_user_id = $vehicle_data['user_id'];
            $vehicle_id = $vehicle_data['vehicle_id'];
            $vehicle_type = $vehicle_data['vehicle_type']; // Use vehicle type from database
        }
    }
    
    if (!empty($license_plate)) {
        // Use the correct column names from your schema (remove description)
        $stmt = $db->prepare("INSERT INTO traffic_penalties (user_id, vehicle_id, penalty_type, fine_amount, issue_date, due_date, is_paid) VALUES (?, ?, ?, ?, NOW(), ?, FALSE)");
        $stmt->bind_param("iisds", $target_user_id, $vehicle_id, $violation_type, $fine_amount, $due_date);
        
        if ($stmt->execute()) {
            $penalty_id = $stmt->insert_id;
            $success = "Penalty issued successfully! Penalty ID: #$penalty_id";
            
            // Send notification to user if vehicle was found
            if ($target_user_id) {
                $violation_name = $violations[$violation_type]['name'] ?? ucfirst(str_replace('_', ' ', $violation_type));
                $message = "You have been issued a traffic penalty of ৳" . number_format($fine_amount, 2) . " for " . $violation_name . " (Vehicle: $license_plate). Due date: " . date('M j, Y', strtotime($due_date));
                
                if (function_exists('sendNotification')) {
                    sendNotification($db, $target_user_id, 'penalty', 'Traffic Penalty Issued', $message);
                }
            }
            
            // Log action
            if (function_exists('logAction')) {
                logAction($db, $user['user_id'], 'issue_penalty', "Issued penalty #$penalty_id for vehicle $license_plate - ৳$fine_amount for $violation_type");
            }
        } else {
            $error = "Error issuing penalty: " . $stmt->error;
        }
    } else {
        $error = "License plate is required.";
    }
}

// Predefined violation types with amounts
$violations = [
    'speeding' => ['name' => 'Over-speeding', 'amount' => 500],
    'red_light_violation' => ['name' => 'Red Light Violation', 'amount' => 1000],
    'parking_violation' => ['name' => 'Wrong Parking', 'amount' => 300],
    'no_seatbelt' => ['name' => 'No Seatbelt', 'amount' => 300],
    'no_helmet' => ['name' => 'No Helmet (Motorcycle)', 'amount' => 500],
    'mobile_use' => ['name' => 'Mobile Phone Use While Driving', 'amount' => 1000],
    'drunk_driving' => ['name' => 'Drunk Driving', 'amount' => 5000],
    'no_license' => ['name' => 'Driving Without License', 'amount' => 2000],
    'reckless_driving' => ['name' => 'Reckless Driving', 'amount' => 1500],
    'illegal_modification' => ['name' => 'Illegal Vehicle Modification', 'amount' => 2000],
    'overloading' => ['name' => 'Overloading', 'amount' => 1500],
    'wrong_way' => ['name' => 'Wrong Way Driving', 'amount' => 750],
    'other' => ['name' => 'Other Violation', 'amount' => 500],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Penalty/Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .vehicle-info-card {
            background: rgba(40, 50, 80, 0.9);
            border: 2px solid #0dcaf0;
            border-radius: 10px;
        }
        .owner-info-card {
            background: rgba(40, 80, 50, 0.9);
            border: 2px solid #20c997;
            border-radius: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #0dcaf0;
        }
        .info-value {
            color: #ffffff;
        }
        .disabled-field {
            background-color: #2a3042 !important;
            color: #8a9ba8 !important;
            border: 1px solid #3a4155 !important;
            cursor: not-allowed !important;
        }
        .enabled-field {
            background-color: #1e222e !important;
            color: #ffffff !important;
            border: 1px solid #495057 !important;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light"><i class="fas fa-gavel me-2"></i>Issue Traffic Penalty/Ticket</h2>
                    <a href="admin_dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Admin
                    </a>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light"><i class="fas fa-file-invoice me-2"></i>Penalty Details</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="penaltyForm">
                                    <!-- Vehicle Input Section -->
                                    <div class="mb-4">
                                        <label class="form-label text-light"><i class="fas fa-car me-2"></i>Vehicle License Plate *</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="licensePlate" name="license_plate" 
                                                   placeholder="Enter vehicle license plate (e.g., DHAKA-GA-11-1234)" 
                                                   required
                                                   onblur="searchVehicle(this.value)">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        </div>
                                        <small class="text-muted">Enter the vehicle license plate and the system will automatically fetch vehicle and owner details</small>
                                    </div>

                                    <!-- Vehicle Information (Auto-filled) -->
                                    <div id="vehicleInfo" class="vehicle-info-card p-3 mb-3" style="display: none;">
                                        <h6 class="text-light mb-3"><i class="fas fa-car me-2"></i>Vehicle Information</h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <span class="info-label">Vehicle Type:</span>
                                                <span class="info-value" id="vehicleTypeDisplay">-</span>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <span class="info-label">Make/Model:</span>
                                                <span class="info-value" id="vehicleMakeModel">-</span>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <span class="info-label">Color:</span>
                                                <span class="info-value" id="vehicleColor">-</span>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <span class="info-label">Year:</span>
                                                <span class="info-value" id="vehicleYear">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Owner Information (Auto-filled) -->
                                    <div id="ownerInfo" class="owner-info-card p-3 mb-3" style="display: none;">
                                        <h6 class="text-light mb-3"><i class="fas fa-user me-2"></i>Owner Information</h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <span class="info-label">Username:</span>
                                                <span class="info-value" id="ownerUsername">-</span>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <span class="info-label">Full Name:</span>
                                                <span class="info-value" id="ownerFullName">-</span>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <span class="info-label">Phone:</span>
                                                <span class="info-value" id="ownerPhone">-</span>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <span class="info-label">Email:</span>
                                                <span class="info-value" id="ownerEmail">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vehicle Not Found Message -->
                                    <div id="vehicleNotFound" class="alert alert-warning mb-3" style="display: none;">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Vehicle not found in system.</strong> You need to manually select the vehicle type below.
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Vehicle Type *</label>
                                            <select class="form-select enabled-field" name="vehicle_type" id="vehicleType" required disabled>
                                                <option value="">Select vehicle type...</option>
                                                <option value="car">Car</option>
                                                <option value="motorcycle">Motorcycle</option>
                                                <option value="truck">Truck</option>
                                                <option value="bus">Bus</option>
                                                <option value="van">Van</option>
                                            </select>
                                            <small class="text-muted" id="vehicleTypeHelp">Vehicle type will be automatically set when vehicle is found</small>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Violation Type *</label>
                                            <select class="form-select" name="violation_type" id="violationType" required onchange="updateAmount()">
                                                <option value="">Select violation...</option>
                                                <?php foreach ($violations as $key => $v): ?>
                                                    <option value="<?php echo $key; ?>" data-amount="<?php echo $v['amount']; ?>">
                                                        <?php echo $v['name']; ?> - ৳<?php echo number_format($v['amount'], 2); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Fine Amount (৳) *</label>
                                            <input type="number" class="form-control" name="fine_amount" id="fineAmount" required min="100" step="0.01" value="500">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label text-light">Due Date *</label>
                                            <input type="date" class="form-control" name="due_date" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>

                                    <button type="submit" name="issue_penalty" class="btn btn-danger btn-lg w-100">
                                        <i class="fas fa-gavel me-2"></i>Issue Penalty
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card cyber-card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0 text-light"><i class="fas fa-info-circle me-2"></i>Penalty Guidelines</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Violation</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($violations as $key => $v): ?>
                                            <tr>
                                                <td class="text-light"><?php echo $v['name']; ?></td>
                                                <td class="text-warning">৳<?php echo number_format($v['amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card cyber-card">
                            <div class="card-header">
                                <h6 class="mb-0 text-light"><i class="fas fa-lightbulb me-2"></i>Quick Tips</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Enter license plate to auto-fill details</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Vehicle type is set automatically</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>System finds vehicle owner automatically</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Owner gets automatic notification</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="darkveil.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body);
        });

        // Auto-search vehicle when license plate is entered
        function searchVehicle(licensePlate) {
            if (!licensePlate.trim()) {
                hideAllInfo();
                disableVehicleType();
                return;
            }

            // Show loading state
            document.getElementById('vehicleInfo').style.display = 'block';
            document.getElementById('vehicleInfo').innerHTML = `
                <div class="text-center text-light">
                    <i class="fas fa-spinner fa-spin me-2"></i>Searching for vehicle...
                </div>
            `;
            document.getElementById('ownerInfo').style.display = 'none';
            document.getElementById('vehicleNotFound').style.display = 'none';

            fetch('search_vehicle.php?license_plate=' + encodeURIComponent(licensePlate))
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        // Vehicle not found
                        document.getElementById('vehicleInfo').style.display = 'none';
                        document.getElementById('ownerInfo').style.display = 'none';
                        document.getElementById('vehicleNotFound').style.display = 'block';
                        enableVehicleType();
                    } else {
                        // Vehicle found - populate all fields
                        populateVehicleInfo(data);
                        populateOwnerInfo(data);
                        setVehicleType(data.vehicle_type);
                    }
                })
                .catch(e => {
                    console.error('Error searching vehicle:', e);
                    document.getElementById('vehicleInfo').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>Error searching vehicle
                        </div>
                    `;
                    enableVehicleType();
                });
        }

        function populateVehicleInfo(data) {
            document.getElementById('vehicleInfo').innerHTML = `
                <h6 class="text-light mb-3"><i class="fas fa-car me-2"></i>Vehicle Information</h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <span class="info-label">Vehicle Type:</span>
                        <span class="info-value">${data.vehicle_type.charAt(0).toUpperCase() + data.vehicle_type.slice(1)}</span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <span class="info-label">Make/Model:</span>
                        <span class="info-value">${data.make || 'N/A'} ${data.model || ''}</span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <span class="info-label">Color:</span>
                        <span class="info-value">${data.color || 'N/A'}</span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <span class="info-label">Year:</span>
                        <span class="info-value">${data.year || 'N/A'}</span>
                    </div>
                </div>
            `;
            document.getElementById('vehicleInfo').style.display = 'block';
        }

        function populateOwnerInfo(data) {
            document.getElementById('ownerInfo').innerHTML = `
                <h6 class="text-light mb-3"><i class="fas fa-user me-2"></i>Owner Information</h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <span class="info-label">Username:</span>
                        <span class="info-value">${data.username}</span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value">${data.first_name} ${data.last_name}</span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <span class="info-label">Phone:</span>
                        <span class="info-value">${data.phone_number || 'N/A'}</span>
                    </div>
                    <div class="col-md-6 mb-2">
                        <span class="info-label">Email:</span>
                        <span class="info-value">${data.email}</span>
                    </div>
                </div>
            `;
            document.getElementById('ownerInfo').style.display = 'block';
        }

        function setVehicleType(vehicleType) {
            const vehicleTypeSelect = document.getElementById('vehicleType');
            const vehicleTypeHelp = document.getElementById('vehicleTypeHelp');
            
            vehicleTypeSelect.value = vehicleType;
            vehicleTypeSelect.disabled = true;
            vehicleTypeSelect.classList.remove('enabled-field');
            vehicleTypeSelect.classList.add('disabled-field');
            
            vehicleTypeHelp.textContent = 'Vehicle type is automatically set from database';
            vehicleTypeHelp.className = 'text-success';
        }

        function enableVehicleType() {
            const vehicleTypeSelect = document.getElementById('vehicleType');
            const vehicleTypeHelp = document.getElementById('vehicleTypeHelp');
            
            vehicleTypeSelect.disabled = false;
            vehicleTypeSelect.classList.remove('disabled-field');
            vehicleTypeSelect.classList.add('enabled-field');
            
            vehicleTypeHelp.textContent = 'Select vehicle type manually (vehicle not found)';
            vehicleTypeHelp.className = 'text-warning';
        }

        function disableVehicleType() {
            const vehicleTypeSelect = document.getElementById('vehicleType');
            const vehicleTypeHelp = document.getElementById('vehicleTypeHelp');
            
            vehicleTypeSelect.value = '';
            vehicleTypeSelect.disabled = true;
            vehicleTypeSelect.classList.remove('enabled-field');
            vehicleTypeSelect.classList.add('disabled-field');
            
            vehicleTypeHelp.textContent = 'Vehicle type will be automatically set when vehicle is found';
            vehicleTypeHelp.className = 'text-muted';
        }

        function hideAllInfo() {
            document.getElementById('vehicleInfo').style.display = 'none';
            document.getElementById('ownerInfo').style.display = 'none';
            document.getElementById('vehicleNotFound').style.display = 'none';
        }

        function updateAmount() {
            const select = document.getElementById('violationType');
            const amount = select.options[select.selectedIndex].getAttribute('data-amount');
            if (amount) {
                document.getElementById('fineAmount').value = amount;
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>