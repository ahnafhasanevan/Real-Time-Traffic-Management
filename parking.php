<?php
require_once 'config.php';
requireAuth();
$db = getDBConnection();

// Handle search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $stmt = $db->prepare("SELECT * FROM parking_spaces WHERE is_operational = TRUE AND location LIKE ? ORDER BY available_spaces DESC");
    $searchTerm = "%" . $search . "%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $parking = $stmt->get_result();
} else {
    $parking = $db->query("SELECT * FROM parking_spaces WHERE is_operational = TRUE ORDER BY available_spaces DESC");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-light"><i class="fas fa-parking me-2"></i>Parking Spaces</h2>
            
            <!-- Search Form -->
            <form method="GET" class="d-flex">
                <div class="input-group">
                    <input type="text" name="search" class="form-control cyber-input" 
                           placeholder="Search location..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="parking.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!empty($search)): ?>
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                Showing results for: "<strong><?= htmlspecialchars($search) ?></strong>"
                <a href="parking.php" class="float-end text-decoration-none">
                    <small>Show all parking spaces</small>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($parking->num_rows === 0): ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No parking spaces found<?= !empty($search) ? ' matching your search' : '' ?>.
            </div>
        <?php endif; ?>

        <div class="row">
            <?php while($p = $parking->fetch_assoc()): 
                $avail_percent = ($p['available_spaces'] / $p['total_spaces']) * 100;
                $status = $avail_percent > 50 ? 'success' : ($avail_percent > 20 ? 'warning' : 'danger');
            ?>
            <div class="col-md-4 mb-4">
                <div class="card cyber-card">
                    <div class="card-body">
                        <h5 class="text-light"><?= htmlspecialchars($p['location']) ?></h5>
                        <h3 class="text-<?= $status ?>"><?= $p['available_spaces'] ?> / <?= $p['total_spaces'] ?></h3>
                        <p class="text-muted">Rate: ৳<?= number_format($p['hourly_rate'], 2) ?>/hr</p>
                        <div class="progress">
                            <div class="progress-bar bg-<?= $status ?>" style="width: <?= 100-$avail_percent ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <script src="darkveil.js"></script>
    <script>new DarkVeil(document.body);</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>