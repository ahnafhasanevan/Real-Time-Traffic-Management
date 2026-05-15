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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $phoneNumber = trim($_POST['phone_number']);
    $userType = 'public_user'; // Default user type
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        $error = "All fields are required";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (!empty($phoneNumber) && !preg_match('/^\+?[0-9\s\-\(\)]{10,}$/', $phoneNumber)) {
        $error = "Invalid phone number format";
    } else {
        // Check if username or email already exists
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // Create account with phone number
            if ($auth->register($username, $email, $password, $firstName, $lastName, $phoneNumber, $userType)) {
                $success = "Account created successfully! You can now <a href='login.php' class='alert-link'>login</a>.";
            } else {
                $error = "Error creating account. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Traffic System</title>
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
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; width: 25%; }
        .strength-medium { background-color: #ffc107; width: 50%; }
        .strength-strong { background-color: #28a745; width: 100%; }
        
        /* Additional styling for better appearance */
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
            position: relative;
            z-index: 2;
        }
        
        /* Custom styling for better contrast */
        .cyber-card h2 {
            color: #ffffff !important;
            text-shadow: 0 0 10px rgba(100, 100, 255, 0.5);
        }
        
        /* Improved text readability */
        .card-text, .form-text, .text-muted {
            color: rgba(200, 200, 255, 0.8) !important;
        }
        
        .segment-info, .report-details {
            color: rgba(180, 180, 255, 0.9) !important;
        }

        /* Enhanced Button Animation */
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

        /* Form Field Enhancement */
        .form-control {
            background: rgba(25, 30, 50, 0.8);
            border: 1px solid rgba(102, 126, 234, 0.3);
            color: #fff;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(25, 30, 50, 0.9);
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.3);
            color: #fff;
        }

        .input-group-text {
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.3);
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="darkveil-container">
        <div class="darkveil-content">
            <div class="register-container">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-md-8 col-lg-6">
                            <div class="card cyber-card shadow-lg">
                                <div class="card-body p-5">
                                    <div class="text-center mb-4">
                                        <h2><i class="fas fa-user-plus me-2"></i>Create Account</h2>
                                        <p class="text-muted">Join the Traffic Management System</p>
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
                                    
                                    <form method="POST" id="registerForm">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">First Name *</label>
                                                    <input type="text" class="form-control" name="first_name" required 
                                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                                                           placeholder="Enter your first name">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Last Name *</label>
                                                    <input type="text" class="form-control" name="last_name" required
                                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                                                           placeholder="Enter your last name">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Username *</label>
                                            <input type="text" class="form-control" name="username" required
                                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                                   placeholder="Choose a username">
                                            <div class="form-text">Must be unique. Letters, numbers, and underscores only.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Email *</label>
                                            <input type="email" class="form-control" name="email" required
                                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                                   placeholder="Enter your email">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Phone Number (Optional)</label>
                                            <input type="tel" class="form-control" name="phone_number"
                                                   value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>"
                                                   placeholder="Enter your phone number">
                                            <div class="form-text">Format: +1234567890 or 1234567890</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Password *</label>
                                            <input type="password" class="form-control" name="password" id="password" required
                                                   onkeyup="checkPasswordStrength()"
                                                   placeholder="Create a password">
                                            <div class="password-strength" id="passwordStrength"></div>
                                            <div class="form-text">Minimum 6 characters</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Confirm Password *</label>
                                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required
                                                   onkeyup="checkPasswordMatch()"
                                                   placeholder="Confirm your password">
                                            <div class="form-text" id="passwordMatch"></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Account Type</label>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Public User Account</strong><br>
                                                <small>All new accounts are created as Public Users. Admin and Traffic Manager accounts can only be created by existing administrators.</small>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary w-100 py-2 mt-3">
                                            <i class="fas fa-user-plus me-2"></i>Create Account
                                        </button>
                                    </form>
                                    
                                    <div class="text-center mt-4">
                                        <p class="text-muted mb-0">
                                            Already have an account? <a href="login.php">Sign in here</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DarkVeil Background -->
    <script src="darkveil.js"></script>
    <script>
        // Initialize the DarkVeil background
        document.addEventListener('DOMContentLoaded', function() {
            new DarkVeil(document.body, {
                hueShift: 45,
                noiseIntensity: 0.02,
                scanlineIntensity: 0.1,
                speed: 0.3,
                scanlineFrequency: 0.8,
                warpAmount: 0.2,
                resolutionScale: 1
            });
        });

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                strengthBar.style.width = '0%';
                return;
            }
            
            let strength = 0;
            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/\d/)) strength += 1;
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;
            
            if (strength <= 1) {
                strengthBar.className = 'password-strength strength-weak';
            } else if (strength <= 2) {
                strengthBar.className = 'password-strength strength-medium';
            } else {
                strengthBar.className = 'password-strength strength-strong';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchText.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.innerHTML = '<span class="text-success">✓ Passwords match</span>';
            } else {
                matchText.innerHTML = '<span class="text-danger">✗ Passwords do not match</span>';
            }
        }

        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[name="first_name"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>