<?php
// Update Handler - Processes Messages and Callbacks

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/telegram_api.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/game_handler.php';
require_once __DIR__ . '/admin_handler.php';

// ==============================================
// 🤖 MAIN MESSAGE PROCESSOR
// ==============================================

function processMessage($chatId, $telegramId, $text) {
    global $log;
    
    // Clean text
    $cleanText = trim($text);
    
    // Log the message being processed
    if (function_exists('is_callable') && is_callable($log ?? null)) {
        $log("🔍 Processing message from {$telegramId}: {$cleanText}");
    }
    
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
                // Game Info command removed - IDs no longer saved
                sendMessage($chatId, "❌ Game Info feature has been removed. IDs are no longer saved.\n\nYou can still purchase by providing Game ID/Zone ID when needed.");
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
            
        case '🎮 Ke Games':
            showKeGamesMenu($chatId);
            break;
            
        case '📜 My History':
            showUserHistory($chatId, $telegramId);
            break;
            
        case ' Game Info':
            // Game Info feature removed
            sendMessage($chatId, "❌ Game Info feature has been removed. IDs are no longer saved.");
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
            // Check if there's a pending purchase and user might be providing Player ID/UID/Game ID
            $pending = getPendingPurchase($telegramId);
            
            // Check for Pubg Player ID input
            if ($pending && isset($pending['action']) && $pending['action'] == 'waiting_pubg_player_id') {
                $country = $pending['country'] ?? 'pubg_br';
                
                // Validate Player ID format (should be numeric)
                $playerId = trim($cleanText);
                if (!preg_match('/^\d+$/', $playerId)) {
                    sendMessage($chatId, "❌ *INVALID PLAYER ID!*\n\nPlayer ID must be numbers only.\nPlease try again:\n`PLAYERID`");
                    return;
                }
                
                // Clear pending action and show packages
                // clearPendingPurchase($telegramId); // Don't clear yet, let the check function handle it or keep it for retry
                checkPubgPlayerIdAndShowPackages($chatId, $telegramId, $country, $playerId, false);
                return;
            }
            
            // Check for HoK UID input
            if ($pending && isset($pending['action']) && $pending['action'] == 'waiting_hok_uid') {
                $country = $pending['country'] ?? 'hok_br';
                
                // Validate UID format (should be numeric)
                $uid = trim($cleanText);
                if (!preg_match('/^\d+$/', $uid)) {
                    sendMessage($chatId, "❌ *INVALID UID!*\n\nUID must be numbers only.\nPlease try again:\n`UID`");
                    return;
                }
                
                // Clear pending action and show packages
                // clearPendingPurchase($telegramId); // Don't clear yet, let the check function handle it or keep it for retry
                checkHoKUIDAndShowPackages($chatId, $telegramId, $country, $uid, false);
                return;
            }

            // Check for MagicChessGoGo UID input
            if ($pending && isset($pending['action']) && $pending['action'] == 'waiting_magicchessgogo_uid') {
                $country = $pending['country'] ?? 'magicchessgogo_br';
                $useMMK = $pending['use_mmk'] ?? false;
                if (preg_match('/^(\d+)\s+(\d+)$/', $cleanText, $matches)) {
                    $uid = $matches[1];
                    $zoneId = $matches[2];
                    checkMagicChessGoGoUIDAndShowPackages($chatId, $telegramId, $country, $uid, $zoneId, $useMMK);
                } else {
                    sendMessage($chatId, "❌ Invalid format. Please send: `UID ZONEID` (e.g., `12345678 1234`)", 'Markdown');
                }
                return;
            }
            
            // Check for Pubg Player ID input during purchase flow
            if ($pending && isset($pending['action']) && $pending['action'] == 'waiting_pubg_player_id_for_purchase') {
                $country = $pending['country'] ?? 'pubg_br';
                $packageName = $pending['package_name'] ?? '';
                $useMMK = $pending['use_mmk'] ?? false;
                
                // Validate Player ID format (should be numeric)
                $playerId = trim($cleanText);
                if (!preg_match('/^\d+$/', $playerId)) {
                    sendMessage($chatId, "❌ *INVALID PLAYER ID!*\n\nPlayer ID must be numbers only.\nPlease try again:\n`PLAYERID`");
                    return;
                }
                
                // Save Player ID and proceed with purchase
                $pending['game_id'] = $playerId;
                $pending['zone_id'] = '';
                $pending['action'] = null; // Clear action
                savePendingPurchase($telegramId, $pending);
                
                // Continue with purchase
                handlePurchase($chatId, $telegramId, $country, $packageName, $useMMK);
                return;
            }
            
            // Check for HoK UID input during purchase flow
            if ($pending && isset($pending['action']) && $pending['action'] == 'waiting_hok_uid_for_purchase') {
                $country = $pending['country'] ?? 'hok_br';
                $packageName = $pending['package_name'] ?? '';
                $useMMK = $pending['use_mmk'] ?? false;
                
                // Validate UID format (should be numeric)
                $uid = trim($cleanText);
                if (!preg_match('/^\d+$/', $uid)) {
                    sendMessage($chatId, "❌ *INVALID UID!*\n\nUID must be numbers only.\nPlease try again:\n`UID`");
                    return;
                }
                
                // Save UID and proceed with purchase
                $pending['game_id'] = $uid;
                $pending['zone_id'] = '';
                $pending['action'] = null; // Clear action
                savePendingPurchase($telegramId, $pending);
                
                // Continue with purchase
                handlePurchase($chatId, $telegramId, $country, $packageName, $useMMK);
                return;
            }
            
            // Check for MLBB Game ID/Zone ID input
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
                $message .= "Format: `GAMEID ZONEID`";
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
                $message .= "Format: `GAMEID ZONEID`";
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
    $messageId = $callbackQuery['message']['message_id'] ?? null;
    $data = $callbackQuery['data'];
    
    // Answer callback first (unless it's a special case handled later with an alert)
    if ($data !== 'coming_soon_ff' && $data !== 'more_games') {
        answerCallbackQuery($callbackId);
    }
    
    // Process callback data
    switch (true) {
        case $data == 'buy_diamonds_php':
            // Direct to enter ID, skip choice menu
            handleEnterGameIdForPurchase($chatId, $telegramId, 'php', false, $messageId);
            break;
            
        case $data == 'buy_diamonds_br':
            // Direct to enter ID, skip choice menu
            handleEnterGameIdForPurchase($chatId, $telegramId, 'br', false, $messageId);
            break;
            
        case $data == 'select_pubg_region':
            showPubgRegionSelection($chatId, $telegramId, $messageId);
            break;
            
        case $data == 'select_hok_region':
            showHoKRegionSelection($chatId, $telegramId, $messageId);
            break;
            
        case $data == 'pubg_br':
            // For Pubg, ask for Player ID first (step-by-step like MLBB)
            askPubgPlayerId($chatId, $telegramId, 'pubg_br', $messageId);
            break;
            
        case $data == 'pubg_php':
            // For Pubg, ask for Player ID first (step-by-step like MLBB)
            askPubgPlayerId($chatId, $telegramId, 'pubg_php', $messageId);
            break;
            
        case $data == 'hok_br':
            // For HoK, ask for UID first (step-by-step like MLBB)
            askHoKUID($chatId, $telegramId, 'hok_br', $messageId);
            break;
            
        case $data == 'hok_php':
            // For HoK, ask for UID first (step-by-step like MLBB)
            askHoKUID($chatId, $telegramId, 'hok_php', $messageId);
            break;
            
        case $data == 'magicchessgogo_br':
            // For MagicChessGoGo, ask for UID first
            askMagicChessGoGoUID($chatId, $telegramId, 'magicchessgogo_br', $messageId);
            break;
            
        case $data == 'magicchessgogo_php':
            // For MagicChessGoGo, ask for UID first
            askMagicChessGoGoUID($chatId, $telegramId, 'magicchessgogo_php', $messageId);
            break;
            
        case $data == 'b_mc_br':
            // Shortened callback for MagicChessGoGo Brazil
            askMagicChessGoGoUID($chatId, $telegramId, 'magicchessgogo_br', $messageId);
            break;
            
        case $data == 'b_mc_ph':
            // Shortened callback for MagicChessGoGo Philippines
            askMagicChessGoGoUID($chatId, $telegramId, 'magicchessgogo_php', $messageId);
            break;
            
        case $data == 'buy_diamonds_back':
            showBuyDiamondsCountrySelection($chatId, $telegramId, $messageId);
            break;
            
        case strpos($data, 'use_my_gameid_') === 0:
            $country = str_replace('use_my_gameid_', '', $data);
            if (strpos($country, 'mmk_') === 0) {
                $country = str_replace('mmk_', '', $country);
                checkGameIdAndShowPackages($chatId, $telegramId, $country, true, null, null, $messageId); // true = use MMK
            } else {
                checkGameIdAndShowPackages($chatId, $telegramId, $country, false, null, null, $messageId);
            }
            break;
            
        case strpos($data, 'enter_gameid_') === 0:
            $country = str_replace('enter_gameid_', '', $data);
            if (strpos($country, 'mmk_') === 0) {
                $country = str_replace('mmk_', '', $country);
                handleEnterGameIdForPurchase($chatId, $telegramId, $country, true, $messageId); // true = use MMK
            } else {
                handleEnterGameIdForPurchase($chatId, $telegramId, $country, false, $messageId);
            }
            break;
            
        // NEW SHORTENED HANDLERS
        
        case strpos($data, 'b_ph_') === 0:
            $packageName = str_replace('b_ph_', '', $data);
            showPaymentMethodSelection($chatId, $telegramId, 'php', $packageName, $messageId);
            break;
            
        case strpos($data, 'b_br_') === 0:
            $packageName = str_replace('b_br_', '', $data);
            showPaymentMethodSelection($chatId, $telegramId, 'br', $packageName, $messageId);
            break;
            
        case strpos($data, 'b_pg_') === 0:
            // b_pg_br_PACKAGE or b_pg_ph_PACKAGE
            try {
                $parts = explode('_', $data);
                if (count($parts) >= 4) {
                    $region = $parts[2]; // br or ph
                    $country = 'pubg_' . ($region == 'br' ? 'br' : 'php');
                    $packageName = implode('_', array_slice($parts, 3));
                    if (isset($log)) $log("Processing PUBG selection: {$country}, {$packageName}");
                    showPaymentMethodSelection($chatId, $telegramId, $country, $packageName, $messageId);
                } else {
                    if (isset($log)) $log("❌ Invalid PUBG callback data: {$data}");
                }
            } catch (Exception $e) {
                if (isset($log)) $log("❌ Error in PUBG selection: " . $e->getMessage());
                sendMessage($chatId, "❌ An error occurred. Please try again.");
            }
            break;
            
        case strpos($data, 'b_hk_') === 0:
            // b_hk_br_PACKAGE or b_hk_ph_PACKAGE
            $parts = explode('_', $data);
            if (count($parts) >= 4) {
                $region = $parts[2]; // br or ph
                $country = 'hok_' . ($region == 'br' ? 'br' : 'php');
                $packageName = implode('_', array_slice($parts, 3));
                showPaymentMethodSelection($chatId, $telegramId, $country, $packageName, $messageId);
            }
            break;
            
        case strpos($data, 'b_mc_') === 0:
            // b_mc_br_PACKAGE or b_mc_ph_PACKAGE
            $parts = explode('_', $data);
            if (count($parts) >= 4) {
                $region = $parts[2]; // br or ph
                $country = 'magicchessgogo_' . ($region == 'br' ? 'br' : 'php');
                $identifier = implode('_', array_slice($parts, 3));
                
                // Pass the identifier directly (ID or Name). showPaymentMethodSelection handles lookup.
                showPaymentMethodSelection($chatId, $telegramId, $country, $identifier, $messageId);
            }
            break;
            
        case strpos($data, 'p_') === 0:
            // p_CODE_PACKAGE_TYPE
            // p_ph_Diamond_c
            $parts = explode('_', $data);
            if (count($parts) >= 4) {
                $shortCode = $parts[1];
                $type = end($parts); // c or m
                $useMMK = ($type === 'm');
                
                // Reconstruct package name
                $packageParts = array_slice($parts, 2, -1);
                $packageName = implode('_', $packageParts);
                
                // Map short code back to country
                $reverseMap = [
                    'ph' => 'php',
                    'br' => 'br',
                    'pb' => 'pubg_br',
                    'pp' => 'pubg_php',
                    'hb' => 'hok_br',
                    'hp' => 'hok_php',
                    'mb' => 'magicchessgogo_br',
                    'mp' => 'magicchessgogo_php'
                ];
                
                $country = $reverseMap[$shortCode] ?? 'php';
                handlePurchase($chatId, $telegramId, $country, $packageName, $useMMK, $messageId);
            }
            break;

        case strpos($data, 'pay:') === 0:
            // Format: pay:currency:country:package
            // Example: pay:php:magicchessgogo_br:123
            $parts = explode(':', $data);
            if (count($parts) >= 4) {
                $currency = $parts[1]; // php, br, or mmk
                $country = $parts[2];
                $packageName = implode(':', array_slice($parts, 3)); // Join rest in case package name has colons
                
                $useMMK = ($currency === 'mmk');
                handlePurchase($chatId, $telegramId, $country, $packageName, $useMMK, $messageId);
            }
            break;

        case strpos($data, 'pay_php_') === 0:
            $parts = explode('_', $data);
            if (count($parts) >= 4) {
                $country = $parts[2];
                $packageName = implode('_', array_slice($parts, 3));
                handlePurchase($chatId, $telegramId, $country, $packageName, false, $messageId);
            }
            break;

        case strpos($data, 'pay_br_') === 0:
            $parts = explode('_', $data);
            if (count($parts) >= 4) {
                $country = $parts[2];
                $packageName = implode('_', array_slice($parts, 3));
                handlePurchase($chatId, $telegramId, $country, $packageName, false, $messageId);
            }
            break;
            
        case $data == 'confirm_order':
            confirmOrder($chatId, $telegramId, $messageId);
            break;
            
        case $data == 'cancel_order':
        case $data == 'cancel_purchase':
            cancelOrder($chatId, $telegramId, $messageId);
            break;
            
        case $data == 'pubg_back':
            showPubgRegionSelection($chatId, $telegramId, $messageId);
            break;
            
        case $data == 'hok_back':
            showHoKRegionSelection($chatId, $telegramId, $messageId);
            break;
            
        case $data == 'magicchessgogo_back':
            showMagicChessGoGoRegionSelection($chatId, $telegramId, $messageId);
            break;

        case $data == 'coming_soon_ff':
            answerCallbackQuery($callbackId, "🔥 Free Fire is coming soon!", true);
            break;

        case $data == 'more_games':
            answerCallbackQuery($callbackId, "🎮 More games are coming soon! Stay tuned.", true);
            break;
            
        case $data == 'mmk_topup_kpay':
            handleMMKTopUpMethod($chatId, $telegramId, 'kpay', $messageId);
            break;
            
        case $data == 'mmk_topup_wave':
            handleMMKTopUpMethod($chatId, $telegramId, 'wave', $messageId);
            break;
            
        case $data == 'mmk_topup_back':
            showMMKTopUpMenu($chatId, $telegramId, $messageId);
            break;
            
        case $data == 'back_to_games':
            showKeGamesMenu($chatId, $messageId);
            break;
            
        case $data == 'back_to_main':
            showUserMainMenu($chatId, $telegramId, $messageId); // This might send new message because main menu uses ReplyKeyboard
            // If we want to clean up, we could try to delete the messageId first
            // deleteMessage($chatId, $messageId); // Optional
            break;
            
        case $data == 'set_game_info':
            // Game Info feature removed
            sendMessage($chatId, "❌ Game Info feature has been removed. IDs are no longer saved.");
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
}
