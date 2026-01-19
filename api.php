<?php
/**
 * SmileOne REST API
 * 
 * This API uses SmileOne cookies and user agent for all requests
 * 
 * Endpoints:
 * - POST /api.php/recharge - Perform recharge
 * - GET  /api.php/balance - Get balance
 * - POST /api.php/check-role - Check role/username
 * - GET  /api.php/products - Get products
 * - POST /api.php/sync-products - Sync products from website
 * - GET  /api.php/status - API status
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// CORS support
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smile.php';

/**
 * Send JSON response
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400, $errorCode = null) {
    $response = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($errorCode !== null) {
        $response['error_code'] = $errorCode;
    }
    
    sendResponse($response, $statusCode);
}

/**
 * Get request method and path
 */
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Get endpoint (last part of path or from query string)
$endpoint = end($pathParts);
if ($endpoint === 'api.php' || $endpoint === '') {
    $endpoint = $_GET['endpoint'] ?? $_POST['endpoint'] ?? 'status';
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
if ($input === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
}

// Initialize SmileOne instance
try {
    $smile = new SmileOne();
} catch (Exception $e) {
    sendError('Failed to initialize SmileOne: ' . $e->getMessage(), 500, 'INIT_ERROR');
}

// Route requests
switch (strtolower($endpoint)) {
    
    /**
     * GET /api.php/status
     * Check API status
     */
    case 'status':
    case '':
        sendResponse([
            'success' => true,
            'api' => 'SmileOne API',
            'version' => '1.0.0',
            'status' => 'online',
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoints' => [
                'POST /api.php/recharge' => 'Perform recharge',
                'GET /api.php/balance' => 'Get balance',
                'POST /api.php/check-role' => 'Check role/username',
                'GET /api.php/products' => 'Get products',
                'POST /api.php/sync-products' => 'Sync products from website',
                'GET /api.php/status' => 'API status'
            ]
        ]);
        break;
    
    /**
     * POST /api.php/recharge
     * Perform recharge
     * 
     * Request body:
     * {
     *   "userId": "123456789",
     *   "zoneId": "1234",
     *   "productName": "100 Diamonds",
     *   "country": "br",
     *   "requestedBy": "user123"
     * }
     */
    case 'recharge':
        if ($method !== 'POST') {
            sendError('Method not allowed. Use POST.', 405);
        }
        
        $userId = $input['userId'] ?? $_POST['userId'] ?? null;
        $zoneId = $input['zoneId'] ?? $_POST['zoneId'] ?? null;
        $productName = $input['productName'] ?? $_POST['productName'] ?? null;
        $country = $input['country'] ?? $_POST['country'] ?? null;
        $requestedBy = $input['requestedBy'] ?? $_POST['requestedBy'] ?? 'api';
        
        // Validate required fields
        if (empty($userId) || empty($productName)) {
            sendError('Missing required fields: userId and productName are required', 400, 'VALIDATION_ERROR');
        }
        
        try {
            $result = $smile->recharge($userId, $zoneId, $productName, $requestedBy, $country);
            
            if ($result === false) {
                sendError($smile->getLastError(), 400, 'RECHARGE_FAILED');
            }
            
            sendResponse([
                'success' => true,
                'data' => $result,
                'message' => 'Recharge completed successfully'
            ]);
            
        } catch (Exception $e) {
            sendError('Recharge error: ' . $e->getMessage(), 500, 'RECHARGE_ERROR');
        }
        break;
    
    /**
     * GET /api.php/balance
     * Get balance
     * 
     * Query parameters:
     * - country: br, php (optional, defaults to current country)
     */
    case 'balance':
        if ($method !== 'GET') {
            sendError('Method not allowed. Use GET.', 405);
        }
        
        $country = $_GET['country'] ?? null;
        
        try {
            if ($country) {
                $smile->setCountry($country);
            }
            
            $balance = $smile->getBalance();
            
            if ($balance === false) {
                sendError($smile->getLastError(), 400, 'BALANCE_ERROR');
            }
            
            sendResponse([
                'success' => true,
                'data' => [
                    'balance' => $balance,
                    'country' => $smile->getCurrentCountry(),
                    'currency' => 'SmileCoins'
                ],
                'message' => 'Balance retrieved successfully'
            ]);
            
        } catch (Exception $e) {
            sendError('Balance error: ' . $e->getMessage(), 500, 'BALANCE_ERROR');
        }
        break;
    
    /**
     * GET /api.php/balance-all
     * Get balance for all countries
     */
    case 'balance-all':
        if ($method !== 'GET') {
            sendError('Method not allowed. Use GET.', 405);
        }
        
        try {
            $balances = $smile->getBalanceAll();
            
            sendResponse([
                'success' => true,
                'data' => $balances,
                'message' => 'Balances retrieved successfully'
            ]);
            
        } catch (Exception $e) {
            sendError('Balance error: ' . $e->getMessage(), 500, 'BALANCE_ERROR');
        }
        break;
    
    /**
     * POST /api.php/check-role
     * Check role/username
     * 
     * Request body:
     * {
     *   "userId": "123456789",
     *   "zoneId": "1234",
     *   "productId": "12345",
     *   "country": "br"
     * }
     */
    case 'check-role':
    case 'checkrole':
        if ($method !== 'POST') {
            sendError('Method not allowed. Use POST.', 405);
        }
        
        $userId = $input['userId'] ?? $_POST['userId'] ?? null;
        $zoneId = $input['zoneId'] ?? $_POST['zoneId'] ?? null;
        $productId = $input['productId'] ?? $_POST['productId'] ?? null;
        $country = $input['country'] ?? $_POST['country'] ?? null;
        
        // Validate required fields
        if (empty($userId) || empty($productId)) {
            sendError('Missing required fields: userId and productId are required', 400, 'VALIDATION_ERROR');
        }
        
        try {
            if ($country) {
                $smile->setCountry($country);
            }
            
            $username = $smile->checkRole($userId, $zoneId, $productId);
            
            if ($username === false) {
                sendError($smile->getLastError(), 400, 'CHECK_ROLE_FAILED');
            }
            
            sendResponse([
                'success' => true,
                'data' => [
                    'userId' => $userId,
                    'zoneId' => $zoneId,
                    'username' => $username,
                    'productId' => $productId,
                    'country' => $smile->getCurrentCountry()
                ],
                'message' => 'Role checked successfully'
            ]);
            
        } catch (Exception $e) {
            sendError('Check role error: ' . $e->getMessage(), 500, 'CHECK_ROLE_ERROR');
        }
        break;
    
    /**
     * GET /api.php/products
     * Get products
     * 
     * Query parameters:
     * - country: br, php (optional)
     * - game: mobilelegends, pubg, hok (optional)
     */
    case 'products':
        if ($method !== 'GET') {
            sendError('Method not allowed. Use GET.', 405);
        }
        
        try {
            $products = $smile->loadProducts();
            
            // Filter by country if provided
            $country = $_GET['country'] ?? null;
            if ($country) {
                $products = array_filter($products, function($product) use ($country) {
                    return ($product['country'] ?? '') === $country;
                });
                $products = array_values($products); // Re-index array
            }
            
            sendResponse([
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products)
                ],
                'message' => 'Products retrieved successfully'
            ]);
            
        } catch (Exception $e) {
            sendError('Products error: ' . $e->getMessage(), 500, 'PRODUCTS_ERROR');
        }
        break;
    
    /**
     * POST /api.php/sync-products
     * Sync products from SmileOne website
     * 
     * Request body (optional):
     * {
     *   "game": "mobilelegends",
     *   "country": "br"
     * }
     */
    case 'sync-products':
    case 'syncproducts':
        if ($method !== 'POST') {
            sendError('Method not allowed. Use POST.', 405);
        }
        
        $game = $input['game'] ?? $_POST['game'] ?? 'mobilelegends';
        $country = $input['country'] ?? $_POST['country'] ?? 'br';
        
        try {
            // Sync specific game/country
            if (isset($input['game']) || isset($_POST['game'])) {
                $products = $smile->fetchProductsFromWebsite($game, $country);
            } else {
                // Sync all products
                $products = $smile->syncAllProducts();
            }
            
            if (empty($products)) {
                sendError($smile->getLastError() ?: 'No products found', 400, 'SYNC_FAILED');
            }
            
            sendResponse([
                'success' => true,
                'data' => [
                    'products' => $products,
                    'count' => count($products)
                ],
                'message' => 'Products synced successfully'
            ]);
            
        } catch (Exception $e) {
            sendError('Sync products error: ' . $e->getMessage(), 500, 'SYNC_ERROR');
        }
        break;
    
    /**
     * GET /api.php/country
     * Get current country or set country
     */
    case 'country':
        if ($method === 'GET') {
            sendResponse([
                'success' => true,
                'data' => [
                    'country' => $smile->getCurrentCountry()
                ]
            ]);
        } elseif ($method === 'POST') {
            $country = $input['country'] ?? $_POST['country'] ?? null;
            
            if (empty($country)) {
                sendError('Missing required field: country', 400, 'VALIDATION_ERROR');
            }
            
            try {
                $result = $smile->setCountry($country);
                sendResponse([
                    'success' => true,
                    'data' => [
                        'country' => $result
                    ],
                    'message' => 'Country set successfully'
                ]);
            } catch (Exception $e) {
                sendError('Set country error: ' . $e->getMessage(), 500, 'COUNTRY_ERROR');
            }
        } else {
            sendError('Method not allowed. Use GET or POST.', 405);
        }
        break;
    
    /**
     * GET /api.php/info
     * Get API and system information
     */
    case 'info':
        if ($method !== 'GET') {
            sendError('Method not allowed. Use GET.', 405);
        }
        
        try {
            $cookiesLoaded = file_exists(COOKIES_FILE);
            $productsLoaded = file_exists(PRODUCTS_FILE);
            
            $cookieCount = 0;
            if ($cookiesLoaded) {
                $cookies = json_decode(file_get_contents(COOKIES_FILE), true);
                $cookieCount = is_array($cookies) ? count($cookies) : 0;
            }
            
            $productCount = 0;
            if ($productsLoaded) {
                $products = json_decode(file_get_contents(PRODUCTS_FILE), true);
                $productCount = is_array($products) ? count($products) : 0;
            }
            
            sendResponse([
                'success' => true,
                'data' => [
                    'api_version' => '1.0.0',
                    'smile_base_url' => SMILE_BASE_URL,
                    'user_agent' => USER_AGENT,
                    'current_country' => $smile->getCurrentCountry(),
                    'cookies' => [
                        'loaded' => $cookiesLoaded,
                        'count' => $cookieCount
                    ],
                    'products' => [
                        'loaded' => $productsLoaded,
                        'count' => $productCount
                    ],
                    'webhook' => [
                        'enabled' => !empty($smile->getWebhookUrl()),
                        'url' => $smile->getWebhookUrl() ?: 'Not configured'
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            sendError('Info error: ' . $e->getMessage(), 500, 'INFO_ERROR');
        }
        break;
    
    default:
        sendError('Endpoint not found: ' . htmlspecialchars($endpoint), 404, 'NOT_FOUND');
        break;
}
