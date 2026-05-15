<?php
require_once 'config.php';
require_once 'weather_config.php';
requireAuth();

header('Content-Type: application/json');

$city = DEFAULT_CITY;
if (isset($_GET['city']) && !empty($_GET['city'])) {
    $city = $_GET['city'];
}

$weather_data = getWeatherData($city);

if (isset($weather_data['error'])) {
    $weatherWidget = '
    <div class="text-center text-muted py-3">
        <i class="fas fa-cloud-slash fa-2x mb-2"></i>
        <p class="mb-1">Weather data unavailable</p>
        <small>' . htmlspecialchars($weather_data['message']) . '</small>
    </div>
    ';
} else {
    $current = $weather_data['current'];
    $impact = getTrafficImpact($current);
    
    $weatherWidget = '
    <div class="row align-items-center">
        <div class="col-md-3 text-center">
            <div class="weather-icon-main">
                <i class="fas fa-' . getWeatherIcon($current['weather'][0]['icon']) . ' fa-3x text-light mb-2"></i>
            </div>
            <div class="temperature-display text-light">
                <h3 class="mb-0">' . round($current['main']['temp']) . '°C</h3>
                <small>' . $current['weather'][0]['main'] . '</small>
            </div>
        </div>
        
        <div class="col-md-5">
            <div class="weather-details text-light">
                <div class="row text-center">
                    <div class="col-4">
                        <i class="fas fa-tint mb-1"></i>
                        <div><small>Humidity</small></div>
                        <strong>' . $current['main']['humidity'] . '%</strong>
                    </div>
                    <div class="col-4">
                        <i class="fas fa-wind mb-1"></i>
                        <div><small>Wind</small></div>
                        <strong>' . $current['wind']['speed'] . ' m/s</strong>
                    </div>
                    <div class="col-4">
                        <i class="fas fa-eye mb-1"></i>
                        <div><small>Visibility</small></div>
                        <strong>' . number_format(($current['visibility'] ?? 10000) / 1000, 1) . ' km</strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="traffic-impact text-center">
                <span class="badge bg-' . $impact['color'] . ' mb-2 p-2">
                    <i class="fas fa-car-side me-1"></i>
                    ' . $impact['level'] . ' Impact
                </span>
                <p class="small mb-0 text-light">' . $impact['message'] . '</p>
                <small class="text-light">' . htmlspecialchars($weather_data['city']) . '</small>
            </div>
        </div>
    </div>
    ';
}

echo json_encode(['weatherWidget' => $weatherWidget]);
?>