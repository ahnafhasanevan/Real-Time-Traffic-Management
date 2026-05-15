<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if ($user['user_type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Get comprehensive statistics
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'active_users' => $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE")->fetch_assoc()['count'],
    'blocked_users' => $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = FALSE")->fetch_assoc()['count'],
    'pending_reports' => $db->query("SELECT COUNT(*) as count FROM user_traffic_reports WHERE status = 'pending'")->fetch_assoc()['count'],
    'unverified_reports' => $db->query("SELECT COUNT(*) as count FROM user_traffic_reports WHERE is_verified = FALSE")->fetch_assoc()['count'],
    'active_sensors' => $db->query("SELECT COUNT(*) as count FROM traffic_sensors WHERE is_active = TRUE")->fetch_assoc()['count'],
    'inactive_sensors' => $db->query("SELECT COUNT(*) as count FROM traffic_sensors WHERE is_active = FALSE")->fetch_assoc()['count'],
    'unpaid_penalties' => $db->query("SELECT COUNT(*) as count FROM traffic_penalties WHERE is_paid = FALSE")->fetch_assoc()['count'],
    'total_penalties_amount' => $db->query("SELECT COALESCE(SUM(fine_amount), 0) as total FROM traffic_penalties WHERE is_paid = FALSE")->fetch_assoc()['total'],
    'total_vehicles' => $db->query("SELECT COUNT(*) as count FROM vehicles")->fetch_assoc()['count'],
    'responding_emergency' => $db->query("SELECT COUNT(*) as count FROM emergency_vehicles WHERE is_responding = TRUE")->fetch_assoc()['count'],
    'active_events' => $db->query("SELECT COUNT(*) as count FROM events WHERE start_time <= NOW() AND end_time >= NOW()")->fetch_assoc()['count'],
];

// Recent activity data
$recent_users = $db->query("SELECT username, user_type, registration_date FROM users ORDER BY registration_date DESC LIMIT 5");
$recent_reports = $db->query("SELECT utr.*, u.username, r.road_name FROM user_traffic_reports utr JOIN users u ON utr.user_id = u.user_id JOIN road_segments rs ON utr.segment_id = rs.segment_id JOIN roads r ON rs.road_id = r.road_id ORDER BY utr.report_time DESC LIMIT 5");
$recent_logs = $db->query("SELECT sl.*, u.username FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.user_id ORDER BY sl.timestamp DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            color: white;
        }
        .action-card {
            background: rgba(25, 30, 50, 0.9);
            border: 2px solid rgba(220, 53, 69, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .action-card:hover {
            border-color: #dc3545;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3);
        }
        .action-card i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .stat-box {
            background: rgba(25, 30, 50, 0.9);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(100, 100, 255, 0.3);
        }
        .admin-card {
            background: rgba(25, 30, 50, 0.9);
            border: 1px solid rgba(220, 53, 69, 0.3);
            transition: all 0.3s ease;
        }
        .admin-card:hover {
            border-color: rgba(220, 53, 69, 0.6);
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.3;
        }
        .quick-action-btn {
            width: 100%;
            padding: 15px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container-fluid mt-4">
                <!-- Admin Header -->
                <div class="admin-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="mb-2"><i class="fas fa-shield-alt me-3"></i>Admin Control Panel</h1>
                            <p class="mb-0">Welcome, <?php echo h($user['username']); ?> - Full System Access</p>
                        </div>
                        <div>
                            <span class="badge bg-light text-danger fs-6 me-2">ADMIN ACCESS</span>
                            <a href="index.php" class="btn btn-light btn-lg me-2">
                                <i class="fas fa-home me-2"></i>User Dashboard
                            </a>
                            <button class="btn btn-outline-light btn-lg" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Overview -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card admin-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-xs text-uppercase mb-1 text-muted">Total Users</div>
                                        <h3 class="text-light"><?php echo $stats['total_users']; ?></h3>
                                        <small class="text-success"><?php echo $stats['active_users']; ?> Active</small>
                                        <?php if ($stats['blocked_users'] > 0): ?>
                                            <div class="mt-1"><small class="text-danger"><?php echo $stats['blocked_users']; ?> Blocked</small></div>
                                        <?php endif; ?>
                                    </div>
                                    <i class="fas fa-users stat-icon text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card admin-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-xs text-uppercase mb-1 text-muted">Pending Reports</div>
                                        <h3 class="text-warning"><?php echo $stats['pending_reports']; ?></h3>
                                        <small class="text-danger"><?php echo $stats['unverified_reports']; ?> Unverified</small>
                                    </div>
                                    <i class="fas fa-exclamation-triangle stat-icon text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card admin-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-xs text-uppercase mb-1 text-muted">Unpaid Penalties</div>
                                        <h3 class="text-light"><?php echo $stats['unpaid_penalties']; ?></h3>
                                        <small class="text-warning">৳<?php echo number_format($stats['total_penalties_amount'], 2); ?></small>
                                    </div>
                                    <i class="fas fa-receipt stat-icon text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card admin-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-xs text-uppercase mb-1 text-muted">Sensors Status</div>
                                        <h3 class="text-success"><?php echo $stats['active_sensors']; ?></h3>
                                        <small class="text-danger"><?php echo $stats['inactive_sensors']; ?> Inactive</small>
                                    </div>
                                    <i class="fas fa-microchip stat-icon text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Admin Actions Grid -->
                <h3 class="text-light mb-4">Admin Actions</h3>
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="user_management.php" class="text-decoration-none">
                            <div class="card action-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users-cog text-danger"></i>
                                    <h5 class="text-light mt-2">Manage Users</h5>
                                    <small class="text-muted">Block, Edit, Change Roles</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="approve_reports.php" class="text-decoration-none">
                            <div class="card action-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle text-warning"></i>
                                    <h5 class="text-light mt-2">Approve Reports</h5>
                                    <small class="text-muted">Verify & Resolve</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="issue_penalty.php" class="text-decoration-none">
                            <div class="card action-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-gavel text-info"></i>
                                    <h5 class="text-light mt-2">Issue Penalties</h5>
                                    <small class="text-muted">Create Fines/Tickets</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="manage_sensors.php" class="text-decoration-none">
                            <div class="card action-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-microchip text-success"></i>
                                    <h5 class="text-light mt-2">Manage Sensors</h5>
                                    <small class="text-muted">Monitor & Control</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="manage_events.php" class="text-decoration-none">
                            <div class="card action-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-alt text-primary"></i>
                                    <h5 class="text-light mt-2">Manage Events</h5>
                                    <small class="text-muted">Add/Edit Events</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="emergency_vehicles.php" class="text-decoration-none">
                            <div class="card action-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-ambulance text-danger"></i>
                                    <h5 class="text-light mt-2">Emergency Vehicles</h5>
                                    <small class="text-muted">Track & Dispatch</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="system_logs.php" class="text-decoration-none">
                            <div class="card action-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt text-secondary"></i>
                                    <h5 class="text-light mt-2">System Logs</h5>
                                    <small class="text-muted">Audit Trail</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <a href="analytics.php" class="text-decoration-none">
                            <div class="card action-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line text-info"></i>
                                    <h5 class="text-light mt-2">Analytics</h5>
                                    <small class="text-muted">Reports & Insights</small>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- Quick Actions -->
                    <div class="col-lg-3 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 col-12 mb-2">
                                        <a href="traffic_data.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-car me-2"></i>Live Traffic
                                        </a>
                                    </div>
                                    <div class="col-md-6 col-12 mb-2">
                                        <a href="view_reports.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-list me-2"></i>All Reports
                                        </a>
                                    </div>
                                    <div class="col-md-6 col-12 mb-2">
                                        <a href="parking.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-parking me-2"></i>Parking Status
                                        </a>
                                    </div>
                                    <div class="col-md-6 col-12 mb-2">
                                        <a href="weather_dashboard.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-cloud-sun me-2"></i>Weather
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Reports -->
                    <div class="col-lg-5 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-light"><i class="fas fa-list me-2"></i>Recent Reports</h5>
                                <a href="approve_reports.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php while ($report = $recent_reports->fetch_assoc()): ?>
                                    <div class="border-start border-3 border-<?php echo $report['status'] == 'pending' ? 'warning' : 'success'; ?> ps-3 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <strong class="text-light"><?php echo ucfirst($report['report_type']); ?></strong>
                                            <small class="text-muted"><?php echo date('M j, H:i', strtotime($report['report_time'])); ?></small>
                                        </div>
                                        <div class="text-muted"><?php echo h($report['road_name']); ?> by <?php echo h($report['username']); ?></div>
                                        <span class="badge bg-<?php echo $report['severity'] == 'high' ? 'danger' : ($report['severity'] == 'medium' ? 'warning' : 'success'); ?>">
                                            <?php echo ucfirst($report['severity']); ?>
                                        </span>
                                        <?php if (!$report['is_verified']): ?>
                                            <span class="badge bg-danger">Unverified</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>

                    <!-- System Activity -->
                    <div class="col-lg-4 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light"><i class="fas fa-history me-2"></i>System Activity</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php while ($log = $recent_logs->fetch_assoc()): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <small class="text-light"><strong><?php echo h($log['username'] ?? 'System'); ?></strong></small>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($log['timestamp'])); ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo h($log['description']); ?></small>
                                        <div>
                                            <span class="badge bg-secondary" style="font-size: 0.7rem;"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="row">
                    <div class="col-12">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light"><i class="fas fa-user-plus me-2"></i>Recently Registered Users</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>User Type</th>
                                                <th>Registration Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($u = $recent_users->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="text-light"><?php echo h($u['username']); ?></td>
                                                    <td><span class="badge bg-<?php echo $u['user_type'] == 'admin' ? 'danger' : ($u['user_type'] == 'traffic_manager' ? 'warning' : 'info'); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $u['user_type'])); ?>
                                                    </span></td>
                                                    <td class="text-light"><?php echo formatDate($u['registration_date']); ?></td>
                                                    <td>
                                                        <a href="user_management.php" class="btn btn-sm btn-outline-primary">Manage</a>
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
        </div>
    </div>

    <script src="darkveil.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body, {
                speed: 0.5,
                particleCount: 60,
                colors: ['#dc3545', '#c82333', '#bd2130']
            });
        });
        
        // Auto-refresh every 30 seconds
        setInterval(() => location.reload(), 30000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>