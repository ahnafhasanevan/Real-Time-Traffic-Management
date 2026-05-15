<?php
require_once 'config.php';

if (!isset($_GET['q']) || strlen($_GET['q']) < 2) {
    echo json_encode([]);
    exit;
}

$search = '%' . $_GET['q'] . '%';
$db = getDBConnection();

$stmt = $db->prepare("
    SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, 
           COUNT(v.vehicle_id) as vehicle_count
    FROM users u 
    LEFT JOIN vehicles v ON u.user_id = v.user_id 
    WHERE (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)
    AND u.is_active = TRUE
    GROUP BY u.user_id 
    ORDER BY u.username
    LIMIT 10
");
$stmt->bind_param("ssss", $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

header('Content-Type: application/json');
echo json_encode($users);
?>