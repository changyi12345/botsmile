<?php

// ==============================================
// 🛠 HELPER FUNCTIONS
// ==============================================

// MMK Conversion Functions
if (!function_exists('convertToMMK')) {
    function convertToMMK($price, $country) {
        $exchange_rates = [
            'brl_to_mmk' => 85.5,    // 1 BRL = 85.5 MMK
            'php_to_mmk' => 38.2,    // 1 PHP = 38.2 MMK
            'usd_to_mmk' => 2100.0   // 1 USD = 2100 MMK
        ];
        
        // Handle pubg_br, pubg_php, hok_br, hok_php
        if (strpos($country, 'pubg_br') === 0 || strpos($country, 'hok_br') === 0 || strpos($country, 'magicchessgogo_br') === 0) {
            return $price * $exchange_rates['brl_to_mmk'];
        } elseif (strpos($country, 'pubg_php') === 0 || strpos($country, 'hok_php') === 0 || strpos($country, 'magicchessgogo_php') === 0) {
            return $price * $exchange_rates['php_to_mmk'];
        }
        
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

// Escape Markdown Special Characters
if (!function_exists('escapeMarkdown')) {
    function escapeMarkdown($text) {
        if ($text === null) return '';
        // Escape characters that have special meaning in Markdown: _ * [ `
        return str_replace(
            ['_', '*', '`', '['], 
            ['\_', '\*', '\`', '\['], 
            $text
        );
    }
}

// Check in-game name via SmileOne (using cookies and user agent)
function checkInGameName($country, $gameId, $zoneId, $productId) {
    // Ensure SmileOne class is loaded
    $smileFile = __DIR__ . '/../../smile.php';
    
    // Check if file exists first
    if (!file_exists($smileFile)) {
        $errorMsg = "SmileOne file not found: " . $smileFile;
        error_log("❌ " . $errorMsg);
        return ['success' => false, 'error' => 'SmileOne class file not found'];
    }
    
    // Require the file if class doesn't exist
    if (!class_exists('SmileOne')) {
        require_once $smileFile;
    }
    
    // Double-check class exists after require
    if (!class_exists('SmileOne')) {
        $errorMsg = "SmileOne class not found after require. File: " . $smileFile;
        error_log("❌ " . $errorMsg);
        return ['success' => false, 'error' => 'SmileOne class not found'];
    }
    
    // Check if cookies file exists
    $cookiesFile = __DIR__ . '/../../cookies.json';
    if (!file_exists($cookiesFile)) {
        return ['success' => false, 'error' => 'Cookies file not found. Please set cookies in admin panel.'];
    }
    
    $cookiesContent = file_get_contents($cookiesFile);
    $cookies = json_decode($cookiesContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($cookies)) {
        return ['success' => false, 'error' => 'Invalid or empty cookies. Please update cookies in admin panel.'];
    }
    
    try {
        // Create SmileOne instance (it will load cookies and user agent automatically)
        $smile = new SmileOne($country);
        
        error_log("🔍 Verifying SmileOne instance with cookies and user agent for country: {$country}");
        
        // Verify cookies were loaded
        $reflection = new ReflectionClass($smile);
        $cookiesProperty = $reflection->getProperty('cookies');
        $cookiesProperty->setAccessible(true);
        $loadedCookies = $cookiesProperty->getValue($smile);
        
        if (empty($loadedCookies)) {
            $errorMsg = 'Cookies not loaded. Please check cookies.json file.';
            error_log("❌ " . $errorMsg);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Use reflection to access private checkRole method
        // This method uses cookies and user agent from SmileOne class
        $method = $reflection->getMethod('checkRole');
        $method->setAccessible(true);
        
        // For HoK and Pubg, zoneId should be empty
        $isHoK = (strpos($country, 'hok') === 0);
        $isPubg = (strpos($country, 'pubg') === 0);
        $isMagicChess = (strpos($country, 'magicchessgogo') === 0);
        
        if ($isHoK || $isPubg) {
            $zoneId = ''; // HoK: UID only, Pubg: Player ID only
        }
        
        $idType = $isHoK ? 'UID' : ($isPubg ? 'Player ID' : ($isMagicChess ? 'UID' : 'Game ID'));
        error_log("🔍 Checking {$idType}: {$gameId}, Zone ID: " . ($zoneId ?: 'N/A (not used)') . ", Product ID: {$productId}, Country: {$country}");
        
        // Call checkRole with cookies and user agent (handled by makeRequest in SmileOne class)
        $username = $method->invoke($smile, $gameId, $zoneId, $productId);
        
        if ($username && is_string($username) && strlen($username) > 0) {
            error_log("✅ Game ID verified successfully. Username: {$username}");
            return ['success' => true, 'username' => $username];
        } else {
            // Get error message from SmileOne
            $lastErrorProperty = $reflection->getProperty('lastError');
            $lastErrorProperty->setAccessible(true);
            $error = $lastErrorProperty->getValue($smile);
            
            if (empty($error)) {
                $error = 'Failed to verify in-game name. Please check Game ID and Zone ID.';
            }
            
            error_log("❌ Game ID verification failed: " . $error);
            
            return ['success' => false, 'error' => $error];
        }
    } catch (Exception $e) {
        $errorMsg = 'Exception: ' . $e->getMessage();
        error_log("❌ Exception in checkInGameName: " . $errorMsg);
        return ['success' => false, 'error' => $errorMsg];
    } catch (Error $e) {
        $errorMsg = 'Fatal Error: ' . $e->getMessage();
        error_log("❌ Fatal Error in checkInGameName: " . $errorMsg);
        return ['success' => false, 'error' => $errorMsg];
    }
}

// ==============================================
// 🔄 COMPATIBILITY WRAPPERS
// ==============================================

if (!function_exists('buyProduct')) {
    function buyProduct($country, $gameId, $zoneId, $productId) {
        // Ensure SmileOne is available
        $smileFile = __DIR__ . '/../../smile.php';
        if (!class_exists('SmileOne')) {
            if (file_exists($smileFile)) {
                require_once $smileFile;
            } else {
                return ['success' => false, 'message' => 'SmileOne API file not found'];
            }
        }
        
        try {
            // Instantiate with country
            $smile = new SmileOne($country);
            
            // Call recharge
            // Note: recharge($userId, $zoneId, $productName, $requestedBy = 'system', $country = null)
            $result = $smile->recharge($gameId, $zoneId, $productId, 'bot', $country);
            
            if ($result && isset($result['success']) && $result['success']) {
                // Extract order ID from the first result
                $orderId = 'N/A';
                if (!empty($result['results'][0]['order_id'])) {
                    $orderId = $result['results'][0]['order_id'];
                } elseif (!empty($result['results'][0]['result']['order_id'])) {
                     $orderId = $result['results'][0]['result']['order_id'];
                }
                
                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'message' => 'Success',
                    'details' => $result
                ];
            } else {
                // Get error from SmileOne instance
                $error = $smile->getLastError();
                return [
                    'success' => false,
                    'message' => $error ?: 'Unknown error',
                    'details' => $result
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}

if (!function_exists('redeemCode')) {
    function redeemCode($telegramId, $code) {
        // Wrapper for redeemTopupCode in database.php
        // redeemTopupCode($code, $telegramId) returns array
        
        if (!function_exists('redeemTopupCode')) {
            return ['success' => false, 'error' => 'redeemTopupCode function not found'];
        }
        
        $result = redeemTopupCode($code, $telegramId);
        
        if ($result['success']) {
            // Fetch fresh user data to get both balances if needed
            if (function_exists('getUser')) {
                $user = getUser($telegramId);
                $result['new_balance_php'] = $user['balance_php'] ?? 0;
                $result['new_balance_br'] = $user['balance_br'] ?? 0;
            }
            
            // Map keys expected by game_handler.php
            $result['amount'] = $result['original_amount'];
            $result['final_amount'] = $result['net_amount'];
            
            // Fix commission rate (DB stores as %, handler expects fraction)
            if (isset($result['commission_rate']) && $result['commission_rate'] > 1) {
                $result['commission_rate'] = $result['commission_rate'] / 100;
            }
        } else {
             // Map error key
             if (isset($result['message']) && !isset($result['error'])) {
                 $result['error'] = $result['message'];
             }
        }
        
        return $result;
    }
}

if (!function_exists('logTransaction')) {
    function logTransaction($userId, $type, $amount, $country, $details) {
        // Wrapper for saveTransaction in database.php
        // saveTransaction($userId, $type, $country, $amount, $details = '')
        
        if (!function_exists('saveTransaction')) {
            return false;
        }
        
        return saveTransaction($userId, $type, $country, $amount, $details);
    }
}
