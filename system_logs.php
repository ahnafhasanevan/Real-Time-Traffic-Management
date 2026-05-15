<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if ($user['user_type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Pagination
$per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filters
$where = "1=1";
if (isset($_GET['action']) && $_GET['action'] !== '') {
    $action = $db->real_escape_string($_GET['action']);
    $where .= " AND sl.action = '$action'";
}
if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
    $user_id = (int)$_GET['user_id'];
    $where .= " AND sl.user_id = $user_id";
}

// Get total count
$total = $db->query("SELECT COUNT(*) as c FROM system_logs WHERE $where")->fetch_assoc()['c'];
$total_pages = ceil($total / $per_page);

// Get logs
$logs = $db->query("
    SELECT sl.*, u.username, u.user_type
    FROM system_logs sl
    LEFT JOIN users u ON sl.user_id = u.user_id
    WHERE $where
    ORDER BY sl.timestamp DESC
    LIMIT $per_page OFFSET $offset
");

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action FROM system_logs ORDER BY action");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin</title>
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
                    <h2 class="text-light"><i class="fas fa-file-alt me-2"></i>System Logs</h2>
                    <a href="admin_dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Admin
                    </a>
                </div>

                <!-- Filters -->
                <div class="card cyber-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Action Type</label>
                                <select class="form-select" name="action">
                                    <option value="">All Actions</option>
                                    <?php while ($action = $actions->fetch_assoc()): ?>
                                        <option value="<?php echo h($action['action']); ?>" 
                                            <?php echo (isset($_GET['action']) && $_GET['action'] == $action['action']) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $action['action'])); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="system_logs.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="card cyber-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($logs->num_rows > 0): ?>
                                        <?php while ($log = $logs->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-light">
                                                    <small><?php echo date('M j, Y g:i:s A', strtotime($log['timestamp'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="text-light"><?php echo h($log['username'] ?? 'System'); ?></span>
                                                    <br><small class="text-muted"><?php echo ucfirst($log['user_type'] ?? 'system'); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></span>
                                                </td>
                                                <td class="text-light"><small><?php echo h($log['description']); ?></small></td>
                                                <td class="text-muted"><small><?php echo h($log['ip_address']); ?></small></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                <p class="text-light">No logs found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="text-center text-muted mt-3">
                            <small>Showing <?php echo min($offset + 1, $total); ?> to <?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> logs</small>
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
</html><?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if ($user['user_type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Pagination
$per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filters
$where = "1=1";
if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
    $where .= " AND sl.user_id = " . (int)$_GET['user_id'];
}
if (isset($_GET['action']) && $_GET['action'] !== '') {
    $action = $db->real_escape_string($_GET['action']);
    $where .= " AND sl.action = '$action'";
}

// Get total count
$total_query = $db->query("SELECT COUNT(*) as count FROM system_logs WHERE $where");
$total_logs = $total_query->fetch_assoc()['count'];
$total_pages = ceil($total_logs / $per_page);

// Get logs
$logs = $db->query("
    SELECT sl.*, u.username, u.user_type
    FROM system_logs sl
    LEFT JOIN users u ON sl.user_id = u.user_id
    WHERE $where
    ORDER BY sl.timestamp DESC
    LIMIT $per_page OFFSET $offset
");

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action FROM system_logs ORDER BY action");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container-fluid mt-4">
                <h2 class="text-light mb-4"><i class="fas fa-file-alt me-2"></i>System Logs</h2>

                <!-- Filters -->
                <div class="card cyber-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Action Type</label>
                                <select class="form-select" name="action">
                                    <option value="">All Actions</option>
                                    <?php while ($action = $actions->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                            <?php echo (isset($_GET['action']) && $_GET['action'] == $action['action']) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $action['action'])); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="system_logs.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="card cyber-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($logs->num_rows > 0): ?>
                                        <?php while ($log = $logs->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-light">
                                                    <small><?php echo date('M j, Y g:i:s A', strtotime($log['timestamp'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="text-light"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></span>
                                                    <br><small class="text-muted"><?php echo ucfirst($log['user_type'] ?? 'system'); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?></span>
                                                </td>
                                                <td class="text-light"><small><?php echo htmlspecialchars($log['description']); ?></small></td>
                                                <td class="text-muted"><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                <p class="text-light">No logs found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="text-center text-muted mt-3">
                            <small>Showing <?php echo min($offset + 1, $total_logs); ?> to <?php echo min($offset + $per_page, $total_logs); ?> of <?php echo $total_logs; ?> logs</small>
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