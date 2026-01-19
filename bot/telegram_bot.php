<?php
require_once '../smile.php';

class TelegramBot {
    private $botToken;
    private $admins;
    private $smile;
    private $updateId = 0;
    
    public function __construct($botToken, $admins) {
        $this->botToken = $botToken;
        $this->admins = $admins;
        $this->smile = new SmileOne();
        
        echo "🤖 Telegram Bot Started\n";
        echo "Token: " . substr($botToken, 0, 10) . "...\n";
        echo "Admins: " . implode(', ', $admins) . "\n";
    }
    
    /**
     * Send message to Telegram
     */
    private function sendMessage($chatId, $text, $parseMode = 'HTML') {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Send balance message
     */
    public function sendBalance($chatId) {
        try {
            $balances = $this->smile->getBalanceAll();
            
            if (!$balances) {
                $this->sendMessage($chatId, "❌ Failed to get balances");
                return;
            }
            
            $message = "💰 <b>SmileOne Balance</b>\n";
            $message .= "====================\n";
            
            foreach ($balances as $country => $balance) {
                $message .= strtoupper($country) . ": <code>" . $balance . "</code>\n";
            }
            
            $message .= "\nLast Updated: " . date('Y-m-d H:i:s');
            
            $this->sendMessage($chatId, $message);
            
        } catch (Exception $e) {
            $this->sendMessage($chatId, "❌ Error: " . $e->getMessage());
        }
    }
    
    /**
     * Process recharge command
     */
    public function processRecharge($chatId, $params) {
        // Format: /recharge userId zoneId productName country
        if (count($params) < 4) {
            $this->sendMessage($chatId, "❌ Format: /recharge userId zoneId productName [country]");
            return;
        }
        
        $userId = $params[0];
        $zoneId = $params[1];
        $productName = $params[2];
        $country = $params[3] ?? null;
        $requester = "telegram_" . $chatId;
        
        $this->sendMessage($chatId, "⚡ Processing recharge...\nUser: $userId\nZone: $zoneId\nProduct: $productName");
        
        try {
            $result = $this->smile->recharge($userId, $zoneId, $productName, $requester, $country);
            
            if ($result && $result['success']) {
                $message = "✅ <b>Recharge Successful!</b>\n";
                $message .= "=====================\n";
                $message .= "👤 User: <code>" . $result['userId'] . "</code>\n";
                $message .= "🏷️ Zone: <code>" . $result['zoneId'] . "</code>\n";
                $message .= "🎮 Username: <code>" . ($result['username'] ?? 'N/A') . "</code>\n";
                $message .= "📦 Product: " . $result['productName'] . "\n";
                $message .= "🌍 Country: " . strtoupper($result['country']) . "\n";
                $message .= "🆔 Product IDs: <code>" . implode(', ', $result['productIds']) . "</code>\n";
                $message .= "👤 By: " . $result['requestedBy'] . "\n";
                $message .= "⏰ Time: " . $result['timestamp'] . "\n";
                
                $this->sendMessage($chatId, $message);
            } else {
                $this->sendMessage($chatId, "❌ Recharge failed: " . $this->smile->getLastError());
            }
            
        } catch (Exception $e) {
            $this->sendMessage($chatId, "❌ Error: " . $e->getMessage());
        }
    }
    
    /**
     * Show help message
     */
    public function sendHelp($chatId) {
        $message = "🤖 <b>SmileOne Bot Commands</b>\n";
        $message .= "======================\n";
        $message .= "/balance - Check SmileOne balance\n";
        $message .= "/recharge userId zoneId productName [country] - Recharge diamonds\n";
        $message .= "/products - List available products\n";
        $message .= "/help - Show this help\n";
        $message .= "\n<b>Examples:</b>\n";
        $message .= "<code>/recharge 1047489910 13194 \"DIAMOND 5\" br</code>\n";
        $message .= "<code>/recharge 1047489911 13195 \"DIAMOND 100\" php</code>\n";
        
        $this->sendMessage($chatId, $message);
    }
    
    /**
     * List available products
     */
    public function sendProducts($chatId) {
        try {
            $products = $this->smile->loadProducts();
            
            if (empty($products)) {
                $this->sendMessage($chatId, "❌ No products found");
                return;
            }
            
            $message = "📦 <b>Available Products</b>\n";
            $message .= "=====================\n";
            
            foreach ($products as $product) {
                $message .= "\n<b>" . $product['name'] . "</b>\n";
                $message .= "Country: " . strtoupper($product['country'] ?? 'br') . "\n";
                
                if (isset($product['id'])) {
                    if (is_array($product['id'])) {
                        $message .= "IDs: <code>" . implode(', ', $product['id']) . "</code>\n";
                    } else {
                        $message .= "ID: <code>" . $product['id'] . "</code>\n";
                    }
                }
                
                if (isset($product['price'])) {
                    $message .= "Price: $" . $product['price'] . "\n";
                }
            }
            
            $this->sendMessage($chatId, $message);
            
        } catch (Exception $e) {
            $this->sendMessage($chatId, "❌ Error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if user is admin
     */
    private function isAdmin($userId) {
        return in_array($userId, $this->admins);
    }
    
    /**
     * Get updates from Telegram
     */
    private function getUpdates() {
        $url = "https://api.telegram.org/bot{$this->botToken}/getUpdates?offset={$this->updateId}&timeout=30";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 35
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Process incoming messages
     */
    private function processMessage($message) {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Check if user is admin
        if (!$this->isAdmin($userId)) {
            $this->sendMessage($chatId, "❌ You are not authorized to use this bot.");
            return;
        }
        
        // Process commands
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            switch ($command) {
                case '/start':
                case '/help':
                    $this->sendHelp($chatId);
                    break;
                    
                case '/balance':
                    $this->sendBalance($chatId);
                    break;
                    
                case '/recharge':
                    $this->processRecharge($chatId, $params);
                    break;
                    
                case '/products':
                    $this->sendProducts($chatId);
                    break;
                    
                default:
                    $this->sendMessage($chatId, "❌ Unknown command. Use /help for commands list.");
                    break;
            }
        }
    }
    
    /**
     * Run bot in polling mode
     */
    public function run() {
        echo "🔄 Starting polling...\n";
        
        while (true) {
            try {
                $updates = $this->getUpdates();
                
                if ($updates['ok'] && !empty($updates['result'])) {
                    foreach ($updates['result'] as $update) {
                        $this->updateId = $update['update_id'] + 1;
                        
                        if (isset($update['message'])) {
                            $this->processMessage($update['message']);
                        }
                    }
                }
                
                sleep(1);
                
            } catch (Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
                sleep(5);
            }
        }
    }
}

// Read bot configuration from bot_config.json
function readAdminConfig() {
    $config_file = '../bot_config.json';
    $default_config = [
        'BOT_TOKEN' => '8324793821:AAGKOirtj6SdELfcEIxL5BPMGLIp69w_0P4',
        'ADMINS' => [7829183790]
    ];
    
    // Try to load from bot_config.json first
    if (file_exists($config_file)) {
        $config_content = file_get_contents($config_file);
        $bot_config = json_decode($config_content, true);
        if ($bot_config) {
            return [
                'BOT_TOKEN' => $bot_config['bot_token'] ?? $default_config['BOT_TOKEN'],
                'ADMINS' => $bot_config['admin_ids'] ?? $default_config['ADMINS']
            ];
        }
    }
    
    // Fallback: Try to read from admin.js (for backward compatibility)
    if (file_exists('admin.js')) {
        $content = file_get_contents('admin.js');
        
        // Simple regex to extract config
        if (preg_match('/BOT_TOKEN:\s*["\']([^"\']+)["\']/', $content, $tokenMatch)) {
            $default_config['BOT_TOKEN'] = $tokenMatch[1];
        }
        
        if (preg_match('/ADMINS:\s*\[([^\]]+)\]/', $content, $adminsMatch)) {
            $admins = explode(',', $adminsMatch[1]);
            $default_config['ADMINS'] = array_map('trim', $admins);
        }
    }
    
    return $default_config;
}

// Start bot
$config = readAdminConfig();
$bot = new TelegramBot($config['BOT_TOKEN'], $config['ADMINS']);
$bot->run();