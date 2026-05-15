<?php
// Weather API Configuration - FIXED to prevent redefinition errors
if (!defined('WEATHER_API_KEY')) {
    define('WEATHER_API_KEY', '2eb89733f74b3b38e63e5753f0bd490b'); // Replace with your actual API key from openweathermap.org
}
if (!defined('WEATHER_API_URL')) {
    define('WEATHER_API_URL', 'https://api.openweathermap.org/data/2.5/');
}
if (!defined('DEFAULT_CITY')) {
    define('DEFAULT_CITY', 'Dhaka, Bangladesh');
}
if (!defined('WEATHER_CACHE_DURATION')) {
    define('WEATHER_CACHE_DURATION', 600); // 10 minutes cache
}

// Function to get weather data with caching
function getWeatherData($city = DEFAULT_CITY) {
    // Check cache first
    $cache_dir = __DIR__ . '/cache';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0777, true);
    }
    
    $cache_file = $cache_dir . '/weather_' . md5($city) . '.json';
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < WEATHER_CACHE_DURATION) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data && !isset($cached_data['error'])) {
            return $cached_data;
        }
    }
    
    $api_key = WEATHER_API_KEY;
    
    // Check if API key is set
    if ($api_key === 'YOUR_API_KEY_HERE' || empty($api_key)) {
        return [
            'error' => true,
            'message' => 'Weather API key not configured. Please add your API key in weather_config.php'
        ];
    }
    
    // Get current weather
    $current_url = WEATHER_API_URL . "weather?q=" . urlencode($city) . "&appid=" . $api_key . "&units=metric";
    $current_data = makeApiRequest($current_url);
    
    if (isset($current_data['error'])) {
        return $current_data;
    }
    
    // Get forecast data
    $forecast_url = WEATHER_API_URL . "forecast?q=" . urlencode($city) . "&appid=" . $api_key . "&units=metric&cnt=8";
    $forecast_data = makeApiRequest($forecast_url);
    
    $weather_data = [
        'current' => $current_data,
        'forecast' => $forecast_data['list'] ?? [],
        'city' => $current_data['name'] ?? $city,
        'timestamp' => time()
    ];
    
    // Save to cache
    file_put_contents($cache_file, json_encode($weather_data));
    
    return $weather_data;
}

// Function to make API requests
function makeApiRequest($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'TrafficManagementSystem/1.0',
        CURLOPT_FAILONERROR => false,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'error' => true,
            'message' => 'Weather API error: ' . ($error ?: 'HTTP ' . $http_code),
            'http_code' => $http_code
        ];
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['cod']) && $data['cod'] != 200) {
        return [
            'error' => true,
            'message' => 'Weather API error: ' . ($data['message'] ?? 'Unknown error'),
            'api_code' => $data['cod']
        ];
    }
    
    return $data;
}

// Get weather icon mapping
function getWeatherIcon($icon_code) {
    $icon_map = [
        '01d' => 'sun',
        '01n' => 'moon',
        '02d' => 'cloud-sun',
        '02n' => 'cloud-moon',
        '03d' => 'cloud',
        '03n' => 'cloud',
        '04d' => 'cloud',
        '04n' => 'cloud',
        '09d' => 'cloud-rain',
        '09n' => 'cloud-rain',
        '10d' => 'cloud-sun-rain',
        '10n' => 'cloud-moon-rain',
        '11d' => 'bolt',
        '11n' => 'bolt',
        '13d' => 'snowflake',
        '13n' => 'snowflake',
        '50d' => 'smog',
        '50n' => 'smog'
    ];
    
    return $icon_map[$icon_code] ?? 'sun';
}

// Get traffic impact based on weather
function getTrafficImpact($weather_data) {
    if (isset($weather_data['error'])) {
        return ['level' => 'Unknown', 'color' => 'secondary', 'message' => 'Weather data unavailable'];
    }
    
    $condition = $weather_data['weather'][0]['main'];
    $description = strtolower($weather_data['weather'][0]['description']);
    $wind_speed = $weather_data['wind']['speed'];
    $visibility = $weather_data['visibility'] ?? 10000;
    
    if (in_array($condition, ['Thunderstorm', 'Squall', 'Tornado'])) {
        return ['level' => 'Severe', 'color' => 'dark', 'message' => 'Dangerous conditions - avoid travel'];
    }
    
    if ($condition === 'Rain' && $wind_speed > 8) {
        return ['level' => 'High', 'color' => 'danger', 'message' => 'Heavy rain with strong winds'];
    }
    
    if ($condition === 'Rain') {
        return ['level' => 'Medium', 'color' => 'warning', 'message' => 'Rain - wet roads, reduced visibility'];
    }
    
    if ($visibility < 2000) {
        return ['level' => 'Medium', 'color' => 'warning', 'message' => 'Low visibility - drive carefully'];
    }
    
    return ['level' => 'Low', 'color' => 'success', 'message' => 'Good driving conditions'];
}

// Get weather alert level
function getWeatherAlert($weather_data) {
    if (isset($weather_data['error'])) {
        return ['level' => 'info', 'message' => 'Weather data currently unavailable'];
    }
    
    $impact = getTrafficImpact($weather_data);
    
    if ($impact['level'] === 'Severe') {
        return ['level' => 'high', 'message' => 'SEVERE WEATHER ALERT: ' . $impact['message']];
    }
    
    if ($impact['level'] === 'High') {
        return ['level' => 'medium', 'message' => 'WEATHER WARNING: ' . $impact['message']];
    }
    
    return ['level' => 'low', 'message' => 'Normal weather conditions'];
}
?>