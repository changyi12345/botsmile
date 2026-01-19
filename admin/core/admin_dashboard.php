<?php
// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    die('Session error: ' . htmlspecialchars($e->getMessage()));
}

// Include configuration
try {
    require_once __DIR__ . '/admin_config.php';
} catch (Exception $e) {
    die('Configuration error: ' . $e->getMessage());
}

// Include header
try {
    require_once __DIR__ . '/admin_header.php';
} catch (Exception $e) {
    die('Header error: ' . $e->getMessage());
}

// Get current page
$current_page = $_GET['page'] ?? 'dashboard';

// Include the appropriate page
try {
    switch ($current_page) {
        case 'dashboard':
            require_once __DIR__ . '/../pages/admin_dashboard_main.php';
            break;
        case 'users':
            require_once __DIR__ . '/../pages/admin_users.php';
            break;
        case 'products':
            require_once __DIR__ . '/../pages/admin_products.php';
            break;
        case 'transactions':
            require_once __DIR__ . '/../pages/admin_transactions.php';
            break;
        case 'bot':
            require_once __DIR__ . '/../pages/admin_bot.php';
            break;
        case 'settings':
            require_once __DIR__ . '/../pages/admin_settings.php';
            break;
        case 'mmk_topups':
            require_once __DIR__ . '/../pages/admin_mmk_topups.php';
            break;
        default:
            require_once __DIR__ . '/../pages/admin_dashboard_main.php';
            break;
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading page: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Include footer
try {
    require_once __DIR__ . '/admin_footer.php';
} catch (Exception $e) {
    echo '</body></html>';
}
?>