<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if (!isManager()) {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_report'])) {
        $report_id = (int)$_POST['report_id'];
        $stmt = $db->prepare("UPDATE user_traffic_reports SET is_verified = TRUE WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);
        if ($stmt->execute()) {
            $success = "Report verified successfully!";
            logAction($db, $user['user_id'], 'verify_report', "Verified report ID $report_id");
            
            // Notify reporter
            $report_data = $db->query("SELECT user_id FROM user_traffic_reports WHERE report_id = $report_id")->fetch_assoc();
            sendNotification($db, $report_data['user_id'], 'traffic_alert', 'Report Verified', 'Your traffic report has been verified by the traffic management team.');
        }
    }
    
    if (isset($_POST['change_status'])) {
        $report_id = (int)$_POST['report_id'];
        $status = $_POST['status'];
        $stmt = $db->prepare("UPDATE user_traffic_reports SET status = ? WHERE report_id = ?");
        $stmt->bind_param("si", $status, $report_id);
        if ($stmt->execute()) {
            $success = "Report status updated to " . ucfirst($status) . "!";
            logAction($db, $user['user_id'], 'change_report_status', "Changed report ID $report_id status to $status");
            
            // Notify reporter
            $report_data = $db->query("SELECT user_id FROM user_traffic_reports WHERE report_id = $report_id")->fetch_assoc();
            sendNotification($db, $report_data['user_id'], 'traffic_alert', 'Report Status Updated', "Your report status has been changed to: " . ucfirst($status));
        }
    }
    
    if (isset($_POST['delete_report'])) {
        $report_id = (int)$_POST['report_id'];
        $stmt = $db->prepare("DELETE FROM user_traffic_reports WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);
        if ($stmt->execute()) {
            $success = "Report deleted successfully!";
            logAction($db, $user['user_id'], 'delete_report', "Deleted report ID $report_id");
        }
    }
}

// Get all reports with filters
$where = "1=1";
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status = $db->real_escape_string($_GET['status']);
    $where .= " AND utr.status = '$status'";
}
if (isset($_GET['verified']) && $_GET['verified'] !== '') {
    $verified = $_GET['verified'] == '1' ? 'TRUE' : 'FALSE';
    $where .= " AND utr.is_verified = $verified";
}

$reports = $db->query("
    SELECT utr.*, u.username, u.email, r.road_name, rs.segment_name
    FROM user_traffic_reports utr
    JOIN users u ON utr.user_id = u.user_id
    JOIN road_segments rs ON utr.segment_id = rs.segment_id
    JOIN roads r ON rs.road_id = r.road_id
    WHERE $where
    ORDER BY utr.report_time DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Reports - Admin</title>
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
                    <h2 class="text-light"><i class="fas fa-check-circle me-2"></i>Report Approval System</h2>
                    <a href="admin_dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Admin
                    </a>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card cyber-card mb-4">
                    <div class="card-body">
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
                                <label class="form-label">Verification</label>
                                <select class="form-select" name="verified" onchange="this.form.submit()">
                                    <option value="">All Reports</option>
                                    <option value="0" <?php echo (isset($_GET['verified']) && $_GET['verified'] == '0') ? 'selected' : ''; ?>>Unverified Only</option>
                                    <option value="1" <?php echo (isset($_GET['verified']) && $_GET['verified'] == '1') ? 'selected' : ''; ?>>Verified Only</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <a href="approve_reports.php" class="btn btn-secondary w-100">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Reports List -->
                <div class="row">
                    <?php if ($reports->num_rows > 0): ?>
                        <?php while ($report = $reports->fetch_assoc()): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card cyber-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="text-light"><?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?></strong>
                                            <?php if (!$report['is_verified']): ?>
                                                <span class="badge bg-danger ms-2">Unverified</span>
                                            <?php else: ?>
                                                <span class="badge bg-success ms-2">Verified</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-<?php echo $report['status'] == 'pending' ? 'warning' : ($report['status'] == 'investigating' ? 'info' : 'success'); ?>">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="text-light mb-2">
                                                <i class="fas fa-road me-2"></i>
                                                <strong>Location:</strong> <?php echo h($report['road_name']); ?>
                                                <?php if ($report['segment_name']): ?>
                                                    - <?php echo h($report['segment_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($report['location_details']): ?>
                                                <div class="text-muted mb-2">
                                                    <i class="fas fa-map-marker-alt me-2"></i>
                                                    <?php echo h($report['location_details']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-muted mb-2">
                                                <i class="fas fa-user me-2"></i>
                                                Reported by: <?php echo h($report['username']); ?> (<?php echo h($report['email']); ?>)
                                            </div>
                                            <div class="text-muted mb-2">
                                                <i class="fas fa-clock me-2"></i>
                                                <?php echo formatDate($report['report_time']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <strong class="text-light">Description:</strong>
                                            <p class="text-light mb-0"><?php echo nl2br(h($report['description'])); ?></p>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="badge bg-<?php echo $report['severity'] == 'high' ? 'danger' : ($report['severity'] == 'medium' ? 'warning' : 'success'); ?>">
                                                <?php echo ucfirst($report['severity']); ?> Severity
                                            </span>
                                        </div>
                                        
                                        <div class="btn-group w-100" role="group">
                                            <?php if (!$report['is_verified']): ?>
                                                <form method="POST" class="flex-fill">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                                    <button type="submit" name="verify_report" class="btn btn-success w-100">
                                                        <i class="fas fa-check me-2"></i>Verify
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <div class="btn-group flex-fill" role="group">
                                                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-edit me-2"></i>Change Status
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST">
                                                            <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                                            <input type="hidden" name="status" value="investigating">
                                                            <button type="submit" name="change_status" class="dropdown-item">Investigating</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST">
                                                            <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                                            <input type="hidden" name="status" value="resolved">
                                                            <button type="submit" name="change_status" class="dropdown-item">Resolved</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                            
                                            <form method="POST" class="flex-fill" onsubmit="return confirm('Delete this report?');">
                                                <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                                <button type="submit" name="delete_report" class="btn btn-danger w-100">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card cyber-card text-center py-5">
                                <div class="card-body">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h4 class="text-light">No reports found</h4>
                                    <p class="text-muted">All reports have been processed or no reports match the filters</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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