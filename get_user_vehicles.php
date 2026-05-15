<?php
require_once 'config.php';

if (!isset($_GET['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = (int)$_GET['user_id'];
$db = getDBConnection();

$stmt = $db->prepare("
    SELECT vehicle_id, license_plate, vehicle_type, make, model, color, year 
    FROM vehicles 
    WHERE user_id = ? 
    ORDER BY license_plate
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$vehicles = [];
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}

header('Content-Type: application/json');
echo json_encode($vehicles);
?>