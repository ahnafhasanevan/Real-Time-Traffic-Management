<?php
require_once 'config.php';
require_once 'auth.php';

// Redirect to index if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$auth = new Auth();
$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        $result = $auth->login($username, $password);
        
        if ($result === true) {
            if ($remember_me) {
                setcookie('remember_user', $username, time() + (30 * 24 * 60 * 60), "/");
            }
            
            header("Location: index.php");
            exit;
        } else {
            $error = $result;
        }
    }
}

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_request'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT user_id, username FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $reset_token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            
            $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $reset_token, $expires, $user['user_id']);
            
            if ($stmt->execute()) {
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $reset_token;
                $success = "Password reset link has been generated. For demo purposes, here's your reset link: <br><br>
                          <code><a href='$reset_link' target='_blank'>$reset_link</a></code><br><br>
                          In a production environment, this would be sent to your email.";
            } else {
                $error = "Error generating reset token. Please try again.";
            }
        } else {
            $error = "No account found with that email address";
        }
    }
}

$remembered_user = isset($_COOKIE['remember_user']) ? $_COOKIE['remember_user'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Traffic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="darkveil.css">
    <style>
        body {
            background: url('wp_1.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
            position: relative;
            z-index: 2;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .demo-credentials {
            background-color: rgba(40, 45, 65, 0.8);
            border-left: 4px solid #667eea;
            color: #e0e0ff;
        }

        /* Enhanced Button Styling */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shine 1.5s infinite;
        }

        @keyframes shine {
            100% {
                left: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="login-container">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-md-6 col-lg-5">
                            <div class="card cyber-card shadow-lg">
                                <div class="card-body p-4">
                                    <div class="text-center mb-4">
                                        <h2><i class="fas fa-traffic-light me-2"></i>Traffic System</h2>
                                        <p class="text-muted">Sign in to your account</p>
                                    </div>
                                    
                                    <?php if ($error): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <?php echo $error; ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($success): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <?php echo $success; ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" id="loginForm">
                                        <input type="hidden" name="login" value="1">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Username or Email</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                <input type="text" class="form-control" name="username" required 
                                                       value="<?php echo htmlspecialchars($remembered_user); ?>"
                                                       placeholder="Enter your username or email">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <div class="position-relative">
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <input type="password" class="form-control" name="password" id="password" required 
                                                           placeholder="Enter your password">
                                                </div>
                                                <span class="password-toggle" onclick="togglePassword()">
                                                    <i class="fas fa-eye" id="passwordIcon"></i>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" name="remember_me" id="remember_me" 
                                                   <?php echo $remembered_user ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="remember_me">Remember me</label>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                        </button>
                                    </form>

                                    <hr>
                                    
                                    <div class="demo-credentials p-3 rounded">
                                        <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Demo Credentials:</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small><strong>Admin:</strong><br>admin / admin123</small>
                                            </div>
                                            <div class="col-6">
                                                <small><strong>Manager:</strong><br>manager / manager123</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            Don't have an account? <a href="create_account.php" class="text-decoration-none">Create one here</a>
                                        </small>
                                    </div>
                                </div>
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
            new DarkVeil(document.body, {
                speed: 0.8,
                particleCount: 80,
                connectionDistance: 120,
                colors: ['#667eea', '#764ba2', '#f093fb', '#4facfe']
            });
        });

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }
        
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>