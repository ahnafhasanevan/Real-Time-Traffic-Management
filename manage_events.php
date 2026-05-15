<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

// Check if user has permission
if ($user['user_type'] !== 'admin' && $user['user_type'] !== 'traffic_manager') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $event_name = trim($_POST['event_name']);
    $event_type = $_POST['event_type'];
    $location = trim($_POST['location']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $expected_attendance = (int)$_POST['expected_attendance'];
    $impact_level = $_POST['impact_level'];
    
    $stmt = $db->prepare("INSERT INTO events (event_name, event_type, location, start_time, end_time, expected_attendance, impact_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssis", $event_name, $event_type, $location, $start_time, $end_time, $expected_attendance, $impact_level);
    
    if ($stmt->execute()) {
        $success = "Event created successfully!";
        
        // Create notifications for users
        $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, notification_type, title, message) SELECT user_id, 'event', ?, ? FROM users WHERE is_active = TRUE");
        $notif_title = "New Event: $event_name";
        $notif_msg = "Event at $location on " . date('M j', strtotime($start_time)) . ". Expected $impact_level traffic impact.";
        $notif_stmt->bind_param("ss", $notif_title, $notif_msg);
        $notif_stmt->execute();
    }
}

// Handle event deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    if ($stmt->execute()) {
        $success = "Event deleted successfully!";
    }
}

// Get all events
$events = $db->query("SELECT * FROM events ORDER BY start_time DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Traffic System</title>
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
                    <h2 class="text-light"><i class="fas fa-calendar-alt me-2"></i>Event Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                        <i class="fas fa-plus me-2"></i>Add Event
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card cyber-card">
                    <div class="card-body">
                        <?php if ($events->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Event Name</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Date & Time</th>
                                            <th>Attendance</th>
                                            <th>Impact</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($event = $events->fetch_assoc()): 
                                            $now = time();
                                            $start = strtotime($event['start_time']);
                                            $end = strtotime($event['end_time']);
                                            
                                            if ($now < $start) {
                                                $status = 'upcoming';
                                                $status_badge = 'info';
                                            } elseif ($now >= $start && $now <= $end) {
                                                $status = 'ongoing';
                                                $status_badge = 'success';
                                            } else {
                                                $status = 'completed';
                                                $status_badge = 'secondary';
                                            }
                                        ?>
                                            <tr>
                                                <td><strong class="text-light"><?php echo htmlspecialchars($event['event_name']); ?></strong></td>
                                                <td><span class="badge bg-primary"><?php echo ucfirst($event['event_type']); ?></span></td>
                                                <td class="text-light"><?php echo htmlspecialchars($event['location']); ?></td>
                                                <td class="text-light">
                                                    <small>
                                                        <?php echo date('M j, Y g:i A', $start); ?><br>
                                                        to <?php echo date('M j, Y g:i A', $end); ?>
                                                    </small>
                                                </td>
                                                <td class="text-light"><?php echo number_format($event['expected_attendance']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $event['impact_level'] == 'high' ? 'danger' : ($event['impact_level'] == 'medium' ? 'warning' : 'success'); ?>">
                                                        <?php echo ucfirst($event['impact_level']); ?>
                                                    </span>
                                                </td>
                                                <td><span class="badge bg-<?php echo $status_badge; ?>"><?php echo ucfirst($status); ?></span></td>
                                                <td>
                                                    <a href="?delete=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this event?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h4 class="text-light">No events scheduled</h4>
                                <p class="text-muted">Create your first event to get started</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: rgba(25, 30, 50, 0.95); color: #e0e0ff;">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Event</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Event Name *</label>
                                <input type="text" class="form-control" name="event_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Event Type *</label>
                                <select class="form-select" name="event_type" required>
                                    <option value="">Select type</option>
                                    <option value="concert">Concert</option>
                                    <option value="sports">Sports Event</option>
                                    <option value="festival">Festival</option>
                                    <option value="conference">Conference</option>
                                    <option value="parade">Parade</option>
                                    <option value="construction">Construction</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location" required placeholder="e.g., National Stadium, Dhaka">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time *</label>
                                <input type="datetime-local" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time *</label>
                                <input type="datetime-local" class="form-control" name="end_time" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expected Attendance *</label>
                                <input type="number" class="form-control" name="expected_attendance" required min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Traffic Impact Level *</label>
                                <select class="form-select" name="impact_level" required>
                                    <option value="">Select impact</option>
                                    <option value="low">Low Impact</option>
                                    <option value="medium">Medium Impact</option>
                                    <option value="high">High Impact</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_event" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create Event
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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>