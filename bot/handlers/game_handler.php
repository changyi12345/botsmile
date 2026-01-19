<?php
// Game Handler - User System & Game Logic

// Load dependencies if not already loaded
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/telegram_api.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../smile.php';

// Main Menu
function showUserMainMenu($chatId, $telegramId, $messageIdToEdit = null) {
    $user = getUser($telegramId);
    
    // Get user balance for preview
    $balancePHP = $user['balance_php'] ?? 0;
    $balanceBR = $user['balance_br'] ?? 0;
    $balanceMMK = $user['balance_mmk'] ?? 0;
    $totalBalance = $balancePHP + $balanceBR;
    
    // Create keyboard layout
    $keyboard = [
        'keyboard' => [
            [['text' => '🎮 Ke Games']],
            [['text' => '💵 MMK Top Up'], ['text' => '💎 Topup Code']],
            [['text' => '📜 My History'], ['text' => '📊 My Stats']],
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
    
    // Game Info removed - no longer saving IDs
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Menu Section with better formatting
    $message .= "📋 *Main Menu*\n\n";
    $message .= "💰 *My Balance* - View detailed balance\n";
    $message .= "💎 *Topup Code* - Redeem topup codes\n";
    $message .= "💵 *MMK Top Up* - Top up with KPay/Wave\n";
    $message .= "🎮 *Ke Games* - Choose game to recharge\n";
    $message .= "📜 *My History* - Transaction history\n";
    $message .= "📊 *My Stats* - View statistics\n";
    
    if (isAdmin($telegramId)) {
        $message .= "👑 *Admin Panel* - Admin controls\n";
    }
    
    $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "💡 *Tip:* Use the buttons below or type commands like `/topup CODE`\n\n";
    $message .= "🆘 Need help? Type `/help`";
    
    // For main menu, we usually want to send a new message because of the custom keyboard
    // But if we are navigating back via inline button, we might want to edit the previous message 
    // to "Returned to main menu" or similar, AND send the new menu with keyboard.
    // However, Telegram doesn't support editing text message to add custom keyboard (only inline keyboard).
    // So for Main Menu with custom keyboard, we MUST use sendMessage.
    // But we can delete the previous message if we want to be clean, but we don't have deleteMessage function yet.
    // For now, let's just use sendMessage as before for Main Menu because of the custom keyboard requirement.
    
    sendMessage($chatId, $message, 'Markdown', $keyboard);
}

// Show Ke Games Menu
function showKeGamesMenu($chatId, $messageIdToEdit = null) {
    $message = "🎮 *Choose Your Game to Recharge*";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🪂 PUBG UC & Items', 'callback_data' => 'select_pubg_region']
            ],
            [
                ['text' => '⚔️ Mobile Legends', 'callback_data' => 'buy_diamonds_back']
            ],
            [
                ['text' => '🔥 Free Fire', 'callback_data' => 'coming_soon_ff'] // Placeholder as requested "Game တွေ အားလုံ" style
            ],
            [
                ['text' => '🛡️ Honor of Kings', 'callback_data' => 'select_hok_region']
            ],
            [
                ['text' => '♟️ Magic Chess: Go Go', 'callback_data' => 'magicchessgogo_br'] // Defaults to BR/PHP selection or direct
            ],
            [
                ['text' => '🎮 More Games', 'callback_data' => 'more_games']
            ]
        ]
    ];
    
    // Note: Mobile Legends usually has region selection. 
    // buy_diamonds_php actually triggers askGameIdChoice for PHP. 
    // Let's check update_handler.php. 'buy_diamonds_php' -> askGameIdChoice(..., 'php'). 
    // But we want region selection first? 
    // showBuyDiamondsCountrySelection uses 'buy_diamonds_br' and 'buy_diamonds_php'.
    // So for MLBB, we should probably go to 'buy_diamonds_back' which triggers showBuyDiamondsCountrySelection.
    
    // Correction for MLBB:
    $keyboard['inline_keyboard'][1][0]['callback_data'] = 'buy_diamonds_back'; 
    
    // Correction for Magic Chess:
    // magicchessgogo_br -> askMagicChessGoGoUID(..., 'magicchessgogo_br').
    // We probably want region selection.
    // showMagicChessGoGoRegionSelection uses 'magicchessgogo_back' to show selection.
    $keyboard['inline_keyboard'][4][0]['callback_data'] = 'magicchessgogo_back';
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Help Message
function showHelpMessage($chatId, $telegramId) {
    $message = "🆘 *Help & Commands*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "📋 *Available Commands:*\n\n";
    $message .= "`/start` or `/menu` - Show main menu\n";
    $message .= "`/topup CODE` - Redeem topup code\n";
    // Game Info command removed
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
    // Game Info removed - no longer saving IDs
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

// Check Game ID and Show Packages
function checkGameIdAndShowPackages($chatId, $telegramId, $country, $useMMK = false, $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $user = getUser($telegramId);
    
    // Use provided Game ID or user's saved Game ID
    $finalGameId = $gameId ?? ($user['game_id'] ?? '');
    $finalZoneId = $zoneId ?? ($user['zone_id'] ?? '');
    
    if (empty($finalGameId) || empty($finalZoneId)) {
        // ID missing, ask for it
        askGameIdChoice($chatId, $telegramId, $country, $useMMK, $messageIdToEdit);
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
                    $errorMsg = escapeMarkdown($nameCheck['error'] ?? 'Unknown error');
                    $message = "❌ *GAME ID VERIFICATION FAILED!*\n\n";
                    $message .= "Could not verify your Game ID and Zone ID.\n";
                    $message .= "Error: " . $errorMsg . "\n\n";
                    $message .= "Please check and try again:\n";
                    $message .= "🎮 GameID: `{$finalGameId}`\n";
                    $message .= "🏠 ZoneID: `{$finalZoneId}`\n\n";
                    $message .= "Format: `GAMEID ZONEID`";
                    if ($messageIdToEdit) {
                        editMessageText($chatId, $messageIdToEdit, $message);
                    } else {
                        sendMessage($chatId, $message);
                    }
                    return;
                }
                
                // Game ID verified successfully
                $inGameName = escapeMarkdown($nameCheck['username']);
                $message = "✅ *GAME ID VERIFIED!*\n\n";
                $message .= "👤 In-Game Name: *{$inGameName}*\n";
                $message .= "🎮 GameID: `{$finalGameId}`\n";
                $message .= "🏠 ZoneID: `{$finalZoneId}`\n\n";
                $message .= "Loading packages...";
                if ($messageIdToEdit) {
                    editMessageText($chatId, $messageIdToEdit, $message);
                } else {
                    sendMessage($chatId, $message);
                }
            }
        }
    }
    
    // Show packages with the Game ID
    if ($useMMK) {
        if ($country == 'php') {
            showMMKPhilippinesPackages($chatId, $telegramId, $finalGameId, $finalZoneId, $messageIdToEdit);
        } else {
            showMMKBrazilPackages($chatId, $telegramId, $finalGameId, $finalZoneId, $messageIdToEdit);
        }
    } else {
        if ($country == 'php') {
            showPhilippinesPackages($chatId, $telegramId, $finalGameId, $finalZoneId, $messageIdToEdit);
        } else {
            showBrazilPackages($chatId, $telegramId, $finalGameId, $finalZoneId, $messageIdToEdit);
        }
    }
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
        // Game Info no longer saved - IDs only used for current purchase
        
        // Check if this is MMK purchase
        $useMMK = $pending['use_mmk'] ?? false;
        
        // Save verified ID to pending purchase for session before showing packages
        // This ensures subsequent package selection can find the ID
        $pending['game_id'] = $gameId;
        $pending['zone_id'] = $zoneId;
        // Keep action or clear it? Better to change action to something else to avoid re-entry loop
        $pending['action'] = 'browsing_packages'; 
        savePendingPurchase($telegramId, $pending);
        
        // Check Game ID and show packages
        checkGameIdAndShowPackages($chatId, $telegramId, $pending['country'], $useMMK, $gameId, $zoneId);
        
        // Do NOT clear pending purchase here! It's needed for the next step (package selection)
        // clearPendingPurchase($telegramId); 
        return;
    }
    
    // Check if there's a pending purchase - if so, continue with purchase flow
    if ($pending && isset($pending['package_name'])) {
        // Game Info no longer saved - proceed with purchase
        $useMMK = $pending['use_mmk'] ?? false;
        
        // Save ID to pending so handlePurchase can find it
        $pending['game_id'] = $gameId;
        $pending['zone_id'] = $zoneId;
        savePendingPurchase($telegramId, $pending);
        
        handlePurchase($chatId, $telegramId, $pending['country'], $pending['package_name'], $useMMK);
        return;
    }
    
    // Game Info no longer saved - just confirm
    $message = "✅ *GAME ID RECEIVED!*\n\n";
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

// Handle Topup Code
function handleTopupCode($chatId, $telegramId, $code) {
    $result = redeemCode($telegramId, $code);
    
    if ($result['success']) {
        $message = "✅ *TOPUP SUCCESSFUL!*\n";
        $message .= "═══════════════════════\n\n";
        $message .= "💰 *Details:*\n";
        $message .= "💎 Amount: " . number_format($result['amount'], 2) . "\n";
        $message .= "📉 Commission: " . number_format($result['commission'], 2) . " (" . ($result['commission_rate'] * 100) . "%)\n";
        $message .= "💵 Added to Balance: " . number_format($result['final_amount'], 2) . "\n\n";
        
        $message .= "💰 *New Balance:*\n";
        $message .= "🇵🇭 PHP: " . number_format($result['new_balance_php'], 2) . "\n";
        $message .= "🇧🇷 BR: " . number_format($result['new_balance_br'], 2) . "\n\n";
        
        $message .= "✨ Thank you for topping up!";
        
        sendMessage($chatId, $message);
    } else {
        $error = escapeMarkdown($result['error'] ?? 'Unknown error');
        sendMessage($chatId, "❌ *TOPUP FAILED!*\n\nError: {$error}\n\nPlease check the code and try again.");
    }
}

// Show MMK Top Up Menu
function showMMKTopUpMenu($chatId, $telegramId, $messageIdToEdit = null) {
    $message = "💵 *MMK TOP UP*\n\n";
    $message .= "Please select payment method:\n\n";
    $message .= "Exchange Rates:\n";
    $message .= "🇵🇭 PHP 1 = 38.2 MMK\n";
    $message .= "🇧🇷 BRL 1 = 85.5 MMK";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '💙 KPay', 'callback_data' => 'mmk_topup_kpay'],
                ['text' => '💛 Wave Money', 'callback_data' => 'mmk_topup_wave']
            ],
            [
                ['text' => '⬅️ Back', 'callback_data' => 'back_to_main']
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Handle MMK Top Up Method Selection
function handleMMKTopUpMethod($chatId, $telegramId, $method, $messageIdToEdit = null) {
    $methodName = ($method == 'kpay') ? 'KPay (KBZ Pay)' : 'Wave Money';
    $paymentPhone = ($method == 'kpay') ? '09XXXXXXXXX' : '09XXXXXXXXX'; // Replace with real phone numbers
    
    $message = "💵 *MMK TOP UP - {$methodName}*\n\n";
    $message .= "Please enter the amount you want to top up (in MMK):\n\n";
    $message .= "*Minimum:* 1,000 MMK\n";
    $message .= "*Maximum:* 1,000,000 MMK\n\n";
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
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown');
    } else {
        sendMessage($chatId, $message, 'Markdown');
    }
}

// Show MMK Packages for Philippines
function showMMKPhilippinesPackages($chatId, $telegramId, $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $products = loadProducts();
    $phProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == 'php') {
            $phProducts[] = $product;
        }
    }
    
    // If no products found, show error message
    if (empty($phProducts)) {
        sendMessage($chatId, "❌ *No Philippines packages available*\n\nPlease contact admin to add packages.", 'Markdown');
        return;
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
                'callback_data' => "p_ph_{$name}_m"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'mmk_topup_back'
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show MMK Packages for Brazil
function showMMKBrazilPackages($chatId, $telegramId, $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $products = loadProducts();
    $brProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == 'br') {
            $brProducts[] = $product;
        }
    }
    
    // If no products found, show error message
    if (empty($brProducts)) {
        sendMessage($chatId, "❌ *No Brazil packages available*\n\nPlease contact admin to add packages.", 'Markdown');
        return;
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
    
    $message .= "Select package to buy:";
    
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
                'callback_data' => "p_br_{$name}_m"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'mmk_topup_back'
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
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
    $file = __DIR__ . '/../../pending_mmk_topups.json';
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
    global $BOT_TOKEN;
    
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
            $safeDetails = escapeMarkdown($tx['details']);
            $message .= "📉 " . $safeDetails . "\n";
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
function showBuyDiamondsCountrySelection($chatId, $telegramId, $messageIdToEdit = null) {
    $message = "🎮 *MLBB (Mobile Legends)*\n\n";
    $message .= "Please select region:\n\n";
    $message .= "🇧🇷 *Brazil (BRL)* - MLBB Diamond packages\n";
    $message .= "🇵🇭 *Philippines (PHP)* - MLBB Diamond packages";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '🇧🇷 Brazil (BRL)',
                    'callback_data' => 'buy_diamonds_br'
                ],
                [
                    'text' => '🇵🇭 Philippines (PHP)',
                    'callback_data' => 'buy_diamonds_php'
                ]
            ],
            [
                [
                    'text' => '⬅️ Back',
                    'callback_data' => 'back_to_games'
                ]
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Ask Game ID Choice After Country Selection
function askGameIdChoice($chatId, $telegramId, $country, $useMMK = false, $messageIdToEdit = null) {
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
    
    // Game Info no longer saved - always ask for Game ID
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
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Handle Enter Different Game ID
function handleEnterGameIdForPurchase($chatId, $telegramId, $country, $useMMK = false, $messageIdToEdit = null) {
    // Handle pubg_br, pubg_php, hok_br, hok_php, magicchessgogo_br, magicchessgogo_php
    $backCallback = 'buy_diamonds_back'; // Default back for MLBB
    
    if (strpos($country, 'pubg_br') === 0) {
        $flag = '🎮';
        $countryName = 'Pubg UC (Brazil)';
        $backCallback = 'select_pubg_region';
    } elseif (strpos($country, 'pubg_php') === 0) {
        $flag = '🎮';
        $countryName = 'Pubg UC (Philippines)';
        $backCallback = 'select_pubg_region';
    } elseif (strpos($country, 'hok_br') === 0) {
        $flag = '⚔️';
        $countryName = 'HoK (Brazil)';
        $backCallback = 'select_hok_region';
    } elseif (strpos($country, 'hok_php') === 0) {
        $flag = '⚔️';
        $countryName = 'HoK (Philippines)';
        $backCallback = 'select_hok_region';
    } elseif (strpos($country, 'magicchessgogo_br') === 0) {
        $flag = '♟️';
        $countryName = 'Magic Chess GoGo (Brazil)';
        $backCallback = 'magicchessgogo_back';
    } elseif (strpos($country, 'magicchessgogo_php') === 0) {
        $flag = '♟️';
        $countryName = 'Magic Chess GoGo (Philippines)';
        $backCallback = 'magicchessgogo_back';
    } else {
        $flag = ($country == 'php') ? '🇵🇭' : '🇧🇷';
        $countryName = ($country == 'php') ? 'Philippines' : 'Brazil';
    }
    
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
    $message .= "`123456789 8888`";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '⬅️ Back', 'callback_data' => $backCallback]
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Ask for Pubg Player ID (step-by-step like MLBB)
function askPubgPlayerId($chatId, $telegramId, $country = 'pubg_br', $messageIdToEdit = null) {
    $flag = ($country == 'pubg_br') ? '🇧🇷' : '🇵🇭';
    $regionName = ($country == 'pubg_br') ? 'Brazil' : 'Philippines';
    
    // Store pending action to wait for Player ID
    $pendingPurchase = [
        'action' => 'waiting_pubg_player_id',
        'country' => $country,
        'timestamp' => time()
    ];
    savePendingPurchase($telegramId, $pendingPurchase);
    
    $message = "🎮 *PUBG UC - {$flag} {$regionName}*\n\n";
    $message .= "Please enter your Player ID:\n\n";
    $message .= "*Format:*\n";
    $message .= "`PLAYERID`\n\n";
    $message .= "*Example:*\n";
    $message .= "`123456789`\n\n";
    $message .= "Or use command:\n";
    $message .= "`/playerid PLAYERID`";
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message);
    } else {
        sendMessage($chatId, $message);
    }
}

// Ask for HoK UID (step-by-step like MLBB)
function askHoKUID($chatId, $telegramId, $country = 'hok_br', $messageIdToEdit = null) {
    $flag = ($country == 'hok_br') ? '🇧🇷' : '🇵🇭';
    $regionName = ($country == 'hok_br') ? 'Brazil' : 'Philippines';
    
    // Store pending action to wait for UID
    $pendingPurchase = [
        'action' => 'waiting_hok_uid',
        'country' => $country,
        'timestamp' => time()
    ];
    savePendingPurchase($telegramId, $pendingPurchase);
    
    $message = "⚔️ *HoK - {$flag} {$regionName}*\n\n";
    $message .= "Please enter your UID:\n\n";
    $message .= "*Format:*\n";
    $message .= "`UID`\n\n";
    $message .= "*Example:*\n";
    $message .= "`1234567890123456`\n\n";
    $message .= "Or use command:\n";
    $message .= "`/hokuid UID`";
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message);
    } else {
        sendMessage($chatId, $message);
    }
}

// Ask for Magic Chess GoGo UID (step-by-step like MLBB)
function askMagicChessGoGoUID($chatId, $telegramId, $country = 'magicchessgogo_br', $messageIdToEdit = null) {
    $flag = ($country == 'magicchessgogo_br') ? '🇧🇷' : '🇵🇭';
    $regionName = ($country == 'magicchessgogo_br') ? 'Brazil' : 'Philippines';
    
    // Store pending action to wait for UID
    $pendingPurchase = [
        'action' => 'waiting_magicchessgogo_uid',
        'country' => $country,
        'timestamp' => time()
    ];
    savePendingPurchase($telegramId, $pendingPurchase);
    
    $message = "♟️ *Magic Chess GoGo - {$flag} {$regionName}*\n\n";
    $message .= "Please enter your User ID and Zone ID:\n\n";
    $message .= "*Format:*\n";
    $message .= "`USERID ZONEID`\n\n";
    $message .= "*Example:*\n";
    $message .= "`12345678 1234`\n\n";
    $message .= "Or use command:\n";
    $message .= "`USERID ZONEID`";
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message);
    } else {
        sendMessage($chatId, $message);
    }
}

// Check Pubg Player ID and Show Packages
function checkPubgPlayerIdAndShowPackages($chatId, $telegramId, $country, $playerId, $useMMK = false, $messageIdToEdit = null) {
    // Get a sample product to check name
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
            // Use dummy zone ID for Pubg as it's not needed but function requires it
            $nameCheck = checkInGameName($country, $playerId, '0', $productId);
            
            if (!$nameCheck['success']) {
                $errorMsg = escapeMarkdown($nameCheck['error'] ?? 'Unknown error');
                $message = "❌ *PLAYER ID VERIFICATION FAILED!*\n\n";
                $message .= "Could not verify your Player ID.\n";
                $message .= "Error: " . $errorMsg . "\n\n";
                $message .= "Please check and try again:\n";
                $message .= "🎮 PlayerID: `{$playerId}`\n";
                if ($messageIdToEdit) {
                    editMessageText($chatId, $messageIdToEdit, $message);
                } else {
                    sendMessage($chatId, $message);
                }
                return;
            }
            
            // Player ID verified successfully
            $inGameName = escapeMarkdown($nameCheck['username']);
            $message = "✅ *PLAYER ID VERIFIED!*\n\n";
            $message .= "👤 In-Game Name: *{$inGameName}*\n";
            $message .= "🎮 PlayerID: `{$playerId}`\n\n";
            $message .= "Loading packages...";
            if ($messageIdToEdit) {
                editMessageText($chatId, $messageIdToEdit, $message);
            } else {
                sendMessage($chatId, $message);
            }
            
            // Save verified ID to pending purchase for the subsequent purchase step
            $pendingPurchase = [
                'action' => 'browsing_packages',
                'country' => $country,
                'game_id' => $playerId,
                'zone_id' => '0', 
                'timestamp' => time()
            ];
            savePendingPurchase($telegramId, $pendingPurchase);
            
            // Show packages
            showPubgPackages($chatId, $telegramId, $country, $playerId, null, $messageIdToEdit);
        } else {
            sendMessage($chatId, "❌ No products available for this region.");
        }
    } else {
        sendMessage($chatId, "❌ No products available for this region.");
    }
}

// Check HoK UID and Show Packages
function checkHoKUIDAndShowPackages($chatId, $telegramId, $country, $uid, $useMMK = false, $messageIdToEdit = null) {
    // Get a sample product to check name
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
            // Use dummy zone ID for HoK as it's not needed but function requires it
            $nameCheck = checkInGameName($country, $uid, '0', $productId);
            
            if (!$nameCheck['success']) {
                $errorMsg = escapeMarkdown($nameCheck['error'] ?? 'Unknown error');
                $message = "❌ *UID VERIFICATION FAILED!*\n\n";
                $message .= "Could not verify your UID.\n";
                $message .= "Error: " . $errorMsg . "\n\n";
                $message .= "Please check and try again:\n";
                $message .= "⚔️ UID: `{$uid}`\n";
                if ($messageIdToEdit) {
                    editMessageText($chatId, $messageIdToEdit, $message);
                } else {
                    sendMessage($chatId, $message);
                }
                return;
            }
            
            // UID verified successfully
            $inGameName = escapeMarkdown($nameCheck['username']);
            $message = "✅ *UID VERIFIED!*\n\n";
            $message .= "👤 In-Game Name: *{$inGameName}*\n";
            $message .= "⚔️ UID: `{$uid}`\n\n";
            $message .= "Loading packages...";
            if ($messageIdToEdit) {
                editMessageText($chatId, $messageIdToEdit, $message);
            } else {
                sendMessage($chatId, $message);
            }
            
            // Save verified ID to pending purchase for the subsequent purchase step
            $pendingPurchase = [
                'action' => 'browsing_packages',
                'country' => $country,
                'game_id' => $uid,
                'zone_id' => '0', 
                'timestamp' => time()
            ];
            savePendingPurchase($telegramId, $pendingPurchase);
            
            // Show packages
            showHoKPackages($chatId, $telegramId, $country, $uid, null, $messageIdToEdit);
        } else {
            sendMessage($chatId, "❌ No products available for this region.");
        }
    } else {
        sendMessage($chatId, "❌ No products available for this region.");
    }
}

// Check Magic Chess GoGo UID and Show Packages
function checkMagicChessGoGoUIDAndShowPackages($chatId, $telegramId, $country, $uid, $zoneId, $useMMK = false, $messageIdToEdit = null) {
    // Get a sample product to check name
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
            $nameCheck = checkInGameName($country, $uid, $zoneId, $productId);
            
            if (!$nameCheck['success']) {
                $errorMsg = escapeMarkdown($nameCheck['error'] ?? 'Unknown error');
                $message = "❌ *ID VERIFICATION FAILED!*\n\n";
                $message .= "Could not verify your ID.\n";
                $message .= "Error: " . $errorMsg . "\n\n";
                $message .= "Please check and try again:\n";
                $message .= "♟️ UserID: `{$uid}`\n";
                $message .= "🏠 ZoneID: `{$zoneId}`\n";
                if ($messageIdToEdit) {
                    editMessageText($chatId, $messageIdToEdit, $message);
                } else {
                    sendMessage($chatId, $message);
                }
                return;
            }
            
            // ID verified successfully
            $inGameName = escapeMarkdown($nameCheck['username']);
            $message = "✅ *ID VERIFIED!*\n\n";
            $message .= "👤 In-Game Name: *{$inGameName}*\n";
            $message .= "♟️ UserID: `{$uid}`\n";
            $message .= "🏠 ZoneID: `{$zoneId}`\n\n";
            $message .= "Loading packages...";
            if ($messageIdToEdit) {
                editMessageText($chatId, $messageIdToEdit, $message);
            } else {
                sendMessage($chatId, $message);
            }
            
            // Save verified ID to pending purchase for the subsequent purchase step
            $pendingPurchase = [
                'action' => 'browsing_packages',
                'country' => $country,
                'game_id' => $uid,
                'zone_id' => $zoneId,
                'timestamp' => time()
            ];
            savePendingPurchase($telegramId, $pendingPurchase);
            
            // Show packages based on MMK flag
            if ($useMMK) {
                showMMKMagicChessGoGoPackages($chatId, $telegramId, $country, $uid, $zoneId, $messageIdToEdit);
            } else {
                showMagicChessGoGoPackages($chatId, $telegramId, $country, $uid, $zoneId, $messageIdToEdit);
            }
        } else {
            sendMessage($chatId, "❌ No products available for this region.");
        }
    } else {
        sendMessage($chatId, "❌ No products available for this region.");
    }
}

// Show MMK Magic Chess GoGo Packages
function showMMKMagicChessGoGoPackages($chatId, $telegramId, $country = 'magicchessgogo_br', $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $products = loadProducts();
    $mcProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == $country) {
            $mcProducts[] = $product;
        }
    }
    
    // If no products found, show error message
    if (empty($mcProducts)) {
        sendMessage($chatId, "❌ *No packages available for this region*\n\nPlease contact admin to add packages.", 'Markdown');
        return;
    }
    
    usort($mcProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $flag = ($country == 'magicchessgogo_br') ? '🇧🇷' : '🇵🇭';
    $regionName = ($country == 'magicchessgogo_br') ? 'Brazil' : 'Philippines';
    
    $message = "♟️ *Magic Chess GoGo - {$flag} {$regionName} (MMK)*\n\n";
    
    // Show Game ID info if provided
    if ($gameId && $zoneId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "UserID: `{$gameId}`\n";
        $message .= "ZoneID: `{$zoneId}`\n\n";
    }
    
    $message .= "Select package to buy:\n\n";
    
    foreach ($mcProducts as $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        
        // Use MMK price from product if available, otherwise calculate
        if (isset($product['mmk_price']) && $product['mmk_price'] !== null && $product['mmk_price'] !== '') {
            $mmkPrice = formatMMK(floatval($product['mmk_price']));
        } else {
            // For BRL products use BRL rate, for PHP products use PHP rate
            $currency = ($country == 'magicchessgogo_br') ? 'br' : 'php';
            $mmkPrice = formatMMK(convertToMMK($price, $currency));
        }
        
        // Shorten callback data to avoid 64-byte limit
        // Format: b_mc_{id}
        // Store mapping in a temporary file or use a deterministic way if possible
        // Since we can't easily store mapping, we'll use the product index or ID if available
        // Let's use product ID (index in the sorted array) + country code
        // b_mc_br_0, b_mc_br_1, etc.
        // Or better: use a short prefix and the index
        $index = array_search($product, $mcProducts);
        $shortCode = "b_mc_" . ($country == 'magicchessgogo_br' ? 'br' : 'ph') . "_" . $index;
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - " . $mmkPrice,
                'callback_data' => $shortCode
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'magicchessgogo_back'
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show Philippines Packages
function showPhilippinesPackages($chatId, $telegramId, $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $products = loadProducts();
    $phProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == 'php') {
            $phProducts[] = $product;
        }
    }
    
    // If no products found, show error message
    if (empty($phProducts)) {
        sendMessage($chatId, "❌ *No Philippines packages available*\n\nPlease contact admin to add packages.", 'Markdown');
        return;
    }
    
    usort($phProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $message = "🇵🇭 *PHILIPPINES DIAMONDS (PHP)*\n\n";
    
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
        $diamonds = $product['diamonds'] ?? intval($name);
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - ₱" . number_format($price, 2),
                'callback_data' => "b_ph_{$name}"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'buy_diamonds_back'
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show Brazil Packages
function showBrazilPackages($chatId, $telegramId, $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $products = loadProducts();
    $brProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == 'br') {
            $brProducts[] = $product;
        }
    }
    
    // If no products found, show error message
    if (empty($brProducts)) {
        sendMessage($chatId, "❌ *No Brazil packages available*\n\nPlease contact admin to add packages.", 'Markdown');
        return;
    }
    
    usort($brProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $message = "🇧🇷 *BRAZIL DIAMONDS (BRL)*\n\n";
    
    // Show Game ID info if provided
    if ($gameId && $zoneId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "GameID: `{$gameId}`\n";
        $message .= "ZoneID: `{$zoneId}`\n\n";
    }
    
    $message .= "Select package to buy:";
    
    foreach ($brProducts as $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        $diamonds = $product['diamonds'] ?? intval($name);
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - R$ " . number_format($price, 2),
                'callback_data' => "b_br_{$name}"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'buy_diamonds_back'
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show Magic Chess GoGo Packages
function showMagicChessGoGoPackages($chatId, $telegramId, $country = 'magicchessgogo_br', $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $products = loadProducts();
    $mcProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == $country) {
            $mcProducts[] = $product;
        }
    }
    
    // If no products found, show error message
    if (empty($mcProducts)) {
        sendMessage($chatId, "❌ *No packages available for this region*\n\nPlease contact admin to add packages.", 'Markdown');
        return;
    }
    
    usort($mcProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $flag = ($country == 'magicchessgogo_br') ? '🇧🇷' : '🇵🇭';
    $regionName = ($country == 'magicchessgogo_br') ? 'Brazil' : 'Philippines';
    $currency = ($country == 'magicchessgogo_br') ? 'BRL' : 'PHP';
    $currencySymbol = ($country == 'magicchessgogo_br') ? 'R$ ' : '₱';
    
    $message = "♟️ *Magic Chess GoGo - {$flag} {$regionName}*\n\n";
    
    // Show Game ID info if provided
    if ($gameId && $zoneId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "UserID: `{$gameId}`\n";
        $message .= "ZoneID: `{$zoneId}`\n\n";
    }
    
    $message .= "Select package to buy:\n\n";
    
    foreach ($mcProducts as $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        
        // Shorten callback data to avoid 64-byte limit
        // Format: b_mc_{id}
        // Use index as ID for now
        $index = array_search($product, $mcProducts);
        // Add country code to differentiate: br or ph
        $countryCode = ($country == 'magicchessgogo_br') ? 'br' : 'ph';
        $shortCode = "b_mc_{$countryCode}_{$index}";
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - {$currencySymbol}" . number_format($price, 2),
                'callback_data' => $shortCode
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'magicchessgogo_back'
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show Pubg Region Selection
function showPubgRegionSelection($chatId, $telegramId, $messageIdToEdit = null) {
    $message = "🎮 *PUBG Mobile UC*\n\n";
    $message .= "Please select region:\n\n";
    $message .= "🇧🇷 *Brazil (BRL)* - Cheapest UC\n";
    $message .= "🇵🇭 *Philippines (PHP)* - Standard UC";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '🇧🇷 Brazil (BRL)',
                    'callback_data' => 'pubg_br'
                ],
                [
                    'text' => '🇵🇭 Philippines (PHP)',
                    'callback_data' => 'pubg_php'
                ]
            ],
            [
                [
                    'text' => '⬅️ Back',
                    'callback_data' => 'back_to_games'
                ]
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show Pubg Packages
function showPubgPackages($chatId, $telegramId, $country = 'pubg_br', $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $products = loadProducts();
    $pubgProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == $country) {
            $pubgProducts[] = $product;
        }
    }
    
    // If no products found, show error message
    if (empty($pubgProducts)) {
        sendMessage($chatId, "❌ *No packages available for this region*\n\nPlease contact admin to add packages.", 'Markdown');
        return;
    }
    
    usort($pubgProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $flag = ($country == 'pubg_br') ? '🇧🇷' : '🇵🇭';
    $regionName = ($country == 'pubg_br') ? 'Brazil' : 'Philippines';
    $currency = ($country == 'pubg_br') ? 'BRL' : 'PHP';
    $currencySymbol = ($country == 'pubg_br') ? 'R$ ' : '₱';
    
    $message = "🎮 *PUBG UC - {$flag} {$regionName}*\n\n";
    
    // Show Game ID info if provided
    if ($gameId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "PlayerID: `{$gameId}`\n\n";
    }
    
    $message .= "Select package to buy:\n\n";
    
    foreach ($pubgProducts as $index => $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        
        $regionCode = ($country == 'pubg_br') ? 'br' : 'ph';
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - {$currencySymbol}" . number_format($price, 2),
                'callback_data' => "b_pg_{$regionCode}_{$index}"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'pubg_back'
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show Magic Chess GoGo Region Selection
function showMagicChessGoGoRegionSelection($chatId, $telegramId, $messageIdToEdit = null) {
    $message = "♟️ *Magic Chess GoGo*\n\n";
    $message .= "Please select region:\n\n";
    $message .= "🇧🇷 *Brazil (BRL)*\n";
    $message .= "🇵🇭 *Philippines (PHP)*";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '🇧🇷 Brazil (BRL)',
                    'callback_data' => 'magicchessgogo_br'
                ],
                [
                    'text' => '🇵🇭 Philippines (PHP)',
                    'callback_data' => 'magicchessgogo_php'
                ]
            ],
            [
                [
                    'text' => '⬅️ Back',
                    'callback_data' => 'back_to_games'
                ]
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show HoK Region Selection
function showHoKRegionSelection($chatId, $telegramId, $messageIdToEdit = null) {
    $message = "⚔️ *Honor of Kings (HoK)*\n\n";
    $message .= "Please select region:\n\n";
    $message .= "🇧🇷 *Brazil (BRL)*\n";
    $message .= "🇵🇭 *Philippines (PHP)*";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '🇧🇷 Brazil (BRL)',
                    'callback_data' => 'hok_br'
                ],
                [
                    'text' => '🇵🇭 Philippines (PHP)',
                    'callback_data' => 'hok_php'
                ]
            ],
            [
                [
                    'text' => '⬅️ Back',
                    'callback_data' => 'back_to_games'
                ]
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show HoK Packages
function showHoKPackages($chatId, $telegramId, $country = 'hok_br', $gameId = null, $zoneId = null, $messageIdToEdit = null) {
    $products = loadProducts();
    $hokProducts = [];
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == $country) {
            $hokProducts[] = $product;
        }
    }
    
    // If no products found, show error message
    if (empty($hokProducts)) {
        sendMessage($chatId, "❌ *No packages available for this region*\n\nPlease contact admin to add packages.", 'Markdown');
        return;
    }
    
    usort($hokProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    $flag = ($country == 'hok_br') ? '🇧🇷' : '🇵🇭';
    $regionName = ($country == 'hok_br') ? 'Brazil' : 'Philippines';
    $currency = ($country == 'hok_br') ? 'BRL' : 'PHP';
    $currencySymbol = ($country == 'hok_br') ? 'R$ ' : '₱';
    
    $message = "⚔️ *HoK - {$flag} {$regionName}*\n\n";
    
    // Show Game ID info if provided
    if ($gameId) {
        $message .= "🎮 *Game Information*\n";
        $message .= "UID: `{$gameId}`\n\n";
    }
    
    $message .= "Select package to buy:\n\n";
    
    foreach ($hokProducts as $index => $product) {
        $name = $product['name'] ?? 'N/A';
        $price = $product['price'] ?? 0;
        
        $regionCode = ($country == 'hok_br') ? 'br' : 'ph';
        
        $keyboard['inline_keyboard'][] = [
            [
                'text' => "💎 {$name} - {$currencySymbol}" . number_format($price, 2),
                'callback_data' => "b_hk_{$regionCode}_{$index}"
            ]
        ];
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '⬅️ Back',
            'callback_data' => 'hok_back'
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Show Payment Method Selection
function showPaymentMethodSelection($chatId, $telegramId, $country, $packageName, $messageIdToEdit = null) {
    global $log;
    if (isset($log)) $log("💳 Showing payment method selection for {$country}, Package: {$packageName}");
    
    // If packageName is an index, resolve it for display but keep index for callback
    $displayPackageName = $packageName;
    
    if (is_numeric($packageName)) {
        $products = loadProducts();
        $regionProducts = [];
        foreach ($products as $product) {
            if (($product['country'] ?? '') == $country) {
                $regionProducts[] = $product;
            }
        }
        
        // Sort exactly as displayed in other functions
        usort($regionProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
        
        $index = intval($packageName);
        if (isset($regionProducts[$index])) {
            $displayPackageName = $regionProducts[$index]['name'];
        }
    }

    // Escape special characters for Markdown
    $safePackageName = escapeMarkdown($displayPackageName);
    $safeCountry = escapeMarkdown(strtoupper($country));
    
    $message = "💳 *Select Payment Method*\n\n";
    $message .= "Package: {$safePackageName}\n";
    $message .= "Country: {$safeCountry}\n\n";
    $message .= "Please choose your payment balance:";
    
    // Determine Back button callback based on country
    $backCallback = 'buy_diamonds_back'; // Default for MLBB
    if (strpos($country, 'pubg_') === 0) {
        $backCallback = 'pubg_back'; // Should probably go to package list, but pubg_back goes to region selection?
        // Actually, let's make it go back to the package list if possible, but that requires knowing the region code.
        // For now, pubg_back (Region Selection) is safer than MLBB menu.
    } elseif (strpos($country, 'hok_') === 0) {
        $backCallback = 'hok_back';
    } elseif (strpos($country, 'magicchessgogo_') === 0) {
        $backCallback = 'magicchessgogo_back';
    }
    
    // Use colon as separator to avoid issues with underscores in country names
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '🇵🇭 PHP Balance',
                    'callback_data' => "pay:php:{$country}:{$packageName}"
                ]
            ],
            [
                [
                    'text' => '🇧🇷 BRL Balance',
                    'callback_data' => "pay:br:{$country}:{$packageName}"
                ]
            ],
            [
                [
                    'text' => '💵 MMK Balance',
                    'callback_data' => "pay:mmk:{$country}:{$packageName}"
                ]
            ],
            [
                [
                    'text' => '❌ Cancel',
                    'callback_data' => 'cancel_purchase'
                ]
            ],
            [
                [
                    'text' => '⬅️ Back',
                    'callback_data' => $backCallback
                ]
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Handle Purchase Selection
function handlePurchase($chatId, $telegramId, $country, $packageName, $useMMK = false, $messageIdToEdit = null) {
    global $log;
    
    // Find the product
    $products = loadProducts();
    $selectedProduct = null;
    
    foreach ($products as $product) {
        if (($product['country'] ?? '') == $country && ($product['name'] ?? '') == $packageName) {
            $selectedProduct = $product;
            break;
        }
    }
    
    // Fallback: Check if packageName is an index (for Magic Chess GoGo and others using shortened callbacks)
    if (!$selectedProduct && is_numeric($packageName)) {
        $regionProducts = [];
        foreach ($products as $product) {
            if (($product['country'] ?? '') == $country) {
                $regionProducts[] = $product;
            }
        }
        
        // Sort exactly as displayed
        usort($regionProducts, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
        
        $index = intval($packageName);
        if (isset($regionProducts[$index])) {
            $selectedProduct = $regionProducts[$index];
            // Update packageName to the real name for display
            $packageName = $selectedProduct['name'];
        }
    }
    
    if (!$selectedProduct) {
        sendMessage($chatId, "❌ Package not found!");
        return;
    }
    
    // Get user to check balance and game ID
    $user = getUser($telegramId);
    
    // For Pubg, HoK, Magic Chess GoGo, we need to check if we have the ID in pending purchase
    // or if we need to ask for it
    $pending = getPendingPurchase($telegramId);
    
    $gameId = $user['game_id'] ?? '';
    $zoneId = $user['zone_id'] ?? '';
    
    // Override with pending purchase IDs if available (for Pubg, HoK, Magic Chess GoGo)
    if ($pending && isset($pending['game_id'])) {
        $gameId = $pending['game_id'];
    }
    if ($pending && isset($pending['zone_id'])) {
        $zoneId = $pending['zone_id'];
    }
    
    // For Pubg, HoK, Magic Chess GoGo, we require ID to be set in pending or passed
    if (strpos($country, 'pubg') === 0 || strpos($country, 'hok') === 0 || strpos($country, 'magicchessgogo') === 0) {
        if (empty($gameId)) {
            // Should not happen if flow is followed
            sendMessage($chatId, "❌ Session expired or ID missing. Please enter your ID again.");
            
            if (strpos($country, 'pubg') === 0) {
                askPubgPlayerId($chatId, $telegramId, $country);
            } elseif (strpos($country, 'hok') === 0) {
                askHoKUID($chatId, $telegramId, $country);
            } elseif (strpos($country, 'magicchessgogo') === 0) {
                askMagicChessGoGoUID($chatId, $telegramId, $country);
            }
            return;
        }
    } else {
        // For MLBB
        if (empty($gameId) || empty($zoneId)) {
            // ID missing, ask for it
            $pendingPurchase = [
                'country' => $country,
                'package_name' => $packageName,
                'use_mmk' => $useMMK,
                'action' => 'enter_gameid',
                'timestamp' => time()
            ];
            savePendingPurchase($telegramId, $pendingPurchase);
            
            handleEnterGameIdForPurchase($chatId, $telegramId, $country, $useMMK, $messageIdToEdit);
            return;
        }
    }
    
    $price = $selectedProduct['price'];
    
    // Determine currency and price based on country or MMK usage
    if ($useMMK) {
        $currency = 'MMK';
        if (isset($selectedProduct['mmk_price']) && $selectedProduct['mmk_price'] !== null) {
            $displayPrice = formatMMK(floatval($selectedProduct['mmk_price']));
            $amount = floatval($selectedProduct['mmk_price']);
        } else {
            // Calculate MMK price
            $origCurrency = ($country == 'php' || strpos($country, '_php') !== false) ? 'php' : 'br';
            $amount = convertToMMK($price, $origCurrency);
            $displayPrice = formatMMK($amount);
        }
    } else {
        if ($country == 'php' || strpos($country, '_php') !== false) {
            $currency = 'PHP';
            $displayPrice = "₱" . number_format($price, 2);
            $amount = $price;
        } else {
            $currency = 'BRL';
            $displayPrice = "R$ " . number_format($price, 2);
            $amount = $price;
        }
    }
    
    // Prepare confirmation message
    $safePackageName = escapeMarkdown($packageName);
    $message = "🛒 *CONFIRM PURCHASE*\n\n";
    $message .= "📦 Package: *{$safePackageName}*\n";
    $message .= "💰 Price: *{$displayPrice}*\n";
    
    if (strpos($country, 'pubg') === 0) {
        $message .= "🎮 Player ID: `{$gameId}`\n";
    } elseif (strpos($country, 'hok') === 0) {
        $message .= "⚔️ UID: `{$gameId}`\n";
    } elseif (strpos($country, 'magicchessgogo') === 0) {
        $message .= "♟️ User ID: `{$gameId}`\n";
        $message .= "🏠 Zone ID: `{$zoneId}`\n";
    } else {
        $message .= "🎮 Game ID: `{$gameId}`\n";
        $message .= "🏠 Zone ID: `{$zoneId}`\n";
    }
    
    $message .= "\n";
    $message .= "⚠️ *Please verify your details before confirming.*";
    
    // Store pending purchase
    $pendingPurchase = [
        'country' => $country,
        'package_name' => $packageName,
        'price' => $amount,
        'currency' => $currency,
        'product_id' => $selectedProduct['products'][0] ?? '', // Use first product ID
        'timestamp' => time(),
        'use_mmk' => $useMMK,
        'game_id' => $gameId,
        'zone_id' => $zoneId
    ];
    savePendingPurchase($telegramId, $pendingPurchase);
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ Confirm Purchase', 'callback_data' => 'confirm_order'],
                ['text' => '❌ Cancel', 'callback_data' => 'cancel_order']
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}

// Confirm Order
function confirmOrder($chatId, $telegramId, $messageIdToEdit = null) {
    global $log;
    
    $pending = getPendingPurchase($telegramId);
    
    if (!$pending || !isset($pending['package_name'])) {
        $msg = "❌ No pending order found or order expired.";
        if ($messageIdToEdit) {
            editMessageText($chatId, $messageIdToEdit, $msg);
        } else {
            sendMessage($chatId, $msg);
        }
        return;
    }
    
    $user = getUser($telegramId);
    $price = $pending['price'];
    $currency = $pending['currency'];
    $country = $pending['country'];
    $useMMK = $pending['use_mmk'] ?? false;
    
    // Check balance
    $hasBalance = false;
    if ($useMMK) {
        if (($user['balance_mmk'] ?? 0) >= $price) {
            $hasBalance = true;
        }
    } else {
        if ($currency == 'PHP') {
            if (($user['balance_php'] ?? 0) >= $price) {
                $hasBalance = true;
            }
        } else {
            if (($user['balance_br'] ?? 0) >= $price) {
                $hasBalance = true;
            }
        }
    }
    
    if (!$hasBalance) {
        $msg = "❌ *Insufficient Balance!*\n\nPlease topup your account first.";
        if ($messageIdToEdit) {
            editMessageText($chatId, $messageIdToEdit, $msg, 'Markdown');
        } else {
            sendMessage($chatId, $msg, 'Markdown');
        }
        return;
    }
    
    // Deduct balance
    if ($useMMK) {
        $newBalance = ($user['balance_mmk'] ?? 0) - $price;
        updateUserBalance($telegramId, 'mmk', $newBalance, 'set');
        
        // Admin Balance Deduction & Commission
        $admins = loadAdmins();
        if (!empty($admins)) {
            $adminId = $admins[0]['telegram_id'];
            $adminUser = getUser($adminId);
            
            if ($adminUser) {
                // Calculate Commission
                $commissionRate = getCommissionRate('mmk', $price);
                $commission = $price * $commissionRate;
                
                // Update Admin Balance: Deduct Cost, Add Commission
                $currentAdminBalance = $adminUser['balance_mmk'] ?? 0;
                $newAdminBalance = $currentAdminBalance - $price + $commission;
                
                updateUserBalance($adminId, 'mmk', $newAdminBalance, 'set');
                
                // Record Commission
                saveCommissionRecord($adminId, 'mmk', $commission, $price, $commissionRate);
            }
        }
    } else {
        if ($currency == 'PHP') {
            $newBalance = ($user['balance_php'] ?? 0) - $price;
            updateUserBalance($telegramId, 'php', $newBalance, 'set');
        } else {
            $newBalance = ($user['balance_br'] ?? 0) - $price;
            updateUserBalance($telegramId, 'br', $newBalance, 'set');
        }
    }
    
    // Process order
    $processingMsg = "🔄 Processing your order...\nPlease wait...";
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $processingMsg);
    } else {
        $sent = sendMessage($chatId, $processingMsg);
        // If we sent a new message, try to capture its ID for subsequent edits
        $sentData = json_decode($sent, true);
        if (isset($sentData['result']['message_id'])) {
            $messageIdToEdit = $sentData['result']['message_id'];
        }
    }
    sendTyping($chatId);
    
    try {
        // Use provided Game ID/Zone ID from pending purchase or fallback to user profile
        $gameId = $pending['game_id'] ?? ($user['game_id'] ?? '');
        $zoneId = $pending['zone_id'] ?? ($user['zone_id'] ?? '');
        $productId = $pending['product_id'];
        
        // Call SmileOne API
        // Map internal country codes to SmileOne regions if needed
        // For now assuming product IDs are correct for the region
        
        // NOTE: For Pubg, HoK, etc., we need to make sure we use the right parameters
        // The buyProduct function in smile.php handles userid and zoneid
        
        $result = buyProduct($country, $gameId, $zoneId, $productId);
        
        if ($result['success']) {
            $status = 'success';
            $details = "Order ID: " . ($result['order_id'] ?? 'N/A');
            $safePackageName = escapeMarkdown($pending['package_name']);
            $message = "✅ *ORDER SUCCESSFUL!*\n\n";
            $message .= "📦 Package: {$safePackageName}\n";
            $message .= "💰 Price: " . ($useMMK ? formatMMK($price) : "{$currency} " . number_format($price, 2)) . "\n";
            $message .= "🆔 Order ID: `{$result['order_id']}`\n\n";
            $message .= "Thank you for your purchase!";
        } else {
            $status = 'failed';
            $details = "Error: " . ($result['message'] ?? 'Unknown error');
            $safeError = escapeMarkdown($result['message'] ?? 'Unknown error');
            $message = "❌ *ORDER FAILED!*\n\n";
            $message .= "Reason: " . $safeError . "\n\n";
            $message .= "Your balance has been refunded.";
            
            // Refund balance
            $currentUser = getUser($telegramId);
            if ($useMMK) {
                $refundBalance = ($currentUser['balance_mmk'] ?? 0) + $price;
                updateUserBalance($telegramId, 'mmk', $refundBalance, 'set');
                
                // Revert Admin Balance
                $admins = loadAdmins();
                if (!empty($admins)) {
                    $adminId = $admins[0]['telegram_id'];
                    $adminUser = getUser($adminId);
                    if ($adminUser) {
                         $commissionRate = getCommissionRate('mmk', $price);
                         $commission = $price * $commissionRate;
                         $revertedAdminBalance = ($adminUser['balance_mmk'] ?? 0) + $price - $commission;
                         updateUserBalance($adminId, 'mmk', $revertedAdminBalance, 'set');
                    }
                }
            } else {
                if ($currency == 'PHP') {
                    $refundBalance = ($currentUser['balance_php'] ?? 0) + $price;
                    updateUserBalance($telegramId, 'php', $refundBalance, 'set');
                } else {
                    $refundBalance = ($currentUser['balance_br'] ?? 0) + $price;
                    updateUserBalance($telegramId, 'br', $refundBalance, 'set');
                }
            }
        }
        
        // Log transaction
        $orderId = $result['order_id'] ?? ('ERR-' . time());
        logTransaction($telegramId, 'purchase', $price, $country, $details . " | Package: " . $pending['package_name']);
        
        // Clear pending
        clearPendingPurchase($telegramId);
        
        if ($messageIdToEdit) {
            editMessageText($chatId, $messageIdToEdit, $message);
        } else {
            sendMessage($chatId, $message);
        }
        
        if (function_exists('is_callable') && is_callable($log ?? null)) {
            $log("✅ Order confirmed successfully for {$telegramId}. Order ID: {$orderId}");
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (function_exists('is_callable') && is_callable($log ?? null)) {
            $log("❌ Error confirming order for {$telegramId}: " . $errorMsg);
        }
        error_log("❌ Error in confirmOrder: " . $errorMsg . "\nStack trace: " . $e->getTraceAsString());
        $safeErrorMsg = escapeMarkdown($errorMsg);
        
        $failMsg = "❌ *ERROR PROCESSING ORDER!*\n\nAn error occurred while processing your purchase.\n\nError: " . $safeErrorMsg . "\n\nPlease try again or contact admin.";
        
        if ($messageIdToEdit) {
            editMessageText($chatId, $messageIdToEdit, $failMsg);
        } else {
            sendMessage($chatId, $failMsg);
        }
        
        // Refund on exception if balance was deducted
        // This is a simple safety net, might need more robust handling
        $currentUser = getUser($telegramId);
        if ($useMMK) {
            $refundBalance = ($currentUser['balance_mmk'] ?? 0) + $price;
            updateUserBalance($telegramId, 'mmk', $refundBalance, 'set');
            
            // Revert Admin Balance
            $admins = loadAdmins();
            if (!empty($admins)) {
                $adminId = $admins[0]['telegram_id'];
                $adminUser = getUser($adminId);
                if ($adminUser) {
                     $commissionRate = getCommissionRate('mmk', $price);
                     $commission = $price * $commissionRate;
                     $revertedAdminBalance = ($adminUser['balance_mmk'] ?? 0) + $price - $commission;
                     updateUserBalance($adminId, 'mmk', $revertedAdminBalance, 'set');
                }
            }
        } else {
            if ($currency == 'PHP') {
                $refundBalance = ($currentUser['balance_php'] ?? 0) + $price;
                updateUserBalance($telegramId, 'php', $refundBalance, 'set');
            } else {
                $refundBalance = ($currentUser['balance_br'] ?? 0) + $price;
                updateUserBalance($telegramId, 'br', $refundBalance, 'set');
            }
        }
        sendMessage($chatId, "⚠️ Your balance has been refunded due to the error.");
        
    } catch (Error $e) {
        $errorMsg = $e->getMessage();
        if (function_exists('is_callable') && is_callable($log ?? null)) {
            $log("❌ Fatal error confirming order for {$telegramId}: " . $errorMsg);
        }
        error_log("❌ Fatal error in confirmOrder: " . $errorMsg . "\nStack trace: " . $e->getTraceAsString());
        sendMessage($chatId, "❌ *FATAL ERROR!*\n\nA fatal error occurred.\nPlease try again or contact admin.");
    }
}

// Cancel Order
function cancelOrder($chatId, $telegramId, $messageIdToEdit = null) {
    clearPendingPurchase($telegramId);
    
    $message = "❌ *Purchase Cancelled*\n\n";
    $message .= "Your purchase has been cancelled.\n";
    $message .= "No charges were made.\n\n";
    $message .= "You can select another package anytime!";
    
    // Add Main Menu button
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '⬅️ Back to Menu', 'callback_data' => 'back_to_main']
            ]
        ]
    ];
    
    if ($messageIdToEdit) {
        editMessageText($chatId, $messageIdToEdit, $message, 'Markdown', $keyboard);
    } else {
        sendMessage($chatId, $message, 'Markdown', $keyboard);
    }
}
