<?php
// SmileOne Admin System - Navigation Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmileOne Admin System</title>
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
        .welcome-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,.2);
            padding: 60px;
            text-align: center;
            max-width: 600px;
        }
        .btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 10px;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .icon {
            font-size: 80px;
            color: #667eea;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="icon">
            <i class="fas fa-smile"></i>
        </div>
        <h1 class="mb-4">Welcome to SmileOne Admin System</h1>
        <p class="text-muted mb-5">Choose an option to get started with your admin dashboard</p>
        
        <div class="d-flex flex-column align-items-center">
            <a href="admin/setup_admin.php" class="btn btn-custom">
                <i class="fas fa-cog me-2"></i>Setup Admin System
            </a>
            <a href="admin/admin_login.php" class="btn btn-custom">
                <i class="fas fa-sign-in-alt me-2"></i>Admin Login
            </a>
            <a href="admin/admin_dashboard.php" class="btn btn-custom">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
        </div>
        
        <div class="mt-4 text-muted">
            <small>
                <i class="fas fa-info-circle me-1"></i>
                If this is your first time, please run setup first
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>