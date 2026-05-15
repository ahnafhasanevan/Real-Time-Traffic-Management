<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'real_t_traffic_m');
require_once 'maptiler_config.php';

// Session configuration
session_start();

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Redirect to login if not authenticated
function requireAuth() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Get current user info
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'user_type' => $_SESSION['user_type']
        ];
    }
    return null;
}

// Check if user is admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

// Check if user is admin or traffic manager
function isManager() {
    return isLoggedIn() && ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'traffic_manager');
}

// REMOVED ALL DUPLICATE FUNCTIONS - They should only be in their respective files
// getSeverityColor() - keep only in view_reports.php
// getCongestionColor() - keep only in ajax_dashboard.php
// getReportIcon() - keep only in view_reports.php

// Utility function for safe HTML output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Format date for display
function formatDate($date, $format = 'M j, Y g:i A') {
    return date($format, strtotime($date));
}

// Send notification to user
function sendNotification($db, $user_id, $type, $title, $message) {
    $stmt = $db->prepare("INSERT INTO notifications (user_id, notification_type, title, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $type, $title, $message);
    return $stmt->execute();
}

// Log system action
function logAction($db, $user_id, $action, $description) {
    $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("isss", $user_id, $action, $description, $ip);
    return $stmt->execute();
}
?>