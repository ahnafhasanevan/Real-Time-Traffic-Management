<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

if ($user['user_type'] !== 'admin' && $user['user_type'] !== 'traffic_manager') {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();

// Handle phase change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_phase'])) {
    $light_id = (int)$_POST['light_id'];
    $new_phase = $_POST['new_phase'];
    
    $stmt = $db->prepare("UPDATE traffic_lights SET current_phase = ?, last_phase_change = NOW() WHERE light_id = ?");
    $stmt->bind_param("si", $new_phase, $light_id);
    
    if ($stmt->execute()) {
        $success = "Traffic light phase updated successfully!";
        logActivity("Changed traffic light {$light_id} to {$new_phase} phase", $user['user_id']);
    }
}

// Get all junctions with traffic lights
$junctions = $db->query("
    SELECT j.*, COUNT(tl.light_id) as light_count
    FROM junctions j
    LEFT JOIN traffic_lights tl ON j.junction_id = tl.junction_id
    GROUP BY j.junction_id
    ORDER BY j.junction_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Lights Management - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .junction-card {
            border-radius: 12px;
            transition: transform 0.3s;
        }
        .junction-card:hover {
            transform: translateY(-5px);
        }
        .light-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-block;
            margin: 0 5px;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        .light-green { background: #28a745; box-shadow: 0 0 20px #28a745; }
        .light-yellow { background: #ffc107; box-shadow: 0 0 20px #ffc107; }
        .light-red { background: #dc3545; box-shadow: 0 0 20px #dc3545; }
        .phase-timer {
            font-size: 1.5rem;
            font-weight: bold;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container mt-4">
                <h2 class="text-light mb-4">
                    <i class="fas fa-traffic-light me-2"></i>Traffic Lights Management
                </h2>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card cyber-card text-center p-3">
                            <i class="fas fa-map-marked-alt fa-3x text-primary mb-2"></i>
                            <h3 class="text-light"><?php echo $junctions->num_rows; ?></h3>
                            <small class="text-muted">Total Junctions</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card cyber-card text-center p-3">
                            <i class="fas fa-traffic-light fa-3x text-success mb-2"></i>
                            <h3 class="text-light">
                                <?php echo $db->query("SELECT COUNT(*) as count FROM traffic_lights")->fetch_assoc()['count']; ?>
                            </h3>
                            <small class="text-muted">Traffic Lights</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card cyber-card text-center p-3">
                            <i class="fas fa-check-circle fa-3x text-info mb-2"></i>
                            <h3 class="text-light">
                                <?php echo $db->query("SELECT COUNT(*) as count FROM traffic_lights WHERE current_phase = 'green'")->fetch_assoc()['count']; ?>
                            </h3>
                            <small class="text-muted">Currently Green</small>
                        </div>
                    </div>
                </div>

                <!-- Junctions List -->
                <?php if ($junctions->num_rows > 0): ?>
                    <?php while ($junction = $junctions->fetch_assoc()): ?>
                        <div class="card cyber-card junction-card mb-4">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="text-light mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <?php echo htmlspecialchars($junction['junction_name']); ?>
                                    </h5>
                                    <span class="badge bg-primary">
                                        <?php echo $junction['light_count']; ?> Traffic Lights
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-location-dot me-1"></i>
                                    <?php echo htmlspecialchars($junction['location']); ?>
                                </small>
                            </div>
                            <div class="card-body">
                                <?php
                                $lights = $db->query("
                                    SELECT * FROM traffic_lights 
                                    WHERE junction_id = {$junction['junction_id']}
                                ");
                                
                                if ($lights->num_rows > 0):
                                ?>
                                    <div class="row">
                                        <?php while ($light = $lights->fetch_assoc()): 
                                            $time_since_change = time() - strtotime($light['last_phase_change']);
                                            $current_duration = $light['phase_duration_' . $light['current_phase']];
                                            $remaining = max(0, $current_duration - $time_since_change);
                                        ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card bg-dark border-secondary">
                                                    <div class="card-body">
                                                        <h6 class="text-light mb-3">
                                                            Light #<?php echo $light['light_id']; ?>
                                                        </h6>
                                                        
                                                        <!-- Traffic Light Visualization -->
                                                        <div class="text-center mb-3">
                                                            <div class="light-indicator <?php echo $light['current_phase'] === 'green' ? 'light-green' : ''; ?>"></div>
                                                            <div class="light-indicator <?php echo $light['current_phase'] === 'yellow' ? 'light-yellow' : ''; ?>"></div>
                                                            <div class="light-indicator <?php echo $light['current_phase'] === 'red' ? 'light-red' : ''; ?>"></div>
                                                        </div>
                                                        
                                                        <div class="text-center mb-3">
                                                            <div class="phase-timer text-light">
                                                                <span id="timer-<?php echo $light['light_id']; ?>" data-remaining="<?php echo $remaining; ?>">
                                                                    <?php echo gmdate("i:s", $remaining); ?>
                                                                </span>
                                                            </div>
                                                            <small class="text-muted">Time remaining</small>
                                                        </div>
                                                        
                                                        <div class="text-light mb-3">
                                                            <div class="row text-center">
                                                                <div class="col-4">
                                                                    <small>Green</small>
                                                                    <div><?php echo $light['phase_duration_green']; ?>s</div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <small>Yellow</small>
                                                                    <div><?php echo $light['phase_duration_yellow']; ?>s</div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <small>Red</small>
                                                                    <div><?php echo $light['phase_duration_red']; ?>s</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Manual Override -->
                                                        <form method="POST" class="d-grid">
                                                            <input type="hidden" name="light_id" value="<?php echo $light['light_id']; ?>">
                                                            <div class="btn-group">
                                                                <button type="submit" name="change_phase" value="green" 
                                                                        class="btn btn-success btn-sm <?php echo $light['current_phase'] === 'green' ? 'active' : ''; ?>">
                                                                    Green
                                                                </button>
                                                                <button type="submit" name="change_phase" value="yellow" 
                                                                        class="btn btn-warning btn-sm <?php echo $light['current_phase'] === 'yellow' ? 'active' : ''; ?>">
                                                                    Yellow
                                                                </button>
                                                                <button type="submit" name="change_phase" value="red" 
                                                                        class="btn btn-danger btn-sm <?php echo $light['current_phase'] === 'red' ? 'active' : ''; ?>">
                                                                    Red
                                                                </button>
                                                            </div>
                                                        </form>
                                                        
                                                        <small class="text-muted d-block mt-2">
                                                            Last changed: <?php echo date('g:i:s A', strtotime($light['last_phase_change'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No traffic lights configured for this junction</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card cyber-card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-traffic-light fa-3x text-muted mb-3"></i>
                            <h4 class="text-light">No junctions configured</h4>
                            <p class="text-muted">Add junctions to start managing traffic lights</p>
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
            
            // Update timers every second
            setInterval(updateTimers, 1000);
        });
        
        function updateTimers() {
            document.querySelectorAll('[id^="timer-"]').forEach(timer => {
                let remaining = parseInt(timer.dataset.remaining);
                if (remaining > 0) {
                    remaining--;
                    timer.dataset.remaining = remaining;
                    const minutes = Math.floor(remaining / 60);
                    const seconds = remaining % 60;
                    timer.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                } else {
                    // Reload page when timer reaches 0 (phase should have changed)
                    location.reload();
                }
            });
        }
        
        // Auto-refresh every 2 minutes
        setTimeout(() => location.reload(), 120000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>