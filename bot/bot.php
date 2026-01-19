<?php
/**
 * 🎮 SmileOne Bot - Main Entry Point
 * 
 * This file handles the bot startup, webhook cleanup, and main polling loop.
 * It delegates logic to handlers in bot/handlers/.
 */

// ==============================================
// 🚀 INITIALIZATION
// ==============================================

// Error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('Asia/Yangon');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log function
$log = function($msg) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$msg}\n";
    $logFile = __DIR__ . '/bot_log.txt';
    @file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
};

$log("🤖 Starting SmileOne Bot...");

// Write PID file
$pidFile = __DIR__ . '/bot.pid';
file_put_contents($pidFile, getmypid());
$log("✅ PID file created: " . getmypid());

// Load Configuration
$configFile = __DIR__ . '/../config.php';
if (file_exists($configFile)) {
    require_once $configFile;
    $log("✅ Loaded config.php");
} else {
    $log("⚠️ config.php not found in parent directory");
}

// Load Bot Configuration
$botConfigFile = __DIR__ . '/../bot_config.json';
$BOT_TOKEN = "8324793821:AAGKOirtj6SdELfcEIxL5BPMGLIp69w_0P4"; // Default fallback
$ADMINS = [7829183790]; // Default fallback

if (file_exists($botConfigFile)) {
    $configContent = @file_get_contents($botConfigFile);
    if ($configContent !== false) {
        $botConfig = json_decode($configContent, true);
        if ($botConfig && !empty($botConfig['bot_token'])) {
            $BOT_TOKEN = $botConfig['bot_token'];
        }
        if ($botConfig && !empty($botConfig['admin_ids']) && is_array($botConfig['admin_ids'])) {
            $ADMINS = array_map(function($id) {
                return is_numeric($id) ? (int)$id : $id;
            }, $botConfig['admin_ids']);
        }
        $log("✅ Loaded bot_config.json");
    }
}

// ==============================================
// 📦 LOAD MODULES
// ==============================================

// Include modules
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/telegram_api.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/handlers/game_handler.php';
require_once __DIR__ . '/handlers/admin_handler.php';
require_once __DIR__ . '/handlers/update_handler.php';

$log("✅ All modules loaded successfully");

// ==============================================
// 🔄 STARTUP CHECKS
// ==============================================

// Verify Bot Token
function getBotInfo() {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/getMe";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$botInfo = getBotInfo();
if ($botInfo && isset($botInfo['ok']) && $botInfo['ok']) {
    $botUser = $botInfo['result']['username'];
    $log("✅ Bot connected successfully: @{$botUser}");
} else {
    $log("❌ Failed to connect to Telegram API. Check BOT_TOKEN.");
    $log("Response: " . json_encode($botInfo));
    // Don't exit, let it try loop, maybe temporary network issue
}

// Clear Webhook first to avoid 409 Conflict
$log("🔄 Deleting webhook to switch to polling mode...");
$deleteResult = deleteWebhook();
if ($deleteResult && ($deleteResult['ok'] ?? false)) {
    $log("✅ Webhook deleted successfully");
} else {
    $log("⚠️ Failed to delete webhook: " . json_encode($deleteResult));
}

$log("🚀 Bot is now running in POLLING mode. Press Ctrl+C to stop.");

// Initialize offset
$offsetFile = __DIR__ . '/last_update_id.txt';
$offset = 0;
if (file_exists($offsetFile)) {
    $offset = (int)file_get_contents($offsetFile);
}

// Main Polling Loop
$loopCount = 0;
while (true) {
    try {
        // Log heartbeat every 60 iterations (approx 30 seconds)
        $loopCount++;
        if ($loopCount % 60 === 0) {
            // Check if still running properly
            // $log("💓 Heartbeat - Offset: {$offset}");
        }

        // Get updates
        $updates = getUpdates($offset);
        
        if ($updates === false) {
             $log("⚠️ Connection error in getUpdates");
        } elseif (isset($updates['ok']) && !$updates['ok']) {
             $log("❌ API Error in getUpdates: " . ($updates['description'] ?? 'Unknown error'));
             // Sleep longer on error
             sleep(5);
        } elseif ($updates && isset($updates['ok']) && $updates['ok'] && !empty($updates['result'])) {
            $log("📥 Received " . count($updates['result']) . " update(s)");
            
            foreach ($updates['result'] as $update) {
                $updateId = $update['update_id'];
                
                // Process Message
                if (isset($update['message'])) {
                    $message = $update['message'];
                    $chatId = $message['chat']['id'];
                    $text = $message['text'] ?? '';
                    $telegramId = $message['from']['id'];
                    $firstName = $message['from']['first_name'] ?? 'Unknown';
                    $username = $message['from']['username'] ?? '';
                    
                    $log("📨 Processing message from {$firstName} ({$telegramId}): {$text}");

                    // Register/Update User
                    if (!userExists($telegramId)) {
                        registerUser($telegramId, $username, $firstName);
                        $log("👤 New user registered: {$firstName} ({$telegramId})");
                    }
                    
                    // Handle Photo (for MMK Topup Screenshot)
                    if (isset($message['photo'])) {
                        // Get the largest photo (last in array)
                        $photo = end($message['photo']);
                        $fileId = $photo['file_id'];
                        
                        // Check if user has pending MMK topup
                        $pending = getPendingPurchase($telegramId);
                        if ($pending && ($pending['action'] ?? '') === 'mmk_topup_screenshot') {
                            handleMMKTopUpScreenshot($chatId, $telegramId, $fileId);
                        }
                    } 
                    // Handle Text
                    elseif (!empty($text)) {
                        processMessage($chatId, $telegramId, $text);
                    }
                }
                
                // Process Callback Query
                if (isset($update['callback_query'])) {
                    processCallbackQuery($update['callback_query']);
                }
                
                // Update offset
                $offset = $updateId + 1;
                file_put_contents($offsetFile, $offset);
            }
        }
        
    } catch (Exception $e) {
        $log("❌ Exception in main loop: " . $e->getMessage());
    }
    
    // Sleep to prevent high CPU usage
    usleep(500000); // 0.5 seconds
}
