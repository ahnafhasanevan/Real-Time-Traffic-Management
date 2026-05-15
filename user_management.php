<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if ($user['user_type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status'])) {
        $user_id = (int)$_POST['user_id'];
        $is_active = (int)$_POST['is_active'];
        $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $is_active, $user_id);
        if ($stmt->execute()) {
            $action = $is_active ? 'activated' : 'blocked';
            $success = "User $action successfully!";
            logAction($db, $user['user_id'], 'toggle_user_status', "User ID $user_id $action");
            
            $msg = $is_active ? "Your account has been activated." : "Your account has been temporarily suspended.";
            sendNotification($db, $user_id, 'system', 'Account Status Update', $msg);
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND user_id != ?");
        $stmt->bind_param("ii", $user_id, $user['user_id']);
        if ($stmt->execute()) {
            $success = "User deleted successfully!";
            logAction($db, $user['user_id'], 'delete_user', "Deleted user ID $user_id");
        }
    }
    
    if (isset($_POST['change_role'])) {
        $user_id = (int)$_POST['user_id'];
        $new_role = $_POST['user_type'];
        
        // Validate role
        $valid_roles = ['public_user', 'traffic_manager', 'admin'];
        if (!in_array($new_role, $valid_roles)) {
            $error = "Invalid role selected!";
        } else {
            $stmt = $db->prepare("UPDATE users SET user_type = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            if ($stmt->execute()) {
                $success = "User role changed to " . ucfirst(str_replace('_', ' ', $new_role)) . " successfully!";
                logAction($db, $user['user_id'], 'change_user_role', "Changed user ID $user_id role to $new_role");
                sendNotification($db, $user_id, 'system', 'Role Changed', "Your account role has been changed to " . ucfirst(str_replace('_', ' ', $new_role)));
            } else {
                $error = "Error changing role: " . $db->error;
            }
        }
    }
    
    // Pay penalty for user
    if (isset($_POST['pay_penalty'])) {
        $penalty_id = (int)$_POST['penalty_id'];
        $stmt = $db->prepare("UPDATE traffic_penalties SET is_paid = TRUE WHERE penalty_id = ?");
        $stmt->bind_param("i", $penalty_id);
        if ($stmt->execute()) {
            $success = "Penalty marked as paid!";
            
            // Get penalty details for notification
            $penalty_info = $db->query("SELECT user_id, fine_amount FROM traffic_penalties WHERE penalty_id = $penalty_id")->fetch_assoc();
            sendNotification($db, $penalty_info['user_id'], 'penalty', 'Penalty Paid', "Your penalty of ৳" . number_format($penalty_info['fine_amount'], 2) . " has been marked as paid by admin.");
            logAction($db, $user['user_id'], 'pay_penalty', "Paid penalty ID $penalty_id for user");
        }
    }
}

// Get all users with statistics
$users_query = "
    SELECT u.*, 
           COUNT(DISTINCT utr.report_id) as report_count,
           COUNT(DISTINCT v.vehicle_id) as vehicle_count,
           COUNT(DISTINCT tp.penalty_id) as penalty_count,
           SUM(CASE WHEN tp.is_paid = FALSE THEN tp.fine_amount ELSE 0 END) as unpaid_amount
    FROM users u
    LEFT JOIN user_traffic_reports utr ON u.user_id = utr.user_id
    LEFT JOIN vehicles v ON u.user_id = v.user_id
    LEFT JOIN traffic_penalties tp ON u.user_id = tp.user_id
    GROUP BY u.user_id
    ORDER BY u.registration_date DESC
";
$users = $db->query($users_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
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
                    <h2 class="text-light"><i class="fas fa-users me-2"></i>User Management</h2>
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

                <div class="card cyber-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Reports</th>
                                        <th>Vehicles</th>
                                        <th>Penalties</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $users->data_seek(0); // Reset pointer
                                    while ($u = $users->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td class="text-light"><?php echo $u['user_id']; ?></td>
                                            <td class="text-light"><?php echo h($u['username']); ?></td>
                                            <td class="text-light"><?php echo h($u['email']); ?></td>
                                            <td class="text-light"><?php echo h($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['user_type'] == 'admin' ? 'danger' : ($u['user_type'] == 'traffic_manager' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $u['user_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="text-center text-light"><?php echo $u['report_count']; ?></td>
                                            <td class="text-center text-light"><?php echo $u['vehicle_count']; ?></td>
                                            <td class="text-center">
                                                <?php if ($u['penalty_count'] > 0): ?>
                                                    <span class="badge bg-danger"><?php echo $u['penalty_count']; ?></span>
                                                    <?php if ($u['unpaid_amount'] > 0): ?>
                                                        <br><small class="text-warning">৳<?php echo number_format($u['unpaid_amount'], 2); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($u['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Blocked</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($u['user_id'] != $user['user_id']): ?>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                            Actions
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <form method="POST" class="dropdown-item">
                                                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                                    <input type="hidden" name="is_active" value="<?php echo $u['is_active'] ? 0 : 1; ?>">
                                                                    <button type="submit" name="toggle_status" class="btn btn-link text-decoration-none p-0 w-100 text-start">
                                                                        <i class="fas fa-<?php echo $u['is_active'] ? 'ban' : 'check'; ?> me-2"></i>
                                                                        <?php echo $u['is_active'] ? 'Block User' : 'Activate User'; ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <button class="dropdown-item" onclick="openRoleModal(<?php echo $u['user_id']; ?>, '<?php echo h($u['username']); ?>', '<?php echo $u['user_type']; ?>')">
                                                                    <i class="fas fa-user-tag me-2"></i>Change Role
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="user_penalties.php?user_id=<?php echo $u['user_id']; ?>">
                                                                    <i class="fas fa-receipt me-2"></i>View Penalties
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="issue_penalty.php?user_id=<?php echo $u['user_id']; ?>">
                                                                    <i class="fas fa-gavel me-2"></i>Issue Penalty
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="dropdown-item" onsubmit="return confirm('Delete this user? This cannot be undone!');">
                                                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                                    <button type="submit" name="delete_user" class="btn btn-link text-danger text-decoration-none p-0 w-100 text-start">
                                                                        <i class="fas fa-trash me-2"></i>Delete User
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">You</span>
                                                <?php endif; ?>
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

    <!-- Universal Role Change Modal -->
    <div class="modal fade" id="roleChangeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: rgba(25,30,50,0.95); color: #e0e0ff;">
                <div class="modal-header">
                    <h5 class="modal-title">Change User Role</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="roleChangeForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="modalUserId">
                        <p class="text-light">Change role for: <strong id="modalUsername"></strong></p>
                        <select class="form-select" name="user_type" id="modalUserType" required>
                            <option value="public_user">Public User</option>
                            <option value="traffic_manager">Traffic Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                        <small class="text-muted d-block mt-2">This will change the user's access level immediately.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_role" class="btn btn-primary">
                            <i class="fas fa-check me-2"></i>Change Role
                        </button>
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
        
        function openRoleModal(userId, username, currentRole) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUsername').textContent = username;
            document.getElementById('modalUserType').value = currentRole;
            
            var modal = new bootstrap.Modal(document.getElementById('roleChangeModal'));
            modal.show();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>