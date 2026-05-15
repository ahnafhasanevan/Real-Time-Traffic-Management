<?php $currentUser = getCurrentUser(); ?>
<style>
    .navbar-scrolled {
        background-color: rgba(33, 37, 41, 0.98) !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
    }
    
    .navbar {
        transition: all 0.3s ease;
    }
    
    .navbar-brand {
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .navbar-scrolled .navbar-brand {
        font-size: 1rem;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top" id="mainNavbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-traffic-light me-2"></i>Traffic Management System
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-home me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="traffic_data.php">
                        <i class="fas fa-car me-1"></i>Live Traffic
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="route_planner.php">
                        <i class="fas fa-route me-1"></i>Route Planner
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="weather_dashboard.php">
                        <i class="fas fa-cloud-sun me-1"></i>Weather
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="notifications.php">
                        <i class="fas fa-bell me-1"></i>Notifications
                        <?php 
                        $db = getDBConnection();
                        $unread = $db->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = {$currentUser['user_id']} AND is_read = FALSE")->fetch_assoc()['count'];
                        if ($unread > 0): 
                        ?>
                        <span class="badge bg-danger"><?php echo $unread; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="penalties.php">
                        <i class="fas fa-receipt me-1"></i>Penalties
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($currentUser['username']); ?>
                        <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $currentUser['user_type'])); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="my_vehicles.php"><i class="fas fa-car me-2"></i>My Vehicles</a></li>
                        <li><a class="dropdown-item" href="view_reports.php"><i class="fas fa-list me-2"></i>My Reports</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($currentUser['user_type'] === 'admin' || $currentUser['user_type'] === 'traffic_manager'): ?>
                        <li><a class="dropdown-item" href="manage_sensors.php"><i class="fas fa-microchip me-2"></i>Manage Sensors</a></li>
                        <li><a class="dropdown-item" href="manage_events.php"><i class="fas fa-calendar me-2"></i>Manage Events</a></li>
                        <li><a class="dropdown-item" href="emergency_vehicles.php"><i class="fas fa-ambulance me-2"></i>Emergency</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-warning" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
// Add scroll effect to navbar
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('mainNavbar');
    if (window.scrollY > 50) {
        navbar.classList.add('navbar-scrolled');
    } else {
        navbar.classList.remove('navbar-scrolled');
    }
});
</script>

<!-- Add padding to body to account for fixed navbar -->
<style>
    body {
        padding-top: 70px;
    }
</style>