<?php
require_once 'config.php';
require_once 'auth.php';

// Redirect to index if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';
$valid_token = false;

// Check if token is provided and valid
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $valid_token = true;
        $user_id = $user['user_id'];
        
        // Handle password reset
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($password) || empty($confirm_password)) {
                $error = "Please enter both password fields";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters long";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password and clear reset token
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
                $stmt->bind_param("si", $password_hash, $user_id);
                
                if ($stmt->execute()) {
                    $success = "Password reset successfully! You can now <a href='login.php' class='alert-link'>login</a> with your new password.";
                    $valid_token = false; // Token is now used
                } else {
                    $error = "Error resetting password. Please try again.";
                }
            }
        }
    } else {
        $error = "Invalid or expired reset token";
    }
} else {
    $error = "No reset token provided";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Traffic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .reset-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body class="reset-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white text-center">
                        <h4><i class="fas fa-key me-2"></i>Reset Password</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error && !$valid_token): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary">Back to Login</a>
                            </div>
                        <?php else: ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php else: ?>
                                
                                <?php if ($valid_token): ?>
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="password" required 
                                                   placeholder="Enter new password">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required 
                                                   placeholder="Confirm new password">
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                                    </form>
                                <?php endif; ?>
                                
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>