<?php
/**
 * 🎮 Mobile Legends Bot - Complete System with Commission & Auto Order
 * Features:
 * ✅ Topup Code System with Commission (Custom %)
 * ✅ User & Admin Management
 * ✅ Philippines & Brazil Balance System
 * ✅ Auto Code Generation
 * ✅ Complete Transaction History
 * ✅ Auto Order to SmileOne with GameID & ZoneID
 * ✅ Custom Commission % per Amount
 * ✅ Order Management System
 */

// ==============================================
// 🚀 INITIALIZATION
// ==============================================

error_reporting(0);
date_default_timezone_set('Asia/Yangon');
session_start();

// ==============================================
// 📦 DATABASE & CONFIG
// ==============================================

// Bot Configuration - Load from bot_config.json if available
$BOT_CONFIG_FILE = '../bot_config.json';
$BOT_TOKEN = "8324793821:AAGKOirtj6SdELfcEIxL5BPMGLIp69w_0P4"; // Default fallback
$ADMINS = [7829183790]; // Default Admin fallback

// Try to load from config file
if (file_exists($BOT_CONFIG_FILE)) {
    $config_content = file_get_contents($BOT_CONFIG_FILE);
    $bot_config = json_decode($config_content, true);
    if ($bot_config && !empty($bot_config['bot_token'])) {
        $BOT_TOKEN = $bot_config['bot_token'];
    }
    if ($bot_config && !empty($bot_config['admin_ids']) && is_array($bot_config['admin_ids'])) {
        $ADMINS = $bot_config['admin_ids'];
    }
}

// Commission Settings
define('MINIMUM_TOPUP', 10);
define('MAXIMUM_TOPUP', 10000);

// File paths
define('USERS_FILE', '../users.json');
define('PRODUCTS_FILE', '../products.json');
define('CODES_FILE', '../topup_codes.json');
define('TRANSACTIONS_FILE', '../transactions.json');
define('COMMISSIONS_FILE', '../commissions.json');
define('ADMINS_FILE', '../admin/admins.json');
define('COMMISSION_RULES_FILE', '../commission_rules.json');
define('ORDERS_FILE', '../orders.json');

// Load required files
if (file_exists('../smile.php')) {
    require_once '../smile.php';
}

// ==============================================
// 📁 DATABASE FUNCTIONS
// ==============================================

// Load users from JSON
function loadUsers() {
    if (!file_exists(USERS_FILE)) {
        file_put_contents(USERS_FILE, json_encode([]));
        return [];
    }
    
    $data = file_get_contents(USERS_FILE);
    $users = json_decode($data, true);
    return is_array($users) ? $users : [];
}

// Save users to JSON
function saveUsers($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// Load admins from JSON
function loadAdmins() {
    global $ADMINS;
    
    if (!file_exists(ADMINS_FILE)) {
        // Save default admin
        $admins = [['telegram_id' => $ADMINS[0], 'added_at' => date('Y-m-d H:i:s')]];
        file_put_contents(ADMINS_FILE, json_encode($admins, JSON_PRETTY_PRINT));
        return $admins;
    }
    
    $data = file_get_contents(ADMINS_FILE);
    $admins = json_decode($data, true);
    return is_array($admins) ? $admins : [];
}

// Save admins to JSON
function saveAdmins($admins) {
    file_put_contents(ADMINS_FILE, json_encode($admins, JSON_PRETTY_PRINT));
}

// Add new admin
function addAdmin($telegramId) {
    $admins = loadAdmins();
    
    // Check if already admin
    foreach ($admins as $admin) {
        if ($admin['telegram_id'] == $telegramId) {
            return false;
        }
    }
    
    $admins[] = [
        'telegram_id' => $telegramId,
        'added_at' => date('Y-m-d H:i:s')
    ];
    
    saveAdmins($admins);
    return true;
}

// Remove admin
function removeAdmin($telegramId) {
    $admins = loadAdmins();
    $newAdmins = [];
    
    foreach ($admins as $admin) {
        if ($admin['telegram_id'] != $telegramId) {
            $newAdmins[] = $admin;
        }
    }
    
    saveAdmins($newAdmins);
    return true;
}

// Load commission rules
function loadCommissionRules() {
    if (!file_exists(COMMISSION_RULES_FILE)) {
        // Default commission rules
        $defaultRules = [
            'php' => [
                ['min_amount' => 0, 'max_amount' => 1000, 'rate' => 0.002], // 0.2%
                ['min_amount' => 1001, 'max_amount' => 5000, 'rate' => 0.005], // 0.5%
                ['min_amount' => 5001, 'max_amount' => 10000, 'rate' => 0.01] // 1%
            ],
            'br' => [
                ['min_amount' => 0, 'max_amount' => 1000, 'rate' => 0.002], // 0.2%
                ['min_amount' => 1001, 'max_amount' => 5000, 'rate' => 0.005], // 0.5%
                ['min_amount' => 5001, 'max_amount' => 10000, 'rate' => 0.01] // 1%
            ]
        ];
        file_put_contents(COMMISSION_RULES_FILE, json_encode($defaultRules, JSON_PRETTY_PRINT));
        return $defaultRules;
    }
    
    $data = file_get_contents(COMMISSION_RULES_FILE);
    $rules = json_decode($data, true);
    return is_array($rules) ? $rules : [];
}

// Save commission rules
function saveCommissionRules($rules) {
    file_put_contents(COMMISSION_RULES_FILE, json_encode($rules, JSON_PRETTY_PRINT));
}

// Get commission rate for amount
function getCommissionRate($country, $amount) {
    $rules = loadCommissionRules();
    $countryRules = $rules[$country] ?? [];
    
    foreach ($countryRules as $rule) {
        if ($amount >= $rule['min_amount'] && $amount <= $rule['max_amount']) {
            return $rule['rate'];
        }
    }
    
    return 0.002; // Default 0.2%
}

// Add commission rule
function addCommissionRule($country, $minAmount, $maxAmount, $rate) {
    $rules = loadCommissionRules();
    
    if (!isset($rules[$country])) {
        $rules[$country] = [];
    }
    
    // Remove any overlapping rules
    $newRules = [];
    foreach ($rules[$country] as $rule) {
        if ($rule['max_amount'] < $minAmount || $rule['min_amount'] > $maxAmount) {
            $newRules[] = $rule;
        }
    }
    
    // Add new rule
    $newRules[] = [
        'min_amount' => $minAmount,
        'max_amount' => $maxAmount,
        'rate' => $rate
    ];
    
    // Sort by min_amount
    usort($newRules, function($a, $b) {
        return $a['min_amount'] <=> $b['min_amount'];
    });
    
    $rules[$country] = $newRules;
    saveCommissionRules($rules);
    return true;
}

// Get user by Telegram ID
function getUser($telegramId) {
    $users = loadUsers();
    
    foreach ($users as $user) {
        if ($user['telegram_id'] == $telegramId) {
            return $user;
        }
    }
    
    // Create new user if not exists
    $newUser = [
        'telegram_id' => $telegramId,
        'username' => '',
        'balance_php' => 0.00,
        'balance_br' => 0.00,
        'total_deposited' => 0.00,
        'total_withdrawn' => 0.00,
        'total_commission' => 0.00,
        'created_at' => date('Y-m-d H:i:s'),
        'last_active' => date('Y-m-d H:i:s'),
        'is_active' => true,
        'total_topups' => 0,
        'total_orders' => 0,
        'game_id' => '',
        'zone_id' => ''
    ];
    
    $users[] = $newUser;
    saveUsers($users);
    
    return $newUser;
}

// Update user game info
function updateUserGameInfo($telegramId, $gameId, $zoneId) {
    $users = loadUsers();
    
    foreach ($users as &$user) {
        if ($user['telegram_id'] == $telegramId) {
            $user['game_id'] = $gameId;
            $user['zone_id'] = $zoneId;
            $user['last_active'] = date('Y-m-d H:i:s');
            saveUsers($users);
            return true;
        }
    }
    
    return false;
}

// Update user balance with commission
function updateUserBalance($telegramId, $country, $amount, $type = 'add', $applyCommission = false, $customRate = null) {
    $users = loadUsers();
    $finalAmount = $amount;
    $commissionRate = 0;
    $commission = 0;
    
    if ($applyCommission && $type == 'add') {
        // Calculate commission
        $commissionRate = ($customRate !== null) ? $customRate : getCommissionRate($country, $amount);
        $commission = $amount * $commissionRate;
        $finalAmount = $amount - $commission;
        
        // Save commission record
        saveCommissionRecord($telegramId, $country, $commission, $amount, $commissionRate);
        
        // Update user commission total
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $telegramId) {
                $user['total_commission'] = ($user['total_commission'] ?? 0) + $commission;
                break;
            }
        }
    }
    
foreach ($users as &$user) {
        if ($user['telegram_id'] == $telegramId) {
            $balanceField = "balance_" . $country;
            
            if ($type == 'add') {
                $user[$balanceField] = ($user[$balanceField] ?? 0) + $finalAmount;
                $user['total_deposited'] = ($user['total_deposited'] ?? 0) + $amount;
                $user['total_topups'] = ($user['total_topups'] ?? 0) + 1;
            } elseif ($type == 'subtract') {
                // ✅ FIXED: Changed $user[$balanceField'] to $user[$balanceField]
                $user[$balanceField] = max(0, ($user[$balanceField] ?? 0) - $amount);
                $user['total_withdrawn'] = ($user['total_withdrawn'] ?? 0) + $amount;
            } elseif ($type == 'set') {
                $user[$balanceField] = $amount;
            }
            
            $user['last_active'] = date('Y-m-d H:i:s');
            saveUsers($users);
            
            return [
                'new_balance' => $user[$balanceField],
                'final_amount' => $finalAmount,
                'commission' => $commission,
                'commission_rate' => $commissionRate * 100
            ];
        }
    }
    
    return false;
}
// Save commission record
function saveCommissionRecord($userId, $country, $commission, $originalAmount, $rate) {
    $commissions = [];
    
    if (file_exists(COMMISSIONS_FILE)) {
        $data = file_get_contents(COMMISSIONS_FILE);
        $commissions = json_decode($data, true) ?: [];
    }
    
    $record = [
        'id' => uniqid(),
        'user_id' => $userId,
        'country' => $country,
        'original_amount' => $originalAmount,
        'commission' => $commission,
        'commission_rate' => $rate * 100,
        'net_amount' => $originalAmount - $commission,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $commissions[] = $record;
    file_put_contents(COMMISSIONS_FILE, json_encode($commissions, JSON_PRETTY_PRINT));
    
    return $record['id'];
}

// Load topup codes
function loadTopupCodes() {
    if (!file_exists(CODES_FILE)) {
        file_put_contents(CODES_FILE, json_encode([]));
        return [];
    }
    
    $data = file_get_contents(CODES_FILE);
    $codes = json_decode($data, true);
    return is_array($codes) ? $codes : [];
}

// Save topup codes
function saveTopupCodes($codes) {
    file_put_contents(CODES_FILE, json_encode($codes, JSON_PRETTY_PRINT));
}

// Generate unique topup code with commission calculation
function generateTopupCode($country, $amount, $applyCommission = true, $customRate = null) {
    $prefix = ($country == 'php') ? 'PHP' : 'BR';
    $timestamp = time();
    $random = substr(md5($timestamp . rand(1000, 9999)), 0, 8);
    $code = $prefix . strtoupper($random);
    
    $codes = loadTopupCodes();
    
    // Check if code already exists
    foreach ($codes as $existingCode) {
        if ($existingCode['code'] == $code) {
            return generateTopupCode($country, $amount, $applyCommission, $customRate);
        }
    }
    
    // Calculate commission
    $commissionRate = ($applyCommission && $customRate !== null) ? $customRate : 
                     ($applyCommission ? getCommissionRate($country, $amount) : 0);
    $commission = $amount * $commissionRate;
    $netAmount = $amount - $commission;
    
    $newCode = [
        'code' => $code,
        'country' => $country,
        'original_amount' => $amount,
        'net_amount' => $netAmount,
        'commission' => $commission,
        'commission_rate' => $commissionRate * 100,
        'apply_commission' => $applyCommission,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => 'system',
        'used' => false,
        'used_by' => null,
        'used_at' => null,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
    ];
    
    $codes[] = $newCode;
    saveTopupCodes($codes);
    
    return $newCode;
}

// Redeem topup code with commission
function redeemTopupCode($code, $telegramId) {
    $codes = loadTopupCodes();
    
    foreach ($codes as &$topupCode) {
        if ($topupCode['code'] == $code && !$topupCode['used']) {
            // Check if expired
            if (strtotime($topupCode['expires_at']) < time()) {
                return [
                    'success' => false,
                    'message' => '❌ Code has expired!'
                ];
            }
            
            // Mark as used
            $topupCode['used'] = true;
            $topupCode['used_by'] = $telegramId;
            $topupCode['used_at'] = date('Y-m-d H:i:s');
            saveTopupCodes($codes);
            
            // Add balance to user with commission
            $result = updateUserBalance(
                $telegramId, 
                $topupCode['country'], 
                $topupCode['original_amount'], 
                'add',
                $topupCode['apply_commission'],
                $topupCode['commission_rate'] / 100
            );
            
            // Save transaction
            $txDetails = "Code: {$code}";
            if ($topupCode['apply_commission']) {
                $txDetails .= " (Commission: " . ($topupCode['commission_rate'] ?? 0) . "%)";
            }
            
            $txId = saveTransaction($telegramId, 'topup_redeem', $topupCode['country'], 
                $topupCode['original_amount'], $txDetails);
            
            return [
                'success' => true,
                'country' => $topupCode['country'],
                'original_amount' => $topupCode['original_amount'],
                'net_amount' => $topupCode['net_amount'],
                'commission' => $topupCode['commission'] ?? 0,
                'commission_rate' => $topupCode['commission_rate'] ?? 0,
                'new_balance' => $result['new_balance'],
                'code' => $code,
                'apply_commission' => $topupCode['apply_commission'] ?? false
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => '❌ Invalid or already used code!'
    ];
}

// Get active codes
function getActiveCodes($country = null) {
    $codes = loadTopupCodes();
    $activeCodes = [];
    
    foreach ($codes as $code) {
        if (!$code['used'] && strtotime($code['expires_at']) > time()) {
            if ($country === null || $code['country'] == $country) {
                $activeCodes[] = $code;
            }
        }
    }
    
    return $activeCodes;
}

// Save transaction
function saveTransaction($userId, $type, $country, $amount, $details = '') {
    $transactions = [];
    
    if (file_exists(TRANSACTIONS_FILE)) {
        $data = file_get_contents(TRANSACTIONS_FILE);
        $transactions = json_decode($data, true) ?: [];
    }
    
    $transaction = [
        'id' => uniqid(),
        'user_id' => $userId,
        'type' => $type,
        'country' => $country,
        'amount' => $amount,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $transactions[] = $transaction;
    file_put_contents(TRANSACTIONS_FILE, json_encode($transactions, JSON_PRETTY_PRINT));
    
    return $transaction['id'];
}

// Save order
function saveOrder($userId, $country, $productName, $price, $gameId, $zoneId, $status = 'pending') {
    $orders = [];
    
    if (file_exists(ORDERS_FILE)) {
        $data = file_get_contents(ORDERS_FILE);
        $orders = json_decode($data, true) ?: [];
    }
    
    $order = [
        'id' => uniqid(),
        'user_id' => $userId,
        'country' => $country,
        'product_name' => $productName,
        'price' => $price,
        'game_id' => $gameId,
        'zone_id' => $zoneId,
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s'),
        'processed_at' => null
    ];
    
    $orders[] = $order;
    file_put_contents(ORDERS_FILE, json_encode($orders, JSON_PRETTY_PRINT));
    
    return $order['id'];
}

// Process order via SmileOne
function processOrderViaSmileOne($orderId) {
    if (!class_exists('SmileOne')) {
        return ['success' => false, 'message' => 'SmileOne class not found'];
    }
    
    $orders = [];
    if (file_exists(ORDERS_FILE)) {
        $data = file_get_contents(ORDERS_FILE);
        $orders = json_decode($data, true) ?: [];
    }
    
    foreach ($orders as &$order) {
        if ($order['id'] == $orderId && $order['status'] == 'pending') {
            try {
                $smile = new SmileOne();
                
                // Process order based on country
                $result = null;
                if ($order['country'] == 'php') {
                    $result = $smile->processOrderPHP($order['product_name'], $order['game_id'], $order['zone_id']);
                } else {
                    $result = $smile->processOrderBR($order['product_name'], $order['game_id'], $order['zone_id']);
                }
                
                if ($result && ($result['success'] ?? false)) {
                    $order['status'] = 'completed';
                    $order['processed_at'] = date('Y-m-d H:i:s');
                    $order['smile_response'] = $result;
                    
                    // Update user's total orders
                    $users = loadUsers();
                    foreach ($users as &$user) {
                        if ($user['telegram_id'] == $order['user_id']) {
                            $user['total_orders'] = ($user['total_orders'] ?? 0) + 1;
                            break;
                        }
                    }
                    saveUsers($users);
                    
                    file_put_contents(ORDERS_FILE, json_encode($orders, JSON_PRETTY_PRINT));
                    
                    return [
                        'success' => true,
                        'message' => '✅ Order processed successfully via SmileOne',
                        'order_id' => $orderId
                    ];
                } else {
                    $order['status'] = 'failed';
                    $order['processed_at'] = date('Y-m-d H:i:s');
                    file_put_contents(ORDERS_FILE, json_encode($orders, JSON_PRETTY_PRINT));
                    
                    return [
                        'success' => false,
                        'message' => '❌ Failed to process order via SmileOne'
                    ];
                }
                
            } catch (Exception $e) {
                $order['status'] = 'error';
                $order['processed_at'] = date('Y-m-d H:i:s');
                file_put_contents(ORDERS_FILE, json_encode($orders, JSON_PRETTY_PRINT));
                
                return [
                    'success' => false,
                    'message' => '❌ Error: ' . $e->getMessage()
                ];
            }
        }
    }
    
    return ['success' => false, 'message' => 'Order not found'];
}

// Get user transactions
function getUserTransactions($telegramId, $limit = 10) {
    if (!file_exists(TRANSACTIONS_FILE)) {
        return [];
    }
    
    $data = file_get_contents(TRANSACTIONS_FILE);
    $allTransactions = json_decode($data, true) ?: [];
    
    $userTransactions = [];
    foreach ($allTransactions as $transaction) {
        if ($transaction['user_id'] == $telegramId) {
            $userTransactions[] = $transaction;
        }
    }
    
    usort($userTransactions, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return array_slice($userTransactions, 0, $limit);
}

// Load products
function loadProducts() {
    if (!file_exists(PRODUCTS_FILE)) {
        return [];
    }
    
    $data = file_get_contents(PRODUCTS_FILE);
    $products = json_decode($data, true);
    return is_array($products) ? $products : [];
}

// MMK Conversion Functions (matching admin panel)
if (!function_exists('convertToMMK')) {
    function convertToMMK($price, $country) {
        $exchange_rates = [
            'brl_to_mmk' => 85.5,    // 1 BRL = 85.5 MMK
            'php_to_mmk' => 38.2,    // 1 PHP = 38.2 MMK
            'usd_to_mmk' => 2100.0   // 1 USD = 2100 MMK
        ];
        
        switch (strtolower($country)) {
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

// Store pending purchase (for confirm/cancel flow)
function savePendingPurchase($telegramId, $purchaseData) {
    $file = __DIR__ . '/pending_purchases.json';
    $purchases = [];
    
    if (file_exists($file)) {
        $data = file_get_contents($file);
        $purchases = json_decode($data, true) ?: [];
    }
    
    $purchases[$telegramId] = $purchaseData;
    file_put_contents($file, json_encode($purchases, JSON_PRETTY_PRINT));
}

function getPendingPurchase($telegramId) {
    $file = __DIR__ . '/pending_purchases.json';
    
    if (!file_exists($file)) {
        return null;
    }
    
    $data = file_get_contents($file);
    $purchases = json_decode($data, true) ?: [];
    
    return $purchases[$telegramId] ?? null;
}

function clearPendingPurchase($telegramId) {
    $file = __DIR__ . '/pending_purchases.json';
    
    if (!file_exists($file)) {
        return;
    }
    
    $data = file_get_contents($file);
    $purchases = json_decode($data, true) ?: [];
    
    unset($purchases[$telegramId]);
    file_put_contents($file, json_encode($purchases, JSON_PRETTY_PRINT));
}

// Check in-game name via SmileOne (using cookies)
function checkInGameName($country, $gameId, $zoneId, $productId) {
    if (!class_exists('SmileOne')) {
        return ['success' => false, 'error' => 'SmileOne class not found'];
    }
    
    // Check if cookies file exists
    $cookiesFile = __DIR__ . '/../cookies.json';
    if (!file_exists($cookiesFile)) {
        return ['success' => false, 'error' => 'Cookies file not found. Please set cookies in admin panel.'];
    }
    
    $cookiesContent = file_get_contents($cookiesFile);
    $cookies = json_decode($cookiesContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($cookies)) {
        return ['success' => false, 'error' => 'Invalid or empty cookies. Please update cookies in admin panel.'];
    }
    
    try {
        // Create SmileOne instance (it will load cookies automatically)
        $smile = new SmileOne($country);
        
        // Verify cookies were loaded
        $reflection = new ReflectionClass($smile);
        $cookiesProperty = $reflection->getProperty('cookies');
        $cookiesProperty->setAccessible(true);
        $loadedCookies = $cookiesProperty->getValue($smile);
        
        if (empty($loadedCookies)) {
            return ['success' => false, 'error' => 'Cookies not loaded. Please check cookies.json file.'];
        }
        
        // Use reflection to access private checkRole method
        $method = $reflection->getMethod('checkRole');
        $method->setAccessible(true);
        
        // Call checkRole with cookies
        $username = $method->invoke($smile, $gameId, $zoneId, $productId);
        
        if ($username && is_string($username) && strlen($username) > 0) {
            return ['success' => true, 'username' => $username];
        } else {
            // Get error message from SmileOne
            $lastErrorProperty = $reflection->getProperty('lastError');
            $lastErrorProperty->setAccessible(true);
            $error = $lastErrorProperty->getValue($smile);
            
            if (empty($error)) {
                $error = 'Failed to verify in-game name. Please check Game ID and Zone ID.';
            }
            
            return ['success' => false, 'error' => $error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    } catch (Error $e) {
        return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
    }
}

// Check if user is admin
function isAdmin($telegramId) {
    $admins = loadAdmins();
    
    foreach ($admins as $admin) {
        if ($admin['telegram_id'] == $telegramId) {
            return true;
        }
    }
    
    return false;
}
// Get commission statistics
function getCommissionStats() {
    $commissions = [];
    
    if (file_exists(COMMISSIONS_FILE)) {
        $data = file_get_contents(COMMISSIONS_FILE);
        $commissions = json_decode($data, true) ?: [];
    }
    
    $totalCommissionPHP = 0;
    $totalCommissionBR = 0;
    $totalTransactions = count($commissions);
    
    foreach ($commissions as $commission) {
        if ($commission['country'] == 'php') {
            $totalCommissionPHP += $commission['commission'];
        } else {
            $totalCommissionBR += $commission['commission'];
        }
    }
    
    return [
        'total_commission_php' => $totalCommissionPHP,
        'total_commission_br' => $totalCommissionBR,
        'total_commission' => $totalCommissionPHP + $totalCommissionBR,
        'total_transactions' => $totalTransactions
    ];
}

// ==============================================
// 📱 TELEGRAM FUNCTIONS
// ==============================================

function sendMessage($chatId, $text, $parseMode = 'Markdown', $keyboard = null) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log errors if any
    if ($httpCode !== 200 || !empty($error)) {
        $logFile = __DIR__ . '/bot_log.txt';
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['description'] ?? $response;
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ❌ Error sending message to {$chatId}: HTTP {$httpCode}, Error: {$error}, API Response: {$errorMsg}\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    } else {
        // Log successful sends for debugging
        $responseData = json_decode($response, true);
        if (isset($responseData['ok']) && !$responseData['ok']) {
            $logFile = __DIR__ . '/bot_log.txt';
            $logMessage = "[" . date('Y-m-d H:i:s') . "] ⚠️ Message send returned ok=false to {$chatId}: " . ($responseData['description'] ?? 'Unknown') . "\n";
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    return $response;
}

function sendTyping($chatId) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendChatAction";
    $data = ['chat_id' => $chatId, 'action' => 'typing'];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data
    ]);
    
    curl_exec($ch);
    curl_close($ch);
}

// ==============================================
// 🎮 USER SYSTEM
// ==============================================

// User Main Menu
function showUserMainMenu($chatId, $telegramId) {
    $user = getUser($telegramId);
    
    // Get user balance for preview
    $balancePHP = $user['balance_php'] ?? 0;
    $balanceBR = $user['balance_br'] ?? 0;
    $balanceMMK = $user['balance_mmk'] ?? 0;
    $totalBalance = $balancePHP + $balanceBR;
    
    // Create keyboard layout
    $keyboard = [
        'keyboard' => [
            [['text' => '💰 My Balance'], ['text' => '💎 Topup Code']],
            [['text' => '💵 MMK Top Up'], ['text' => '🇵🇭 Philippines']],
            [['text' => '🇧🇷 Brazil'], ['text' => '📜 My History']],
            [['text' => '🎮 Buy Diamonds'], ['text' => '🎯 Game Info']],
            [['text' => '📊 My Stats']],
            isAdmin($telegramId) ? [['text' => '👑 Admin Panel']] : []
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    // Enhanced welcome message with better UI/UX
    $message = "✨ *Welcome to SmileOne Bot!* ✨\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // User Info Section
    $message .= "👤 *Your Account*\n";
    $message .= "🆔 User ID: `{$telegramId}`\n";
    if (isAdmin($telegramId)) {
        $message .= "👑 *Admin Account*\n";
    }
    $message .= "\n";
    
    // Quick Balance Preview
    $message .= "💰 *Quick Balance*\n";
    $message .= "🇵🇭 PHP: " . number_format($balancePHP, 2) . "\n";
    $message .= "🇧🇷 BRL: " . number_format($balanceBR, 2) . "\n";
    $message .= "💵 MMK: " . formatMMK($balanceMMK) . "\n";
    $message .= "📊 Total (PHP+BRL): " . number_format($totalBalance, 2) . "\n\n";
    
    // Game Info Preview
    if (!empty($user['game_id']) && !empty($user['zone_id'])) {
        $message .= "🎮 *Game Info*\n";
        $message .= "GameID: `{$user['game_id']}`\n";
        $message .= "ZoneID: `{$user['zone_id']}`\n\n";
    } else {
        $message .= "⚠️ *Game Info Not Set*\n";
        $message .= "Please set your GameID & ZoneID to purchase diamonds\n\n";
    }
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Menu Section with better formatting
    $message .= "📋 *Main Menu*\n\n";
    $message .= "💰 *My Balance* - View detailed balance\n";
    $message .= "💎 *Topup Code* - Redeem topup codes\n";
    $message .= "🇵🇭 *Philippines* - PHP diamond packages\n";
    $message .= "🇧🇷 *Brazil* - BRL diamond packages\n";
    $message .= "📜 *My History* - Transaction history\n";
    $message .= "🎮 *Buy Diamonds* - Purchase diamonds\n";
    $message .= "🎯 *Game Info* - Set GameID & ZoneID\n";
    $message .= "📊 *My Stats* - View statistics\n";
    
    if (isAdmin($telegramId)) {
        $message .= "👑 *Admin Panel* - Admin controls\n";
    }
    
    $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "💡 *Tip:* Use the buttons below or type commands like `/topup CODE`\n\n";
    $message .= "🆘 Need help? Type `/help`";
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Help Message
function showHelpMessage($chatId, $telegramId) {
    $message = "🆘 *Help & Commands*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "📋 *Available Commands:*\n\n";
    $message .= "`/start` or `/menu` - Show main menu\n";
    $message .= "`/topup CODE` - Redeem topup code\n";
    $message .= "`/gameinfo GAMEID ZONEID` - Set game info\n";
    $message .= "`/help` - Show this help message\n\n";
    
    if (isAdmin($telegramId)) {
        $message .= "👑 *Admin Commands:*\n\n";
        $message .= "`/gencode country amount` - Generate code\n";
        $message .= "`/gencode_nocomm country amount` - Generate code (no commission)\n";
        $message .= "`/setcommission country min max rate` - Set commission\n";
        $message .= "`/addadmin USERID` - Add admin\n";
        $message .= "`/removeadmin USERID` - Remove admin\n";
        $message .= "`/listcodes` - List active codes\n";
        $message .= "`/listorders` - List pending orders\n";
        $message .= "`/processorder ORDERID` - Process order\n\n";
    }
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "💡 *Quick Tips:*\n";
    $message .= "• Use buttons for quick access\n";
    $message .= "• Set GameID & ZoneID before buying\n";
    $message .= "• Check balance regularly\n";
    $message .= "• Contact support if you need help\n\n";
    $message .= "✨ Thank you for using SmileOne Bot!";
    
    sendMessage($chatId, $message, 'Markdown');
}

// User Balance
function showUserBalance($chatId, $telegramId) {
    $user = getUser($telegramId);
    
    $balancePHP = $user['balance_php'] ?? 0;
    $balanceBR = $user['balance_br'] ?? 0;
    $balanceMMK = $user['balance_mmk'] ?? 0;
    $total = $balancePHP + $balanceBR;
    
    $message = "💰 *YOUR BALANCE*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "💵 *Current Balance*\n\n";
    $message .= "🇵🇭 *Philippines (PHP)*\n";
    $message .= "└── PHP " . number_format($balancePHP, 2) . "\n\n";
    
    $message .= "🇧🇷 *Brazil (BRL)*\n";
    $message .= "└── BRL " . number_format($balanceBR, 2) . "\n\n";
    
    $message .= "💵 *Myanmar (MMK)*\n";
    $message .= "└── " . formatMMK($balanceMMK) . "\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "📊 *Total Balance (PHP+BRL):* " . number_format($total, 2) . "\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "📋 *Account Information*\n\n";
    $message .= "🆔 User ID: `{$telegramId}`\n";
    if (!empty($user['game_id']) && !empty($user['zone_id'])) {
        $message .= "🎮 GameID: `{$user['game_id']}`\n";
        $message .= "🏠 ZoneID: `{$user['zone_id']}`\n";
    } else {
        $message .= "⚠️ Game Info: Not Set\n";
    }
    $message .= "\n";
    
    $message .= "📈 *Statistics*\n\n";
    $message .= "💳 Total Deposited: " . number_format($user['total_deposited'] ?? 0, 2) . "\n";
    $message .= "📉 Total Commission: " . number_format($user['total_commission'] ?? 0, 2) . "\n";
    $message .= "🔄 Topups: " . ($user['total_topups'] ?? 0) . " times\n";
    $message .= "🛒 Orders: " . ($user['total_orders'] ?? 0) . " times\n";
    $message .= "🕐 Last Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "💡 *Tip:* Use 💎 Topup Code to add balance";
    
    sendMessage($chatId, $message, 'Markdown');
}

// Game Info Setup
function showGameInfoSetup($chatId) {
    $message = "🎯 *GAME INFORMATION*\n\n";
    $message .= "Please set your Mobile Legends Game ID and Zone ID:\n\n";
    $message .= "*Format:*\n";
    $message .= "`/gameinfo GAMEID ZONEID`\n\n";
    $message .= "*Example:*\n";
    $message .= "`/gameinfo 123456789 8888`\n\n";
    $message .= "⚠️ *Note:* This is required for diamond purchases!";
    
    sendMessage($chatId, $message);
}

// Handle Game Info Setup
function handleGameInfoSetup($chatId, $telegramId, $gameId, $zoneId) {
    if (empty($gameId) || empty($zoneId)) {
        sendMessage($chatId, "❌ Please provide both GameID and ZoneID!\n\nFormat: `/gameinfo GAMEID ZONEID`");
        return;
    }
    
    if (!is_numeric($gameId) || !is_numeric($zoneId)) {
        sendMessage($chatId, "❌ GameID and ZoneID must be numbers!");
        return;
    }
    
    // Check if there's a pending purchase for "enter_gameid" action
    $pending = getPendingPurchase($telegramId);
    if ($pending && isset($pending['action']) && $pending['action'] == 'enter_gameid') {
        // Update user game info first (optional, for future use)
        updateUserGameInfo($telegramId, $gameId, $zoneId);
        
        // Check if this is MMK purchase
        $useMMK = $pending['use_mmk'] ?? false;
        
        // Check Game ID and show packages
        checkGameIdAndShowPackages($chatId, $telegramId, $pending['country'], $useMMK, $gameId, $zoneId);
        
        // Clear pending purchase
        clearPendingPurchase($telegramId);
        return;
    }
    
    // Check if there's a pending purchase - if so, continue with purchase flow
    if ($pending && isset($pending['package_name'])) {
        // Update user game info first
        updateUserGameInfo($telegramId, $gameId, $zoneId);
        
        // Retry the purchase with new game info
        $useMMK = $pending['use_mmk'] ?? false;
        handlePurchase($chatId, $telegramId, $pending['country'], $pending['package_name'], $useMMK);
        return;
    }
    
    // Update user game info
    if (!updateUserGameInfo($telegramId, $gameId, $zoneId)) {
        sendMessage($chatId, "❌ Failed to update game info!");
        return;
    }
    
    $message = "✅ *GAME INFO UPDATED!*\n\n";
    $message .= "🎮 GameID: `{$gameId}`\n";
    $message .= "🏠 ZoneID: `{$zoneId}`\n\n";
    $message .= "Now you can purchase diamonds!";
    sendMessage($chatId, $message);
}

// User Statistics
function showUserStats($chatId, $telegramId) {
    $user = getUser($telegramId);
    $transactions = getUserTransactions($telegramId, 50);
    
    // Calculate statistics
    $totalDepositedPHP = 0;
    $totalDepositedBR = 0;
    $totalCommission = 0;
    $totalPurchases = 0;
    
    foreach ($transactions as $tx) {
        if ($tx['type'] == 'topup_redeem') {
            if ($tx['country'] == 'php') {
                $totalDepositedPHP += $tx['amount'];
            } else {
                $totalDepositedBR += $tx['amount'];
            }
        } elseif ($tx['type'] == 'purchase') {
            $totalPurchases++;
        }
    }
    
    $message = "📊 *YOUR STATISTICS*\n";
    $message .= "═══════════════════════\n\n";
    
    $message .= "👤 *User Information:*\n";
    $message .= "🆔 ID: `{$telegramId}`\n";
    $message .= "🎮 GameID: `{$user['game_id']}`\n";
    $message .= "🏠 ZoneID: `{$user['zone_id']}`\n";
    $message .= "📅 Joined: " . ($user['created_at'] ?? 'N/A') . "\n";
    $message .= "🕐 Last Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
    
    $message .= "💰 *Deposit Statistics:*\n";
    $message .= "🇵🇭 Total PHP Deposited: " . number_format($totalDepositedPHP, 2) . "\n";
    $message .= "🇧🇷 Total BR Deposited: " . number_format($totalDepositedBR, 2) . "\n";
    $message .= "📊 Total Deposited: " . number_format($totalDepositedPHP + $totalDepositedBR, 2) . "\n";
    $message .= "📈 Commission Paid: " . number_format($user['total_commission'] ?? 0, 2) . "\n\n";
    
    $message .= "🎮 *Activity Statistics:*\n";
    $message .= "🔄 Total Topups: " . ($user['total_topups'] ?? 0) . "\n";
    $message .= "🛒 Total Orders: " . ($user['total_orders'] ?? 0) . "\n";
    $message .= "📝 Total Transactions: " . count($transactions) . "\n\n";
    
    $message .= "💎 *Current Balance:*\n";
    $message .= "🇵🇭 PHP: " . number_format($user['balance_php'] ?? 0, 2) . "\n";
    $message .= "🇧🇷 BR: " . number_format($user['balance_br'] ?? 0, 2) . "\n";
    $message .= "💵 MMK: " . formatMMK($user['balance_mmk'] ?? 0) . "\n";
    $message .= "📊 Total (PHP+BRL): " . number_format(($user['balance_php'] ?? 0) + ($user['balance_br'] ?? 0), 2);
    
    sendMessage($chatId, $message);
}

// Topup Code Input
function showTopupCodeInput($chatId) {
    $rules = loadCommissionRules();
    
    $message = "💎 *TOPUP CODE REDEMPTION*\n\n";
    
    // Show PHP commission rates
    $message .= "🇵🇭 *PHP Commission Rates:*\n";
    foreach ($rules['php'] as $rule) {
        $message .= "└── PHP {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    // Show BR commission rates
    $message .= "🇧🇷 *BR Commission Rates:*\n";
    foreach ($rules['br'] as $rule) {
        $message .= "└── BRL {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    $message .= "Enter your topup code:\n\n";
    $message .= "*Format:*\n";
    $message .= "`/topup YOURCODE`\n\n";
    $message .= "*Example:*\n";
    $message .= "`/topup PHP12345678`\n";
    $message .= "`/topup BRABCD1234`\n\n";
    $message .= "*Note:* Commission will be deducted based on amount.";
    
    sendMessage($chatId, $message);
}

// Redeem Topup Code with Commission
function handleTopupCode($chatId, $telegramId, $code) {
    sendTyping($chatId);
    
    if (empty($code)) {
        sendMessage($chatId, "❌ Please enter a code!\n\nFormat: `/topup YOURCODE`");
        return;
    }
    
    $code = strtoupper(trim($code));
    $result = redeemTopupCode($code, $telegramId);
    
    if ($result['success']) {
        $country = $result['country'];
        $originalAmount = $result['original_amount'];
        $netAmount = $result['net_amount'];
        $commission = $result['commission'];
        $newBalance = $result['new_balance'];
        $applyCommission = $result['apply_commission'];
        $commissionRate = $result['commission_rate'];
        
        $flag = ($country == 'php') ? '🇵🇭' : '🇧🇷';
        $currency = ($country == 'php') ? 'PHP' : 'BRL';
        
        $message = "✅ *TOPUP SUCCESSFUL!*\n\n";
        $message .= "{$flag} Code: `{$result['code']}`\n";
        $message .= "💰 Original Amount: {$currency} " . number_format($originalAmount, 2) . "\n";
        
        if ($applyCommission) {
            $message .= "📉 Commission (" . $commissionRate . "%): -{$currency} " . number_format($commission, 2) . "\n";
            $message .= "📊 Net Amount: {$currency} " . number_format($netAmount, 2) . "\n";
        }
        
        $message .= "💳 New Balance: {$currency} " . number_format($newBalance, 2) . "\n\n";
        $message .= "🎉 Balance updated successfully!";
        
        sendMessage($chatId, $message);
    } else {
        sendMessage($chatId, $result['message']);
    }
}

// MMK Top Up Menu - Add MMK Balance
function showMMKTopUpMenu($chatId, $telegramId) {
    $user = getUser($telegramId);
    $balanceMMK = $user['balance_mmk'] ?? 0;
    
    $message = "💵 *MMK TOP UP*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "💰 *Your MMK Balance:*\n";
    $message .= "💵 " . formatMMK($balanceMMK) . "\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "Select payment method to add MMK balance:\n\n";
    $message .= "💳 *Wave Money* - Fast & Secure\n";
    $message .= "💳 *KBZ Pay* - Easy Payment\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "⚠️ *Note:* After payment, send screenshot for verification.\n";
    $message .= "Admin will confirm and add MMK balance to your account.";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '💳 Wave Money', 'callback_data' => 'mmk_topup_wave']
            ],
            [
                ['text' => '💳 KBZ Pay', 'callback_data' => 'mmk_topup_kpay']
            ],
            [
                ['text' => '⬅️ Back', 'callback_data' => 'back_to_main']
            ]
        ]
    ];
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Handle MMK Top Up Payment Method Selection
function handleMMKTopUpMethod($chatId, $telegramId, $method) {
    $methodName = $method === 'wave' ? 'Wave Money' : 'KBZ Pay';
    
    // Payment phone numbers (configure these in admin panel later)
    $wavePhone = '09XXXXXXXXX'; // Replace with actual Wave Money number
    $kpayPhone = '09XXXXXXXXX'; // Replace with actual KBZ Pay number
    $paymentPhone = $method === 'wave' ? $wavePhone : $kpayPhone;
    
    $message = "💳 *{$methodName} TOP UP*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "📱 *Payment Details:*\n";
    $message .= "Phone: `{$paymentPhone}`\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "💰 *Please enter the amount in MMK:*\n\n";
    $message .= "*Example:*\n";
    $message .= "`10000` (for 10,000 MMK)\n";
    $message .= "`50000` (for 50,000 MMK)\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "⚠️ *After entering amount, you'll be asked to send payment screenshot.*";
    
    // Store pending MMK top up
    $pendingTopUp = [
        'action' => 'mmk_topup_amount',
        'method' => $method,
        'method_name' => $methodName,
        'payment_phone' => $paymentPhone,
        'timestamp' => time()
    ];
    savePendingPurchase($telegramId, $pendingTopUp);
    
    sendMessage($chatId, $message, 'Markdown');
}

// Show MMK Packages for Philippines
function showMMKPhilippinesPackages($chatId, $telegramId, $gameId = null, $zoneId = null) {
    $products = loadProducts();
    $phProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == 'php') {
            $phProducts[] = $product;
        }
    }
    
    usort($phProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $message = "🇵🇭 *PHILIPPINES DIAMONDS (MMK)*\n\n";
    
    // Show Game ID info if provided
    if ($gameId && $zoneId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "GameID: `{$gameId}`\n";
        $message .= "ZoneID: `{$zoneId}`\n\n";
    }
    
    $message .= "Select package to buy:\n\n";
    
    foreach ($phProducts as $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        $diamonds = $product['diamonds'] ?? intval($name);
        
        // Use MMK price from product if available, otherwise calculate
        if (isset($product['mmk_price']) && $product['mmk_price'] !== null && $product['mmk_price'] !== '') {
            $mmkPrice = formatMMK(floatval($product['mmk_price']));
        } else {
            $mmkPrice = formatMMK(convertToMMK($price, 'php'));
        }
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - " . $mmkPrice,
                'callback_data' => "buy_mmk_php_{$name}"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'mmk_topup_back'
        ]
    ];
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Show MMK Packages for Brazil
function showMMKBrazilPackages($chatId, $telegramId, $gameId = null, $zoneId = null) {
    $products = loadProducts();
    $brProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == 'br') {
            $brProducts[] = $product;
        }
    }
    
    usort($brProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $message = "🇧🇷 *BRAZIL DIAMONDS (MMK)*\n\n";
    
    // Show Game ID info if provided
    if ($gameId && $zoneId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "GameID: `{$gameId}`\n";
        $message .= "ZoneID: `{$zoneId}`\n\n";
    }
    
    $message .= "Select package to buy:\n\n";
    
    foreach ($brProducts as $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        $diamonds = $product['diamonds'] ?? intval($name);
        
        // Use MMK price from product if available, otherwise calculate
        if (isset($product['mmk_price']) && $product['mmk_price'] !== null && $product['mmk_price'] !== '') {
            $mmkPrice = formatMMK(floatval($product['mmk_price']));
        } else {
            $mmkPrice = formatMMK(convertToMMK($price, 'br'));
        }
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - " . $mmkPrice,
                'callback_data' => "buy_mmk_br_{$name}"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'mmk_topup_back'
        ]
    ];
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Handle MMK Top Up Amount Input
function handleMMKTopUpAmount($chatId, $telegramId, $amount) {
    $pending = getPendingPurchase($telegramId);
    
    if (!$pending || ($pending['action'] ?? '') !== 'mmk_topup_amount') {
        sendMessage($chatId, "❌ Invalid request. Please start from MMK Top Up menu.");
        return;
    }
    
    $amount = floatval($amount);
    if ($amount <= 0 || !is_numeric($amount)) {
        sendMessage($chatId, "❌ Invalid amount. Please enter a valid number (e.g., 10000)");
        return;
    }
    
    $method = $pending['method'] ?? 'wave';
    $methodName = $pending['method_name'] ?? 'Wave Money';
    $paymentPhone = $pending['payment_phone'] ?? '09XXXXXXXXX';
    
    // Calculate equivalent PHP and BRL amounts
    $exchange_rates = [
        'php_to_mmk' => 38.2,
        'brl_to_mmk' => 85.5
    ];
    $phpAmount = $amount / $exchange_rates['php_to_mmk'];
    $brlAmount = $amount / $exchange_rates['brl_to_mmk'];
    
    $message = "💵 *MMK TOP UP REQUEST*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "📱 *Payment Method:* {$methodName}\n";
    $message .= "📞 *Phone Number:* `{$paymentPhone}`\n";
    $message .= "💰 *Amount:* " . formatMMK($amount) . "\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "💵 *MMK Balance to be added:*\n";
    $message .= formatMMK($amount) . "\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "📸 *Next Step:*\n";
    $message .= "Please send payment screenshot after transferring money.\n\n";
    $message .= "⚠️ *Important:*\n";
    $message .= "• Transfer exactly " . formatMMK($amount) . "\n";
    $message .= "• Send clear screenshot\n";
    $message .= "• Admin will verify and add MMK balance to your account";
    
    // Update pending top up with amount (only MMK, no PHP/BRL conversion)
    $pending['action'] = 'mmk_topup_screenshot';
    $pending['amount'] = $amount;
    $pending['amount_mmk'] = $amount; // Store MMK amount directly
    $pending['timestamp'] = time();
    savePendingPurchase($telegramId, $pending);
    
    sendMessage($chatId, $message, 'Markdown');
}

// Handle MMK Top Up Screenshot
function handleMMKTopUpScreenshot($chatId, $telegramId, $photoFileId) {
    $pending = getPendingPurchase($telegramId);
    
    if (!$pending || ($pending['action'] ?? '') !== 'mmk_topup_screenshot') {
        sendMessage($chatId, "❌ Invalid request. Please complete the MMK Top Up process.");
        return;
    }
    
    // Save pending MMK top up request
    $topUpRequest = [
        'id' => 'MMK' . time() . '_' . $telegramId,
        'telegram_id' => $telegramId,
        'method' => $pending['method'] ?? 'wave',
        'method_name' => $pending['method_name'] ?? 'Wave Money',
        'payment_phone' => $pending['payment_phone'] ?? '',
        'amount_mmk' => $pending['amount'] ?? 0,
        'amount_php' => $pending['php_amount'] ?? 0,
        'amount_brl' => $pending['brl_amount'] ?? 0,
        'photo_file_id' => $photoFileId,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ];
    
    // Save to pending MMK top-ups file
    $file = __DIR__ . '/../pending_mmk_topups.json';
    $topUps = [];
    if (file_exists($file)) {
        $data = file_get_contents($file);
        $topUps = json_decode($data, true) ?: [];
    }
    $topUps[] = $topUpRequest;
    file_put_contents($file, json_encode($topUps, JSON_PRETTY_PRINT));
    
    // Clear pending purchase
    clearPendingPurchase($telegramId);
    
    $message = "✅ *PAYMENT SCREENSHOT RECEIVED!*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "📋 *Request Details:*\n";
    $message .= "ID: `{$topUpRequest['id']}`\n";
    $message .= "Method: {$topUpRequest['method_name']}\n";
    $message .= "Amount: " . formatMMK($topUpRequest['amount_mmk']) . "\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "⏳ *Status:* Pending Admin Approval\n\n";
    $message .= "👨‍💼 Admin will verify your payment and add balance to your account.\n";
    $message .= "You will be notified once approved.";
    
    sendMessage($chatId, $message, 'Markdown');
    
    // Notify admins
    notifyAdminsAboutMMKTopUp($topUpRequest);
}

// Notify admins about new MMK top up request
function notifyAdminsAboutMMKTopUp($topUpRequest) {
    global $ADMINS, $BOT_TOKEN;
    
    $admins = loadAdmins();
    foreach ($admins as $admin) {
        $adminId = $admin['telegram_id'] ?? null;
        if (!$adminId) continue;
        
        $message = "🔔 *NEW MMK TOP UP REQUEST*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "🆔 *Request ID:* `{$topUpRequest['id']}`\n";
        $message .= "👤 *User ID:* `{$topUpRequest['telegram_id']}`\n";
        $message .= "💳 *Method:* {$topUpRequest['method_name']}\n";
        $message .= "📞 *Phone:* `{$topUpRequest['payment_phone']}`\n";
        $message .= "💰 *Amount:* " . formatMMK($topUpRequest['amount_mmk']) . "\n";
        $message .= "🇵🇭 *PHP Equivalent:* " . number_format($topUpRequest['amount_php'], 2) . "\n";
        $message .= "🇧🇷 *BRL Equivalent:* " . number_format($topUpRequest['amount_brl'], 2) . "\n";
        $message .= "📅 *Time:* {$topUpRequest['created_at']}\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📸 Payment screenshot received.\n";
        $message .= "Please verify in admin panel.";
        
        sendMessage($adminId, $message, 'Markdown');
        
        // Send photo to admin
        if (!empty($topUpRequest['photo_file_id'])) {
            sendPhotoToAdmin($adminId, $topUpRequest['photo_file_id'], $topUpRequest['id']);
        }
    }
}

// Send photo to admin
function sendPhotoToAdmin($chatId, $photoFileId, $requestId) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendPhoto";
    $data = [
        'chat_id' => $chatId,
        'photo' => $photoFileId,
        'caption' => "Payment Screenshot - Request ID: {$requestId}"
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// User Transaction History
function showUserHistory($chatId, $telegramId) {
    $transactions = getUserTransactions($telegramId, 10);
    
    if (empty($transactions)) {
        sendMessage($chatId, "📜 *No transactions yet!*\n\nUse topup codes or buy diamonds.");
        return;
    }
    
    $message = "📜 *TRANSACTION HISTORY*\n";
    $message .= "═══════════════════════\n\n";
    
    foreach ($transactions as $index => $tx) {
        $emoji = match($tx['type']) {
            'topup_redeem' => '💎',
            'purchase' => '🛒',
            'admin_add' => '➕',
            'admin_subtract' => '➖',
            default => '📝'
        };
        
        $flag = ($tx['country'] == 'php') ? '🇵🇭' : '🇧🇷';
        $currency = ($tx['country'] == 'php') ? 'PHP' : 'BRL';
        
        $message .= "{$emoji} *#" . ($index + 1) . "*\n";
        $message .= "{$flag} " . strtoupper($tx['country']) . "\n";
        $message .= "💰 " . $currency . " " . number_format($tx['amount'], 2) . "\n";
        $message .= "📝 " . ucfirst(str_replace('_', ' ', $tx['type'])) . "\n";
        
        if (strpos($tx['details'], 'Commission') !== false) {
            $message .= "📉 " . $tx['details'] . "\n";
        }
        
        $message .= "🕐 " . date('H:i', strtotime($tx['timestamp'])) . "\n\n";
    }
    
    $message .= "📊 Total: " . count($transactions) . " transactions";
    
    sendMessage($chatId, $message);
}

// ==============================================
// 💎 PURCHASE SYSTEM WITH AUTO ORDER
// ==============================================

// Show Buy Diamonds Country Selection
function showBuyDiamondsCountrySelection($chatId, $telegramId) {
    $message = "💎 *BUY DIAMONDS*\n\n";
    $message .= "Please select country:\n\n";
    $message .= "🇵🇭 *Philippines* - PHP packages\n";
    $message .= "🇧🇷 *Brazil* - BRL packages";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '🇵🇭 Philippines',
                    'callback_data' => 'buy_diamonds_php'
                ],
                [
                    'text' => '🇧🇷 Brazil',
                    'callback_data' => 'buy_diamonds_br'
                ]
            ],
            [
                [
                    'text' => '⬅️ Back',
                    'callback_data' => 'back_to_main'
                ]
            ]
        ]
    ];
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Ask Game ID Choice After Country Selection
function askGameIdChoice($chatId, $telegramId, $country, $useMMK = false) {
    $user = getUser($telegramId);
    $flag = ($country == 'php') ? '🇵🇭' : '🇧🇷';
    $countryName = ($country == 'php') ? 'Philippines' : 'Brazil';
    $mmkPrefix = $useMMK ? 'mmk_' : '';
    
    $message = "{$flag} *{$countryName} DIAMONDS";
    if ($useMMK) {
        $message .= " (MMK)*";
    } else {
        $message .= "*";
    }
    $message .= "\n\n";
    
    // Check if user has saved Game ID
    if (!empty($user['game_id']) && !empty($user['zone_id'])) {
        $message .= "You have saved Game Info:\n";
        $message .= "🎮 GameID: `{$user['game_id']}`\n";
        $message .= "🏠 ZoneID: `{$user['zone_id']}`\n\n";
        $message .= "Choose an option:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✅ Use My Game ID',
                        'callback_data' => $useMMK ? "use_my_gameid_mmk_{$country}" : "use_my_gameid_{$country}"
                    ],
                    [
                        'text' => '🆕 Enter Different Game ID',
                        'callback_data' => $useMMK ? "enter_gameid_mmk_{$country}" : "enter_gameid_{$country}"
                    ]
                ],
                [
                    [
                        'text' => '⬅️ Back',
                        'callback_data' => $useMMK ? 'mmk_topup_back' : 'buy_diamonds_back'
                    ]
                ]
            ]
        ];
    } else {
        $message .= "You don't have saved Game Info.\n";
        $message .= "Please enter your Game ID and Zone ID:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🆕 Enter Game ID & Zone ID',
                        'callback_data' => $useMMK ? "enter_gameid_mmk_{$country}" : "enter_gameid_{$country}"
                    ]
                ],
                [
                    [
                        'text' => '⬅️ Back',
                        'callback_data' => $useMMK ? 'mmk_topup_back' : 'buy_diamonds_back'
                    ]
                ]
            ]
        ];
    }
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Handle Enter Different Game ID
function handleEnterGameIdForPurchase($chatId, $telegramId, $country, $useMMK = false) {
    $flag = ($country == 'php') ? '🇵🇭' : '🇧🇷';
    $countryName = ($country == 'php') ? 'Philippines' : 'Brazil';
    
    // Store pending purchase selection
    $pendingPurchase = [
        'country' => $country,
        'action' => 'enter_gameid',
        'use_mmk' => $useMMK,
        'timestamp' => time()
    ];
    savePendingPurchase($telegramId, $pendingPurchase);
    
    $message = "{$flag} *{$countryName} DIAMONDS*\n\n";
    $message .= "Please enter your Game ID and Zone ID:\n\n";
    $message .= "*Format:*\n";
    $message .= "`GAMEID ZONEID`\n\n";
    $message .= "*Example:*\n";
    $message .= "`123456789 8888`\n\n";
    $message .= "Or use command:\n";
    $message .= "`/gameinfo GAMEID ZONEID`";
    
    sendMessage($chatId, $message);
}

// Check Game ID and Show Packages
function checkGameIdAndShowPackages($chatId, $telegramId, $country, $useMMK = false, $gameId = null, $zoneId = null) {
    $user = getUser($telegramId);
    
    // Use provided Game ID or user's saved Game ID
    $finalGameId = $gameId ?? ($user['game_id'] ?? '');
    $finalZoneId = $zoneId ?? ($user['zone_id'] ?? '');
    
    if (empty($finalGameId) || empty($finalZoneId)) {
        sendMessage($chatId, "❌ Game ID and Zone ID are required!");
        return;
    }
    
    // If Game ID was provided (different from saved), verify it first
    if ($gameId !== null && $zoneId !== null) {
        // Get a sample product to check
        $products = loadProducts();
        $sampleProduct = null;
        foreach ($products as $product) {
            if (($product['country'] ?? '') == $country) {
                $sampleProduct = $product;
                break;
            }
        }
        
        if ($sampleProduct) {
            $productIds = $sampleProduct['products'] ?? [];
            if (!empty($productIds) && is_array($productIds)) {
                $productId = $productIds[0];
                
                // Check in-game name
                sendTyping($chatId);
                $nameCheck = checkInGameName($country, $finalGameId, $finalZoneId, $productId);
                
                if (!$nameCheck['success']) {
                    $message = "❌ *GAME ID VERIFICATION FAILED!*\n\n";
                    $message .= "Could not verify your Game ID and Zone ID.\n";
                    $message .= "Error: " . ($nameCheck['error'] ?? 'Unknown error') . "\n\n";
                    $message .= "Please check and try again:\n";
                    $message .= "🎮 GameID: `{$finalGameId}`\n";
                    $message .= "🏠 ZoneID: `{$finalZoneId}`\n\n";
                    $message .= "Format: `GAMEID ZONEID`";
                    sendMessage($chatId, $message);
                    return;
                }
                
                // Game ID verified successfully
                $inGameName = $nameCheck['username'];
                $message = "✅ *GAME ID VERIFIED!*\n\n";
                $message .= "👤 In-Game Name: *{$inGameName}*\n";
                $message .= "🎮 GameID: `{$finalGameId}`\n";
                $message .= "🏠 ZoneID: `{$finalZoneId}`\n\n";
                $message .= "Loading packages...";
                sendMessage($chatId, $message);
            }
        }
    }
    
    // Show packages with the Game ID
    if ($useMMK) {
        if ($country == 'php') {
            showMMKPhilippinesPackages($chatId, $telegramId, $finalGameId, $finalZoneId);
        } else {
            showMMKBrazilPackages($chatId, $telegramId, $finalGameId, $finalZoneId);
        }
    } else {
        if ($country == 'php') {
            showPhilippinesPackages($chatId, $telegramId, $finalGameId, $finalZoneId);
        } else {
            showBrazilPackages($chatId, $telegramId, $finalGameId, $finalZoneId);
        }
    }
}

// Show Philippines Packages
function showPhilippinesPackages($chatId, $telegramId, $gameId = null, $zoneId = null) {
    $products = loadProducts();
    $phProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == 'php') {
            $phProducts[] = $product;
        }
    }
    
    usort($phProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $message = "🇵🇭 *PHILIPPINES DIAMONDS*\n\n";
    
    // Show Game ID info if provided
    if ($gameId && $zoneId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "GameID: `{$gameId}`\n";
        $message .= "ZoneID: `{$zoneId}`\n\n";
    }
    
    $message .= "Select package to buy:";
    
    foreach ($phProducts as $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        $diamonds = $product['diamonds'] ?? intval($name); // Use name as diamonds if not set
        $mmkPrice = formatMMK(convertToMMK($price, 'php'));
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - PHP " . number_format($price, 2) . " | " . $mmkPrice,
                'callback_data' => "buy_php_{$name}"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'buy_diamonds_back'
        ]
    ];
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Show Brazil Packages
function showBrazilPackages($chatId, $telegramId, $gameId = null, $zoneId = null) {
    $products = loadProducts();
    $brProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == 'br') {
            $brProducts[] = $product;
        }
    }
    
    usort($brProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $message = "🇧🇷 *BRAZIL DIAMONDS*\n";
    $message .= "═══════════════════════\n\n";
    
    // Show Game ID info if provided
    if ($gameId && $zoneId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "GameID: `{$gameId}`\n";
        $message .= "ZoneID: `{$zoneId}`\n\n";
    }
    
    $message .= "Select package to buy:\n\n";
    
    foreach ($brProducts as $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        $diamonds = $product['diamonds'] ?? intval($name); // Use name as diamonds if not set
        $mmkPrice = formatMMK(convertToMMK($price, 'br'));
        
        $message .= "✦ *{$name} Diamonds* 💎\n";
        $message .= "   💰 BRL " . number_format($price, 2) . "\n\n";
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - BRL " . number_format($price, 2) . " | " . $mmkPrice,
                'callback_data' => "buy_br_{$name}"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'buy_diamonds_back'
        ]
    ];
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Handle Purchase with Auto Order (MLBB Auto Topup)
function handlePurchase($chatId, $telegramId, $country, $packageName, $useMMK = false) {
    $products = loadProducts();
    $selectedProduct = null;
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == $country && ($product['name'] ?? '') == $packageName) {
            $selectedProduct = $product;
            break;
        }
    }
    
    if (!$selectedProduct) {
        sendMessage($chatId, "❌ Package not found!");
        return;
    }
    
    $user = getUser($telegramId);
    $price = $selectedProduct['price'] ?? 0;
    $diamonds = $selectedProduct['diamonds'] ?? intval($packageName);
    
    // Get MMK price if using MMK
    $mmkPrice = 0;
    if ($useMMK) {
        if (isset($selectedProduct['mmk_price']) && $selectedProduct['mmk_price'] !== null && $selectedProduct['mmk_price'] !== '') {
            $mmkPrice = floatval($selectedProduct['mmk_price']);
        } else {
            $mmkPrice = convertToMMK($price, $country);
        }
    }
    
    $balanceField = "balance_" . $country;
    $userBalance = $user[$balanceField] ?? 0;
    $userMMKBalance = $user['balance_mmk'] ?? 0;
    $currency = ($country == 'php') ? 'PHP' : 'BRL';
    $flag = ($country == 'php') ? '🇵🇭' : '🇧🇷';
    
    // Check balance based on payment method
    if ($useMMK) {
        // Using MMK balance
        if ($userMMKBalance < $mmkPrice) {
            $message = "❌ *INSUFFICIENT MMK BALANCE!*\n\n";
            $message .= "{$flag} Package: {$packageName} Diamonds 💎\n";
            $message .= "💰 Price: " . formatMMK($mmkPrice) . "\n";
            $message .= "📊 Your MMK Balance: " . formatMMK($userMMKBalance) . "\n";
            $message .= "📈 Need: " . formatMMK($mmkPrice - $userMMKBalance) . " more\n\n";
            $message .= "💎 Use Topup Code to add balance!";
            sendMessage($chatId, $message);
            return;
        }
    } else {
        // Using original currency balance
        if ($userBalance < $price) {
            $message = "❌ *INSUFFICIENT BALANCE!*\n\n";
            $message .= "{$flag} Package: {$packageName} Diamonds 💎\n";
            $message .= "💰 Price: {$currency} " . number_format($price, 2) . "\n";
            $message .= "📊 Your Balance: {$currency} " . number_format($userBalance, 2) . "\n";
            $message .= "📈 Need: {$currency} " . number_format($price - $userBalance, 2) . " more\n\n";
            $message .= "💎 Use Topup Code to add balance!";
            sendMessage($chatId, $message);
            return;
        }
    }
    
    // Get Game ID and Zone ID from pending purchase or user's saved info
    $pending = getPendingPurchase($telegramId);
    $gameId = null;
    $zoneId = null;
    
    // Check if we have Game ID from the purchase flow
    if ($pending && isset($pending['game_id']) && isset($pending['zone_id'])) {
        $gameId = $pending['game_id'];
        $zoneId = $pending['zone_id'];
    } else {
        // Use user's saved Game ID
        $gameId = $user['game_id'] ?? '';
        $zoneId = $user['zone_id'] ?? '';
    }
    
    if (empty($gameId) || empty($zoneId)) {
        // Ask for Game ID and Zone ID
        $message = "🎮 *GAME INFO REQUIRED!*\n\n";
        $message .= "{$flag} Package: *{$packageName} Diamonds* 💎\n";
        if ($useMMK) {
            $message .= "💰 Price: " . formatMMK($mmkPrice) . "\n\n";
        } else {
            $message .= "💰 Price: {$currency} " . number_format($price, 2) . "\n\n";
        }
        $message .= "Please provide your Game ID and Zone ID:\n\n";
        $message .= "*Format:*\n";
        $message .= "`GAMEID ZONEID`\n\n";
        $message .= "*Example:*\n";
        $message .= "`123456789 8888`\n\n";
        $message .= "Or use: `/gameinfo GAMEID ZONEID`";
        
        // Store pending purchase for later
        $pendingPurchase = [
            'country' => $country,
            'package_name' => $packageName,
            'price' => $price,
            'mmk_price' => $useMMK ? $mmkPrice : null,
            'use_mmk' => $useMMK,
            'diamonds' => $diamonds,
            'timestamp' => time()
        ];
        savePendingPurchase($telegramId, $pendingPurchase);
        
        sendMessage($chatId, $message);
        return;
    }
    
    // Get product ID for SmileOne API
    $productIds = $selectedProduct['products'] ?? [];
    if (empty($productIds) || !is_array($productIds)) {
        sendMessage($chatId, "❌ Product configuration error. Please contact admin.");
        return;
    }
    $productId = $productIds[0]; // Use first product ID
    
    // Check in-game name
    sendTyping($chatId);
    $nameCheck = checkInGameName($country, $gameId, $zoneId, $productId);
    
    if (!$nameCheck['success']) {
        $message = "❌ *VERIFICATION FAILED!*\n\n";
        $message .= "Could not verify your in-game name.\n";
        $message .= "Error: " . ($nameCheck['error'] ?? 'Unknown error') . "\n\n";
        $message .= "Please check your Game ID and Zone ID:\n";
        $message .= "🎮 GameID: `{$gameId}`\n";
        $message .= "🏠 ZoneID: `{$zoneId}`\n\n";
        $message .= "Use `/gameinfo GAMEID ZONEID` to update.";
        sendMessage($chatId, $message);
        return;
    }
    
    $inGameName = $nameCheck['username'];
    
    // Store pending purchase for confirmation
    $pendingPurchase = [
        'country' => $country,
        'package_name' => $packageName,
        'price' => $price,
        'mmk_price' => $useMMK ? $mmkPrice : null,
        'use_mmk' => $useMMK,
        'diamonds' => $diamonds,
        'game_id' => $gameId,
        'zone_id' => $zoneId,
        'product_id' => $productId,
        'in_game_name' => $inGameName,
        'timestamp' => time()
    ];
    savePendingPurchase($telegramId, $pendingPurchase);
    
    // Show confirmation with in-game name
    $message = "✅ *IN-GAME NAME VERIFIED!*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "📦 *Package Details*\n";
    $message .= "{$flag} Package: *{$packageName} Diamonds* 💎\n";
    $message .= "💎 Diamonds: {$diamonds}\n";
    if ($useMMK) {
        $message .= "💰 Price: " . formatMMK($mmkPrice) . "\n\n";
        $message .= "📊 *Balance*\n";
        $message .= "Current MMK: " . formatMMK($userMMKBalance) . "\n";
        $message .= "After Purchase: " . formatMMK($userMMKBalance - $mmkPrice) . "\n\n";
    } else {
        $message .= "💰 Price: {$currency} " . number_format($price, 2) . "\n\n";
        $message .= "📊 *Balance*\n";
        $message .= "Current: {$currency} " . number_format($userBalance, 2) . "\n";
        $message .= "After Purchase: {$currency} " . number_format($userBalance - $price, 2) . "\n\n";
    }
    $message .= "🎮 *Game Information*\n";
    $message .= "GameID: `{$gameId}`\n";
    $message .= "ZoneID: `{$zoneId}`\n";
    $message .= "👤 In-Game Name: *{$inGameName}*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "⚠️ *Please confirm your purchase*\n";
    $message .= "Diamonds will be sent to the account above.";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '✅ Confirm Purchase',
                    'callback_data' => 'confirm_order'
                ],
                [
                    'text' => '❌ Cancel',
                    'callback_data' => 'cancel_order'
                ]
            ]
        ]
    ];
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Confirm Order
function confirmOrder($chatId, $telegramId) {
    $pending = getPendingPurchase($telegramId);
    
    if (!$pending) {
        sendMessage($chatId, "❌ No pending purchase found. Please select a package again.");
        return;
    }
    
    // Check if purchase is too old (5 minutes)
    if (time() - $pending['timestamp'] > 300) {
        clearPendingPurchase($telegramId);
        sendMessage($chatId, "❌ Purchase request expired. Please select a package again.");
        return;
    }
    
    $user = getUser($telegramId);
    $country = $pending['country'];
    $packageName = $pending['package_name'];
    $price = $pending['price'];
    $mmkPrice = $pending['mmk_price'] ?? null;
    $useMMK = $pending['use_mmk'] ?? false;
    $diamonds = $pending['diamonds'];
    $gameId = $pending['game_id'];
    $zoneId = $pending['zone_id'];
    $inGameName = $pending['in_game_name'];
    $balanceField = "balance_" . $country;
    $userBalance = $user[$balanceField] ?? 0;
    $userMMKBalance = $user['balance_mmk'] ?? 0;
    $currency = ($country == 'php') ? 'PHP' : 'BRL';
    $flag = ($country == 'php') ? '🇵🇭' : '🇧🇷';
    
    // Double-check balance
    if ($useMMK && $mmkPrice !== null) {
        if ($userMMKBalance < $mmkPrice) {
            clearPendingPurchase($telegramId);
            sendMessage($chatId, "❌ Insufficient MMK balance. Please add balance and try again.");
            return;
        }
        // Deduct MMK balance
        $users = loadUsers();
        foreach ($users as &$u) {
            if (($u['telegram_id'] ?? '') == $telegramId) {
                $u['balance_mmk'] = ($u['balance_mmk'] ?? 0) - $mmkPrice;
                $newBalance = $u['balance_mmk'];
                break;
            }
        }
        saveUsers($users);
    } else {
        if ($userBalance < $price) {
            clearPendingPurchase($telegramId);
            sendMessage($chatId, "❌ Insufficient balance. Please add balance and try again.");
            return;
        }
        // Deduct balance
        $result = updateUserBalance($telegramId, $country, $price, 'subtract');
        $newBalance = $result['new_balance'];
    }
    
    // Create order (use MMK price if using MMK)
    $orderPrice = $useMMK && $mmkPrice !== null ? $mmkPrice : $price;
    $orderId = saveOrder($telegramId, $country, $packageName, $orderPrice, $gameId, $zoneId);
    
    // Save transaction
    $txCountry = $useMMK ? 'mmk' : $country;
    $txAmount = $useMMK && $mmkPrice !== null ? $mmkPrice : $price;
    $txDetails = "Purchased {$packageName} Diamonds ({$diamonds} diamonds) - Order: {$orderId} - IGN: {$inGameName}";
    if ($useMMK) {
        $txDetails .= " (Paid with MMK)";
    }
    $txId = saveTransaction($telegramId, 'purchase', $txCountry, $txAmount, $txDetails);
    
    // Try to process order via SmileOne
    sendTyping($chatId);
    $orderResult = processOrderViaSmileOne($orderId);
    
    // Clear pending purchase
    clearPendingPurchase($telegramId);
    
    $message = "✅ *PURCHASE CONFIRMED!*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "📦 *Order Details*\n";
    $message .= "{$flag} Package: *{$packageName} Diamonds* 💎\n";
    $message .= "💎 Diamonds: {$diamonds}\n";
    if ($useMMK && $mmkPrice !== null) {
        $message .= "💰 Price: " . formatMMK($mmkPrice) . "\n\n";
        $message .= "📊 *Balance Update*\n";
        $message .= "New MMK Balance: " . formatMMK($newBalance) . "\n";
    } else {
        $message .= "💰 Price: {$currency} " . number_format($price, 2) . "\n\n";
        $message .= "📊 *Balance Update*\n";
        $message .= "New Balance: {$currency} " . number_format($newBalance, 2) . "\n";
    }
    $message .= "🎮 *Game Information*\n";
    $message .= "GameID: `{$gameId}`\n";
    $message .= "ZoneID: `{$zoneId}`\n";
    $message .= "👤 In-Game Name: *{$inGameName}*\n\n";
    $message .= "🆔 Order ID: `{$orderId}`\n";
    $message .= "🕐 " . date('Y-m-d H:i:s') . "\n\n";
    
    if ($orderResult['success']) {
        $message .= "✅ *Auto Topup Successful!*\n";
        $message .= "Diamonds are being sent to your account now.\n";
        $message .= "Please check your game in a few moments.";
    } else {
        $message .= "⚠️ *Order saved, processing...*\n";
        $message .= "Admin will process it if auto-topup fails.\n";
        $message .= "You will be notified when completed.";
    }
    
    sendMessage($chatId, $message);
}

// Cancel Order
function cancelOrder($chatId, $telegramId) {
    clearPendingPurchase($telegramId);
    
    $message = "❌ *Purchase Cancelled*\n\n";
    $message .= "Your purchase has been cancelled.\n";
    $message .= "No charges were made.\n\n";
    $message .= "You can select another package anytime!";
    
    sendMessage($chatId, $message);
}

// ==============================================
// 👑 ADMIN SYSTEM WITH COMMISSION
// ==============================================

// Admin Panel
function showAdminPanel($chatId) {
    $keyboard = [
        'keyboard' => [
            [['text' => '🔑 Generate Code'], ['text' => '📋 Active Codes']],
            [['text' => '💰 Smile Balance'], ['text' => '👥 Users']],
            [['text' => '➕ Add Balance'], ['text' => '➖ Subtract Balance']],
            [['text' => '📊 Stats'], ['text' => '💸 Commission Stats']],
            [['text' => '⚙️ Commission Rules'], ['text' => '👑 Manage Admins']],
            [['text' => '📦 Pending Orders'], ['text' => '⚙️ Settings']],
            [['text' => '⬅️ User Panel']]
        ],
        'resize_keyboard' => true
    ];
    
    $rules = loadCommissionRules();
    
    $message = "👑 *ADMIN PANEL*\n\n";
    
    // Show commission rates summary
    $message .= "🇵🇭 *PHP Commission Rates:*\n";
    foreach ($rules['php'] as $rule) {
        $message .= "└── PHP {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    $message .= "🇧🇷 *BR Commission Rates:*\n";
    foreach ($rules['br'] as $rule) {
        $message .= "└── BRL {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    $message .= "*Admin Commands:*\n\n";
    $message .= "🔑 Generate Code - Create topup code\n";
    $message .= "📋 Active Codes - View unused codes\n";
    $message .= "💰 Smile Balance - Check SmileOne\n";
    $message .= "👥 Users - Manage users\n";
    $message .= "➕ Add Balance - Add to user\n";
    $message .= "➖ Subtract Balance - Remove from user\n";
    $message .= "📊 Stats - System statistics\n";
    $message .= "💸 Commission Stats - Commission reports\n";
    $message .= "⚙️ Commission Rules - Set commission %\n";
    $message .= "👑 Manage Admins - Add/remove admins\n";
    $message .= "📦 Pending Orders - View pending orders\n";
    $message .= "⚙️ Settings - Bot settings";
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Manage Admins Panel
function showManageAdmins($chatId) {
    $admins = loadAdmins();
    
    $message = "👑 *MANAGE ADMINS*\n";
    $message .= "═══════════════════════\n\n";
    
    foreach ($admins as $index => $admin) {
        $message .= "*Admin #" . ($index + 1) . "*\n";
        $message .= "🆔 ID: `{$admin['telegram_id']}`\n";
        $message .= "📅 Added: " . $admin['added_at'] . "\n\n";
    }
    
    $message .= "*Commands:*\n";
    $message .= "`/addadmin TELEGRAM_ID` - Add new admin\n";
    $message .= "`/removeadmin TELEGRAM_ID` - Remove admin\n\n";
    $message .= "*Example:*\n";
    $message .= "`/addadmin 1234567890`\n";
    $message .= "`/removeadmin 1234567890`";
    
    sendMessage($chatId, $message);
}

// Handle Add Admin
function handleAddAdmin($chatId, $adminId) {
    if (!is_numeric($adminId)) {
        sendMessage($chatId, "❌ Invalid Telegram ID! Must be numeric.");
        return;
    }
    
    if (addAdmin($adminId)) {
        sendMessage($chatId, "✅ Admin added successfully!\n\nID: `{$adminId}`");
    } else {
        sendMessage($chatId, "❌ Admin already exists!");
    }
}

// Handle Remove Admin
function handleRemoveAdmin($chatId, $adminId) {
    if (!is_numeric($adminId)) {
        sendMessage($chatId, "❌ Invalid Telegram ID! Must be numeric.");
        return;
    }
    
    $admins = loadAdmins();
    $currentAdminCount = count($admins);
    
    if ($currentAdminCount <= 1) {
        sendMessage($chatId, "❌ Cannot remove the last admin!");
        return;
    }
    
    if (removeAdmin($adminId)) {
        sendMessage($chatId, "✅ Admin removed successfully!\n\nID: `{$adminId}`");
    } else {
        sendMessage($chatId, "❌ Admin not found!");
    }
}

// Commission Rules Panel
function showCommissionRulesPanel($chatId) {
    $rules = loadCommissionRules();
    
    $message = "⚙️ *COMMISSION RULES*\n";
    $message .= "═══════════════════════\n\n";
    
    $message .= "🇵🇭 *PHP Commission Rules:*\n";
    foreach ($rules['php'] as $index => $rule) {
        $message .= "*Rule #" . ($index + 1) . "*\n";
        $message .= "└── Amount: PHP {$rule['min_amount']} - {$rule['max_amount']}\n";
        $message .= "└── Rate: " . ($rule['rate'] * 100) . "%\n\n";
    }
    
    $message .= "🇧🇷 *BR Commission Rules:*\n";
    foreach ($rules['br'] as $index => $rule) {
        $message .= "*Rule #" . ($index + 1) . "*\n";
        $message .= "└── Amount: BRL {$rule['min_amount']} - {$rule['max_amount']}\n";
        $message .= "└── Rate: " . ($rule['rate'] * 100) . "%\n\n";
    }
    
    $message .= "*Commands:*\n";
    $message .= "`/setcommission php MIN MAX RATE` - Set PHP commission\n";
    $message .= "`/setcommission br MIN MAX RATE` - Set BR commission\n\n";
    $message .= "*Example:*\n";
    $message .= "`/setcommission php 0 1000 0.2` - 0.2% for PHP 0-1000\n";
    $message .= "`/setcommission br 2000 5000 1.0` - 1.0% for BR 2000-5000\n\n";
    $message .= "*Note:* Rate is in percentage (1.0 = 1%)";
    
    sendMessage($chatId, $message);
}

// Handle Set Commission Rule
function handleSetCommissionRule($chatId, $params) {
    if (count($params) < 4) {
        sendMessage($chatId, "❌ Format: `/setcommission country MIN MAX RATE`\n\nExample: `/setcommission php 0 1000 0.2`");
        return;
    }
    
    $country = strtolower($params[0]);
    $min = floatval($params[1]);
    $max = floatval($params[2]);
    $rate = floatval($params[3]) / 100; // Convert from percentage to decimal
    
    if (!in_array($country, ['php', 'br'])) {
        sendMessage($chatId, "❌ Invalid country! Use: php or br");
        return;
    }
    
    if ($min < 0 || $max < 0 || $min >= $max) {
        sendMessage($chatId, "❌ Invalid amount range! MIN must be less than MAX and both positive.");
        return;
    }
    
    if ($rate < 0 || $rate > 1) {
        sendMessage($chatId, "❌ Invalid rate! Rate must be between 0-100 (0-1 in decimal).");
        return;
    }
    
    if (addCommissionRule($country, $min, $max, $rate)) {
        $message = "✅ *COMMISSION RULE ADDED!*\n\n";
        $message .= "Country: " . strtoupper($country) . "\n";
        $message .= "Amount Range: " . ($country == 'php' ? 'PHP' : 'BRL') . " {$min} - {$max}\n";
        $message .= "Commission Rate: " . ($rate * 100) . "%\n\n";
        $message .= "Rule will be applied to new topup codes.";
        
        sendMessage($chatId, $message);
    } else {
        sendMessage($chatId, "❌ Failed to add commission rule!");
    }
}

// Generate Topup Code Menu
function showGenerateCodeMenu($chatId) {
    $rules = loadCommissionRules();
    
    $keyboard = [
        'keyboard' => [
            [['text' => '🇵🇭 PHP 100'], ['text' => '🇧🇷 BR 100']],
            [['text' => '💎 PHP Custom'], ['text' => '💎 BR Custom']],
            [['text' => '🆓 PHP No Commission'], ['text' => '🆓 BR No Commission']],
            [['text' => '📋 View Codes']],
            [['text' => '⬅️ Back']]
        ],
        'resize_keyboard' => true
    ];
    
    $message = "🔑 *GENERATE TOPUP CODE*\n\n";
    
    // Show PHP commission rates
    $message .= "🇵🇭 *PHP Commission Rates:*\n";
    foreach ($rules['php'] as $rule) {
        $message .= "└── PHP {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    // Show BR commission rates
    $message .= "🇧🇷 *BR Commission Rates:*\n";
    foreach ($rules['br'] as $rule) {
        $message .= "└── BRL {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    $message .= "Choose an option:\n\n";
    $message .= "🇵🇭 PHP 100 - PHP 100 (with commission)\n";
    $message .= "🇧🇷 BR 100 - BR 100 (with commission)\n";
    $message .= "💎 PHP Custom - Custom PHP amount\n";
    $message .= "💎 BR Custom - Custom BR amount\n";
    $message .= "🆓 PHP No Commission - PHP code without commission\n";
    $message .= "🆓 BR No Commission - BR code without commission\n";
    $message .= "📋 View Codes - Active codes";
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Generate Topup Code with Commission Info
function generateAndShowCode($chatId, $country, $amount = null, $applyCommission = true, $customRate = null) {
    if ($amount === null) {
        $amount = 100; // Default amount
    }
    
    $topupCode = generateTopupCode($country, $amount, $applyCommission, $customRate);
    
    $flag = ($country == 'php') ? '🇵🇭' : '🇧🇷';
    $currency = ($country == 'php') ? 'PHP' : 'BRL';
    
    $message = "✅ *TOPUP CODE GENERATED!*\n\n";
    $message .= "{$flag} Country: " . strtoupper($country) . "\n";
    $message .= "💰 Original Amount: {$currency} " . number_format($amount, 2) . "\n";
    
    if ($applyCommission) {
        $commission = $amount * $topupCode['commission_rate'] / 100;
        $netAmount = $amount - $commission;
        
        $message .= "📉 Commission (" . $topupCode['commission_rate'] . "%): -{$currency} " . number_format($commission, 2) . "\n";
        $message .= "📊 Net Amount: {$currency} " . number_format($netAmount, 2) . "\n";
    } else {
        $message .= "🆓 No Commission Applied\n";
    }
    
    $message .= "🔑 *Code:* `{$topupCode['code']}`\n";
    $message .= "📅 Created: " . date('H:i:s') . "\n";
    $message .= "⏰ Expires: " . date('Y-m-d H:i:s', strtotime('+30 days')) . "\n\n";
    $message .= "*Share this code with users!*";
    
    sendMessage($chatId, $message);
}

// Show Custom Amount Input
function showCustomAmountInput($chatId, $country) {
    $flag = ($country == 'php') ? '🇵🇭' : '🇧🇷';
    $currency = ($country == 'php') ? 'PHP' : 'BRL';
    
    $rules = loadCommissionRules();
    $countryRules = $rules[$country] ?? [];
    
    $message = "💎 *CUSTOM AMOUNT TOPUP CODE*\n\n";
    $message .= "{$flag} Country: " . strtoupper($country) . "\n\n";
    
    $message .= "*Current Commission Rates:*\n";
    foreach ($countryRules as $rule) {
        $message .= "└── {$currency} {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    $message .= "Enter amount for topup code:\n\n";
    $message .= "*Format:*\n";
    $message .= "`/gencode {$country} amount`\n\n";
    $message .= "*Example:*\n";
    $message .= "`/gencode {$country} 500.50`\n\n";
    $message .= "Minimum: " . MINIMUM_TOPUP . " | Maximum: " . MAXIMUM_TOPUP;
    
    sendMessage($chatId, $message);
}

// Handle Custom Code Generation
function handleCustomCodeGeneration($chatId, $params) {
    if (count($params) < 2) {
        sendMessage($chatId, "❌ Format: `/gencode country amount`\n\nExample: `/gencode php 500`");
        return;
    }
    
    $country = strtolower($params[0]);
    $amount = floatval($params[1]);
    
    if (!in_array($country, ['php', 'br'])) {
        sendMessage($chatId, "❌ Invalid country! Use: php or br");
        return;
    }
    
    if ($amount < MINIMUM_TOPUP || $amount > MAXIMUM_TOPUP) {
        sendMessage($chatId, "❌ Amount must be between " . MINIMUM_TOPUP . " and " . MAXIMUM_TOPUP . "!");
        return;
    }
    
    generateAndShowCode($chatId, $country, $amount, true);
}

// Generate No Commission Code
function generateNoCommissionCode($chatId, $params) {
    if (count($params) < 2) {
        sendMessage($chatId, "❌ Format: `/gencode_nocomm country amount`\n\nExample: `/gencode_nocomm php 500`");
        return;
    }
    
    $country = strtolower($params[0]);
    $amount = floatval($params[1]);
    
    if (!in_array($country, ['php', 'br'])) {
        sendMessage($chatId, "❌ Invalid country! Use: php or br");
        return;
    }
    
    if ($amount < MINIMUM_TOPUP || $amount > MAXIMUM_TOPUP) {
        sendMessage($chatId, "❌ Amount must be between " . MINIMUM_TOPUP . " and " . MAXIMUM_TOPUP . "!");
        return;
    }
    
    generateAndShowCode($chatId, $country, $amount, false);
}

// Show Active Codes
function showActiveCodes($chatId) {
    $activeCodes = getActiveCodes();
    
    if (empty($activeCodes)) {
        sendMessage($chatId, "📋 *No active codes found!*\n\nGenerate new codes first.");
        return;
    }
    
    $message = "📋 *ACTIVE TOPUP CODES*\n";
    $message .= "═══════════════════════\n\n";
    
    $phpCodes = [];
    $brCodes = [];
    
    foreach ($activeCodes as $code) {
        if ($code['country'] == 'php') {
            $phpCodes[] = $code;
        } else {
            $brCodes[] = $code;
        }
    }
    
    if (!empty($phpCodes)) {
        $message .= "🇵🇭 *PHILIPPINES CODES*\n";
        foreach ($phpCodes as $code) {
            $message .= "🔑 `{$code['code']}`\n";
            $message .= "💰 PHP " . number_format($code['original_amount'], 2);
            
            if ($code['apply_commission']) {
                $message .= " (Net: PHP " . number_format($code['net_amount'], 2) . ")\n";
                $message .= "📉 Commission: " . ($code['commission_rate'] ?? 0) . "%\n";
            } else {
                $message .= " 🆓 No Commission\n";
            }
            
            $message .= "⏰ Expires: " . date('Y-m-d', strtotime($code['expires_at'])) . "\n\n";
        }
    }
    
    if (!empty($brCodes)) {
        $message .= "🇧🇷 *BRAZIL CODES*\n";
        foreach ($brCodes as $code) {
            $message .= "🔑 `{$code['code']}`\n";
            $message .= "💰 BRL " . number_format($code['original_amount'], 2);
            
            if ($code['apply_commission']) {
                $message .= " (Net: BRL " . number_format($code['net_amount'], 2) . ")\n";
                $message .= "📉 Commission: " . ($code['commission_rate'] ?? 0) . "%\n";
            } else {
                $message .= " 🆓 No Commission\n";
            }
            
            $message .= "⏰ Expires: " . date('Y-m-d', strtotime($code['expires_at'])) . "\n\n";
        }
    }
    
    $message .= "📊 Total Active Codes: " . count($activeCodes);
    
    sendMessage($chatId, $message);
}

// Show Pending Orders
function showPendingOrders($chatId) {
    $orders = [];
    if (file_exists(ORDERS_FILE)) {
        $data = file_get_contents(ORDERS_FILE);
        $orders = json_decode($data, true) ?: [];
    }
    
    $pendingOrders = [];
    foreach ($orders as $order) {
        if ($order['status'] == 'pending') {
            $pendingOrders[] = $order;
        }
    }
    
    if (empty($pendingOrders)) {
        sendMessage($chatId, "📦 *No pending orders found!*");
        return;
    }
    
    $message = "📦 *PENDING ORDERS*\n";
    $message .= "═══════════════════════\n\n";
    
    foreach ($pendingOrders as $index => $order) {
        $flag = ($order['country'] == 'php') ? '🇵🇭' : '🇧🇷';
        $currency = ($order['country'] == 'php') ? 'PHP' : 'BRL';
        
        $message .= "*Order #" . ($index + 1) . "*\n";
        $message .= "🆔 Order ID: `{$order['id']}`\n";
        $message .= "👤 User ID: `{$order['user_id']}`\n";
        $message .= "{$flag} Package: {$order['product_name']}\n";
        $message .= "💰 Price: {$currency} " . number_format($order['price'], 2) . "\n";
        $message .= "🎮 GameID: `{$order['game_id']}`\n";
        $message .= "🏠 ZoneID: `{$order['zone_id']}`\n";
        $message .= "📅 Created: " . $order['created_at'] . "\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✅ Process via SmileOne',
                        'callback_data' => 'process_order_' . $order['id']
                    ]
                ]
            ]
        ];
        
        sendMessage($chatId, $message, 'Markdown', $keyboard);
        $message = "";
    }
    
    if (!empty($message)) {
        sendMessage($chatId, $message);
    }
}
// Process Order via Callback
function processOrderCallback($chatId, $orderId) {
    $result = processOrderViaSmileOne($orderId);
    
    if ($result['success']) {
        sendMessage($chatId, "✅ *Order Processed Successfully!*\n\nOrder ID: `{$orderId}`\n\n" . $result['message']);
    } else {
        sendMessage($chatId, "❌ *Order Processing Failed!*\n\nOrder ID: `{$orderId}`\n\n" . $result['message']);
    }
}

// Commission Statistics
function showCommissionStats($chatId) {
    $stats = getCommissionStats();
    $users = loadUsers();
    $rules = loadCommissionRules();
    
    // Calculate total user commissions
    $totalUserCommission = 0;
    foreach ($users as $user) {
        $totalUserCommission += $user['total_commission'] ?? 0;
    }
    
    $message = "💸 *COMMISSION STATISTICS*\n";
    $message .= "═══════════════════════\n\n";
    
    // Show current commission rates
    $message .= "🇵🇭 *Current PHP Commission Rates:*\n";
    foreach ($rules['php'] as $rule) {
        $message .= "└── PHP {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    $message .= "🇧🇷 *Current BR Commission Rates:*\n";
    foreach ($rules['br'] as $rule) {
        $message .= "└── BRL {$rule['min_amount']}-{$rule['max_amount']}: " . ($rule['rate'] * 100) . "%\n";
    }
    $message .= "\n";
    
    $message .= "💰 *Total Commission Collected:*\n";
    $message .= "🇵🇭 PHP Commission: " . number_format($stats['total_commission_php'], 2) . "\n";
    $message .= "🇧🇷 BR Commission: " . number_format($stats['total_commission_br'], 2) . "\n";
    $message .= "📊 Total Commission: " . number_format($stats['total_commission'], 2) . "\n\n";
    
    $message .= "👥 *User Commission Totals:*\n";
    $message .= "Total User Commission: " . number_format($totalUserCommission, 2) . "\n";
    $message .= "Total Commission Transactions: " . $stats['total_transactions'] . "\n\n";
    
    $message .= "📅 *Last 7 Days Estimate:*\n";
    $dailyAvg = $stats['total_commission'] / max(1, $stats['total_transactions'] / 10);
    $weeklyEstimate = $dailyAvg * 7;
    $message .= "Estimated Weekly Commission: " . number_format($weeklyEstimate, 2) . "\n";
    $message .= "Estimated Monthly Commission: " . number_format($dailyAvg * 30, 2) . "\n\n";
    
    $message .= "🕐 Last Updated: " . date('Y-m-d H:i:s');
    
    sendMessage($chatId, $message);
}

// Show SmileOne Balance
function showSmileBalance($chatId) {
    sendTyping($chatId);
    
    if (!class_exists('SmileOne')) {
        sendMessage($chatId, "❌ SmileOne class not loaded!");
        return;
    }
    
    try {
        $smile = new SmileOne();
        $balance = $smile->getBalanceAll();
        
        $message = "💰 *SMILEONE BALANCE*\n";
        $message .= "═══════════════════════\n\n";
        
        if (isset($balance['php'])) {
            $message .= "🇵🇭 Philippines: `{$balance['php']}`\n";
        } else {
            $message .= "🇵🇭 Philippines: ❌\n";
        }
        
        if (isset($balance['br'])) {
            $message .= "🇧🇷 Brazil: `{$balance['br']}`\n";
        } else {
            $message .= "🇧🇷 Brazil: ❌\n";
        }
        
        $message .= "\n🕐 " . date('H:i:s') . "\n";
        $message .= "📅 " . date('Y-m-d');
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Refresh', 'callback_data' => 'refresh_smile_balance'],
                    ['text' => '📦 Process Orders', 'callback_data' => 'process_pending_orders']
                ]
            ]
        ];
        
        sendMessage($chatId, $message, 'Markdown', $keyboard);
        
    } catch (Exception $e) {
        sendMessage($chatId, "❌ Error: " . $e->getMessage());
    }
}

// Manage Users
function showManageUsers($chatId) {
    $users = loadUsers();
    
    $message = "👥 *MANAGE USERS*\n";
    $message .= "═══════════════════════\n\n";
    $message .= "Total Users: " . count($users) . "\n\n";
    
    $totalBalancePHP = 0;
    $totalBalanceBR = 0;
    $totalCommission = 0;
    
    foreach (array_slice($users, 0, 10) as $index => $user) {
        $message .= "*User #" . ($index + 1) . "*\n";
        $message .= "🆔 ID: `{$user['telegram_id']}`\n";
        $message .= "🎮 GameID: `{$user['game_id']}`\n";
        $message .= "🏠 ZoneID: `{$user['zone_id']}`\n";
        $message .= "🇵🇭 PHP: " . number_format($user['balance_php'] ?? 0, 2) . "\n";
        $message .= "🇧🇷 BR: " . number_format($user['balance_br'] ?? 0, 2) . "\n";
        $message .= "📉 Commission Paid: " . number_format($user['total_commission'] ?? 0, 2) . "\n";
        $message .= "🔄 Topups: " . ($user['total_topups'] ?? 0) . "\n";
        $message .= "🛒 Orders: " . ($user['total_orders'] ?? 0) . "\n";
        $message .= "🕐 Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
        
        $totalBalancePHP += $user['balance_php'] ?? 0;
        $totalBalanceBR += $user['balance_br'] ?? 0;
        $totalCommission += $user['total_commission'] ?? 0;
    }
    
    if (count($users) > 10) {
        $message .= "➕ " . (count($users) - 10) . " more users...\n\n";
    }
    
    $message .= "📊 *System Totals:*\n";
    $message .= "🇵🇭 Total PHP: " . number_format($totalBalancePHP, 2) . "\n";
    $message .= "🇧🇷 Total BR: " . number_format($totalBalanceBR, 2) . "\n";
    $message .= "📉 Total Commission: " . number_format($totalCommission, 2) . "\n";
    $message .= "📈 Combined Balance: " . number_format($totalBalancePHP + $totalBalanceBR, 2);
    
    sendMessage($chatId, $message);
}

// System Statistics
function showSystemStats($chatId) {
    $users = loadUsers();
    $totalUsers = count($users);
    
    $totalBalancePHP = 0;
    $totalBalanceBR = 0;
    $activeUsers = 0;
    $totalTopups = 0;
    $totalCommission = 0;
    $totalOrders = 0;
    
    foreach ($users as $user) {
        $totalBalancePHP += $user['balance_php'] ?? 0;
        $totalBalanceBR += $user['balance_br'] ?? 0;
        $totalTopups += $user['total_topups'] ?? 0;
        $totalCommission += $user['total_commission'] ?? 0;
        $totalOrders += $user['total_orders'] ?? 0;
        
        if (($user['is_active'] ?? false)) {
            $activeUsers++;
        }
    }
    
    $codes = loadTopupCodes();
    $totalCodes = count($codes);
    $usedCodes = 0;
    $activeCodes = 0;
    
    foreach ($codes as $code) {
        if ($code['used']) {
            $usedCodes++;
        } elseif (strtotime($code['expires_at']) > time()) {
            $activeCodes++;
        }
    }
    
    $orders = [];
    if (file_exists(ORDERS_FILE)) {
        $data = file_get_contents(ORDERS_FILE);
        $orders = json_decode($data, true) ?: [];
    }
    
    $pendingOrders = 0;
    $completedOrders = 0;
    foreach ($orders as $order) {
        if ($order['status'] == 'pending') {
            $pendingOrders++;
        } elseif ($order['status'] == 'completed') {
            $completedOrders++;
        }
    }
    
    $message = "📊 *SYSTEM STATISTICS*\n";
    $message .= "═══════════════════════\n\n";
    
    $message .= "👥 *Users:*\n";
    $message .= "└── Total: {$totalUsers}\n";
    $message .= "└── Active: {$activeUsers}\n";
    $message .= "└── Total Topups: {$totalTopups}\n";
    $message .= "└── Total Orders: {$totalOrders}\n\n";
    
    $message .= "💰 *Balances:*\n";
    $message .= "└── 🇵🇭 PHP: " . number_format($totalBalancePHP, 2) . "\n";
    $message .= "└── 🇧🇷 BR: " . number_format($totalBalanceBR, 2) . "\n";
    $message .= "└── 📊 Combined: " . number_format($totalBalancePHP + $totalBalanceBR, 2) . "\n\n";
    
    $message .= "💸 *Commission:*\n";
    $message .= "└── Total Collected: " . number_format($totalCommission, 2) . "\n\n";
    
    $message .= "🔑 *Topup Codes:*\n";
    $message .= "└── Total: {$totalCodes}\n";
    $message .= "└── Used: {$usedCodes}\n";
    $message .= "└── Active: {$activeCodes}\n\n";
    
    $message .= "📦 *Orders:*\n";
    $message .= "└── Total: " . count($orders) . "\n";
    $message .= "└── Pending: {$pendingOrders}\n";
    $message .= "└── Completed: {$completedOrders}\n\n";
    
    $message .= "📁 *Files:*\n";
    $message .= (file_exists(USERS_FILE) ? "✅" : "❌") . " users.json\n";
    $message .= (file_exists(PRODUCTS_FILE) ? "✅" : "❌") . " products.json\n";
    $message .= (file_exists(CODES_FILE) ? "✅" : "❌") . " topup_codes.json\n";
    $message .= (file_exists(TRANSACTIONS_FILE) ? "✅" : "❌") . " transactions.json\n";
    $message .= (file_exists(COMMISSIONS_FILE) ? "✅" : "❌") . " commissions.json\n";
    $message .= (file_exists(ADMINS_FILE) ? "✅" : "❌") . " admins.json\n";
    $message .= (file_exists(COMMISSION_RULES_FILE) ? "✅" : "❌") . " commission_rules.json\n";
    $message .= (file_exists(ORDERS_FILE) ? "✅" : "❌") . " orders.json\n";
    $message .= (file_exists('smile.php') ? "✅" : "❌") . " smile.php\n\n";
    
    $message .= "🕐 Server Time: " . date('Y-m-d H:i:s');
    
    sendMessage($chatId, $message);
}

// ==============================================
## 🤖 MAIN MESSAGE PROCESSOR
// ==============================================

function processMessage($chatId, $telegramId, $text) {
    // Clean text
    $cleanText = trim($text);
    
    // Check if it's a command
    if (strpos($cleanText, '/') === 0) {
        $parts = explode(' ', $cleanText);
        $command = strtolower($parts[0]);
        $params = array_slice($parts, 1);
        
        switch ($command) {
            case '/start':
            case '/menu':
                showUserMainMenu($chatId, $telegramId);
                break;
                
            case '/help':
                showHelpMessage($chatId, $telegramId);
                break;
                
            case '/topup':
                handleTopupCode($chatId, $telegramId, $params[0] ?? '');
                break;
                
            case '/gencode':
                if (isAdmin($telegramId)) {
                    handleCustomCodeGeneration($chatId, $params);
                } else {
                    sendMessage($chatId, "❌ Admin access required!");
                }
                break;
                
            case '/gencode_nocomm':
                if (isAdmin($telegramId)) {
                    generateNoCommissionCode($chatId, $params);
                } else {
                    sendMessage($chatId, "❌ Admin access required!");
                }
                break;
                
            case '/gameinfo':
                $gameId = $params[0] ?? '';
                $zoneId = $params[1] ?? '';
                
                // If only one parameter, try to split by space
                if (empty($zoneId) && !empty($gameId)) {
                    $parts = explode(' ', $gameId, 2);
                    $gameId = $parts[0] ?? '';
                    $zoneId = $parts[1] ?? '';
                }
                
                handleGameInfoSetup($chatId, $telegramId, $gameId, $zoneId);
                break;
                
            case '/setcommission':
                if (isAdmin($telegramId)) {
                    handleSetCommissionRule($chatId, $params);
                } else {
                    sendMessage($chatId, "❌ Admin access required!");
                }
                break;
                
            case '/addadmin':
                if (isAdmin($telegramId)) {
                    handleAddAdmin($chatId, $params[0] ?? '');
                } else {
                    sendMessage($chatId, "❌ Admin access required!");
                }
                break;
                
            case '/removeadmin':
                if (isAdmin($telegramId)) {
                    handleRemoveAdmin($chatId, $params[0] ?? '');
                } else {
                    sendMessage($chatId, "❌ Admin access required!");
                }
                break;
                
                default:
                    $message = "❌ *Unknown Command*\n\n";
                    $message .= "Please use a valid command or select from the menu.\n\n";
                    $message .= "Type `/start` to see the main menu or `/help` for help.";
                    sendMessage($chatId, $message, 'Markdown');
                    break;
        }
        return;
    }
    
    // Handle button presses
    switch ($cleanText) {
        // User buttons
        case '💰 My Balance':
            showUserBalance($chatId, $telegramId);
            break;
            
        case '💎 Topup Code':
            showTopupCodeInput($chatId);
            break;
            
        case '💵 MMK Top Up':
            showMMKTopUpMenu($chatId, $telegramId);
            break;
            
        case '🇵🇭 Philippines':
            showPhilippinesPackages($chatId, $telegramId);
            break;
            
        case '🇧🇷 Brazil':
            showBrazilPackages($chatId, $telegramId);
            break;
            
        case '📜 My History':
            showUserHistory($chatId, $telegramId);
            break;
            
        case '🎮 Buy Diamonds':
            showBuyDiamondsCountrySelection($chatId, $telegramId);
            break;
            
        case '🎯 Game Info':
            showGameInfoSetup($chatId);
            break;
            
        case '📊 My Stats':
            showUserStats($chatId, $telegramId);
            break;
            
        // Admin buttons
        case '👑 Admin Panel':
            if (isAdmin($telegramId)) {
                showAdminPanel($chatId);
            } else {
                sendMessage($chatId, "❌ Admin access required!");
            }
            break;
            
        case '🔑 Generate Code':
            if (isAdmin($telegramId)) {
                showGenerateCodeMenu($chatId);
            }
            break;
            
        case '🇵🇭 PHP 100':
            if (isAdmin($telegramId)) {
                generateAndShowCode($chatId, 'php', 100, true);
            }
            break;
            
        case '🇧🇷 BR 100':
            if (isAdmin($telegramId)) {
                generateAndShowCode($chatId, 'br', 100, true);
            }
            break;
            
        case '💎 PHP Custom':
            if (isAdmin($telegramId)) {
                showCustomAmountInput($chatId, 'php');
            }
            break;
            
        case '💎 BR Custom':
            if (isAdmin($telegramId)) {
                showCustomAmountInput($chatId, 'br');
            }
            break;
            
        case '🆓 PHP No Commission':
            if (isAdmin($telegramId)) {
                $message = "🆓 *PHP NO COMMISSION CODE*\n\n";
                $message .= "Generate PHP code without commission:\n\n";
                $message .= "*Format:*\n";
                $message .= "`/gencode_nocomm php amount`\n\n";
                $message .= "*Example:*\n";
                $message .= "`/gencode_nocomm php 100`";
                sendMessage($chatId, $message);
            }
            break;
            
        case '🆓 BR No Commission':
            if (isAdmin($telegramId)) {
                $message = "🆓 *BR NO COMMISSION CODE*\n\n";
                $message .= "Generate BR code without commission:\n\n";
                $message .= "*Format:*\n";
                $message .= "`/gencode_nocomm br amount`\n\n";
                $message .= "*Example:*\n";
                $message .= "`/gencode_nocomm br 200`";
                sendMessage($chatId, $message);
            }
            break;
            
        case '📋 View Codes':
        case '📋 Active Codes':
            if (isAdmin($telegramId)) {
                showActiveCodes($chatId);
            }
            break;
            
        case '💰 Smile Balance':
            if (isAdmin($telegramId)) {
                showSmileBalance($chatId);
            }
            break;
            
        case '👥 Users':
            if (isAdmin($telegramId)) {
                showManageUsers($chatId);
            }
            break;
            
        case '📊 Stats':
            if (isAdmin($telegramId)) {
                showSystemStats($chatId);
            }
            break;
            
        case '💸 Commission Stats':
            if (isAdmin($telegramId)) {
                showCommissionStats($chatId);
            }
            break;
            
        case '⚙️ Commission Rules':
            if (isAdmin($telegramId)) {
                showCommissionRulesPanel($chatId);
            }
            break;
            
        case '👑 Manage Admins':
            if (isAdmin($telegramId)) {
                showManageAdmins($chatId);
            }
            break;
            
        case '📦 Pending Orders':
            if (isAdmin($telegramId)) {
                showPendingOrders($chatId);
            }
            break;
            
        case '⬅️ User Panel':
        case '⬅️ Back':
            showUserMainMenu($chatId, $telegramId);
            break;
            
        default:
            // Check if there's a pending purchase and user might be providing Game ID/Zone ID
            $pending = getPendingPurchase($telegramId);
            if ($pending && isset($pending['action']) && $pending['action'] == 'enter_gameid') {
                // Try to parse Game ID and Zone ID from text
                $parts = preg_split('/[\s,]+/', trim($cleanText), 2);
                if (count($parts) >= 2) {
                    $gameId = trim($parts[0]);
                    $zoneId = trim($parts[1]);
                    if (is_numeric($gameId) && is_numeric($zoneId)) {
                        handleGameInfoSetup($chatId, $telegramId, $gameId, $zoneId);
                        break;
                    }
                }
                
                // If not valid format, show error
                $message = "❌ *Invalid Format!*\n\n";
                $message .= "Please provide Game ID and Zone ID:\n\n";
                $message .= "*Format:*\n";
                $message .= "`GAMEID ZONEID`\n\n";
                $message .= "*Example:*\n";
                $message .= "`123456789 8888`\n\n";
                $message .= "Or use: `/gameinfo GAMEID ZONEID`";
                sendMessage($chatId, $message);
                break;
            }
            
            // Check if there's a pending purchase for package purchase
            if ($pending && isset($pending['package_name'])) {
                // Try to parse Game ID and Zone ID from text
                $parts = preg_split('/[\s,]+/', trim($cleanText), 2);
                if (count($parts) >= 2) {
                    $gameId = trim($parts[0]);
                    $zoneId = trim($parts[1]);
                    if (is_numeric($gameId) && is_numeric($zoneId)) {
                        handleGameInfoSetup($chatId, $telegramId, $gameId, $zoneId);
                        break;
                    }
                }
                
                // If not valid format, show error
                $message = "❌ *Invalid Format!*\n\n";
                $message .= "Please provide Game ID and Zone ID:\n\n";
                $message .= "*Format:*\n";
                $message .= "`GAMEID ZONEID`\n\n";
                $message .= "*Example:*\n";
                $message .= "`123456789 8888`\n\n";
                $message .= "Or use: `/gameinfo GAMEID ZONEID`";
                sendMessage($chatId, $message);
                break;
            }
            
            // Check if there's a pending MMK top up amount input
            if ($pending && isset($pending['action']) && $pending['action'] === 'mmk_topup_amount') {
                // User is entering amount
                if (is_numeric($cleanText) && floatval($cleanText) > 0) {
                    handleMMKTopUpAmount($chatId, $telegramId, $cleanText);
                } else {
                    sendMessage($chatId, "❌ Invalid amount. Please enter a valid number (e.g., 10000)");
                }
                break;
            }
            
            // Show friendly message for unknown button/text
            $message = "❓ *I didn't understand that*\n\n";
            $message .= "💡 *Try these options:*\n";
            $message .= "• Use the buttons below\n";
            $message .= "• Type `/start` for main menu\n";
            $message .= "• Type `/help` for commands\n\n";
            $message .= "Or select an option from the menu:";
            sendMessage($chatId, $message, 'Markdown');
            sleep(1);
            showUserMainMenu($chatId, $telegramId);
            break;
    }
}

// Handle Callback Queries
function processCallbackQuery($callbackQuery) {
    global $BOT_TOKEN;
    
    $callbackId = $callbackQuery['id'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $telegramId = $callbackQuery['from']['id'];
    $data = $callbackQuery['data'];
    
    // Answer callback first
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/answerCallbackQuery";
    $answerData = ['callback_query_id' => $callbackId];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $answerData
    ]);
    curl_exec($ch);
    curl_close($ch);
    
    // Process callback data
    switch (true) {
        case $data == 'buy_diamonds_php':
            askGameIdChoice($chatId, $telegramId, 'php');
            break;
            
        case $data == 'buy_diamonds_br':
            askGameIdChoice($chatId, $telegramId, 'br');
            break;
            
        case $data == 'buy_diamonds_back':
            showBuyDiamondsCountrySelection($chatId, $telegramId);
            break;
            
        case strpos($data, 'use_my_gameid_') === 0:
            $country = str_replace('use_my_gameid_', '', $data);
            if (strpos($country, 'mmk_') === 0) {
                $country = str_replace('mmk_', '', $country);
                checkGameIdAndShowPackages($chatId, $telegramId, $country, true); // true = use MMK
            } else {
                checkGameIdAndShowPackages($chatId, $telegramId, $country, false);
            }
            break;
            
        case strpos($data, 'enter_gameid_') === 0:
            $country = str_replace('enter_gameid_', '', $data);
            if (strpos($country, 'mmk_') === 0) {
                $country = str_replace('mmk_', '', $country);
                handleEnterGameIdForPurchase($chatId, $telegramId, $country, true); // true = use MMK
            } else {
                handleEnterGameIdForPurchase($chatId, $telegramId, $country, false);
            }
            break;
            
        case strpos($data, 'buy_php_') === 0:
            $packageName = str_replace('buy_php_', '', $data);
            handlePurchase($chatId, $telegramId, 'php', $packageName);
            break;
            
        case strpos($data, 'buy_br_') === 0:
            $packageName = str_replace('buy_br_', '', $data);
            handlePurchase($chatId, $telegramId, 'br', $packageName);
            break;
            
        case $data == 'confirm_order':
            confirmOrder($chatId, $telegramId);
            break;
            
        case $data == 'cancel_order':
            cancelOrder($chatId, $telegramId);
            break;
            
        case strpos($data, 'mmk_topup_') === 0:
            $method = str_replace('mmk_topup_', '', $data);
            if (in_array($method, ['wave', 'kpay'])) {
                handleMMKTopUpMethod($chatId, $telegramId, $method);
            }
            break;
            
        case $data == 'mmk_topup_back':
            showMMKTopUpMenu($chatId, $telegramId);
            break;
            
        case $data == 'back_to_main':
            showUserMainMenu($chatId, $telegramId);
            break;
            
        case $data == 'set_game_info':
            showGameInfoSetup($chatId);
            break;
            
        case $data == 'refresh_smile_balance':
            showSmileBalance($chatId);
            break;
            
        case $data == 'process_pending_orders':
            if (isAdmin($telegramId)) {
                showPendingOrders($chatId);
            }
            break;
            
        case strpos($data, 'process_order_') === 0:
            if (isAdmin($telegramId)) {
                $orderId = str_replace('process_order_', '', $data);
                processOrderCallback($chatId, $orderId);
            }
            break;
    }
}// ==============================================// 📡 GET UPDATES (POLLING)
// ==============================================

$lastUpdateId = 0;
$updateIdFile = __DIR__ . '/last_update_id.txt';

// Load last update ID
if (file_exists($updateIdFile)) {
    $lastUpdateId = (int)file_get_contents($updateIdFile);
}

function getUpdates($offset = 0) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/getUpdates?offset={$offset}&timeout=30";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['ok' => false, 'error' => 'HTTP ' . $httpCode];
    }
    
    return json_decode($response, true);
}

function saveLastUpdateId($updateId) {
    global $updateIdFile;
    file_put_contents($updateIdFile, $updateId);
}

// ==============================================
## 🚀 START BOT
// ==============================================

// Check if running as webhook (has input) or polling mode
$input = @file_get_contents('php://input');

if (!empty($input)) {
    // Webhook mode
    $update = json_decode($input, true);
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $telegramId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        processMessage($chatId, $telegramId, $text);
        
    } elseif (isset($update['callback_query'])) {
        processCallbackQuery($update['callback_query']);
    }
    
} else {
    // Check if running from CLI or direct access
    if (php_sapi_name() === 'cli' || !isset($_SERVER['REQUEST_METHOD'])) {
        // Polling mode - run continuously
        echo "🤖 Bot Starting in Polling Mode...\n";
        echo "Token: " . substr($BOT_TOKEN, 0, 10) . "...\n";
        echo "Admins: " . implode(', ', $ADMINS) . "\n\n";
        
        // Create PID file for admin panel status check
        $pidFile = __DIR__ . '/bot.pid';
        $pid = getmypid();
        file_put_contents($pidFile, $pid);
        
        // Register shutdown function to clean up PID file
        register_shutdown_function(function() use ($pidFile) {
            if (file_exists($pidFile)) {
                @unlink($pidFile);
            }
        });
        
        // Log file
        $logFile = __DIR__ . '/bot_log.txt';
        $log = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            echo $logMessage;
        };
        
        $log("🔄 Starting polling loop... (PID: {$pid})");
        
        while (true) {
            try {
                $updates = getUpdates($lastUpdateId);
                
                if ($updates['ok']) {
                    // Log if there are results
                    if (!empty($updates['result'])) {
                        $log("📥 Received " . count($updates['result']) . " update(s)");
                    }
                    
                    // Process all updates first, then save the last update ID once
                    $maxUpdateId = $lastUpdateId;
                    
                    foreach ($updates['result'] as $update) {
                        // Track the highest update ID
                        if (isset($update['update_id']) && $update['update_id'] >= $maxUpdateId) {
                            $maxUpdateId = $update['update_id'] + 1;
                        }
                        
                        if (isset($update['message'])) {
                            $message = $update['message'];
                            $chatId = $message['chat']['id'];
                            $telegramId = $message['from']['id'];
                            $text = $message['text'] ?? '';
                            
                            // Check for photo (MMK top up screenshot)
                            if (isset($message['photo']) && !empty($message['photo'])) {
                                $photos = $message['photo'];
                                $photoFileId = end($photos)['file_id']; // Get highest quality
                                
                                $pending = getPendingPurchase($telegramId);
                                if ($pending && isset($pending['action']) && $pending['action'] === 'mmk_topup_screenshot') {
                                    $log("📸 Photo from {$telegramId} (MMK Top Up)");
                                    handleMMKTopUpScreenshot($chatId, $telegramId, $photoFileId);
                                } else {
                                    sendMessage($chatId, "❌ Please complete the MMK Top Up process first. Use /start to begin.");
                                }
                            } else {
                                $log("📨 Message from {$telegramId}: {$text}");
                                processMessage($chatId, $telegramId, $text);
                            }
                            
                        } elseif (isset($update['callback_query'])) {
                            $log("🔘 Callback query from {$update['callback_query']['from']['id']}");
                            processCallbackQuery($update['callback_query']);
                        }
                    }
                    
                    // Save the last update ID ONCE after processing all updates
                    if ($maxUpdateId > $lastUpdateId) {
                        $lastUpdateId = $maxUpdateId;
                        saveLastUpdateId($lastUpdateId);
                    }
                    
                } elseif (isset($updates['error_code'])) {
                    $log("❌ Telegram API Error: " . ($updates['description'] ?? 'Unknown error') . " (Code: " . ($updates['error_code'] ?? 'N/A') . ")");
                    sleep(5);
                } elseif (!$updates['ok']) {
                    $log("⚠️ getUpdates returned ok=false. Response: " . json_encode($updates));
                    sleep(2);
                }
                
                sleep(1);
                
            } catch (Exception $e) {
                $log("❌ Exception: " . $e->getMessage());
                sleep(5);
            }
        }
        
    } else {
        // Direct web access - show status
        header('Content-Type: text/plain');
        echo "🤖 Mobile Legends Bot - Complete System with Commission & Auto Order\n";
        echo "=====================================================================\n\n";
        echo "Status: ✅ Online\n";
        echo "Mode: " . (empty($input) ? "Polling (CLI)" : "Webhook") . "\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
        
        $users = loadUsers();
        $codes = loadTopupCodes();
        $stats = getCommissionStats();
        $admins = loadAdmins();
        $rules = loadCommissionRules();
        
        echo "👥 Users Registered: " . count($users) . "\n";
        echo "👑 Admins: " . count($admins) . "\n";
        echo "🔑 Topup Codes: " . count($codes) . "\n";
        echo "💸 Total Commission: " . number_format($stats['total_commission'], 2) . "\n\n";
        
        echo "⚙️ Commission Rules:\n";
        echo "🇵🇭 PHP: " . count($rules['php'] ?? []) . " rules\n";
        echo "🇧🇷 BR: " . count($rules['br'] ?? []) . " rules\n\n";
        
        echo "📁 Files:\n";
        echo file_exists(USERS_FILE) ? "✅ users.json\n" : "❌ users.json\n";
        echo file_exists(CODES_FILE) ? "✅ topup_codes.json\n" : "❌ topup_codes.json\n";
        echo file_exists(PRODUCTS_FILE) ? "✅ products.json\n" : "❌ products.json\n";
        echo file_exists(COMMISSIONS_FILE) ? "✅ commissions.json\n" : "❌ commissions.json\n";
        echo file_exists(ADMINS_FILE) ? "✅ admins.json\n" : "❌ admins.json\n";
        echo file_exists(COMMISSION_RULES_FILE) ? "✅ commission_rules.json\n" : "❌ commission_rules.json\n";
        echo file_exists(ORDERS_FILE) ? "✅ orders.json\n" : "❌ orders.json\n";
        echo file_exists('../smile.php') ? "✅ smile.php\n" : "❌ smile.php\n";
    }
}