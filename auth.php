<?php
require_once 'config.php';

class Auth {
    private $db;
    private $max_login_attempts = 5;
    private $lockout_duration = 900; // 15 minutes in seconds
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    public function login($username, $password) {
        // Check if user is temporarily locked out
        if ($this->isLockedOut($username)) {
            return "Too many login attempts. Please try again in 15 minutes.";
        }
        
        $stmt = $this->db->prepare("SELECT user_id, username, password_hash, user_type, login_attempts, is_active FROM users WHERE (username = ? OR email = ?)");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $this->recordFailedAttempt($username);
            return "Invalid username or password";
        }
        
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if (!$user['is_active']) {
            return "This account has been deactivated. Please contact administrator.";
        }
        
        if (password_verify($password, $user['password_hash'])) {
            // Successful login - reset attempt counter
            $this->resetLoginAttempts($user['user_id']);
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Update last login
            $this->updateLastLogin($user['user_id']);
            
            return true;
        } else {
            // Failed login - increment attempt counter
            $this->recordFailedAttempt($username, $user['user_id']);
            $attempts_left = $this->max_login_attempts - ($user['login_attempts'] + 1);
            
            if ($attempts_left <= 0) {
                return "Too many failed attempts. Account temporarily locked for 15 minutes.";
            }
            
            return "Invalid username or password. {$attempts_left} attempts remaining.";
        }
    }
    
    private function isLockedOut($username) {
        $stmt = $this->db->prepare("SELECT last_failed_attempt, login_attempts FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($user['login_attempts'] >= $this->max_login_attempts) {
                $last_attempt = strtotime($user['last_failed_attempt']);
                if (time() - $last_attempt < $this->lockout_duration) {
                    return true;
                } else {
                    // Lockout period expired, reset attempts
                    $this->resetLoginAttemptsByUsername($username);
                }
            }
        }
        return false;
    }
    
    private function recordFailedAttempt($username, $user_id = null) {
        if (!$user_id) {
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $user_id = $user['user_id'];
            } else {
                return; // User doesn't exist
            }
        }
        
        $stmt = $this->db->prepare("UPDATE users SET login_attempts = login_attempts + 1, last_failed_attempt = NOW() WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    private function resetLoginAttempts($user_id) {
        $stmt = $this->db->prepare("UPDATE users SET login_attempts = 0, last_failed_attempt = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    private function resetLoginAttemptsByUsername($username) {
        $stmt = $this->db->prepare("UPDATE users SET login_attempts = 0, last_failed_attempt = NULL WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    // In Auth class - update the register method
    public function register($username, $email, $password, $firstName, $lastName, $phoneNumber = null,
        $userType = 'public_user') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password_hash, first_name, 
                last_name, phone_number, user_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $email, $passwordHash, $firstName, $lastName, $phoneNumber, 
                $userType);
    
            return $stmt->execute();
    }
    
    public function logout() {
        session_destroy();
        header("Location: login.php");
        exit;
    }
}
?>