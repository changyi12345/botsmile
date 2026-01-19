<?php
// Common configuration and functions for admin panel

// Define root directory path (2 levels up from admin/core/)
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__DIR__)));
}

// --- Data Loading Functions ---
function readJsonFile($filename) {
    try {
        if (!file_exists($filename)) {
            return [];
        }
        $content = @file_get_contents($filename);
        if ($content === false) {
            return [];
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    } catch (Exception $e) {
        return [];
    }
}

function writeJsonFile($filename, $data) {
    try {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return @file_put_contents($filename, $json) !== false;
    } catch (Exception $e) {
        return false;
    }
}

// --- Currency & Pricing Helpers ---
$exchange_rates = [
    'brl_to_mmk' => 85.5,    // 1 BRL = 85.5 MMK
    'php_to_mmk' => 38.2,    // 1 PHP = 38.2 MMK
    'usd_to_mmk' => 2100.0   // 1 USD = 2100 MMK
];

if (!function_exists('convertToMMK')) {
    function convertToMMK($price, $country) {
        global $exchange_rates;
        
        $country = strtolower($country);
        
        // Handle pubg_br, pubg_php, hok_br, hok_php, and old pubg/hok
        if (strpos($country, 'pubg_br') === 0 || strpos($country, 'hok_br') === 0 || $country === 'pubg' || $country === 'hok') {
            return $price * $exchange_rates['brl_to_mmk'];
        } elseif (strpos($country, 'pubg_php') === 0 || strpos($country, 'hok_php') === 0) {
            return $price * $exchange_rates['php_to_mmk'];
        }
        
        switch ($country) {
            case 'php': return $price * $exchange_rates['php_to_mmk'];
            case 'br':
            case 'brl': return $price * $exchange_rates['brl_to_mmk'];
            default: return $price * $exchange_rates['usd_to_mmk'];
        }
    }
}

if (!function_exists('formatMMK')) {
    function formatMMK($amount) {
        return number_format($amount, 0, '.', ',') . ' Ks';
    }
}

function getCurrencySymbol($country) {
    $country = strtolower($country);
    
    // Handle pubg_br, pubg_php, hok_br, hok_php, and old pubg/hok
    if (strpos($country, 'pubg_br') === 0 || strpos($country, 'hok_br') === 0 || $country === 'pubg' || $country === 'hok') {
        return 'R$'; // Brazilian Real
    } elseif (strpos($country, 'pubg_php') === 0 || strpos($country, 'hok_php') === 0) {
        return '₱'; // Philippine Peso
    }
    
    switch ($country) {
        case 'php':
            return '₱'; // Philippine Peso
        case 'br':
        case 'brl':
            return 'R$'; // Brazilian Real
        default:
            return '$'; // Default to USD
    }
}

// --- Load Data ---
// Use ROOT_DIR constant for consistent path resolution
$users = readJsonFile(ROOT_DIR . '/users.json');
$products = readJsonFile(ROOT_DIR . '/products.json');
$transactions = readJsonFile(ROOT_DIR . '/transactions.json');
$commissions = readJsonFile(ROOT_DIR . '/commissions.json');

// Sort transactions by date, most recent first
usort($transactions, function($a, $b) {
    return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
});

$recent_transactions = array_slice($transactions, 0, 5);

// --- Statistics Calculation ---
$br_products = array_filter($products, function($p) { return ($p['country'] ?? 'br') === 'br'; });
$php_products = array_filter($products, function($p) { return ($p['country'] ?? '') === 'php'; });
// Pubg products - include both old 'pubg' and new 'pubg_br'/'pubg_php'
$pubg_br_products = array_filter($products, function($p) { 
    $country = strtolower($p['country'] ?? ''); 
    return $country === 'pubg_br' || $country === 'pubg'; 
});
$pubg_php_products = array_filter($products, function($p) { 
    $country = strtolower($p['country'] ?? ''); 
    return $country === 'pubg_php'; 
});
// HoK products - include both old 'hok' and new 'hok_br'/'hok_php'
$hok_br_products = array_filter($products, function($p) { 
    $country = strtolower($p['country'] ?? ''); 
    return $country === 'hok_br' || $country === 'hok'; 
});
$hok_php_products = array_filter($products, function($p) { 
    $country = strtolower($p['country'] ?? ''); 
    return $country === 'hok_php'; 
});
// MagicChessGoGo products
$magicchessgogo_br_products = array_filter($products, function($p) { 
    $country = strtolower($p['country'] ?? ''); 
    return $country === 'magicchessgogo_br'; 
});
$magicchessgogo_php_products = array_filter($products, function($p) { 
    $country = strtolower($p['country'] ?? ''); 
    return $country === 'magicchessgogo_php'; 
});
// Combined counts for display
$pubg_products = array_merge($pubg_br_products, $pubg_php_products);
$hok_products = array_merge($hok_br_products, $hok_php_products);
$magicchessgogo_products = array_merge($magicchessgogo_br_products, $magicchessgogo_php_products);

$total_users = count($users);
$total_transactions = count($transactions);
$total_revenue = array_sum(array_column($transactions, 'amount'));
$total_commissions = array_sum(array_column($commissions, 'amount'));

// Calculate BR/PHP/Pubg/HoK specific stats
$br_revenue = 0;
$php_revenue = 0;
$pubg_br_revenue = 0;
$pubg_php_revenue = 0;
$hok_br_revenue = 0;
$hok_php_revenue = 0;
foreach ($transactions as $transaction) {
    $country = strtolower($transaction['country'] ?? 'br');
    $amount = $transaction['amount'] ?? 0;
    
    if ($country === 'br') {
        $br_revenue += $amount;
    } elseif ($country === 'php') {
        $php_revenue += $amount;
    } elseif ($country === 'pubg_br' || $country === 'pubg') {
        $pubg_br_revenue += $amount;
    } elseif ($country === 'pubg_php') {
        $pubg_php_revenue += $amount;
    } elseif ($country === 'hok_br' || $country === 'hok') {
        $hok_br_revenue += $amount;
    } elseif ($country === 'hok_php') {
        $hok_php_revenue += $amount;
    }
}

// SmileOne Balance will be loaded via AJAX to prevent page blocking
// Initialize as loading state (will be updated by JavaScript)
$smile_balance_br = 'Loading...';
$smile_balance_php = 'Loading...';
?>
