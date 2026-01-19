<?php
// Quick server test
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Server Test</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; color: #0c5460; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="success">
        <h2>✅ Server is Running!</h2>
        <p>PHP Development Server is working correctly.</p>
    </div>
    
    <div class="info">
        <h3>Server Information:</h3>
        <ul>
            <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
            <li><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
            <li><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? __DIR__; ?></li>
        </ul>
    </div>
    
    <div class="info">
        <h3>Quick Links:</h3>
        <ul>
            <li><a href="index.php">Home Page</a></li>
            <li><a href="admin/admin_login.php">Admin Login</a></li>
            <li><a href="admin/admin_dashboard.php">Admin Dashboard</a></li>
        </ul>
    </div>
</body>
</html>
