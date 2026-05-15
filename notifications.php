<?php
require_once 'config.php';
requireAuth();

$db = getDBConnection();
$user = getCurrentUser();

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user['user_id']);
    $stmt->execute();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $success = "All notifications marked as read!";
}

// Handle delete notification
if (isset($_POST['delete_notification'])) {
    $notification_id = (int)$_POST['notification_id'];
    
    $stmt = $db->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user['user_id']);
    $stmt->execute();
    $success = "Notification deleted!";
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$where_filter = "";
if ($filter === 'unread') {
    $where_filter = "AND is_read = FALSE";
} elseif ($filter === 'read') {
    $where_filter = "AND is_read = TRUE";
}

// Get notifications
$notifications = $db->query("
    SELECT * FROM notifications 
    WHERE user_id = {$user['user_id']} 
    $where_filter
    ORDER BY created_at DESC
");

// Get counts
$total_count = $db->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = {$user['user_id']}")->fetch_assoc()['count'];
$unread_count = $db->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = {$user['user_id']} AND is_read = FALSE")->fetch_assoc()['count'];
$read_count = $total_count - $unread_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .notification-card {
            border-left: 4px solid;
            transition: all 0.2s;
            cursor: pointer;
        }
        .notification-card:hover {
            transform: translateX(5px);
        }
        .notification-unread {
            border-left-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        .notification-read {
            border-left-color: #6c757d;
            opacity: 0.7;
        }
        .notification-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        .icon-traffic { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .icon-penalty { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .icon-event { background: rgba(0, 123, 255, 0.2); color: #007bff; }
        .icon-system { background: rgba(108, 117, 125, 0.2); color: #6c757d; }
        .icon-weather { background: rgba(23, 162, 184, 0.2); color: #17a2b8; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-light">
                        <i class="fas fa-bell me-2"></i>Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if ($unread_count > 0): ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-primary">
                            <i class="fas fa-check-double me-2"></i>Mark All as Read
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filter Tabs -->
                <div class="card cyber-card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="btn-group" role="group">
                                    <a href="?filter=all" class="btn btn-<?php echo $filter === 'all' ? 'primary' : 'outline-primary'; ?>">
                                        All (<?php echo $total_count; ?>)
                                    </a>
                                    <a href="?filter=unread" class="btn btn-<?php echo $filter === 'unread' ? 'primary' : 'outline-primary'; ?>">
                                        Unread (<?php echo $unread_count; ?>)
                                    </a>
                                    <a href="?filter=read" class="btn btn-<?php echo $filter === 'read' ? 'primary' : 'outline-primary'; ?>">
                                        Read (<?php echo $read_count; ?>)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <?php if ($notifications->num_rows > 0): ?>
                    <?php while ($notif = $notifications->fetch_assoc()): 
                        $icon_class = 'icon-system';
                        $icon = 'bell';
                        
                        switch ($notif['notification_type']) {
                            case 'traffic':
                                $icon_class = 'icon-traffic';
                                $icon = 'car-crash';
                                break;
                            case 'penalty':
                                $icon_class = 'icon-penalty';
                                $icon = 'receipt';
                                break;
                            case 'event':
                                $icon_class = 'icon-event';
                                $icon = 'calendar-alt';
                                break;
                            case 'weather':
                                $icon_class = 'icon-weather';
                                $icon = 'cloud-rain';
                                break;
                        }
                    ?>
                        <div class="card notification-card notification-<?php echo $notif['is_read'] ? 'read' : 'unread'; ?> mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-auto">
                                        <div class="notification-icon <?php echo $icon_class; ?>">
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h5 class="text-light mb-2">
                                                    <?php echo htmlspecialchars($notif['title']); ?>
                                                    <?php if (!$notif['is_read']): ?>
                                                        <span class="badge bg-primary">New</span>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="text-light mb-2"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php 
                                                    $time_diff = time() - strtotime($notif['created_at']);
                                                    if ($time_diff < 3600) {
                                                        echo floor($time_diff / 60) . ' minutes ago';
                                                    } elseif ($time_diff < 86400) {
                                                        echo floor($time_diff / 3600) . ' hours ago';
                                                    } else {
                                                        echo date('M j, Y', strtotime($notif['created_at']));
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <?php if (!$notif['is_read']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-primary" title="Mark as read">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>">
                                                    <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card cyber-card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h4 class="text-light">No Notifications</h4>
                            <p class="text-muted">
                                <?php if ($filter === 'unread'): ?>
                                    You have no unread notifications
                                <?php elseif ($filter === 'read'): ?>
                                    You have no read notifications
                                <?php else: ?>
                                    You don't have any notifications yet
                                <?php endif; ?>
                            </p>
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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>