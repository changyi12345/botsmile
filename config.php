<?php
// SmileOne Configuration
define('SMILE_BASE_URL', 'https://www.smile.one');
define('TIMEOUT', 30);
define('MAX_RETRIES', 3);
define('RETRY_DELAY', 2000000); // 2 seconds in microseconds

// User Agent
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36');

// Paths
define('COOKIES_FILE', __DIR__ . '/cookies.json');
define('PRODUCTS_FILE', __DIR__ . '/products.json');

/// Webhook Configuration

// Country Configuration
$COUNTRY_CONFIG = [
    'br' => [
        'code' => 'br',
        'basePath' => '',
        'success_url' => SMILE_BASE_URL . '/message/success'
    ],
    'php' => [
        'code' => 'php',
        'basePath' => '/ph',
        'success_url' => SMILE_BASE_URL . '/ph/message/success'
    ],
    'pubg_br' => [
        'code' => 'pubg_br',
        'basePath' => '',
        'success_url' => SMILE_BASE_URL . '/message/success'
    ],
    'pubg_php' => [
        'code' => 'pubg_php',
        'basePath' => '/ph',
        'success_url' => SMILE_BASE_URL . '/ph/message/success'
    ],
    'hok_br' => [
        'code' => 'hok_br',
        'basePath' => '',
        'success_url' => SMILE_BASE_URL . '/message/success'
    ],
    'hok_php' => [
        'code' => 'hok_php',
        'basePath' => '/ph',
        'success_url' => SMILE_BASE_URL . '/ph/message/success'
    ],
    'magicchessgogo_br' => [
        'code' => 'magicchessgogo_br',
        'basePath' => '',
        'success_url' => SMILE_BASE_URL . '/message/success'
    ],
    'magicchessgogo_php' => [
        'code' => 'magicchessgogo_php',
        'basePath' => '/ph',
        'success_url' => SMILE_BASE_URL . '/ph/message/success'
    ]
];

// Default Country
define('DEFAULT_COUNTRY', 'br');

// Balance Selectors
$BALANCE_SELECTORS = [
    'br' => 'body > div.main-container > div > div > div > div > div > div.personal-center > div.my-account-top-menu > div.user-balance-section > div.balance > div.balance-coins > p:nth-child(2)',
    'php' => 'body > div.main-container > div > div > div > div > div > div.personal-center > div.my-account-top-menu > div.user-balance-section > div.balance > div.balance-coins > p:nth-child(2)'
];

// API Endpoints - Different games use different endpoints
// Function to get endpoint based on country/game
if (!function_exists('getEndpointForCountry')) {
    function getEndpointForCountry($country, $endpointType) {
        // Determine game type from country code
        $gameType = 'mobilelegends'; // Default
        
        if (strpos($country, 'pubg') === 0) {
            $gameType = 'pubgmobile'; // Pubg uses /merchant/pubgmobile/ endpoints
        } elseif (strpos($country, 'hok') === 0) {
            $gameType = 'hok'; // HoK uses /merchant/hok/ endpoints
        } elseif (strpos($country, 'magicchessgogo') === 0) {
            $gameType = 'magicchessgogo'; // MagicChessGoGo uses /merchant/magicchessgogo/ endpoints
        } else {
            $gameType = 'mobilelegends'; // MLBB uses /merchant/mobilelegends/ endpoints
        }
        
        $baseEndpoint = "/merchant/{$gameType}";
        
        switch ($endpointType) {
            case 'checkrole':
                return $baseEndpoint . '/checkrole';
            case 'query':
                return $baseEndpoint . '/query';
            case 'pay':
                return $baseEndpoint . '/pay';
            default:
                return $baseEndpoint . '/checkrole';
        }
    }
}

// Default endpoints (for backward compatibility)
define('CHECK_ROLE_ENDPOINT', '/merchant/mobilelegends/checkrole');
define('QUERY_ENDPOINT', '/merchant/mobilelegends/query');
define('PAY_ENDPOINT', '/merchant/mobilelegends/pay');
define('ORDER_PAGE', '/customer/order');