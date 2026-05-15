<?php
require_once 'config.php';
requireAuth();
$user = getCurrentUser();

// Redirect admins to their own dashboard
if ($user['user_type'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
}

$db = getDBConnection();

// Get user-specific statistics
$stats = [
    'my_reports' => $db->query("SELECT COUNT(*) as c FROM user_traffic_reports WHERE user_id = {$user['user_id']}")->fetch_assoc()['c'],
    'my_vehicles' => $db->query("SELECT COUNT(*) as c FROM vehicles WHERE user_id = {$user['user_id']}")->fetch_assoc()['c'],
    'unpaid_penalties' => $db->query("SELECT COUNT(*) as c FROM traffic_penalties WHERE user_id = {$user['user_id']} AND is_paid = FALSE")->fetch_assoc()['c'],
    'unread_notifications' => $db->query("SELECT COUNT(*) as c FROM notifications WHERE user_id = {$user['user_id']} AND is_read = FALSE")->fetch_assoc()['c'],
    'active_sensors' => $db->query("SELECT COUNT(*) as c FROM traffic_sensors WHERE is_active = TRUE")->fetch_assoc()['c'],
    'current_incidents' => $db->query("SELECT COUNT(*) as c FROM user_traffic_reports WHERE report_time >= NOW() - INTERVAL 24 HOUR")->fetch_assoc()['c'],
];

// Get average traffic speed
$avg_speed = $db->query("SELECT AVG(average_speed) as avg FROM traffic_data WHERE timestamp >= NOW() - INTERVAL 1 HOUR")->fetch_assoc()['avg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .quick-card {
            background: rgba(25, 30, 50, 0.9);
            border: 2px solid rgba(100, 100, 255, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 180px;
        }
        .quick-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .quick-card .icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
        }
        .stat-mini {
            background: rgba(25, 30, 50, 0.8);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid;
        }

        /* Animated Route Button Styles */
        .animated-route-card {
            background: rgba(25, 30, 50, 0.9);
            border: 2px solid rgba(102, 126, 234, 0.5);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 180px;
            position: relative;
            overflow: hidden;
        }
        
        .animated-route-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }

        .animated-route-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .route-animation-container {
            position: relative;
            width: 100%;
            height: 80px;
            margin-bottom: 10px;
        }

        .route-svg {
            width: 100%;
            height: 100%;
        }

        .route-path {
            fill: none;
            stroke: rgba(102, 126, 234, 0.6);
            stroke-width: 3;
            stroke-linecap: round;
            stroke-dasharray: 8, 8;
            animation: dash 20s linear infinite;
        }

        @keyframes dash {
            to {
                stroke-dashoffset: -1000;
            }
        }

        .animated-car {
            position: absolute;
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.5);
            z-index: 10;
            animation: moveAlongPath 8s ease-in-out infinite;
        }

        .animated-car i {
            color: white;
            font-size: 14px;
            animation: bounce 0.5s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        @keyframes moveAlongPath {
            0% {
                left: 5%;
                top: 50%;
                transform: translate(-50%, -50%) rotate(0deg);
            }
            10% {
                left: 15%;
                top: 30%;
                transform: translate(-50%, -50%) rotate(-20deg);
            }
            25% {
                left: 30%;
                top: 20%;
                transform: translate(-50%, -50%) rotate(-10deg);
            }
            40% {
                left: 45%;
                top: 35%;
                transform: translate(-50%, -50%) rotate(15deg);
            }
            50% {
                left: 55%;
                top: 50%;
                transform: translate(-50%, -50%) rotate(0deg);
            }
            60% {
                left: 65%;
                top: 35%;
                transform: translate(-50%, -50%) rotate(-15deg);
            }
            75% {
                left: 78%;
                top: 25%;
                transform: translate(-50%, -50%) rotate(-10deg);
            }
            90% {
                left: 90%;
                top: 45%;
                transform: translate(-50%, -50%) rotate(10deg);
            }
            100% {
                left: 95%;
                top: 50%;
                transform: translate(-50%, -50%) rotate(0deg);
            }
        }

        .route-markers {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .start-marker {
            position: absolute;
            left: 3%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            background: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(34, 197, 94, 0.6);
            animation: pulse 2s ease-in-out infinite;
        }

        .end-marker {
            position: absolute;
            right: 3%;
            top: 50%;
            transform: translate(50%, -50%);
            width: 20px;
            height: 20px;
            background: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.6);
            animation: pulse 2s ease-in-out infinite 0.5s;
        }

        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.2); }
        }

        .start-marker i, .end-marker i {
            color: white;
            font-size: 10px;
        }

        .route-text {
            position: relative;
            z-index: 5;
            text-align: center;
        }

        .route-text h5 {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .route-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            border-radius: 20px;
            font-size: 0.75rem;
            color: #10b981;
            animation: glow 2s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 0 5px rgba(16, 185, 129, 0.3); }
            50% { box-shadow: 0 0 15px rgba(16, 185, 129, 0.6); }
        }

        /* Synthwave Parking Button Styles - IMPROVED CAR */
        .synthwave-parking-card {
            background: rgba(25, 30, 50, 0.9);
            border: 2px solid rgba(42, 252, 224, 0.5);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 180px;
            position: relative;
            overflow: hidden;
        }

        .synthwave-parking-card:hover {
            border-color: #2afce0;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(42, 252, 224, 0.5);
        }

        #synthwave-mini {
            position: relative;
            width: 100%;
            height: 120px;
            background-color: #2e0d3f;
            overflow: hidden;
        }

        .parking-text-overlay {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            z-index: 10;
            width: 100%;
        }

        .parking-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(42, 252, 224, 0.2);
            border: 1px solid rgba(42, 252, 224, 0.4);
            border-radius: 20px;
            font-size: 0.7rem;
            color: #2afce0;
            animation: parkingGlow 2s ease-in-out infinite;
        }

        @keyframes parkingGlow {
            0%, 100% { box-shadow: 0 0 5px rgba(42, 252, 224, 0.3); }
            50% { box-shadow: 0 0 15px rgba(42, 252, 224, 0.6); }
        }

        /* Mini Synthwave Animation Styles */
        #sun-mini {
            overflow: hidden;
            width: 60px;
            height: 60px;
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        #ball-mini {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #fbe54f;
        }

        .stripe-mini {
            position: absolute;
            width: 100%;
            background-color: #2e0d3f;
            left: 50%;
            transform: translate(-50%);
            animation: stripeMov infinite 1s linear;
        }

        @keyframes stripeMov {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        #fog-mini {
            position: absolute;
            top: 25px;
            width: 100%;
            height: 60%;
            background-image: linear-gradient(transparent, #b811c6);
        }

        #fog2-mini {
            position: absolute;
            top: 75px;
            width: 100%;
            height: 300px;
            background-image: linear-gradient(transparent, #b711c63f);
            transform: rotate(180deg);
        }

        #stars-mini {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        .star-mini {
            background-color: white;
            border-radius: 50%;
            position: absolute;
            width: 2px;
            height: 2px;
            animation: twinkle 2s ease-in-out infinite;
        }

        .star-mini:nth-child(1) { left: 20px; top: 10px; animation-delay: 0s; }
        .star-mini:nth-child(2) { right: 30px; top: 15px; animation-delay: 0.3s; }
        .star-mini:nth-child(3) { left: 40px; top: 30px; animation-delay: 0.6s; }
        .star-mini:nth-child(4) { right: 50px; top: 25px; animation-delay: 0.9s; }
        .star-mini:nth-child(5) { left: 60%; top: 20px; animation-delay: 1.2s; }
        .star-mini:nth-child(6) { right: 40%; top: 35px; animation-delay: 1.5s; }

        @keyframes twinkle {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }

        #land-mini {
            width: 100%;
            height: 35%;
            bottom: 0;
            position: absolute;
            background-color: #120b12;
        }

        #roadSide0-mini {
            position: absolute;
            width: 80px;
            height: 400px;
            perspective: 400px;
            bottom: -170px;
            left: -30px;
        }

        #roadSideGrid0-mini {
            border: solid #2afce0 2px;
            width: 100%;
            height: 100%;
            background:
                linear-gradient(to right, #2afce0 1px, transparent 1px) 0 0 / 10px 10px,
                linear-gradient(to bottom, #2afce0 1px, #120b12 1px) 0 0 / 10px 10px;
            transform: rotateX(85deg) rotateZ(10deg);
            transform-origin: center;
            animation: movingGrid0Mini infinite 0.2s linear;
        }

        @keyframes movingGrid0Mini {
            0% { transform: rotateX(85deg) rotateZ(10deg) translateY(0px); }
            100% { transform: rotateX(85deg) rotateZ(10deg) translateY(10px); }
        }

        #roadSide1-mini {
            position: absolute;
            width: 80px;
            height: 400px;
            perspective: 400px;
            bottom: -170px;
            right: -30px;
        }

        #roadSideGrid1-mini {
            border: solid #2afce0 2px;
            width: 100%;
            height: 100%;
            background:
                linear-gradient(to right, #2afce0 1px, transparent 1px) 0 0 / 10px 10px,
                linear-gradient(to bottom, #2afce0 1px, #120b12 1px) 0 0 / 10px 10px;
            transform: rotateX(85deg) rotateZ(-10deg);
            transform-origin: center;
            animation: movingGrid1Mini infinite 0.2s linear;
        }

        @keyframes movingGrid1Mini {
            0% { transform: rotateX(85deg) rotateZ(-10deg) translateY(0px); }
            100% { transform: rotateX(85deg) rotateZ(-10deg) translateY(10px); }
        }

        #roadLines-mini {
            perspective: 400px;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: -210px;
            width: 3px;
            height: 500px;
        }

        #lines-mini {
            transform: rotateX(85deg);
            transform-origin: center;
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: repeat(4, 1fr);
            grid-row-gap: 15px;
            width: 100%;
            height: 100%;
        }

        .line-mini {
            border-radius: 10px;
            background-color: #fcff1a;
            animation: movingLinesMini infinite 0.5s linear;
        }

        @keyframes movingLinesMini {
            0% { transform: translateY(0px); }
            100% { transform: translateY(65px); }
        }

        /* IMPROVED CAR WITH SVG ICON */
        #car-mini {
            position: absolute;
            height: 35px;
            width: 60px;
            left: 50%;
            bottom: 18px;
            transform: translate(-50%);
            animation: movingCarMini infinite 4s;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes movingCarMini {
            0% { bottom: 18px; opacity: 1; transform: translate(-50%) scale(1); }
            19% { bottom: 28px; opacity: 0.8; transform: translate(-50%) scale(0.8); }
            20% { bottom: -300px; opacity: 0; transform: translate(-50%) scale(1.5); }
            30% { bottom: 18px; opacity: 1; transform: translate(-50%) scale(1); }
        }

        /* SVG Car Icon */
        #car-mini svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 4px 8px rgba(42, 252, 224, 0.6));
        }

        /* Car body gradient */
        .car-body {
            fill: url(#carGradient);
        }

        /* Car windows */
        .car-window {
            fill: #1a1a2e;
            opacity: 0.9;
        }

        /* Car lights animation */
        .car-light {
            fill: #fcff1a;
            animation: blinkLights 1s ease-in-out infinite;
        }

        @keyframes blinkLights {
            0%, 49% { opacity: 1; }
            50%, 100% { opacity: 0.3; }
        }

        /* Car wheels */
        .car-wheel {
            fill: #333;
            animation: rotateWheel 0.5s linear infinite;
            transform-origin: center;
        }

        @keyframes rotateWheel {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Weather Button Styles */
        .weather-card {
            background: rgba(25, 30, 50, 0.9);
            border: 2px solid rgba(158, 221, 254, 0.5);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 180px;
            position: relative;
            overflow: hidden;
        }

        .weather-card:hover {
            border-color: #9eddfe;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(158, 221, 254, 0.5);
        }

        .weather-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(158, 221, 254, 0.1), transparent);
            animation: shineWeather 3s infinite;
        }

        @keyframes shineWeather {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .weather-loader-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 80px;
            margin-bottom: 15px;
        }

        .weather-loader {
            width: 80px;
            height: 40px;
            border-radius: 100px 100px 0 0;
            position: relative;
            overflow: hidden;
        }

        .weather-loader:before {
            content: "";
            position: absolute;
            inset: 0 0 -100%;
            background: radial-gradient(farthest-side, #ffd738 80%, transparent) left 70% top 20%/15px 15px,
                radial-gradient(farthest-side, #020308 92%, transparent) left 65% bottom 19%/12px 12px,
                radial-gradient(farthest-side, #ecfefe 92%, transparent) left 70% bottom 20%/15px 15px,
                linear-gradient(#9eddfe 50%, #020308 0);
            background-repeat: no-repeat;
            animation: weatherAnim 2s infinite;
        }

        @keyframes weatherAnim {
            0%, 20% { transform: rotate(0); }
            40%, 60% { transform: rotate(0.5turn); }
            80%, 100% { transform: rotate(1turn); }
        }

        .weather-text {
            text-align: center;
            position: relative;
            z-index: 5;
        }

        .weather-text h5 {
            background: linear-gradient(135deg, #9eddfe 0%, #4facfe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .weather-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(158, 221, 254, 0.2);
            border: 1px solid rgba(158, 221, 254, 0.4);
            border-radius: 20px;
            font-size: 0.75rem;
            color: #9eddfe;
            animation: weatherGlow 2s ease-in-out infinite;
        }

        @keyframes weatherGlow {
            0%, 100% { box-shadow: 0 0 5px rgba(158, 221, 254, 0.3); }
            50% { box-shadow: 0 0 15px rgba(158, 221, 254, 0.6); }
        }

        /* Typewriter Report Incident Button Styles */
        .typewriter-incident-card {
            background: rgba(25, 30, 50, 0.9);
            border: 2px solid rgba(251, 197, 108, 0.5);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 180px;
            position: relative;
            overflow: hidden;
        }

        .typewriter-incident-card:hover {
            border-color: #FBC56C;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(251, 197, 108, 0.5);
        }

        .typewriter-incident-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(251, 197, 108, 0.15), transparent);
            animation: shineIncident 3s infinite;
        }

        @keyframes shineIncident {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .typewriter-container {
            margin-bottom: 20px;
            transform: scale(0.8);
        }

        .incident-text {
            margin-top: 10px;
        }

        .incident-text h5 {
            background: linear-gradient(135deg, #FBC56C 0%, #f59e0b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .incident-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(251, 197, 108, 0.2);
            border: 1px solid rgba(251, 197, 108, 0.4);
            border-radius: 20px;
            font-size: 0.7rem;
            color: #FBC56C;
            animation: incidentGlow 2s ease-in-out infinite;
        }

        @keyframes incidentGlow {
            0%, 100% { box-shadow: 0 0 5px rgba(251, 197, 108, 0.3); }
            50% { box-shadow: 0 0 15px rgba(251, 197, 108, 0.6); }
        }

        /* Typewriter Animation Styles */
        .typewriter {
            --blue: #5C86FF;
            --blue-dark: #275EFE;
            --key: #fff;
            --paper: #EEF0FD;
            --text: #D3D4EC;
            --tool: #FBC56C;
            --duration: 3s;
            position: relative;
            animation: bounce05 var(--duration) linear infinite;
        }

        .typewriter .slide {
            width: 92px;
            height: 20px;
            border-radius: 3px;
            margin-left: 14px;
            transform: translateX(14px);
            background: linear-gradient(var(--blue), var(--blue-dark));
            animation: slide05 var(--duration) ease infinite;
        }

        .typewriter .slide:before, 
        .typewriter .slide:after,
        .typewriter .slide i:before {
            content: "";
            position: absolute;
            background: var(--tool);
        }

        .typewriter .slide:before {
            width: 2px;
            height: 8px;
            top: 6px;
            left: 100%;
        }

        .typewriter .slide:after {
            left: 94px;
            top: 3px;
            height: 14px;
            width: 6px;
            border-radius: 3px;
        }

        .typewriter .slide i {
            display: block;
            position: absolute;
            right: 100%;
            width: 6px;
            height: 4px;
            top: 4px;
            background: var(--tool);
        }

        .typewriter .slide i:before {
            right: 100%;
            top: -2px;
            width: 4px;
            border-radius: 2px;
            height: 14px;
        }

        .typewriter .paper {
            position: absolute;
            left: 24px;
            top: -26px;
            width: 40px;
            height: 46px;
            border-radius: 5px;
            background: var(--paper);
            transform: translateY(46px);
            animation: paper05 var(--duration) linear infinite;
        }

        .typewriter .paper:before {
            content: "";
            position: absolute;
            left: 6px;
            right: 6px;
            top: 7px;
            border-radius: 2px;
            height: 4px;
            transform: scaleY(0.8);
            background: var(--text);
            box-shadow: 0 12px 0 var(--text), 0 24px 0 var(--text), 0 36px 0 var(--text);
        }

        .typewriter .keyboard {
            width: 120px;
            height: 56px;
            margin-top: -10px;
            z-index: 1;
            position: relative;
        }

        .typewriter .keyboard:before, 
        .typewriter .keyboard:after {
            content: "";
            position: absolute;
        }

        .typewriter .keyboard:before {
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 7px;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            transform: perspective(10px) rotateX(2deg);
            transform-origin: 50% 100%;
        }

        .typewriter .keyboard:after {
            left: 2px;
            top: 25px;
            width: 11px;
            height: 4px;
            border-radius: 2px;
            box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                        60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                        22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                        60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            animation: keyboard05 var(--duration) linear infinite;
        }

        @keyframes bounce05 {
            85%, 92%, 100% { transform: translateY(0); }
            89% { transform: translateY(-4px); }
            95% { transform: translateY(2px); }
        }

        @keyframes slide05 {
            5% { transform: translateX(14px); }
            15%, 30% { transform: translateX(6px); }
            40%, 55% { transform: translateX(0); }
            65%, 70% { transform: translateX(-4px); }
            80%, 89% { transform: translateX(-12px); }
            100% { transform: translateX(14px); }
        }

        @keyframes paper05 {
            5% { transform: translateY(46px); }
            20%, 30% { transform: translateY(34px); }
            40%, 55% { transform: translateY(22px); }
            65%, 70% { transform: translateY(10px); }
            80%, 85% { transform: translateY(0); }
            92%, 100% { transform: translateY(46px); }
        }

        @keyframes keyboard05 {
            5%, 12%, 21%, 30%, 39%, 48%, 57%, 66%, 75%, 84% {
                box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            }
            9% {
                box-shadow: 15px 2px 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            }
            18% {
                box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                            60px 2px 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            }
            27% {
                box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 12px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            }
            36% {
                box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 12px 0 var(--key), 
                            60px 12px 0 var(--key), 68px 12px 0 var(--key), 83px 10px 0 var(--key);
            }
            45% {
                box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 2px 0 var(--key), 
                            22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            }
            54% {
                box-shadow: 15px 0 0 var(--key), 30px 2px 0 var(--key), 45px 0 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            }
            63% {
                box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 12px 0 var(--key);
            }
            72% {
                box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 2px 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 10px 0 var(--key), 37px 10px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            }
            81% {
                box-shadow: 15px 0 0 var(--key), 30px 0 0 var(--key), 45px 0 0 var(--key), 
                            60px 0 0 var(--key), 75px 0 0 var(--key), 90px 0 0 var(--key), 
                            22px 10px 0 var(--key), 37px 12px 0 var(--key), 52px 10px 0 var(--key), 
                            60px 10px 0 var(--key), 68px 10px 0 var(--key), 83px 10px 0 var(--key);
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="container-fluid mt-4">
                <!-- Welcome Card -->
                <div class="welcome-card">
                    <h1 class="mb-3">Welcome back, <?php echo h($user['username']); ?>!</h1>
                    <p class="mb-4 h5">Here's your traffic overview for today</p>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h2><?php echo $stats['my_vehicles']; ?></h2>
                                <small>My Vehicles</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h2><?php echo $stats['my_reports']; ?></h2>
                                <small>My Reports</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h2 class="<?php echo $stats['unpaid_penalties'] > 0 ? 'text-warning' : ''; ?>">
                                    <?php echo $stats['unpaid_penalties']; ?>
                                </h2>
                                <small>Unpaid Penalties</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h2><?php echo $stats['unread_notifications']; ?></h2>
                                <small>Notifications</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <h3 class="text-light mb-4">What would you like to do?</h3>
                <div class="row mb-4">
                    <!-- WEATHER BUTTON (REPLACES LIVE TRAFFIC) -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <a href="weather_dashboard.php" class="text-decoration-none">
                            <div class="card weather-card">
                                <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                                    <div class="weather-loader-container">
                                        <div class="weather-loader"></div>
                                    </div>
                                    <div class="weather-text">
                                        <h5>Weather</h5>
                                        <span class="weather-badge">
                                            <i class="fas fa-cloud-sun me-1"></i>Live Forecast
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- ANIMATED REPORT INCIDENT BUTTON -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <a href="report_incident.php" class="text-decoration-none">
                            <div class="card typewriter-incident-card">
                                <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                                    <div class="typewriter-container">
                                        <div class="typewriter">
                                            <div class="slide"><i></i></div>
                                            <div class="paper"></div>
                                            <div class="keyboard"></div>
                                        </div>
                                    </div>
                                    <div class="incident-text">
                                        <h5 class="text-light mb-1">Report Incident</h5>
                                        <span class="incident-badge">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Quick Alert
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- ANIMATED PLAN ROUTE BUTTON -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <a href="route_planner.php" class="text-decoration-none">
                            <div class="card animated-route-card">
                                <div class="card-body text-center d-flex flex-column justify-content-center p-3">
                                    <div class="route-animation-container">
                                        <!-- SVG Path for the route -->
                                        <svg class="route-svg" viewBox="0 0 200 60" preserveAspectRatio="none">
                                            <path class="route-path" 
                                                  d="M 10,30 Q 40,15 60,20 T 100,30 Q 130,35 160,25 T 190,30" />
                                        </svg>
                                        
                                        <!-- Route markers -->
                                        <div class="route-markers">
                                            <div class="start-marker">
                                                <i class="fas fa-play"></i>
                                            </div>
                                            <div class="end-marker">
                                                <i class="fas fa-flag"></i>
                                            </div>
                                        </div>
                                        
                                        <!-- Animated car -->
                                        <div class="animated-car">
                                            <i class="fas fa-car"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="route-text">
                                        <h5>Plan Route</h5>
                                        <span class="route-badge">
                                            <i class="fas fa-route me-1"></i>Smart Navigation
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- ANIMATED FIND PARKING BUTTON (WITH IMPROVED SVG CAR) -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <a href="parking.php" class="text-decoration-none">
                            <div class="card synthwave-parking-card">
                                <div class="card-body p-0">
                                    <div id="synthwave-mini">
                                        <div id="stars-mini">
                                            <div class="star-mini"></div>
                                            <div class="star-mini"></div>
                                            <div class="star-mini"></div>
                                            <div class="star-mini"></div>
                                            <div class="star-mini"></div>
                                            <div class="star-mini"></div>
                                        </div>
                                        <div id="sun-mini">
                                            <div id="ball-mini"></div>
                                            <div class="stripe-mini" style="top: 8px; height: 2px;"></div>
                                            <div class="stripe-mini" style="top: 16px; height: 2px;"></div>
                                            <div class="stripe-mini" style="top: 24px; height: 3px;"></div>
                                            <div class="stripe-mini" style="top: 32px; height: 3px;"></div>
                                            <div class="stripe-mini" style="top: 40px; height: 4px;"></div>
                                        </div>
                                        <div id="fog-mini"></div>
                                        <div id="land-mini"></div>
                                        <div id="roadSide0-mini">
                                            <div id="roadSideGrid0-mini"></div>
                                        </div>
                                        <div id="roadSide1-mini">
                                            <div id="roadSideGrid1-mini"></div>
                                        </div>
                                        <div id="roadLines-mini">
                                            <div id="lines-mini">
                                                <div class="line-mini"></div>
                                                <div class="line-mini"></div>
                                                <div class="line-mini"></div>
                                                <div class="line-mini"></div>
                                            </div>
                                        </div>
                                        <!-- IMPROVED SVG CAR -->
                                        <div id="car-mini">
                                            <svg viewBox="0 0 100 50" xmlns="http://www.w3.org/2000/svg">
                                                <defs>
                                                    <linearGradient id="carGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                                        <stop offset="0%" style="stop-color:#2afce0;stop-opacity:1" />
                                                        <stop offset="100%" style="stop-color:#1a9dbb;stop-opacity:1" />
                                                    </linearGradient>
                                                </defs>
                                                
                                                <!-- Car Body -->
                                                <rect class="car-body" x="15" y="25" width="70" height="15" rx="3"/>
                                                <path class="car-body" d="M 25 25 L 35 15 L 65 15 L 75 25 Z"/>
                                                
                                                <!-- Windows -->
                                                <rect class="car-window" x="28" y="17" width="15" height="7" rx="1"/>
                                                <rect class="car-window" x="57" y="17" width="15" height="7" rx="1"/>
                                                
                                                <!-- Headlights -->
                                                <circle class="car-light" cx="82" cy="32" r="2"/>
                                                <circle class="car-light" cx="82" cy="38" r="2"/>
                                                
                                                <!-- Taillights -->
                                                <rect class="car-light" x="16" y="30" width="3" height="3" rx="1"/>
                                                <rect class="car-light" x="16" y="37" width="3" height="3" rx="1"/>
                                                
                                                <!-- Wheels -->
                                                <circle class="car-wheel" cx="30" cy="40" r="5"/>
                                                <circle class="car-wheel" cx="70" cy="40" r="5"/>
                                                <circle fill="#555" cx="30" cy="40" r="3"/>
                                                <circle fill="#555" cx="70" cy="40" r="3"/>
                                            </svg>
                                        </div>
                                        <div id="fog2-mini"></div>
                                    </div>
                                    <div class="parking-text-overlay">
                                        <h5 class="text-light mb-1">Find Parking</h5>
                                        <span class="parking-badge">
                                            <i class="fas fa-parking me-1"></i>Real-time Availability
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- My Stuff Section -->
                <h3 class="text-light mb-4">My Account</h3>
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="my_vehicles.php" class="text-decoration-none">
                            <div class="stat-mini border-primary">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="text-light mb-0"><?php echo $stats['my_vehicles']; ?></h4>
                                        <small class="text-muted">My Vehicles</small>
                                    </div>
                                    <i class="fas fa-car-side fa-2x text-primary"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="penalties.php" class="text-decoration-none">
                            <div class="stat-mini border-<?php echo $stats['unpaid_penalties'] > 0 ? 'warning' : 'success'; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="text-light mb-0"><?php echo $stats['unpaid_penalties']; ?></h4>
                                        <small class="text-muted">Unpaid Penalties</small>
                                    </div>
                                    <i class="fas fa-receipt fa-2x text-<?php echo $stats['unpaid_penalties'] > 0 ? 'warning' : 'success'; ?>"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="view_reports.php" class="text-decoration-none">
                            <div class="stat-mini border-info">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="text-light mb-0"><?php echo $stats['my_reports']; ?></h4>
                                        <small class="text-muted">My Reports</small>
                                    </div>
                                    <i class="fas fa-list fa-2x text-info"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="notifications.php" class="text-decoration-none">
                            <div class="stat-mini border-danger">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="text-light mb-0"><?php echo $stats['unread_notifications']; ?></h4>
                                        <small class="text-muted">Notifications</small>
                                    </div>
                                    <i class="fas fa-bell fa-2x text-danger"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Live Data Section -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card cyber-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-light">
                                    <i class="fas fa-traffic-light me-2"></i>Live Traffic Conditions
                                </h5>
                                <span class="badge bg-success">Auto-refresh: 30s</span>
                            </div>
                            <div class="card-body" id="liveTraffic">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary"></div>
                                    <p class="mt-3 text-muted">Loading live traffic data...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-4">
                        <div class="card cyber-card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0 text-light"><i class="fas fa-cloud-sun me-2"></i>Weather Impact</h6>
                            </div>
                            <div class="card-body" id="weatherWidget">
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card cyber-card">
                            <div class="card-header">
                                <h6 class="mb-0 text-light"><i class="fas fa-info-circle me-2"></i>System Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-light">Active Sensors</span>
                                        <span class="badge bg-success"><?php echo $stats['active_sensors']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-light">Current Incidents</span>
                                        <span class="badge bg-warning"><?php echo $stats['current_incidents']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-light">Avg Speed</span>
                                        <span class="badge bg-info"><?php echo round($avg_speed ?? 0, 1); ?> km/h</span>
                                    </div>
                                </div>
                                <a href="public_transport.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-bus me-2"></i>Public Transport
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="darkveil.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body);
            loadLiveData();
            setInterval(loadLiveData, 30000); // Auto-refresh every 30 seconds
        });

        function loadLiveData() {
            // Load traffic data
            fetch('ajax_dashboard.php')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('liveTraffic').innerHTML = data.trafficTable;
                })
                .catch(e => {
                    document.getElementById('liveTraffic').innerHTML = '<div class="alert alert-danger">Error loading traffic data</div>';
                });
            
            // Load weather
            fetch('ajax_weather.php')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('weatherWidget').innerHTML = data.weatherWidget;
                })
                .catch(e => {
                    document.getElementById('weatherWidget').innerHTML = '<p class="text-muted small">Weather unavailable</p>';
                });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>