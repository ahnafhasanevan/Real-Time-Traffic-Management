<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();
$db = getDBConnection();

// Handle penalty payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_penalty'])) {
    $penalty_id = (int)$_POST['penalty_id'];
    $check = $db->prepare("SELECT * FROM traffic_penalties WHERE penalty_id = ? AND user_id = ?");
    $check->bind_param("ii", $penalty_id, $user['user_id']);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $stmt = $db->prepare("UPDATE traffic_penalties SET is_paid = TRUE, paid_date = NOW() WHERE penalty_id = ?");
        $stmt->bind_param("i", $penalty_id);
        if ($stmt->execute()) {
            $success = "Penalty paid successfully!";
            
            // Log the payment action
            if (function_exists('logAction')) {
                logAction($db, $user['user_id'], 'pay_penalty', "Paid penalty #$penalty_id");
            }
            
            // Send confirmation notification
            if (function_exists('sendNotification')) {
                sendNotification($db, $user['user_id'], 'system', 'Payment Confirmed', "Your penalty #$penalty_id has been paid successfully.");
            }
        } else {
            $error = "Error processing payment: " . $stmt->error;
        }
    } else {
        $error = "Penalty not found or access denied.";
    }
}

// Get penalties with correct column names based on your schema
$penalties = $db->prepare("
    SELECT 
        tp.penalty_id,
        tp.penalty_type,
        tp.fine_amount,
        tp.issue_date,
        tp.due_date,
        tp.is_paid,
        tp.paid_date,
        v.license_plate,
        v.vehicle_type,
        v.make,
        v.model,
        v.color
    FROM traffic_penalties tp 
    LEFT JOIN vehicles v ON tp.vehicle_id = v.vehicle_id 
    WHERE tp.user_id = ? 
    ORDER BY tp.issue_date DESC
");
$penalties->bind_param("i", $user['user_id']);
$penalties->execute();
$result = $penalties->get_result();

// Get comprehensive statistics
$totals = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_paid = 0 THEN 1 ELSE 0 END) as unpaid,
        SUM(CASE WHEN is_paid = 0 THEN fine_amount ELSE 0 END) as amount_due,
        SUM(CASE WHEN is_paid = 1 THEN fine_amount ELSE 0 END) as amount_paid,
        SUM(fine_amount) as total_amount
    FROM traffic_penalties 
    WHERE user_id = ?
");
$totals->bind_param("i", $user['user_id']);
$totals->execute();
$stats = $totals->get_result()->fetch_assoc();

// Get overdue penalties count
$overdue = $db->prepare("
    SELECT COUNT(*) as overdue_count 
    FROM traffic_penalties 
    WHERE user_id = ? AND is_paid = 0 AND due_date < CURDATE()
");
$overdue->bind_param("i", $user['user_id']);
$overdue->execute();
$overdue_count = $overdue->get_result()->fetch_assoc()['overdue_count'];

// Define violation types for display
$violation_types = [
    'speeding' => 'Over-speeding',
    'red_light_violation' => 'Red Light Violation',
    'parking_violation' => 'Parking Violation',
    'no_seatbelt' => 'No Seatbelt',
    'no_helmet' => 'No Helmet',
    'mobile_use' => 'Mobile Phone Use',
    'drunk_driving' => 'Drunk Driving',
    'no_license' => 'No License',
    'reckless_driving' => 'Reckless Driving',
    'illegal_modification' => 'Illegal Modification',
    'overloading' => 'Overloading',
    'wrong_way' => 'Wrong Way Driving',
    'other' => 'Other Violation'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Penalties - Dhaka Traffic Management</title>
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
                    <h2 class="text-light"><i class="fas fa-receipt me-2"></i>My Traffic Penalties</h2>
                    <!-- Removed "Report Incident" and "My Vehicles" buttons -->
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

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card cyber-card text-center p-3">
                            <div class="card-body">
                                <h4 class="text-light"><?= $stats['total'] ?></h4>
                                <small class="text-muted">Total Penalties</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card cyber-card text-center p-3">
                            <div class="card-body">
                                <h4 class="text-danger"><?= $stats['unpaid'] ?></h4>
                                <small class="text-muted">Unpaid Penalties</small>
                                <?php if ($overdue_count > 0): ?>
                                    <div class="mt-1">
                                        <span class="badge bg-warning"><?= $overdue_count ?> Overdue</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card cyber-card text-center p-3">
                            <div class="card-body">
                                <h4 class="text-warning">৳<?= number_format($stats['amount_due'], 2) ?></h4>
                                <small class="text-muted">Amount Due</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card cyber-card text-center p-3">
                            <div class="card-body">
                                <h4 class="text-success">৳<?= number_format($stats['amount_paid'], 2) ?></h4>
                                <small class="text-muted">Total Paid</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Penalties Table -->
                <div class="card cyber-card">
                    <div class="card-header">
                        <h5 class="mb-0 text-light"><i class="fas fa-list me-2"></i>Penalty History</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-dark">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Issue Date</th>
                                            <th>Vehicle</th>
                                            <th>Violation Type</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($p = $result->fetch_assoc()): 
                                            $is_overdue = !$p['is_paid'] && strtotime($p['due_date']) < time();
                                            $status_class = $p['is_paid'] ? 'success' : ($is_overdue ? 'danger' : 'warning');
                                            $status_text = $p['is_paid'] ? 'Paid' : ($is_overdue ? 'Overdue' : 'Pending');
                                        ?>
                                        <tr class="<?= $is_overdue ? 'table-danger' : '' ?>">
                                            <td class="text-light">#<?= $p['penalty_id'] ?></td>
                                            <td class="text-light"><?= date('M j, Y', strtotime($p['issue_date'])) ?></td>
                                            <td>
                                                <?php if ($p['license_plate']): ?>
                                                    <div class="fw-bold text-light"><?= htmlspecialchars($p['license_plate']) ?></div>
                                                    <small class="text-muted"><?= ucfirst($p['vehicle_type']) ?></small>
                                                    <?php if ($p['make']): ?>
                                                        <br><small class="text-muted"><?= $p['make'] ?> <?= $p['model'] ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No vehicle specified</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $violation_types[$p['penalty_type']] ?? ucfirst(str_replace('_', ' ', $p['penalty_type'])) ?></span>
                                            </td>
                                            <td class="text-warning fw-bold">৳<?= number_format($p['fine_amount'], 2) ?></td>
                                            <td class="text-light">
                                                <?= date('M j, Y', strtotime($p['due_date'])) ?>
                                                <?php if ($is_overdue): ?>
                                                    <br><small class="text-danger">Overdue!</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                                <?php if ($p['is_paid'] && $p['paid_date']): ?>
                                                    <br><small class="text-muted">Paid: <?= date('M j, Y', strtotime($p['paid_date'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$p['is_paid']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="penalty_id" value="<?= $p['penalty_id'] ?>">
                                                        <button type="submit" name="pay_penalty" class="btn btn-sm btn-success" onclick="return confirm('Confirm payment of ৳<?= number_format($p['fine_amount'], 2) ?>?')">
                                                            <i class="fas fa-credit-card me-1"></i>Pay Now
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i> Paid</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-smile fa-4x text-success mb-3"></i>
                                <h4 class="text-light">No penalties found!</h4>
                                <p class="text-muted">You have a clean traffic record.</p>
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
            new DarkVeil(document.body);
            
            // Add confirmation for payment
            const payButtons = document.querySelectorAll('button[name="pay_penalty"]');
            payButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to pay this penalty?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>