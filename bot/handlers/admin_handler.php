<?php
// Admin Handler - Admin Panel & Management Logic

// Load dependencies if not already loaded
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/telegram_api.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../smile.php';

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
        $safeProductName = escapeMarkdown($order['product_name']);
        $message .= "{$flag} Package: {$safeProductName}\n";
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
    
    $safeMessage = escapeMarkdown($result['message']);
    
    if ($result['success']) {
        sendMessage($chatId, "✅ *Order Processed Successfully!*\n\nOrder ID: `{$orderId}`\n\n" . $safeMessage);
    } else {
        sendMessage($chatId, "❌ *Order Processing Failed!*\n\nOrder ID: `{$orderId}`\n\n" . $safeMessage);
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
    
    // Ensure SmileOne class is loaded
    if (!class_exists('SmileOne')) {
        $smileFile = __DIR__ . '/../../smile.php';
        if (file_exists($smileFile)) {
            require_once $smileFile;
        } else {
            error_log("❌ SmileOne file not found: " . $smileFile);
            sendMessage($chatId, "❌ SmileOne class file not found. Please contact admin.");
            return;
        }
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
    $totalBalanceMMK = 0;
    $totalCommission = 0;
    
    foreach (array_slice($users, 0, 10) as $index => $user) {
        $message .= "*User #" . ($index + 1) . "*\n";
        $message .= "🆔 ID: `{$user['telegram_id']}`\n";
        $message .= "🎮 GameID: `{$user['game_id']}`\n";
        $message .= "🏠 ZoneID: `{$user['zone_id']}`\n";
        $message .= "🇵🇭 PHP: " . number_format($user['balance_php'] ?? 0, 2) . "\n";
        $message .= "🇧🇷 BR: " . number_format($user['balance_br'] ?? 0, 2) . "\n";
        $message .= "🇲🇲 MMK: " . number_format($user['balance_mmk'] ?? 0, 2) . "\n";
        $message .= "📉 Commission Paid: " . number_format($user['total_commission'] ?? 0, 2) . "\n";
        $message .= "🔄 Topups: " . ($user['total_topups'] ?? 0) . "\n";
        $message .= "🛒 Orders: " . ($user['total_orders'] ?? 0) . "\n";
        $message .= "🕐 Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
        
        $totalBalancePHP += $user['balance_php'] ?? 0;
        $totalBalanceBR += $user['balance_br'] ?? 0;
        $totalBalanceMMK += $user['balance_mmk'] ?? 0;
        $totalCommission += $user['total_commission'] ?? 0;
    }
    
    if (count($users) > 10) {
        $message .= "➕ " . (count($users) - 10) . " more users...\n\n";
    }
    
    $message .= "📊 *System Totals:*\n";
    $message .= "🇵🇭 Total PHP: " . number_format($totalBalancePHP, 2) . "\n";
    $message .= "🇧🇷 Total BR: " . number_format($totalBalanceBR, 2) . "\n";
    $message .= "🇲🇲 Total MMK: " . number_format($totalBalanceMMK, 2) . "\n";
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
    $totalBalanceMMK = 0;
    $activeUsers = 0;
    $totalTopups = 0;
    $totalCommission = 0;
    $totalOrders = 0;
    
    foreach ($users as $user) {
        $totalBalancePHP += $user['balance_php'] ?? 0;
        $totalBalanceBR += $user['balance_br'] ?? 0;
        $totalBalanceMMK += $user['balance_mmk'] ?? 0;
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
    
    $message .= "👥 *Users*\n";
    $message .= "Total Users: {$totalUsers}\n";
    $message .= "Active Users: {$activeUsers}\n\n";
    
    $message .= "💰 *Balances*\n";
    $message .= "Total PHP: " . number_format($totalBalancePHP, 2) . "\n";
    $message .= "Total BR: " . number_format($totalBalanceBR, 2) . "\n";
    $message .= "Total MMK: " . number_format($totalBalanceMMK, 2) . "\n\n";
    
    $message .= "🔄 *Transactions*\n";
    $message .= "Total Topups: {$totalTopups}\n";
    $message .= "Total Orders: {$totalOrders}\n";
    $message .= "Pending Orders: {$pendingOrders}\n";
    $message .= "Completed Orders: {$completedOrders}\n\n";
    
    $message .= "🔑 *Codes*\n";
    $message .= "Total Codes: {$totalCodes}\n";
    $message .= "Active Codes: {$activeCodes}\n";
    $message .= "Used Codes: {$usedCodes}\n\n";
    
    $message .= "🕐 " . date('Y-m-d H:i:s');
    
    sendMessage($chatId, $message);
}
