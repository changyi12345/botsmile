<?php
// FATAL ERROR HANDLER
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Fatal Error',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        exit();
    }
});

// Admin API - RESTful endpoint for admin panel operations
// Error handling - prevent fatal errors from causing 502
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set default timeout (only if not in safe mode)
if (function_exists('set_time_limit')) {
    @set_time_limit(300); // 5 minutes timeout
}

// Start output buffering to catch errors
if (!ob_get_level()) {
    ob_start();
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    // Session error - return error response
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Session error', 'message' => $e->getMessage()]);
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Please login first']);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

// Include admin config for helper functions
require_once __DIR__ . '/../core/admin_config.php';

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle different actions
switch ($action) {
    
    // ========== BOT MANAGEMENT ==========
    
    case 'delete_webhook':
        try {
            // Load bot config
            $bot_config_file = ROOT_DIR . '/bot_config.json';
            $bot_config = readJsonFile($bot_config_file);
            $bot_token = $bot_config['bot_token'] ?? '';
            
            if (empty($bot_token)) {
                echo json_encode(['success' => false, 'message' => 'Bot token not configured']);
                break;
            }
            
            // Delete webhook
            $webhook_url = "https://api.telegram.org/bot{$bot_token}/deleteWebhook";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $webhook_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['drop_pending_updates' => true]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($http_code === 200) {
                $result = json_decode($response, true);
                if ($result && isset($result['ok']) && $result['ok']) {
                    echo json_encode(['success' => true, 'message' => 'Webhook deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete webhook: ' . ($result['description'] ?? 'Unknown error')]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'HTTP Error: ' . $http_code . ($curl_error ? ' - ' . $curl_error : '')]);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete webhook', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'get_bot_status':
        try {
            // Set timeout for this operation (only if allowed)
            if (function_exists('set_time_limit')) {
                @set_time_limit(5); // Max 5 seconds
            }
            
            // Cloudflare-friendly headers
            header('Cache-Control: no-cache, must-revalidate');
            header('X-Accel-Buffering: no');
            
            $bot_running = false;
            $bot_pid = null;
            $pid_file = ROOT_DIR . '/bot/bot.pid';
            $last_activity = 'N/A';
            
            if (file_exists($pid_file)) {
                $bot_pid = trim(@file_get_contents($pid_file));
                // Just check if PID file exists and contains valid number
                $bot_running = !empty($bot_pid) && is_numeric($bot_pid);
            }
            
            // Extract last activity from bot logs (read last few lines)
            $log_file = ROOT_DIR . '/bot/bot_log.txt';
            if (file_exists($log_file)) {
                try {
                    $file_size = filesize($log_file);
                    $read_size = min(2000, $file_size); // Read last 2KB for performance
                    $handle = @fopen($log_file, 'r');
                    if ($handle) {
                        if ($file_size > $read_size) {
                            fseek($handle, -$read_size, SEEK_END);
                            // Skip partial first line
                            fgets($handle);
                        }
                        $log_content = @stream_get_contents($handle);
                        fclose($handle);
                        
                        if (!empty($log_content)) {
                            // Extract timestamps from log lines (format: [2026-01-16 01:55:57] ...)
                            $lines = explode("\n", $log_content);
                            $lines = array_filter($lines, function($line) {
                                return !empty(trim($line));
                            });
                            
                            if (!empty($lines)) {
                                // Get last line with timestamp
                                $last_line = end($lines);
                                if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $last_line, $matches)) {
                                    $last_activity = $matches[1];
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Silently fail - keep N/A if can't read logs
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'running' => $bot_running,
                    'pid' => $bot_pid,
                    'last_activity' => $last_activity,
                    'last_log' => '' // Logs loaded separately via get_bot_logs
                ]
            ]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get bot status', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'get_bot_logs':
        try {
            // Set timeout for log reading (only if allowed)
            if (function_exists('set_time_limit')) {
                @set_time_limit(3); // Max 3 seconds
            }
            
            $log_file = ROOT_DIR . '/bot/bot_log.txt';
            $logs = '';
            
            if (file_exists($log_file)) {
                // Read only last 50KB for performance
                $file_size = filesize($log_file);
                $read_size = min(50000, $file_size);
                $handle = @fopen($log_file, 'r');
                if ($handle) {
                    if ($file_size > $read_size) {
                        fseek($handle, -$read_size, SEEK_END);
                    }
                    $logs = @stream_get_contents($handle);
                    fclose($handle);
                }
            }
            
            // Sanitize logs for valid UTF-8 to prevent json_encode failure
            $logs = mb_convert_encoding($logs, 'UTF-8', 'UTF-8');
            
            $json = json_encode([
                'success' => true,
                'data' => [
                    'logs' => $logs ?: 'No logs available'
                ]
            ]);
            
            if ($json === false) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'JSON Encoding Error', 
                    'message' => json_last_error_msg()
                ]);
            } else {
                echo $json;
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get bot logs', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'start_bot':
        try {
            @set_time_limit(30); // Increase timeout for this long-running action
            if (!function_exists('exec') || !function_exists('popen') || !function_exists('pclose')) {
                echo json_encode(['success' => false, 'message' => 'Required functions (exec, popen, pclose) are disabled on the server. Cannot start bot.']);
                break;
            }
            $pid_file = ROOT_DIR . '/bot/bot.pid';
            
            // Check if bot is already running
            if (file_exists($pid_file)) {
                $pid = trim(@file_get_contents($pid_file));
                if (!empty($pid) && is_numeric($pid)) {
                    echo json_encode(['success' => false, 'message' => 'Bot is already running']);
                    break;
                }
            }
            
            // Load bot config to get token
            $bot_config_file = ROOT_DIR . '/bot_config.json';
            $bot_config = readJsonFile($bot_config_file);
            $bot_token = $bot_config['bot_token'] ?? '';
            
            // Delete webhook first to avoid HTTP 409 conflict (polling vs webhook)
            if (!empty($bot_token)) {
                $webhook_url = "https://api.telegram.org/bot{$bot_token}/deleteWebhook";
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $webhook_url,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['drop_pending_updates' => true]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 5
                ]);
                @curl_exec($ch);
                curl_close($ch);
            }
            
            // Start bot process
            $bot_script = ROOT_DIR . '/bot/bot.php';
            if (!file_exists($bot_script)) {
                echo json_encode(['success' => false, 'message' => 'Bot script not found']);
                break;
            }
            
            // Determine OS and start bot accordingly
            $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            
            if ($is_windows) {
                // Windows: Start in background using WScript to get proper PID
                $wscript_file = sys_get_temp_dir() . '/start_bot_' . time() . '.vbs';
                $vbs_content = 'Set objShell = CreateObject("WScript.Shell")' . "\n";
                $vbs_content .= 'Set objExec = objShell.Exec("php \"' . str_replace('\\', '\\\\', realpath($bot_script)) . '\"")' . "\n";
                $vbs_content .= 'WScript.Echo objExec.ProcessID' . "\n";
                file_put_contents($wscript_file, $vbs_content);
                
                // Try to get PID from WScript
                $output = [];
                exec('cscript //nologo "' . $wscript_file . '"', $output);
                @unlink($wscript_file);
                
                // If WScript method failed, use start /B (PID will be created by bot itself)
                if (empty($output[0]) || !is_numeric($output[0])) {
                    $command = 'start /B php "' . realpath($bot_script) . '" > nul 2>&1';
                    pclose(popen($command, 'r'));
                } else {
                    // Save PID from WScript
                    file_put_contents($pid_file, trim($output[0]));
                }
            } else {
                // Linux/Unix: Use nohup
                $command = 'nohup php "' . realpath($bot_script) . '" > /dev/null 2>&1 & echo $!';
                $output = [];
                exec($command, $output);
                // If we got a PID, save it immediately
                if (!empty($output[0]) && is_numeric($output[0])) {
                    file_put_contents($pid_file, trim($output[0]));
                }
            }
            
            // Wait a moment for PID file to be created (bot creates it on startup)
            $max_wait = 10; // Wait up to 10 seconds
            $waited = 0;
            while (!file_exists($pid_file) && $waited < $max_wait) {
                sleep(1);
                $waited++;
            }
            
            if (file_exists($pid_file)) {
                $pid = trim(@file_get_contents($pid_file));
                if (!empty($pid) && is_numeric($pid)) {
                    echo json_encode(['success' => true, 'message' => 'Bot started successfully', 'pid' => $pid]);
                } else {
                    echo json_encode(['success' => true, 'message' => 'Bot started (checking status...)']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Bot may have failed to start. Check logs.']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to start bot', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'stop_bot':
        try {
            @set_time_limit(30); // Increase timeout for this long-running action
            $pid_file = ROOT_DIR . '/bot/bot.pid';
            $bot_script = ROOT_DIR . '/bot/bot.php';
            
            if (!file_exists($pid_file)) {
                // Try to find and kill by script name as fallback
                $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
                $script_path = realpath($bot_script);
                
                if ($is_windows && $script_path) {
                    // Windows: Kill all PHP processes running bot.php
                    $command = 'taskkill /F /FI "WINDOWTITLE eq php*" /FI "COMMANDLINE eq *bot.php*" 2>nul';
                    @shell_exec($command);
                    // Also try with wmic
                    $command2 = 'wmic process where "commandline like \'%bot.php%\'" delete 2>nul';
                    @shell_exec($command2);
                } else if ($script_path) {
                    // Linux: Kill by script path
                    $command = 'pkill -f "php.*bot.php" 2>/dev/null';
                    @shell_exec($command);
                }
                
                echo json_encode(['success' => true, 'message' => 'Bot stopped (PID file not found, killed by script name)']);
                break;
            }
            
            $pid = trim(@file_get_contents($pid_file));
            if (empty($pid) || !is_numeric($pid)) {
                @unlink($pid_file);
                echo json_encode(['success' => false, 'message' => 'Invalid PID file']);
                break;
            }
            
            // Determine OS and stop bot accordingly
            $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $pid = intval($pid);
            
            if ($is_windows) {
                // Windows: Try multiple methods to ensure process is killed
                // Method 1: taskkill by PID
                $command1 = 'taskkill /F /PID ' . $pid . ' 2>nul';
                @shell_exec($command1);
                
                // Method 2: Also kill by script name (in case PID is wrong)
                $script_path = realpath($bot_script);
                if ($script_path) {
                    $command2 = 'taskkill /F /FI "WINDOWTITLE eq php*" /FI "COMMANDLINE eq *bot.php*" 2>nul';
                    @shell_exec($command2);
                }
                
                // Method 3: Use wmic as fallback
                $command3 = 'wmic process where "processid=' . $pid . '" delete 2>nul';
                @shell_exec($command3);
            } else {
                // Linux/Unix: Use kill
                $command = 'kill ' . $pid . ' 2>/dev/null';
                @shell_exec($command);
                
                // Fallback: kill by script name
                $script_path = realpath($bot_script);
                if ($script_path) {
                    $command2 = 'pkill -f "php.*bot.php" 2>/dev/null';
                    @shell_exec($command2);
                }
            }
            
            // Wait a moment to ensure process is killed
            sleep(2);
            
            // Verify process is actually stopped
            $process_running = false;
            if ($is_windows) {
                $check_command = 'tasklist /FI "PID eq ' . $pid . '" 2>nul | find "' . $pid . '"';
                $output = @shell_exec($check_command);
                $process_running = !empty($output);
            } else {
                $check_command = 'ps -p ' . $pid . ' > /dev/null 2>&1';
                exec($check_command, $output, $return_var);
                $process_running = ($return_var === 0);
            }
            
            // Remove PID file
            if (file_exists($pid_file)) {
                @unlink($pid_file);
            }
            
            if ($process_running) {
                echo json_encode(['success' => false, 'message' => 'Bot process may still be running. Please check manually.']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Bot stopped successfully']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to stop bot', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'save_bot_config':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                echo json_encode(['success' => false, 'message' => 'Invalid request data']);
                break;
            }
            
            $bot_token = trim($input['bot_token'] ?? '');
            $admin_ids_text = trim($input['admin_ids'] ?? '');
            $check_interval = intval($input['check_interval'] ?? 30);
            $max_retries = intval($input['max_retries'] ?? 3);
            $min_topup = floatval($input['min_topup'] ?? 10);
            $max_topup = floatval($input['max_topup'] ?? 10000);
            
            // Validate
            if (empty($bot_token)) {
                echo json_encode(['success' => false, 'message' => 'Bot token is required']);
                break;
            }
            
            // Parse admin IDs (newline-separated or comma-separated)
            $admin_ids = [];
            if (!empty($admin_ids_text)) {
                // Support both newline and comma separation
                $lines = preg_split('/[\n\r,]+/', $admin_ids_text);
                foreach ($lines as $line) {
                    $id = trim($line);
                    // Telegram user IDs are numeric strings (can be very large)
                    if (!empty($id) && is_numeric($id) && strlen($id) > 0) {
                        // Store as string to preserve large IDs
                        $admin_ids[] = (string)$id;
                    }
                }
            }
            
            if (empty($admin_ids)) {
                echo json_encode(['success' => false, 'message' => 'At least one admin ID is required']);
                break;
            }
            
            // Prepare config
            $config = [
                'bot_token' => $bot_token,
                'admin_ids' => $admin_ids,
                'check_interval' => max(5, min(300, $check_interval)),
                'max_retries' => max(1, min(10, $max_retries)),
                'min_topup' => max(0.01, $min_topup),
                'max_topup' => max($min_topup, $max_topup)
            ];
            
            // Save to bot_config.json
            $config_file = ROOT_DIR . '/bot_config.json';
            if (writeJsonFile($config_file, $config)) {
                echo json_encode(['success' => true, 'message' => 'Bot configuration saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save configuration']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save bot config', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'get_bot_config':
        try {
            $config_file = ROOT_DIR . '/bot_config.json';
            $config = readJsonFile($config_file);
            
            if (empty($config)) {
                // Default configuration
                $config = [
                    'bot_token' => '',
                    'admin_ids' => [],
                    'check_interval' => 30,
                    'max_retries' => 3,
                    'min_topup' => 10,
                    'max_topup' => 10000
                ];
            }
            
            echo json_encode(['success' => true, 'data' => $config]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get bot config', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'process_order':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $order_id = $input['order_id'] ?? '';
            
            if (empty($order_id)) {
                echo json_encode(['success' => false, 'message' => 'Order ID is required']);
                break;
            }
            
            $orders = readJsonFile(ROOT_DIR . '/orders.json');
            $order_found = false;
            
            foreach ($orders as &$order) {
                if (($order['id'] ?? '') === $order_id) {
                    $order['status'] = 'processing';
                    $order['processed_at'] = date('Y-m-d H:i:s');
                    $order_found = true;
                    break;
                }
            }
            
            if ($order_found) {
                if (writeJsonFile(ROOT_DIR . '/orders.json', $orders)) {
                    echo json_encode(['success' => true, 'message' => 'Order processed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update order']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Order not found']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to process order', 'message' => $e->getMessage()]);
        }
        break;
    
    // ========== PRODUCT MANAGEMENT ==========
    
    case 'get_products':
        try {
            $products = readJsonFile(ROOT_DIR . '/products.json');
            echo json_encode(['success' => true, 'data' => $products]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get products', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'add_product':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                echo json_encode(['success' => false, 'message' => 'Invalid request data']);
                break;
            }
            
            $products = readJsonFile(ROOT_DIR . '/products.json');
            
            // Generate new product ID
            $new_id = 'PROD' . str_pad(count($products) + 1, 6, '0', STR_PAD_LEFT);
            
            // Handle both old format (direct fields) and new format (nested in 'product')
            $productData = isset($input['product']) ? $input['product'] : $input;
            
            $product = [
                'id' => $new_id,
                'name' => trim($productData['name'] ?? ''),
                'description' => trim($productData['description'] ?? ''),
                'price' => floatval($productData['price'] ?? 0),
                'country' => strtolower(trim($productData['country'] ?? 'br')),
                'currency' => $productData['currency'] ?? 'USD',
                'available' => isset($productData['available']) ? (bool)$productData['available'] : true,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Add products array if provided
            if (isset($productData['products']) && is_array($productData['products'])) {
                $product['products'] = $productData['products'];
            }
            
            // Add MMK price if provided
            if (isset($productData['mmk_price']) && $productData['mmk_price'] !== null && $productData['mmk_price'] !== '') {
                $product['mmk_price'] = floatval($productData['mmk_price']);
            }
            
            // Validate
            if (empty($product['name'])) {
                echo json_encode(['success' => false, 'message' => 'Product name is required']);
                break;
            }
            
            $products[] = $product;
            
            if (writeJsonFile(ROOT_DIR . '/products.json', $products)) {
                echo json_encode(['success' => true, 'message' => 'Product added successfully', 'data' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save product']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to add product', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'edit_product':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $index = intval($input['index'] ?? -1);
            
            if ($index < 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product index']);
                break;
            }
            
            $products = readJsonFile(ROOT_DIR . '/products.json');
            
            if (!isset($products[$index])) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                break;
            }
            
            // Update product
            if (isset($input['product'])) {
                $productData = $input['product'];
                if (isset($productData['name'])) $products[$index]['name'] = trim($productData['name']);
                if (isset($productData['description'])) $products[$index]['description'] = trim($productData['description']);
                if (isset($productData['price'])) $products[$index]['price'] = floatval($productData['price']);
                if (isset($productData['country'])) $products[$index]['country'] = strtolower(trim($productData['country']));
                if (isset($productData['currency'])) $products[$index]['currency'] = $productData['currency'];
                if (isset($productData['available'])) $products[$index]['available'] = (bool)$productData['available'];
                if (isset($productData['products'])) $products[$index]['products'] = $productData['products'];
                if (isset($productData['mmk_price'])) $products[$index]['mmk_price'] = floatval($productData['mmk_price']);
            } else {
                // Legacy support
                if (isset($input['name'])) $products[$index]['name'] = trim($input['name']);
                if (isset($input['description'])) $products[$index]['description'] = trim($input['description']);
                if (isset($input['price'])) $products[$index]['price'] = floatval($input['price']);
                if (isset($input['country'])) $products[$index]['country'] = strtolower(trim($input['country']));
                if (isset($input['currency'])) $products[$index]['currency'] = $input['currency'];
                if (isset($input['available'])) $products[$index]['available'] = (bool)$input['available'];
                if (isset($input['mmk_price'])) $products[$index]['mmk_price'] = floatval($input['mmk_price']);
            }
            $products[$index]['updated_at'] = date('Y-m-d H:i:s');
            
            if (writeJsonFile(ROOT_DIR . '/products.json', $products)) {
                echo json_encode(['success' => true, 'message' => 'Product updated successfully', 'data' => $products[$index]]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update product']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to edit product', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'delete_product':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $index = intval($input['index'] ?? -1);
            
            if ($index < 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product index']);
                break;
            }
            
            $products = readJsonFile(ROOT_DIR . '/products.json');
            
            if (!isset($products[$index])) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                break;
            }
            
            // Remove product
            array_splice($products, $index, 1);
            
            if (writeJsonFile(ROOT_DIR . '/products.json', $products)) {
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete product', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'sync_products':
        try {
            // Increase execution time for product sync (may take longer)
            // With delays and multiple games/countries, need more time
            if (function_exists('set_time_limit')) {
                @set_time_limit(180); // 180 seconds (3 minutes) for product sync (increased for magicchessgogo)
            }
            
            // Include SmileOne class
            require_once ROOT_DIR . '/smile.php';
            
            // Check if cookies file exists
            $cookies_file = ROOT_DIR . '/cookies.json';
            if (!file_exists($cookies_file)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Cookies file not found', 
                    'message' => 'Please set cookies in Cookie Management first. cookies.json file is missing.'
                ]);
                break;
            }
            
            // Verify cookies are valid JSON
            $cookies_content = @file_get_contents($cookies_file);
            $cookies_data = json_decode($cookies_content, true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($cookies_data)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Invalid cookies', 
                    'message' => 'Cookies file is invalid or empty. Please update cookies in Cookie Management.'
                ]);
                break;
            }
            
            // Create SmileOne instance (uses cookies and user agent automatically)
            $smile = new SmileOne();
            
            // Verify cookies were loaded
            $reflection = new ReflectionClass($smile);
            $cookiesProperty = $reflection->getProperty('cookies');
            $cookiesProperty->setAccessible(true);
            $loadedCookies = $cookiesProperty->getValue($smile);
            
            if (empty($loadedCookies)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Cookies not loaded', 
                    'message' => 'Cookies file exists but could not be loaded. Please check cookies.json format.'
                ]);
                break;
            }
            
            error_log("🔄 Starting product sync with " . count($loadedCookies) . " cookies");
            
            // Fetch all products from SmileOne website (uses cookies and user agent)
            $fetchedProducts = $smile->syncAllProducts();
            
            // Check for DNS errors
            $lastError = $smile->getLastError();
            if (strpos($lastError, 'DNS Error') !== false || strpos($lastError, 'Could not resolve host') !== false) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'DNS Error', 
                    'message' => 'Could not connect to SmileOne website. Please check your internet connection and DNS settings. Error: ' . $lastError
                ]);
                break;
            }
            
            // Log for debugging
            error_log("✅ Fetched products count: " . count($fetchedProducts));
            $lastError = $smile->getLastError();
            if (!empty($lastError)) {
                error_log("⚠️ Last error: " . $lastError);
            }
            
            if (empty($fetchedProducts)) {
                // Return helpful error message
                $errorMsg = $lastError ?: 'No products found. Please check cookies and website access.';
                echo json_encode([
                    'success' => false, 
                    'error' => 'No products found', 
                    'message' => 'Could not fetch products from SmileOne website. Error: ' . $errorMsg . 
                                ' Make sure cookies.json contains valid cookies and you can access SmileOne website.'
                ]);
                break;
            }
            
            // Load existing products
            $existingProducts = readJsonFile(ROOT_DIR . '/products.json');
            $existingMap = [];
            foreach ($existingProducts as $product) {
                $key = ($product['country'] ?? '') . '_' . ($product['name'] ?? '');
                $existingMap[$key] = $product;
            }
            
            // Merge fetched products with existing ones (update existing, add new)
            $mergedProducts = [];
            foreach ($fetchedProducts as $fetched) {
                $key = ($fetched['country'] ?? '') . '_' . ($fetched['name'] ?? '');
                
                if (isset($existingMap[$key])) {
                    // Update existing product with new data
                    $existing = $existingMap[$key];
                    $mergedProducts[] = array_merge($existing, [
                        'price' => $fetched['price'] ?? $existing['price'] ?? 0,
                        'products' => !empty($fetched['products']) ? $fetched['products'] : ($existing['products'] ?? []),
                        'diamonds' => $fetched['diamonds'] ?? $existing['diamonds'] ?? null,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'fetched_at' => $fetched['fetched_at'] ?? null
                    ]);
                    unset($existingMap[$key]);
                } else {
                    // Add new product
                    $mergedProducts[] = $fetched;
                }
            }
            
            // Add remaining existing products that weren't updated
            foreach ($existingMap as $product) {
                $mergedProducts[] = $product;
            }
            
            // Save merged products
            $saved = writeJsonFile(ROOT_DIR . '/products.json', $mergedProducts);
            
            if ($saved) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Products synced successfully! Found ' . count($fetchedProducts) . ' products.',
                    'count' => count($fetchedProducts),
                    'total' => count($mergedProducts)
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Failed to save products', 
                    'message' => 'Could not save products to file'
                ]);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to sync products', 'message' => $e->getMessage()]);
            exit();
        }
        break;
    
    // ========== USER MANAGEMENT ==========
    
    case 'get_users':
        try {
            $users = readJsonFile(ROOT_DIR . '/users.json');
            echo json_encode(['success' => true, 'data' => $users]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get users', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'get_user':
        try {
            $telegram_id = $_GET['telegram_id'] ?? '';
            
            if (empty($telegram_id)) {
                echo json_encode(['success' => false, 'message' => 'Telegram ID is required']);
                break;
            }
            
            $users = readJsonFile(ROOT_DIR . '/users.json');
            
            foreach ($users as $user) {
                if (($user['telegram_id'] ?? '') == $telegram_id || ($user['telegram_id'] ?? '') === $telegram_id) {
                    echo json_encode(['success' => true, 'data' => $user]);
                    exit();
                }
            }
            
            echo json_encode(['success' => false, 'message' => 'User not found']);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get user', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'update_user':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $telegram_id = $input['telegram_id'] ?? '';
            
            if (empty($telegram_id)) {
                echo json_encode(['success' => false, 'message' => 'Telegram ID is required']);
                break;
            }
            
            $users = readJsonFile(ROOT_DIR . '/users.json');
            $user_found = false;
            
            foreach ($users as &$user) {
                if (($user['telegram_id'] ?? '') == $telegram_id || ($user['telegram_id'] ?? '') === $telegram_id) {
                    // Update user fields
                    if (isset($input['username'])) $user['username'] = trim($input['username']);
                    if (isset($input['balance_php'])) $user['balance_php'] = floatval($input['balance_php']);
                    if (isset($input['balance_br'])) $user['balance_br'] = floatval($input['balance_br']);
                    if (isset($input['balance'])) $user['balance'] = floatval($input['balance']); // Legacy support
                    if (isset($input['name'])) $user['name'] = trim($input['name']); // Legacy support
                    if (isset($input['is_active'])) {
                        $user['is_active'] = (bool)$input['is_active'];
                    } elseif (isset($input['active'])) {
                        $user['is_active'] = (bool)$input['active']; // Legacy support
                    }
                    $user['updated_at'] = date('Y-m-d H:i:s');
                    $user_found = true;
                    break;
                }
            }
            
            if ($user_found) {
                if (writeJsonFile(ROOT_DIR . '/users.json', $users)) {
                    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update user']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update user', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'add_user':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $telegram_id = $input['telegram_id'] ?? '';
            
            if (empty($telegram_id)) {
                echo json_encode(['success' => false, 'message' => 'Telegram ID is required']);
                break;
            }
            
            $users = readJsonFile(ROOT_DIR . '/users.json');
            
            // Check if user already exists
            foreach ($users as $user) {
                if (($user['telegram_id'] ?? '') == $telegram_id || ($user['telegram_id'] ?? '') === $telegram_id) {
                    echo json_encode(['success' => false, 'message' => 'User with this Telegram ID already exists']);
                    break 2;
                }
            }
            
            // Create new user
            $new_user = [
                'telegram_id' => $telegram_id,
                'username' => trim($input['username'] ?? ''),
                'balance_php' => floatval($input['balance_php'] ?? 0),
                'balance_br' => floatval($input['balance_br'] ?? 0),
                'is_active' => isset($input['is_active']) ? (bool)$input['is_active'] : true,
                'created_at' => date('Y-m-d H:i:s'),
                'last_active' => date('Y-m-d H:i:s'),
                'total_topups' => 0
            ];
            
            $users[] = $new_user;
            
            if (writeJsonFile(ROOT_DIR . '/users.json', $users)) {
                echo json_encode(['success' => true, 'message' => 'User added successfully', 'data' => $new_user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add user']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to add user', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'delete_user':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $telegram_id = $input['telegram_id'] ?? '';
            
            if (empty($telegram_id)) {
                echo json_encode(['success' => false, 'message' => 'Telegram ID is required']);
                break;
            }
            
            $users = readJsonFile(ROOT_DIR . '/users.json');
            $user_found = false;
            
            foreach ($users as $index => $user) {
                if (($user['telegram_id'] ?? '') == $telegram_id || ($user['telegram_id'] ?? '') === $telegram_id) {
                    array_splice($users, $index, 1);
                    $user_found = true;
                    break;
                }
            }
            
            if ($user_found) {
                if (writeJsonFile(ROOT_DIR . '/users.json', $users)) {
                    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete user', 'message' => $e->getMessage()]);
        }
        break;
    
    // ========== TRANSACTION MANAGEMENT ==========
    
    case 'get_transactions':
        try {
            $transactions = readJsonFile(ROOT_DIR . '/transactions.json');
            echo json_encode(['success' => true, 'data' => $transactions]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get transactions', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'get_transaction':
        try {
            $transaction_id = $_GET['id'] ?? '';
            
            if (empty($transaction_id)) {
                echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
                break;
            }
            
            $transactions = readJsonFile(ROOT_DIR . '/transactions.json');
            
            foreach ($transactions as $transaction) {
                if (($transaction['id'] ?? '') === $transaction_id) {
                    echo json_encode(['success' => true, 'data' => $transaction]);
                    exit();
                }
            }
            
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get transaction', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'update_transaction':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $transaction_id = $input['id'] ?? '';
            
            if (empty($transaction_id)) {
                echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
                break;
            }
            
            $transactions = readJsonFile(ROOT_DIR . '/transactions.json');
            $transaction_found = false;
            
            foreach ($transactions as &$transaction) {
                if (($transaction['id'] ?? '') === $transaction_id) {
                    // Update transaction fields
                    if (isset($input['user_id'])) $transaction['user_id'] = $input['user_id'];
                    if (isset($input['amount'])) $transaction['amount'] = floatval($input['amount']);
                    if (isset($input['type'])) $transaction['type'] = trim($input['type']);
                    if (isset($input['country'])) $transaction['country'] = trim($input['country']);
                    if (isset($input['status'])) $transaction['status'] = trim($input['status']);
                    if (isset($input['details'])) $transaction['details'] = trim($input['details']);
                    $transaction['updated_at'] = date('Y-m-d H:i:s');
                    $transaction_found = true;
                    break;
                }
            }
            
            if ($transaction_found) {
                if (writeJsonFile(ROOT_DIR . '/transactions.json', $transactions)) {
                    echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update transaction']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Transaction not found']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update transaction', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'add_transaction':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $user_id = $input['user_id'] ?? '';
            $amount = floatval($input['amount'] ?? 0);
            $type = trim($input['type'] ?? 'topup_redeem');
            $country = trim($input['country'] ?? 'php');
            $status = trim($input['status'] ?? 'pending');
            $details = trim($input['details'] ?? '');
            
            if (empty($user_id)) {
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                break;
            }
            
            if ($amount < 0) {
                echo json_encode(['success' => false, 'message' => 'Amount must be positive']);
                break;
            }
            
            $transactions = readJsonFile(ROOT_DIR . '/transactions.json');
            
            // Generate transaction ID
            $transaction_id = uniqid() . substr(md5(time()), 0, 5);
            
            // Create new transaction
            $new_transaction = [
                'id' => $transaction_id,
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $type,
                'country' => $country,
                'status' => $status,
                'details' => $details,
                'created_at' => date('Y-m-d H:i:s'),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $transactions[] = $new_transaction;
            
            if (writeJsonFile(ROOT_DIR . '/transactions.json', $transactions)) {
                echo json_encode(['success' => true, 'message' => 'Transaction added successfully', 'data' => $new_transaction]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add transaction']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to add transaction', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'delete_transaction':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $transaction_id = $input['id'] ?? '';
            
            if (empty($transaction_id)) {
                echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
                break;
            }
            
            $transactions = readJsonFile(ROOT_DIR . '/transactions.json');
            $transaction_found = false;
            
            foreach ($transactions as $index => $transaction) {
                if (($transaction['id'] ?? '') === $transaction_id) {
                    array_splice($transactions, $index, 1);
                    $transaction_found = true;
                    break;
                }
            }
            
            if ($transaction_found) {
                if (writeJsonFile(ROOT_DIR . '/transactions.json', $transactions)) {
                    echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete transaction']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Transaction not found']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete transaction', 'message' => $e->getMessage()]);
        }
        break;
    
    // ========== SYSTEM OPERATIONS ==========
    
    case 'get_statistics':
        try {
            $users = readJsonFile(ROOT_DIR . '/users.json');
            $products = readJsonFile(ROOT_DIR . '/products.json');
            $transactions = readJsonFile(ROOT_DIR . '/transactions.json');
            $commissions = readJsonFile(ROOT_DIR . '/commissions.json');
            
            $stats = [
                'total_users' => count($users),
                'total_products' => count($products),
                'total_transactions' => count($transactions),
                'total_revenue' => array_sum(array_column($transactions, 'amount')),
                'total_commissions' => array_sum(array_column($commissions, 'amount'))
            ];
            
            echo json_encode(['success' => true, 'data' => $stats]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get statistics', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'backup_data':
        try {
            $backup_data = [
                'users' => readJsonFile(ROOT_DIR . '/users.json'),
                'products' => readJsonFile(ROOT_DIR . '/products.json'),
                'transactions' => readJsonFile(ROOT_DIR . '/transactions.json'),
                'commissions' => readJsonFile(ROOT_DIR . '/commissions.json'),
                'orders' => readJsonFile(ROOT_DIR . '/orders.json'),
                'topup_codes' => readJsonFile(ROOT_DIR . '/topup_codes.json'),
                'backup_date' => date('Y-m-d H:i:s')
            ];
            
            $backup_filename = ROOT_DIR . '/backup_' . date('Y-m-d_H-i-s') . '.json';
            if (writeJsonFile($backup_filename, $backup_data)) {
                echo json_encode(['success' => true, 'message' => 'Backup created successfully', 'filename' => $backup_filename]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create backup']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create backup', 'message' => $e->getMessage()]);
        }
        break;
    
    // ========== COOKIE MANAGEMENT ==========
    
    case 'get_cookies':
        try {
            $cookies_file = ROOT_DIR . '/cookies.json';
            if (!file_exists($cookies_file)) {
                // Return empty array if file doesn't exist instead of error
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $cookies = readJsonFile($cookies_file);
            echo json_encode(['success' => true, 'data' => $cookies]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get cookies', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'save_cookies':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['cookies']) || !is_array($input['cookies'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid cookies data']);
                break;
            }
            
            $cookies_file = ROOT_DIR . '/cookies.json';
            if (writeJsonFile($cookies_file, $input['cookies'])) {
                echo json_encode(['success' => true, 'message' => 'Cookies saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save cookies']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save cookies', 'message' => $e->getMessage()]);
        }
        break;
    
    // ========== BOT FILE EDITOR ==========
    
    case 'get_bot_file':
        try {
            $bot_file = ROOT_DIR . '/bot/bot.php';
            if (!file_exists($bot_file)) {
                echo json_encode(['success' => false, 'message' => 'Bot file not found']);
                break;
            }
            
            $content = @file_get_contents($bot_file);
            if ($content === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to read bot file']);
                break;
            }
            
            echo json_encode(['success' => true, 'data' => ['content' => $content]]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get bot file', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'save_bot_file':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['content'])) {
                echo json_encode(['success' => false, 'message' => 'No content provided']);
                break;
            }
            
            $bot_file = ROOT_DIR . '/bot/bot.php';
            
            // Create backup first
            if (file_exists($bot_file)) {
                $backup_file = ROOT_DIR . '/bot/bot_backup_' . date('Y-m-d_H-i-s') . '.php';
                @copy($bot_file, $backup_file);
            }
            
            // Save new content
            if (@file_put_contents($bot_file, $input['content']) !== false) {
                echo json_encode(['success' => true, 'message' => 'Bot file saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save bot file']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save bot file', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'backup_bot_file':
        try {
            $bot_file = ROOT_DIR . '/bot/bot.php';
            if (!file_exists($bot_file)) {
                echo json_encode(['success' => false, 'message' => 'Bot file not found']);
                break;
            }
            
            $backup_file = ROOT_DIR . '/bot/bot_backup_' . date('Y-m-d_H-i-s') . '.php';
            if (@copy($bot_file, $backup_file)) {
                echo json_encode(['success' => true, 'message' => 'Backup created successfully', 'filename' => basename($backup_file)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create backup']);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create backup', 'message' => $e->getMessage()]);
        }
        break;
    
    // ========== SEND MESSAGE TO USERS ==========
    
    case 'send_message':
        try {
            // Handle both JSON and FormData (multipart/form-data)
            // Check Content-Type header first, then check for POST data
            $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
            $is_multipart = strpos($content_type, 'multipart/form-data') !== false || !empty($_POST);
            
            if ($is_multipart) {
                // FormData (file upload or regular form)
                $telegram_id = isset($_POST['telegram_id']) && $_POST['telegram_id'] !== '' ? trim($_POST['telegram_id']) : null;
                $message = isset($_POST['message']) ? trim($_POST['message']) : '';
                $send_to_all = isset($_POST['send_to_all']) && ($_POST['send_to_all'] === '1' || $_POST['send_to_all'] === 'true' || $_POST['send_to_all'] === true);
                // Check for image file only if file was actually uploaded successfully
                $image_file = isset($_FILES['image']) && isset($_FILES['image']['error']) && $_FILES['image']['error'] === UPLOAD_ERR_OK ? $_FILES['image'] : null;
            } else {
                // JSON (text only)
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
                    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
                    break;
                }
                $telegram_id = $input['telegram_id'] ?? null;
                $message = isset($input['message']) ? trim($input['message']) : '';
                $send_to_all = isset($input['send_to_all']) && ($input['send_to_all'] === true || $input['send_to_all'] === '1');
                $image_file = null;
            }
            
            // Validate: need either message or image
            // Check if message is not empty (after trim) or if we have a valid image file
            $has_message = !empty($message) && strlen(trim($message)) > 0;
            $has_image = $image_file !== null && isset($image_file['error']) && $image_file['error'] === UPLOAD_ERR_OK;
            
            if (!$has_message && !$has_image) {
                echo json_encode(['success' => false, 'message' => 'Message or image is required']);
                break;
            }
            
            // Load bot config
            $bot_config_file = ROOT_DIR . '/bot_config.json';
            $bot_config = readJsonFile($bot_config_file);
            $bot_token = $bot_config['bot_token'] ?? '';
            
            if (empty($bot_token)) {
                echo json_encode(['success' => false, 'message' => 'Bot token not configured']);
                break;
            }
            
            // Load users
            $users = readJsonFile(ROOT_DIR . '/users.json');
            
            $success_count = 0;
            $fail_count = 0;
            $errors = [];
            
            // Use the already validated $has_image variable
            
            if ($send_to_all) {
                // Send to all users
                foreach ($users as $user) {
                    $user_id = $user['telegram_id'] ?? null;
                    if (empty($user_id)) continue;
                    
                    if ($has_image) {
                        // Send photo with caption
                        $result = sendTelegramPhoto($bot_token, $user_id, $image_file, $message);
                    } else {
                        // Send text message
                        $result = sendTelegramMessage($bot_token, $user_id, $message);
                    }
                    
                    if ($result['success']) {
                        $success_count++;
                    } else {
                        $fail_count++;
                        $errors[] = 'User ' . $user_id . ': ' . $result['error'];
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "Message sent to {$success_count} users. Failed: {$fail_count}",
                    'success_count' => $success_count,
                    'fail_count' => $fail_count,
                    'errors' => $errors
                ]);
            } else {
                // Send to single user
                if (empty($telegram_id)) {
                    echo json_encode(['success' => false, 'message' => 'Telegram ID is required']);
                    break;
                }
                
                if ($has_image) {
                    // Send photo with caption
                    $result = sendTelegramPhoto($bot_token, $telegram_id, $image_file, $message);
                } else {
                    // Send text message
                    $result = sendTelegramMessage($bot_token, $telegram_id, $message);
                }
                
                if ($result['success']) {
                    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $result['error']]);
                }
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send message', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'get_smile_balance':
        try {
            // Increase timeout for balance fetching (needs more time due to delays)
            if (function_exists('set_time_limit')) {
                @set_time_limit(30); // 30 seconds for balance fetching
            }
            
            // Prevent concurrent requests using a lock file
            $lock_file = ROOT_DIR . '/balance_fetch.lock';
            $lock_timeout = 30; // 30 seconds timeout
            
            // Check if lock file exists and is not stale
            if (file_exists($lock_file)) {
                $lock_time = filemtime($lock_file);
                $current_time = time();
                
                // If lock is older than timeout, remove it (stale lock)
                if (($current_time - $lock_time) > $lock_timeout) {
                    @unlink($lock_file);
                    error_log("⚠️ Removed stale balance fetch lock file");
                } else {
                    // Lock exists and is recent, return cached or wait response
                    error_log("⚠️ Balance fetch already in progress, skipping request");
                    echo json_encode([
                        'success' => false,
                        'error' => 'Request in progress',
                        'message' => 'Another balance fetch is already in progress. Please wait.',
                        'data' => [
                            'br' => 'In Progress',
                            'php' => 'In Progress'
                        ]
                    ]);
                    break;
                }
            }
            
            // Create lock file
            @file_put_contents($lock_file, time());
            
            // Check if SmileOne class exists, if not try to load it
            if (!class_exists('SmileOne')) {
                $smile_file = ROOT_DIR . '/smile.php';
                if (file_exists($smile_file)) {
                    require_once $smile_file;
                }
            }
            
            $balance_br = 'N/A';
            $balance_php = 'N/A';
            $error_br = '';
            $error_php = '';
            
            if (class_exists('SmileOne')) {
                // Check if cookies file exists
                $cookies_file = ROOT_DIR . '/cookies.json';
                if (!file_exists($cookies_file)) {
                    if (file_exists($lock_file)) {
                        @unlink($lock_file);
                    }
                    echo json_encode([
                        'success' => false,
                        'error' => 'Cookies file not found',
                        'message' => 'Please set cookies in Cookie Management',
                        'data' => [
                            'br' => 'No Cookies',
                            'php' => 'No Cookies'
                        ]
                    ]);
                    break;
                }
                
                // Verify cookies are valid
                $cookies_content = @file_get_contents($cookies_file);
                $cookies_data = json_decode($cookies_content, true);
                if (json_last_error() !== JSON_ERROR_NONE || empty($cookies_data)) {
                    if (file_exists($lock_file)) {
                        @unlink($lock_file);
                    }
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid cookies',
                        'message' => 'Cookies file is invalid or empty',
                        'data' => [
                            'br' => 'Invalid Cookies',
                            'php' => 'Invalid Cookies'
                        ]
                    ]);
                    break;
                }
                
                $smile = new SmileOne();
                
                // Verify cookies were loaded
                $reflection = new ReflectionClass($smile);
                $cookiesProperty = $reflection->getProperty('cookies');
                $cookiesProperty->setAccessible(true);
                $loadedCookies = $cookiesProperty->getValue($smile);
                
                if (empty($loadedCookies)) {
                    if (file_exists($lock_file)) {
                        @unlink($lock_file);
                    }
                    echo json_encode([
                        'success' => false,
                        'error' => 'Cookies not loaded',
                        'message' => 'Cookies file exists but could not be loaded',
                        'data' => [
                            'br' => 'Load Failed',
                            'php' => 'Load Failed'
                        ]
                    ]);
                    break;
                }
                
                // Get BR balance
                $smile->setCountry('br');
                error_log("🔄 Fetching BR balance with cookies and user agent...");
                
                // Add a delay before first request to avoid immediate rate limiting
                sleep(2); // 2 second delay to avoid rate limiting
                
                $br_result = $smile->getBalance();
                $is_br_rate_limited = false;
                
                if ($br_result !== false && $br_result !== null && $br_result !== '') {
                    $balance_br = $br_result;
                    error_log("✅ BR balance fetched successfully: {$balance_br}");
                } else {
                    // Try to get error message
                    $lastErrorProperty = $reflection->getProperty('lastError');
                    $lastErrorProperty->setAccessible(true);
                    $error_br = $lastErrorProperty->getValue($smile);
                    if (!empty($error_br)) {
                        // Check for different error types
                        if (strpos($error_br, '302') !== false || strpos($error_br, 'Session expired') !== false || strpos($error_br, 'Redirected') !== false) {
                            $balance_br = 'Session Expired';
                            $error_br = 'Cookies expired or invalid. Please update cookies in Cookie Management.';
                        } elseif (strpos($error_br, '429') !== false || strpos($error_br, 'Rate limited') !== false || strpos($error_br, 'Rate Limited') !== false) {
                            $balance_br = 'Rate Limited';
                            $error_br = 'Too many requests. Please wait a few minutes before checking again.';
                            $is_br_rate_limited = true;
                        } elseif (strpos($error_br, 'Cookies not loaded') !== false || strpos($error_br, 'Cookies file') !== false || strpos($error_br, 'No cookies') !== false) {
                            $balance_br = 'No Cookies';
                            $error_br = 'Cookies not found or invalid. Please set cookies in Cookie Management.';
                        } else {
                            $balance_br = 'Error';
                            $error_br = substr($error_br, 0, 100);
                        }
                        error_log("❌ BR balance error: {$error_br}");
                    } else {
                        $balance_br = 'Error';
                        $error_br = 'Unknown error occurred while fetching balance.';
                    }
                }
                
                // If BR is rate limited, skip PHP request to avoid further rate limiting
                if ($is_br_rate_limited) {
                    $balance_php = 'Rate Limited';
                    $error_php = 'Skipped due to rate limit on BR request. Please wait a few minutes.';
                    error_log("⚠️ Skipping PHP balance fetch due to BR rate limit");
                } else {
                    // Add delay between requests to avoid rate limiting
                    sleep(3); // 3 second delay between requests
                    
                    // Get PHP balance (create new instance to avoid state issues)
                    $smile_php = new SmileOne('php');
                    error_log("🔄 Fetching PHP balance with cookies and user agent...");
                    
                    // Add a small delay before second request
                    sleep(1); // 1 second delay before PHP request
                    
                    $php_result = $smile_php->getBalance();
                    if ($php_result !== false && $php_result !== null && $php_result !== '') {
                        $balance_php = $php_result;
                        error_log("✅ PHP balance fetched successfully: {$balance_php}");
                    } else {
                        // Try to get error message
                        $reflection_php = new ReflectionClass($smile_php);
                        $lastErrorProperty = $reflection_php->getProperty('lastError');
                        $lastErrorProperty->setAccessible(true);
                        $error_php = $lastErrorProperty->getValue($smile_php);
                        if (!empty($error_php)) {
                            // Check for different error types
                            if (strpos($error_php, '302') !== false || strpos($error_php, 'Session expired') !== false || strpos($error_php, 'Redirected') !== false) {
                                $balance_php = 'Session Expired';
                                $error_php = 'Cookies expired or invalid. Please update cookies in Cookie Management.';
                            } elseif (strpos($error_php, '429') !== false || strpos($error_php, 'Rate limited') !== false || strpos($error_php, 'Rate Limited') !== false) {
                                $balance_php = 'Rate Limited';
                                $error_php = 'Too many requests. Please wait a few minutes before checking again.';
                            } elseif (strpos($error_php, 'Cookies not loaded') !== false || strpos($error_php, 'Cookies file') !== false || strpos($error_php, 'No cookies') !== false) {
                                $balance_php = 'No Cookies';
                                $error_php = 'Cookies not found or invalid. Please set cookies in Cookie Management.';
                            } else {
                                $balance_php = 'Error';
                                $error_php = substr($error_php, 0, 100);
                            }
                            error_log("❌ PHP balance error: {$error_php}");
                        } else {
                            $balance_php = 'Error';
                            $error_php = 'Unknown error occurred while fetching balance.';
                        }
                    }
                }
            } else {
                if (file_exists($lock_file)) {
                    @unlink($lock_file);
                }
                echo json_encode([
                    'success' => false,
                    'error' => 'SmileOne class not found',
                    'message' => 'Could not load SmileOne class',
                    'data' => [
                        'br' => 'Class Not Found',
                        'php' => 'Class Not Found'
                    ]
                ]);
                break;
            }
            
            // Remove lock file before returning
            if (file_exists($lock_file)) {
                @unlink($lock_file);
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'br' => $balance_br,
                    'php' => $balance_php
                ],
                'errors' => [
                    'br' => $error_br,
                    'php' => $error_php
                ]
            ]);
        } catch (Exception $e) {
            // Remove lock file on error
            if (isset($lock_file) && file_exists($lock_file)) {
                @unlink($lock_file);
            }
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to get SmileOne balance',
                'message' => $e->getMessage(),
                'data' => [
                    'br' => 'Exception',
                    'php' => 'Exception'
                ]
            ]);
        } catch (Error $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to get SmileOne balance',
                'message' => $e->getMessage(),
                'data' => [
                    'br' => 'Fatal Error',
                    'php' => 'Fatal Error'
                ]
            ]);
        }
        break;
    
    default:
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action',
            'message' => 'Unknown action: ' . htmlspecialchars($action),
            'available_actions' => [
                'Bot: get_bot_status, get_bot_logs, start_bot, stop_bot, save_bot_config, get_bot_config, process_order',
                'Products: get_products, add_product, edit_product, delete_product, sync_products',
                'Users: get_users, get_user, update_user, add_user, delete_user',
                'Transactions: get_transactions, get_transaction, update_transaction, add_transaction, delete_transaction',
                'System: get_statistics, backup_data, get_smile_balance',
                'Cookies: get_cookies, save_cookies',
                'Bot File: get_bot_file, save_bot_file, backup_bot_file',
                'Messages: send_message',
                'MMK Top Ups: get_mmk_topups, approve_mmk_topup, reject_mmk_topup',
                'Telegram: get_telegram_file'
            ]
        ]);
        break;
    
    // ========== TELEGRAM FILE API ==========
    
    case 'get_telegram_file':
        try {
            $file_id = $_GET['file_id'] ?? '';
            
            if (empty($file_id)) {
                echo json_encode(['success' => false, 'message' => 'File ID is required']);
                break;
            }
            
            // Get bot token
            $bot_config = readJsonFile(ROOT_DIR . '/bot_config.json');
            $bot_token = $bot_config['bot_token'] ?? '';
            
            if (empty($bot_token)) {
                echo json_encode(['success' => false, 'message' => 'Bot token not configured']);
                break;
            }
            
            // Get file info from Telegram
            $url = "https://api.telegram.org/bot{$bot_token}/getFile?file_id=" . urlencode($file_id);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($http_code === 200) {
                $result = json_decode($response, true);
                if ($result && isset($result['ok']) && $result['ok'] && isset($result['result']['file_path'])) {
                    echo json_encode([
                        'success' => true,
                        'file_path' => $result['result']['file_path']
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => $result['description'] ?? 'Failed to get file path'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'HTTP ' . $http_code . ($curl_error ? ': ' . $curl_error : '')
                ]);
            }
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get Telegram file', 'message' => $e->getMessage()]);
        }
        break;
    
    // ========== MMK TOP UP MANAGEMENT ==========
    
    case 'get_mmk_topups':
        try {
            $file = ROOT_DIR . '/pending_mmk_topups.json';
            $topUps = [];
            
            if (file_exists($file)) {
                $data = file_get_contents($file);
                $topUps = json_decode($data, true) ?: [];
            }
            
            echo json_encode(['success' => true, 'data' => $topUps]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get MMK top ups', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'approve_mmk_topup':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $topUpId = $input['topup_id'] ?? '';
            
            if (empty($topUpId)) {
                echo json_encode(['success' => false, 'message' => 'Top up ID is required']);
                break;
            }
            
            $file = ROOT_DIR . '/pending_mmk_topups.json';
            $topUps = [];
            
            if (file_exists($file)) {
                $data = file_get_contents($file);
                $topUps = json_decode($data, true) ?: [];
            }
            
            $topUpIndex = -1;
            foreach ($topUps as $index => $topUp) {
                if (($topUp['id'] ?? '') === $topUpId) {
                    $topUpIndex = $index;
                    break;
                }
            }
            
            if ($topUpIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Top up request not found']);
                break;
            }
            
            $topUp = $topUps[$topUpIndex];
            
            if (($topUp['status'] ?? '') !== 'pending') {
                echo json_encode(['success' => false, 'message' => 'Top up request is not pending']);
                break;
            }
            
            // Update status
            $topUps[$topUpIndex]['status'] = 'approved';
            $topUps[$topUpIndex]['approved_at'] = date('Y-m-d H:i:s');
            $topUps[$topUpIndex]['approved_by'] = $_SESSION['admin_username'] ?? 'Admin';
            
            // Save updated top ups
            file_put_contents($file, json_encode($topUps, JSON_PRETTY_PRINT));
            
            // Add MMK balance to user account
            $telegramId = $topUp['telegram_id'] ?? null;
            $amountMMK = floatval($topUp['amount_mmk'] ?? 0);
            
            if ($telegramId && $amountMMK > 0) {
                // Load users
                $users = readJsonFile(ROOT_DIR . '/users.json');
                $userIndex = -1;
                
                foreach ($users as $index => $user) {
                    if (($user['telegram_id'] ?? '') == $telegramId) {
                        $userIndex = $index;
                        break;
                    }
                }
                
                if ($userIndex !== -1) {
                    // Add MMK balance only
                    $users[$userIndex]['balance_mmk'] = ($users[$userIndex]['balance_mmk'] ?? 0) + $amountMMK;
                    
                    // Save users
                    writeJsonFile(ROOT_DIR . '/users.json', $users);
                    
                    // Save transaction
                    $txDetails = "MMK Top Up - {$topUp['method_name']} - " . number_format($amountMMK, 0, '.', ',') . ' Ks';
                    
                    $transactions = readJsonFile(ROOT_DIR . '/transactions.json');
                    $transactions[] = [
                        'id' => 'TX' . time() . '_' . $telegramId,
                        'user_id' => $telegramId,
                        'type' => 'mmk_topup',
                        'country' => 'mmk',
                        'amount' => $amountMMK,
                        'details' => $txDetails,
                        'status' => 'completed',
                        'created_at' => date('Y-m-d H:i:s'),
                        'timestamp' => time()
                    ];
                    writeJsonFile(ROOT_DIR . '/transactions.json', $transactions);
                    
                    // Notify user via Telegram
                    $bot_config = readJsonFile(ROOT_DIR . '/bot_config.json');
                    $bot_token = $bot_config['bot_token'] ?? '';
                    
                    if ($bot_token) {
                        $newBalance = $users[$userIndex]['balance_mmk'];
                        $message = "✅ *MMK TOP UP APPROVED!*\n\n";
                        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        $message .= "📋 *Request ID:* `{$topUpId}`\n";
                        $message .= "💳 *Method:* {$topUp['method_name']}\n";
                        $message .= "💰 *Amount Added:* " . number_format($amountMMK, 0, '.', ',') . ' Ks' . "\n\n";
                        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        $message .= "💵 *New MMK Balance:*\n";
                        $message .= number_format($newBalance, 0, '.', ',') . ' Ks' . "\n\n";
                        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        $message .= "🎉 Thank you for your payment!";
                        
                        sendTelegramMessage($bot_token, $telegramId, $message);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Top up approved and balance added successfully'
            ]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to approve top up', 'message' => $e->getMessage()]);
        }
        break;
    
    case 'reject_mmk_topup':
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $topUpId = $input['topup_id'] ?? '';
            $reason = trim($input['reason'] ?? '');
            
            if (empty($topUpId)) {
                echo json_encode(['success' => false, 'message' => 'Top up ID is required']);
                break;
            }
            
            $file = ROOT_DIR . '/pending_mmk_topups.json';
            $topUps = [];
            
            if (file_exists($file)) {
                $data = file_get_contents($file);
                $topUps = json_decode($data, true) ?: [];
            }
            
            $topUpIndex = -1;
            foreach ($topUps as $index => $topUp) {
                if (($topUp['id'] ?? '') === $topUpId) {
                    $topUpIndex = $index;
                    break;
                }
            }
            
            if ($topUpIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Top up request not found']);
                break;
            }
            
            // Update status
            $topUps[$topUpIndex]['status'] = 'rejected';
            $topUps[$topUpIndex]['rejected_at'] = date('Y-m-d H:i:s');
            $topUps[$topUpIndex]['rejected_by'] = $_SESSION['admin_username'] ?? 'Admin';
            if (!empty($reason)) {
                $topUps[$topUpIndex]['rejection_reason'] = $reason;
            }
            
            // Save updated top ups
            file_put_contents($file, json_encode($topUps, JSON_PRETTY_PRINT));
            
            // Notify user via Telegram
            $topUp = $topUps[$topUpIndex];
            $telegramId = $topUp['telegram_id'] ?? null;
            
            if ($telegramId) {
                $bot_config = readJsonFile(ROOT_DIR . '/bot_config.json');
                $bot_token = $bot_config['bot_token'] ?? '';
                
                if ($bot_token) {
                    $message = "❌ *TOP UP REJECTED*\n\n";
                    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                    $message .= "📋 *Request ID:* `{$topUpId}`\n";
                    $message .= "💳 *Method:* {$topUp['method_name']}\n";
                    $message .= "💰 *Amount:* " . formatMMK($topUp['amount_mmk'] ?? 0) . "\n\n";
                    if (!empty($reason)) {
                        $message .= "📝 *Reason:* {$reason}\n\n";
                    }
                    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                    $message .= "⚠️ Please contact admin for more information.";
                    
                    sendTelegramMessage($bot_token, $telegramId, $message);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Top up rejected'
            ]);
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to reject top up', 'message' => $e->getMessage()]);
        }
        break;
}

// Helper function to send Telegram message
function sendTelegramMessage($bot_token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    // Debug logging
    $log_message = "[AdminAPI] Sending message to {$chat_id}";
    error_log($log_message);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false // Added to avoid SSL issues in some environments
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['ok']) && $result['ok']) {
            error_log("[AdminAPI] Message sent successfully to {$chat_id}");
            return ['success' => true];
        } else {
            $error_desc = $result['description'] ?? 'Unknown error';
            error_log("[AdminAPI] Telegram API Error: {$error_desc}");
            return ['success' => false, 'error' => $error_desc];
        }
    } else {
        $network_error = 'HTTP ' . $http_code . ($curl_error ? ': ' . $curl_error : '');
        error_log("[AdminAPI] Network Error: {$network_error}");
        // Also log the raw response if available
        if ($response) {
            error_log("[AdminAPI] Raw Response: " . substr($response, 0, 200));
        }
        return ['success' => false, 'error' => $network_error];
    }
}

// Helper function to send Telegram photo
function sendTelegramPhoto($bot_token, $chat_id, $image_file, $caption = '') {
    $url = "https://api.telegram.org/bot{$bot_token}/sendPhoto";
    
    // Validate file
    if (!isset($image_file['error']) || $image_file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error: ' . ($image_file['error'] ?? 'Unknown error')];
    }
    
    // Check file size (max 10MB for Telegram)
    if ($image_file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Image file too large (max 10MB)'];
    }
    
    // Validate image type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    
    // Get MIME type
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $image_file['tmp_name']);
        finfo_close($finfo);
    } else {
        // Fallback: use file extension
        $ext = strtolower(pathinfo($image_file['name'], PATHINFO_EXTENSION));
        $mime_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ];
        $mime_type = $mime_map[$ext] ?? 'image/jpeg';
    }
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid image type. Allowed: JPG, PNG, GIF'];
    }
    
    // Prepare CURLFile for upload
    if (class_exists('CURLFile')) {
        $cfile = new CURLFile($image_file['tmp_name'], $mime_type, $image_file['name']);
    } else {
        // Fallback for older PHP versions
        $cfile = '@' . $image_file['tmp_name'] . ';type=' . $mime_type;
    }
    
    $data = [
        'chat_id' => $chat_id,
        'photo' => $cfile
    ];
    
    if (!empty($caption)) {
        $data['caption'] = $caption;
        $data['parse_mode'] = 'HTML';
    }
    
    error_log("[AdminAPI] Sending photo to {$chat_id}");
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30, // Longer timeout for file uploads
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['ok']) && $result['ok']) {
            error_log("[AdminAPI] Photo sent successfully to {$chat_id}");
            return ['success' => true];
        } else {
            $error_desc = $result['description'] ?? 'Unknown error';
            error_log("[AdminAPI] Telegram Photo API Error: {$error_desc}");
            return ['success' => false, 'error' => $error_desc];
        }
    } else {
        $network_error = 'HTTP ' . $http_code . ($curl_error ? ': ' . $curl_error : '');
        error_log("[AdminAPI] Photo Network Error: {$network_error}");
        return ['success' => false, 'error' => $network_error];
    }
}

// Ensure output buffer is flushed and clean
if (ob_get_level() > 0) {
    ob_end_flush();
}

// Prevent any additional output
exit();
?>
