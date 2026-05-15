<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if (!isManager()) {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Traffic Statistics
$total_reports = $db->query("SELECT COUNT(*) as c FROM user_traffic_reports")->fetch_assoc()['c'];
$reports_today = $db->query("SELECT COUNT(*) as c FROM user_traffic_reports WHERE DATE(report_time) = CURDATE()")->fetch_assoc()['c'];
$reports_this_week = $db->query("SELECT COUNT(*) as c FROM user_traffic_reports WHERE report_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];

// Reports by Type
$reports_by_type = $db->query("
    SELECT report_type, COUNT(*) as count 
    FROM user_traffic_reports 
    GROUP BY report_type 
    ORDER BY count DESC
");

// Reports by Status
$reports_by_status = $db->query("
    SELECT status, COUNT(*) as count 
    FROM user_traffic_reports 
    GROUP BY status
");

// Top Congested Roads
$congested_roads = $db->query("
    SELECT r.road_name, AVG(td.average_speed) as avg_speed, COUNT(*) as data_points
    FROM traffic_data td
    JOIN road_segments rs ON td.segment_id = rs.segment_id
    JOIN roads r ON rs.road_id = r.road_id
    WHERE td.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY r.road_id
    ORDER BY avg_speed ASC
    LIMIT 5
");

// User Activity
$user_stats = $db->query("
    SELECT 
        COUNT(DISTINCT user_id) as total_users,
        COUNT(DISTINCT CASE WHEN is_active = TRUE THEN user_id END) as active_users,
        COUNT(DISTINCT CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN user_id END) as monthly_active
    FROM users
")->fetch_assoc();

// Penalty Statistics
$penalty_stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_paid = TRUE THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN is_paid = FALSE THEN 1 ELSE 0 END) as unpaid,
        SUM(fine_amount) as total_amount,
        SUM(CASE WHEN is_paid = TRUE THEN fine_amount ELSE 0 END) as paid_amount
    FROM traffic_penalties
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
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
                    <h2 class="text-light"><i class="fas fa-chart-line me-2"></i>Analytics Dashboard</h2>
                    <a href="<?php echo $user['user_type'] == 'admin' ? 'admin_dashboard.php' : 'index.php'; ?>" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>

                <!-- Overview Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card cyber-card text-center p-3">
                            <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                            <h3 class="text-light"><?php echo $total_reports; ?></h3>
                            <small class="text-muted">Total Reports</small>
                            <small class="text-success d-block">+<?php echo $reports_today; ?> today</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card cyber-card text-center p-3">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h3 class="text-light"><?php echo $user_stats['active_users']; ?></h3>
                            <small class="text-muted">Active Users</small>
                            <small class="text-info d-block"><?php echo $user_stats['monthly_active']; ?> this month</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card cyber-card text-center p-3">
                            <i class="fas fa-receipt fa-2x text-warning mb-2"></i>
                            <h3 class="text-light"><?php echo $penalty_stats['total']; ?></h3>
                            <small class="text-muted">Total Penalties</small>
                            <small class="text-danger d-block"><?php echo $penalty_stats['unpaid']; ?> unpaid</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card cyber-card text-center p-3">
                            <i class="fas fa-money-bill fa-2x text-success mb-2"></i>
                            <h3 class="text-light">৳<?php echo number_format($penalty_stats['total_amount'], 0); ?></h3>
                            <small class="text-muted">Penalty Revenue</small>
                            <small class="text-success d-block">৳<?php echo number_format($penalty_stats['paid_amount'], 0); ?> collected</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Reports by Type -->
                    <div class="col-lg-6 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light">Reports by Type</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th class="text-end">Count</th>
                                            <th class="text-end">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $reports_by_type->fetch_assoc()): 
                                            $percent = ($row['count'] / $total_reports) * 100;
                                        ?>
                                            <tr>
                                                <td class="text-light"><?php echo ucfirst(str_replace('_', ' ', $row['report_type'])); ?></td>
                                                <td class="text-end text-light"><?php echo $row['count']; ?></td>
                                                <td class="text-end">
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-primary" style="width: <?php echo $percent; ?>%">
                                                            <?php echo round($percent, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Reports by Status -->
                    <div class="col-lg-6 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light">Reports by Status</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th class="text-end">Count</th>
                                            <th class="text-end">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $reports_by_status->fetch_assoc()): 
                                            $percent = ($row['count'] / $total_reports) * 100;
                                            $color = $row['status'] == 'pending' ? 'warning' : ($row['status'] == 'investigating' ? 'info' : 'success');
                                        ?>
                                            <tr>
                                                <td><span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                                <td class="text-end text-light"><?php echo $row['count']; ?></td>
                                                <td class="text-end">
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percent; ?>%">
                                                            <?php echo round($percent, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Most Congested Roads -->
                    <div class="col-12">
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h5 class="mb-0 text-light">Most Congested Roads (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Road Name</th>
                                            <th class="text-end">Average Speed</th>
                                            <th class="text-end">Data Points</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($road = $congested_roads->fetch_assoc()): 
                                            $speed = round($road['avg_speed'], 1);
                                            $status_color = $speed < 20 ? 'danger' : ($speed < 40 ? 'warning' : 'success');
                                        ?>
                                            <tr>
                                                <td class="text-light"><?php echo h($road['road_name']); ?></td>
                                                <td class="text-end text-light"><?php echo $speed; ?> km/h</td>
                                                <td class="text-end text-light"><?php echo $road['data_points']; ?></td>
                                                <td><span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo $speed < 20 ? 'Severe' : ($speed < 40 ? 'High' : 'Moderate'); ?> Congestion
                                                </span></td>
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

    <script src="darkveil.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>