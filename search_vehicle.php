<?php
require_once 'config.php';

if (!isset($_GET['license_plate']) || empty($_GET['license_plate'])) {
    echo json_encode(['error' => 'License plate required']);
    exit;
}

$license_plate = trim($_GET['license_plate']);
$db = getDBConnection();

$stmt = $db->prepare("
    SELECT 
        v.license_plate,
        v.vehicle_type,
        v.make,
        v.model,
        v.color,
        v.year,
        u.user_id,
        u.username,
        u.first_name,
        u.last_name,
        u.phone_number,
        u.user_type,
        u.email
    FROM vehicles v 
    JOIN users u ON v.user_id = u.user_id 
    WHERE v.license_plate = ? AND u.is_active = TRUE
");
$stmt->bind_param("s", $license_plate);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $vehicle_data = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($vehicle_data);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Vehicle not found']);
}
?>