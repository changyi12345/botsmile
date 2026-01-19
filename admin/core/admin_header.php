<?php
// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../auth/admin_login.php');
    exit();
}

// Get current page
$current_page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmileOne Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #e2e8f0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar .nav-link {
            color: #cbd5e1;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 4px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            transform: translateX(5px);
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .sidebar-header {
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        .sidebar-header h3 {
            color: #fff;
            font-weight: 600;
            margin: 0;
        }
        .main-content {
            padding: 30px;
            min-height: 100vh;
            margin-left: 280px;
            width: calc(100% - 280px);
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .stats-card {
            color: white;
            border-radius: 12px;
        }
        .stats-icon {
            font-size: 3rem;
            opacity: 0.3;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #475569;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                padding: 15px;
                margin-left: 0;
                width: 100%;
            }
        }
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        .mobile-overlay.show {
            display: block;
        }
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                padding: 15px;
                margin-left: 0;
                width: 100%;
            }
        }
        
        /* Toast Notification System */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        .toast-notification {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease-out;
            border-left: 4px solid;
            min-width: 300px;
        }
        .toast-notification.success {
            border-left-color: #10b981;
        }
        .toast-notification.error {
            border-left-color: #ef4444;
        }
        .toast-notification.warning {
            border-left-color: #f59e0b;
        }
        .toast-notification.info {
            border-left-color: #3b82f6;
        }
        .toast-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        .toast-notification.success .toast-icon {
            color: #10b981;
        }
        .toast-notification.error .toast-icon {
            color: #ef4444;
        }
        .toast-notification.warning .toast-icon {
            color: #f59e0b;
        }
        .toast-notification.info .toast-icon {
            color: #3b82f6;
        }
        .toast-content {
            flex: 1;
        }
        .toast-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            color: #1f2937;
        }
        .toast-message {
            font-size: 13px;
            color: #6b7280;
        }
        .toast-close {
            background: none;
            border: none;
            font-size: 18px;
            color: #9ca3af;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .toast-close:hover {
            background: #f3f4f6;
            color: #374151;
        }
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        .toast-notification.hiding {
            animation: slideOutRight 0.3s ease-out forwards;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(4px);
        }
        .loading-overlay.show {
            display: flex;
        }
        .loading-spinner {
            background: white;
            border-radius: 16px;
            padding: 32px 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            min-width: 200px;
        }
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }
        .spinner-text {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        /* Button Loading State */
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        /* Smooth Transitions */
        * {
            transition: background-color 0.2s, color 0.2s, border-color 0.2s;
        }
        .card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column p-3" id="sidebar" style="width: 280px;">
            <div class="sidebar-header">
                <h3><i class="fas fa-gem me-2"></i>SmileOne</h3>
                <small class="text-muted">Admin Panel</small>
            </div>
            <hr class="text-secondary">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="?page=dashboard" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li>
                    <a href="?page=users" class="nav-link <?php echo $current_page === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>Users
                    </a>
                </li>
                <li>
                    <a href="?page=products" class="nav-link <?php echo $current_page === 'products' ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i>Products
                    </a>
                </li>
                <li>
                    <a href="?page=transactions" class="nav-link <?php echo $current_page === 'transactions' ? 'active' : ''; ?>">
                        <i class="fas fa-exchange-alt"></i>Transactions
                    </a>
                </li>
                <li>
                    <a href="?page=bot" class="nav-link <?php echo $current_page === 'bot' ? 'active' : ''; ?>">
                        <i class="fas fa-robot"></i>Bot Management
                    </a>
                </li>
                <li>
                    <a href="?page=settings" class="nav-link <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                </li>
                <li>
                    <a href="?page=mmk_topups" class="nav-link <?php echo $current_page === 'mmk_topups' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>MMK Top Ups
                    </a>
                </li>
            </ul>
            <hr class="text-secondary">
            <div>
                <a href="../auth/admin_logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="spinner-text" id="loadingText">Loading...</div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
