<?php
session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: ../core/admin_dashboard.php');
    exit();
}

// Handle login form submission
$login_error = '';
if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Load admin credentials from JSON file
    $admins = json_decode(file_get_contents('../assets/admins.json'), true) ?: [];
    
    // Simple authentication - in production, use proper password hashing
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        
        header('Location: ../core/admin_dashboard.php');
        exit();
    } else {
        $login_error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmileOne Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,.2);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 500px;
        }
        
        .login-form {
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
        }
        
        .login-image {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: white;
            text-align: center;
        }
        
        .login-image i {
            font-size: 100px;
            margin-bottom: 20px;
            opacity: 0.8;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .login-form {
                padding: 40px 30px;
            }
            
            .login-image {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="row g-0">
            <div class="col-md-6">
                <div class="login-image">
                    <div>
                        <i class="fas fa-smile"></i>
                        <h2 class="mb-3">Welcome Back!</h2>
                        <p class="mb-0">Login to access your SmileOne admin dashboard and manage your system.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="login-form">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold text-primary">Admin Login</h3>
                        <p class="text-muted">Enter your credentials to continue</p>
                    </div>
                    
                    <?php if ($login_error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($login_error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user me-1"></i>Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   placeholder="Enter your username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i>Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="Enter your password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-login w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Default login: admin / admin123
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...';
            submitBtn.disabled = true;
            
            // Re-enable button after 3 seconds in case of error
            setTimeout(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Auto-focus username field
        document.getElementById('username').focus();
    </script>
</body>
</html>