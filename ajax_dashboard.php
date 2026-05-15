<?php
require_once 'config.php';
requireAuth();

header('Content-Type: application/json');

$db = getDBConnection();
$response = [];

// Get active sensors count
$result = $db->query("SELECT COUNT(*) as count FROM traffic_sensors WHERE is_active = TRUE");
$response['activeSensors'] = $result->fetch_assoc()['count'];

// Get current incidents (reports from last 24 hours)
$result = $db->query("SELECT COUNT(*) as count FROM user_traffic_reports WHERE report_time >= NOW() - INTERVAL 24 HOUR");
$response['currentIncidents'] = $result->fetch_assoc()['count'];

// Get today's reports
$result = $db->query("SELECT COUNT(*) as count FROM user_traffic_reports WHERE DATE(report_time) = CURDATE()");
$response['todayReports'] = $result->fetch_assoc()['count'];

// Get average speed
$result = $db->query("SELECT AVG(average_speed) as avg FROM traffic_data WHERE timestamp >= NOW() - INTERVAL 1 HOUR");
$response['avgSpeed'] = round($result->fetch_assoc()['avg'] ?? 0, 1);

// Get live traffic data
$result = $db->query("
    SELECT r.road_name, rs.segment_id, td.average_speed, td.congestion_level, td.timestamp 
    FROM traffic_data td 
    JOIN road_segments rs ON td.segment_id = rs.segment_id 
    JOIN roads r ON rs.road_id = r.road_id 
    WHERE td.timestamp >= NOW() - INTERVAL 30 MINUTE 
    ORDER BY td.timestamp DESC 
    LIMIT 10
");

$trafficTable = '<div class="table-responsive"><table class="table table-sm table-hover">';
$trafficTable .= '<thead><tr><th>Road</th><th>Speed</th><th>Congestion</th><th>Last Update</th></tr></thead><tbody>';

while ($row = $result->fetch_assoc()) {
    $congestionClass = 'traffic-' . $row['congestion_level'];
    $trafficTable .= "<tr class='{$congestionClass}'>";
    $trafficTable .= "<td>{$row['road_name']}</td>";
    $trafficTable .= "<td>{$row['average_speed']} km/h</td>";
    $trafficTable .= "<td><span class='badge bg-".getCongestionColor($row['congestion_level'])."'>".ucfirst($row['congestion_level'])."</span></td>";
    $trafficTable .= "<td>".date('H:i', strtotime($row['timestamp']))."</td>";
    $trafficTable .= "</tr>";
}
$trafficTable .= '</tbody></table></div>';
$response['trafficTable'] = $trafficTable;

// Get recent reports
$result = $db->query("
    SELECT utr.*, r.road_name, u.username 
    FROM user_traffic_reports utr 
    JOIN road_segments rs ON utr.segment_id = rs.segment_id 
    JOIN roads r ON rs.road_id = r.road_id 
    JOIN users u ON utr.user_id = u.user_id 
    ORDER BY utr.report_time DESC 
    LIMIT 5
");

$recentReports = '';
while ($row = $result->fetch_assoc()) {
    $recentReports .= '<div class="border-start border-4 border-'.getSeverityColor($row['severity']).' ps-3 mb-3">';
    $recentReports .= '<div class="d-flex justify-content-between">';
    $recentReports .= '<strong>'.ucfirst($row['report_type']).' - '.$row['road_name'].'</strong>';
    $recentReports .= '<small class="text-muted">'.date('M j, H:i', strtotime($row['report_time'])).'</small>';
    $recentReports .= '</div>';
    $recentReports .= '<div class="text-muted">Reported by: '.$row['username'].'</div>';
    $recentReports .= '<div>'.$row['description'].'</div>';
    $recentReports .= '<span class="badge bg-'.getSeverityColor($row['severity']).'">'.ucfirst($row['severity']).' severity</span>';
    $recentReports .= '</div>';
}
$response['recentReports'] = $recentReports ?: '<p class="text-muted">No recent reports</p>';

echo json_encode($response);

function getCongestionColor($level) {
    switch($level) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'orange';
        case 'severe': return 'danger';
        default: return 'secondary';
    }
}

function getSeverityColor($severity) {
    switch($severity) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'danger';
        default: return 'secondary';
    }
}
?>