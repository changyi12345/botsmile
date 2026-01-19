<?php

// ==============================================
// 📁 DATABASE FUNCTIONS
// ==============================================

// Define constants if not defined
if (!defined('USERS_FILE')) define('USERS_FILE', __DIR__ . '/../../users.json');
if (!defined('CODES_FILE')) define('CODES_FILE', __DIR__ . '/../../topup_codes.json');
if (!defined('TRANSACTIONS_FILE')) define('TRANSACTIONS_FILE', __DIR__ . '/../../transactions.json');
if (!defined('COMMISSIONS_FILE')) define('COMMISSIONS_FILE', __DIR__ . '/../../commissions.json');
if (!defined('ADMINS_FILE')) define('ADMINS_FILE', __DIR__ . '/../../admin/assets/admins.json');
if (!defined('COMMISSION_RULES_FILE')) define('COMMISSION_RULES_FILE', __DIR__ . '/../../commission_rules.json');
if (!defined('ORDERS_FILE')) define('ORDERS_FILE', __DIR__ . '/../../orders.json');
if (!defined('PRODUCTS_FILE')) define('PRODUCTS_FILE', __DIR__ . '/../../products.json');
if (!defined('PENDING_PURCHASES_FILE')) define('PENDING_PURCHASES_FILE', __DIR__ . '/../pending_purchases.json');

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
    global $ADMINS; // Fallback admins from config
    
    if (!file_exists(ADMINS_FILE)) {
        // Save default admin if available
        $admins = [];
        if (!empty($ADMINS)) {
            $admins = [['telegram_id' => $ADMINS[0], 'added_at' => date('Y-m-d H:i:s')]];
        }
        
        // Ensure directory exists
        $dir = dirname(ADMINS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
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
        'first_name' => '',
        'balance_php' => 0.00,
        'balance_br' => 0.00,
        'balance_mmk' => 0.00,
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

// Check if user exists
function userExists($telegramId) {
    $users = loadUsers();
    foreach ($users as $user) {
        if ($user['telegram_id'] == $telegramId) {
            return true;
        }
    }
    return false;
}

// Register new user
function registerUser($telegramId, $username, $firstName) {
    if (userExists($telegramId)) {
        return getUser($telegramId);
    }
    
    $users = loadUsers();
    
    $newUser = [
        'telegram_id' => $telegramId,
        'username' => $username,
        'first_name' => $firstName,
        'balance_php' => 0.00,
        'balance_br' => 0.00,
        'balance_mmk' => 0.00,
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
    // Ensure SmileOne class is loaded
    $smileFile = __DIR__ . '/../../smile.php';
    if (!class_exists('SmileOne')) {
        if (file_exists($smileFile)) {
            require_once $smileFile;
        } else {
            error_log("❌ SmileOne file not found: " . $smileFile);
            return ['success' => false, 'message' => 'SmileOne class file not found'];
        }
    }
    
    // Double-check class exists after require
    if (!class_exists('SmileOne')) {
        error_log("❌ SmileOne class not found after require. File: " . $smileFile);
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
                // Get country from order (supports pubg_br, pubg_php, hok_br, hok_php)
                $orderCountry = $order['country'] ?? 'br';
                
                // Log order processing with cookies and user agent
                error_log("🔍 Processing order {$orderId} for country: {$orderCountry}");
                error_log("📦 Product: {$order['product_name']}, GameID: {$order['game_id']}, ZoneID: {$order['zone_id']}");
                
                // Create SmileOne instance with country (loads cookies and user agent automatically)
                $smile = new SmileOne($orderCountry);
                
                // Verify cookies and user agent are loaded
                $reflection = new ReflectionClass($smile);
                $cookiesProperty = $reflection->getProperty('cookies');
                $cookiesProperty->setAccessible(true);
                $loadedCookies = $cookiesProperty->getValue($smile);
                
                error_log("🍪 Cookies loaded: " . count($loadedCookies) . " cookie(s)");
                
                // Use recharge method which automatically uses cookies and user agent
                // This connects to SmileOne website using cookies and user agent
                // For Pubg: game_id is Player ID (no zoneId needed, will be empty)
                // For HoK: game_id is UID (no zoneId needed, will be empty)
                // For MLBB: game_id and zone_id are both required
                $gameId = $order['game_id'] ?? '';
                $zoneId = $order['zone_id'] ?? '';
                
                // For Pubg and HoK, zoneId should be empty (not used)
                $isPubg = (strpos($orderCountry, 'pubg') === 0);
                $isHoK = (strpos($orderCountry, 'hok') === 0);
                $isMagicChess = (strpos($orderCountry, 'magicchessgogo') === 0);
                if ($isPubg || $isHoK) {
                    $zoneId = ''; // Pubg: Player ID only, HoK: UID only
                }
                
                if (is_callable($log ?? null)) {
                    $gameType = $isPubg ? 'Player ID' : ($isHoK ? 'UID' : ($isMagicChess ? 'UID' : 'Game ID'));
                    $log("🔄 Calling SmileOne recharge API with cookies and user agent");
                    $log("   {$gameType}: {$gameId}, ZoneID: " . ($zoneId ?: 'N/A (not used)') . ", Product: {$order['product_name']}, Country: {$orderCountry}");
                }
                
                $result = $smile->recharge(
                    $gameId, 
                    $zoneId, 
                    $order['product_name'],
                    'bot',
                    $orderCountry
                );
                
                if (is_callable($log ?? null)) {
                    if ($result && ($result['success'] ?? false)) {
                        $log("✅ SmileOne recharge successful via API (cookies & user agent)");
                    } else {
                        $log("❌ SmileOne recharge failed: " . ($smile->getLastError() ?? 'Unknown error'));
                    }
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
        error_log("⚠️ Products file not found: " . PRODUCTS_FILE);
        return [];
    }
    
    $data = @file_get_contents(PRODUCTS_FILE);
    if ($data === false) {
        error_log("❌ Failed to read products file: " . PRODUCTS_FILE);
        return [];
    }
    
    $products = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("❌ JSON decode error in products file: " . json_last_error_msg());
        return [];
    }
    
    if (!is_array($products)) {
        error_log("⚠️ Products data is not an array");
        return [];
    }
    
    return $products;
}

// Store pending purchase (for confirm/cancel flow)
function savePendingPurchase($telegramId, $purchaseData) {
    $file = PENDING_PURCHASES_FILE;
    $purchases = [];
    
    if (file_exists($file)) {
        $data = file_get_contents($file);
        $purchases = json_decode($data, true) ?: [];
    }
    
    $purchases[$telegramId] = $purchaseData;
    file_put_contents($file, json_encode($purchases, JSON_PRETTY_PRINT));
}

function getPendingPurchase($telegramId) {
    $file = PENDING_PURCHASES_FILE;
    
    if (!file_exists($file)) {
        return null;
    }
    
    $data = file_get_contents($file);
    $purchases = json_decode($data, true) ?: [];
    
    return $purchases[$telegramId] ?? null;
}

function clearPendingPurchase($telegramId) {
    $file = PENDING_PURCHASES_FILE;
    
    if (!file_exists($file)) {
        return;
    }
    
    $data = file_get_contents($file);
    $purchases = json_decode($data, true) ?: [];
    
    unset($purchases[$telegramId]);
    file_put_contents($file, json_encode($purchases, JSON_PRETTY_PRINT));
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
