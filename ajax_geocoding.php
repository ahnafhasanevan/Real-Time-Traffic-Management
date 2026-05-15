<?php
require_once 'config.php';
require_once 'maptiler_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Reverse Geocoding - Convert coordinates to place name
if (isset($_POST['action']) && $_POST['action'] === 'reverse_geocode') {
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);
    
    $url = MAPTILER_GEOCODING_URL . $lng . ',' . $lat . '.json?key=' . MAPTILER_API_KEY . '&limit=1';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['features'])) {
            $placeName = $data['features'][0]['place_name'];
            echo json_encode([
                'success' => true,
                'place_name' => $placeName
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No location found for these coordinates'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Geocoding service unavailable'
        ]);
    }
    exit;
}

// Forward Geocoding - Convert place name to coordinates
if (isset($_POST['action']) && $_POST['action'] === 'forward_geocode') {
    $query = trim($_POST['query']);
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'Empty query']);
        exit;
    }
    
    // Add Bangladesh context for better results
    $searchQuery = urlencode($query . ', Bangladesh');
    $url = MAPTILER_GEOCODING_URL . $searchQuery . '.json?key=' . MAPTILER_API_KEY . '&limit=5';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        // Debug logging (remove in production)
        error_log("MapTiler Forward Geocoding Response: " . print_r($data, true));
        
        if (!empty($data['features']) && is_array($data['features'])) {
            $results = [];
            foreach ($data['features'] as $feature) {
                $results[] = [
                    'place_name' => $feature['place_name'],
                    'latitude' => $feature['geometry']['coordinates'][1],
                    'longitude' => $feature['geometry']['coordinates'][0],
                    'relevance' => $feature['relevance'] ?? 0
                ];
            }
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No results found for "' . $query . '"',
                'debug' => $data // Include debug info
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Geocoding service unavailable. HTTP Code: ' . $httpCode
        ]);
    }
    exit;
}

// === ADD THE BUS LOCATION SEARCH CODE RIGHT HERE ===
// Bus location search
if (isset($_POST['action']) && $_POST['action'] === 'search_bus_locations') {
    $query = trim($_POST['query']);
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'Empty query']);
        exit;
    }
    
    $db = getDBConnection();
    
    $search_stmt = $db->prepare("
        SELECT location_name, location_name_bangla 
        FROM bus_locations 
        WHERE location_name LIKE ? OR location_name_bangla LIKE ?
        ORDER BY location_name
        LIMIT 10
    ");
    
    $search_param = "%$query%";
    $search_stmt->bind_param("ss", $search_param, $search_param);
    $search_stmt->execute();
    $result_set = $search_stmt->get_result();
    
    $locations = [];
    while ($row = $result_set->fetch_assoc()) {
        $locations[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'results' => $locations
    ]);
    exit;
}
// === END OF BUS LOCATION SEARCH CODE ===

echo json_encode(['error' => 'Invalid action']);
?>