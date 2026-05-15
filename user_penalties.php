<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if ($user['user_type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Get user_id from URL
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Handle penalty payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_penalty'])) {
    $penalty_id = (int)$_POST['penalty_id'];
    $stmt = $db->prepare("UPDATE traffic_penalties SET is_paid = TRUE WHERE penalty_id = ?");
    $stmt->bind_param("i", $penalty_id);
    if ($stmt->execute()) {
        $success = "Penalty marked as paid!";
        
        $penalty_info = $db->query("SELECT user_id, fine_amount FROM traffic_penalties WHERE penalty_id = $penalty_id")->fetch_assoc();
        sendNotification($db, $penalty_info['user_id'], 'penalty', 'Penalty Paid', "Your penalty of ৳" . number_format($penalty_info['fine_amount'], 2) . " has been marked as paid by admin.");
        logAction($db, $user['user_id'], 'pay_penalty_for_user', "Admin paid penalty ID $penalty_id");
    }
}

// Get user info
$user_info = $db->query("SELECT * FROM users WHERE user_id = $target_user_id")->fetch_assoc();

if (!$user_info) {
    header("Location: user_management.php");
    exit;
}

// Get user's penalties with vehicle info
$penalties = $db->query("
    SELECT tp.*, v.license_plate, v.make, v.model
    FROM traffic_penalties tp
    LEFT JOIN vehicles v ON tp.vehicle_id = v.vehicle_id
    WHERE tp.user_id = $target_user_id
    ORDER BY tp.issue_date DESC
");

// Calculate totals - FIXED QUERY
$totals = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_paid = TRUE THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN is_paid = FALSE THEN 1 ELSE 0 END) as unpaid_count,
        SUM(fine_amount) as total_amount,
        SUM(CASE WHEN is_paid = TRUE THEN fine_amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN is_paid = FALSE THEN fine_amount ELSE 0 END) as unpaid_amount
    FROM traffic_penalties
    WHERE user_id = $target_user_id
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Penalties - Admin</title>
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
                    <h2 class="text-light">
                        <i class="fas fa-receipt me-2"></i>Penalties for <?php echo h($user_info['username']); ?>
                    </h2>
                    <a href="user_management.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- User Info -->
                <div class="card cyber-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="text-light"><?php echo h($user_info['first_name'] . ' ' . $user_info['last_name']); ?></h5>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-envelope me-2"></i><?php echo h($user_info['email']); ?><br>
                                    <i class="fas fa-phone me-2"></i><?php echo h($user_info['phone_number'] ?: 'Not provided'); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <a href="issue_penalty.php?user_id=<?php echo $target_user_id; ?>" class="btn btn-danger">
                                    <i class="fas fa-plus me-2"></i>Issue New Penalty
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card cyber-card text-center p-3">
                            <h3 class="text-light"><?php echo $totals['total']; ?></h3>
                            <small class="text-muted">Total Penalties</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card cyber-card text-center p-3">
                            <h3 class="text-danger"><?php echo $totals['unpaid_count']; ?></h3>
                            <small class="text-muted">Unpaid</small>
                            <div class="mt-2"><small class="text-warning">৳<?php echo number_format($totals['unpaid_amount'], 2); ?></small></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card cyber-card text-center p-3">
                            <h3 class="text-success"><?php echo $totals['paid_count']; ?></h3>
                            <small class="text-muted">Paid</small>
                            <div class="mt-2"><small class="text-success">৳<?php echo number_format($totals['paid_amount'], 2); ?></small></div>
                        </div>
                    </div>
                </div>

                <!-- Penalties List -->
                <div class="card cyber-card">
                    <div class="card-body">
                        <?php if ($penalties->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Vehicle</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($p = $penalties->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-light"><?php echo date('M j, Y', strtotime($p['issue_date'])); ?></td>
                                                <td class="text-light">
                                                    <?php if ($p['license_plate']): ?>
                                                        <?php echo h($p['license_plate']); ?>
                                                        <br><small class="text-muted"><?php echo h($p['make'] . ' ' . $p['model']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">No vehicle</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $p['vehicle_type'])); ?></span></td>
                                                <td class="text-light"><strong>৳<?php echo number_format($p['fine_amount'], 2); ?></strong></td>
                                                <td class="text-light">
                                                    <?php 
                                                    $due = strtotime($p['due_date']);
                                                    $overdue = !$p['is_paid'] && $due < time();
                                                    ?>
                                                    <span class="<?php echo $overdue ? 'text-danger' : ''; ?>">
                                                        <?php echo date('M j, Y', $due); ?>
                                                    </span>
                                                    <?php if ($overdue): ?>
                                                        <br><small class="text-danger"><i class="fas fa-exclamation-circle"></i> Overdue</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($p['is_paid']): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check"></i> Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><i class="fas fa-clock"></i> Unpaid</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$p['is_paid']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="penalty_id" value="<?php echo $p['penalty_id']; ?>">
                                                            <button type="submit" name="pay_penalty" class="btn btn-sm btn-success" onclick="return confirm('Mark penalty as paid?')">
                                                                <i class="fas fa-check me-1"></i>Mark Paid
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-smile fa-3x text-success mb-3"></i>
                                <h4 class="text-light">No penalties found</h4>
                                <p class="text-muted">This user has a clean record</p>
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
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>