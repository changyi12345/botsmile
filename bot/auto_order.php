<?php
require_once '../smile.php';

echo "🤖 SmileOne Auto Order System\n";
echo "=============================\n\n";

class AutoOrderSystem {
    private $smile;
    private $orderQueue = [];
    private $processedOrders = [];
    
    public function __construct() {
        $this->smile = new SmileOne();
    }
    
    /**
     * Load orders from file or database
     */
    public function loadOrders($source) {
        // Example: Load from JSON file
        if (file_exists($source)) {
            $content = file_get_contents($source);
            $orders = json_decode($content, true);
            
            if ($orders && is_array($orders)) {
                $this->orderQueue = $orders;
                echo "📥 Loaded " . count($orders) . " orders from $source\n";
                return true;
            }
        }
        
        // Example orders
        $this->orderQueue = [
            [
                'userId' => '1047489910',
                'zoneId' => '13194',
                'product' => 'DIAMOND 5',
                'requester' => 'customer001',
                'country' => 'br'
            ],
            [
                'userId' => '1047489911',
                'zoneId' => '13195',
                'product' => 'DIAMOND 10',
                'requester' => 'customer002',
                'country' => 'br'
            ]
        ];
        
        echo "📥 Loaded " . count($this->orderQueue) . " example orders\n";
        return true;
    }
    
    /**
     * Process all orders in queue
     */
    public function processQueue() {
        if (empty($this->orderQueue)) {
            echo "📭 No orders in queue\n";
            return;
        }
        
        $total = count($this->orderQueue);
        $success = 0;
        $failed = 0;
        
        echo "⚡ Processing $total orders...\n\n";
        
        foreach ($this->orderQueue as $index => $order) {
            $orderNum = $index + 1;
            echo "\n--- Order $orderNum/$total ---\n";
            echo "User: {$order['userId']} | Zone: {$order['zoneId']}\n";
            echo "Product: {$order['product']} | Country: {$order['country']}\n";
            
            // Check balance before processing
            $this->smile->setCountry($order['country']);
            $balance = $this->smile->getBalance();
            echo "💰 Current Balance: " . ($balance ? $balance : 'Unknown') . "\n";
            
            // Process order
            $result = $this->smile->recharge(
                $order['userId'],
                $order['zoneId'],
                $order['product'],
                $order['requester'],
                $order['country']
            );
            
            if ($result && $result['success']) {
                echo "✅ Order Processed Successfully!\n";
                echo "   Username: " . ($result['username'] ?? 'N/A') . "\n";
                $success++;
                
                // Add to processed orders
                $order['result'] = $result;
                $order['status'] = 'success';
                $order['processed_at'] = date('Y-m-d H:i:s');
                $this->processedOrders[] = $order;
            } else {
                echo "❌ Order Failed!\n";
                echo "   Error: " . $this->smile->getLastError() . "\n";
                $failed++;
                
                // Add to processed orders
                $order['result'] = null;
                $order['status'] = 'failed';
                $order['error'] = $this->smile->getLastError();
                $order['processed_at'] = date('Y-m-d H:i:s');
                $this->processedOrders[] = $order;
            }
            
            // Delay between orders
            if ($orderNum < $total) {
                echo "⏳ Waiting 3 seconds before next order...\n";
                sleep(3);
            }
        }
        
        echo "\n📊 Processing Complete!\n";
        echo "✅ Success: $success\n";
        echo "❌ Failed: $failed\n";
        
        $this->saveReport();
    }
    
    /**
     * Save processing report
     */
    private function saveReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_orders' => count($this->orderQueue),
            'successful' => count(array_filter($this->processedOrders, function($order) {
                return $order['status'] === 'success';
            })),
            'failed' => count(array_filter($this->processedOrders, function($order) {
                return $order['status'] === 'failed';
            })),
            'orders' => $this->processedOrders
        ];
        
        $filename = 'order_report_' . date('Ymd_His') . '.json';
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "📄 Report saved to: $filename\n";
    }
    
    /**
     * Show balance summary
     */
    public function showBalanceSummary() {
        echo "\n💰 Balance Summary\n";
        echo "=================\n";
        
        $balances = $this->smile->getBalanceAll();
        if ($balances) {
            foreach ($balances as $country => $balance) {
                echo strtoupper($country) . ": " . $balance . "\n";
            }
        } else {
            echo "Failed to get balances\n";
        }
    }
    
    /**
     * Run continuous monitoring
     */
    public function runMonitor($intervalSeconds = 60) {
        echo "🔍 Starting Auto Order Monitor\n";
        echo "Interval: $intervalSeconds seconds\n";
        echo "Press Ctrl+C to stop\n\n";
        
        while (true) {
            echo "\n[" . date('Y-m-d H:i:s') . "] Checking for new orders...\n";
            
            // Here you would check for new orders from database, API, or file
            // For now, we'll just show balance
            $this->showBalanceSummary();
            
            // Process any pending orders
            $this->processQueue();
            
            // Clear processed orders for next cycle
            $this->orderQueue = [];
            
            echo "\n⏳ Waiting $intervalSeconds seconds...\n";
            sleep($intervalSeconds);
        }
    }
}

// Main execution
try {
    $autoOrder = new AutoOrderSystem();
    
    // Show initial balance
    $autoOrder->showBalanceSummary();
    
    // Ask for mode
    echo "\nSelect mode:\n";
    echo "1. Process single order\n";
    echo "2. Process batch orders\n";
    echo "3. Run monitor mode\n";
    echo "4. Exit\n";
    
    echo "Choice: ";
    $choice = trim(fgets(STDIN));
    
    switch ($choice) {
        case '1':
            // Manual single order
            echo "\nEnter User ID: ";
            $userId = trim(fgets(STDIN));
            echo "Enter Zone ID: ";
            $zoneId = trim(fgets(STDIN));
            echo "Enter Product Name: ";
            $product = trim(fgets(STDIN));
            echo "Enter Country (br/php): ";
            $country = trim(fgets(STDIN));
            echo "Enter Requester: ";
            $requester = trim(fgets(STDIN));
            
            $smile = new SmileOne();
            $result = $smile->recharge($userId, $zoneId, $product, $requester, $country);
            
            if ($result && $result['success']) {
                echo "\n✅ Order Successful!\n";
                print_r($result);
            } else {
                echo "\n❌ Order Failed: " . $smile->getLastError() . "\n";
            }
            break;
            
        case '2':
            // Batch processing
            $autoOrder->loadOrders('orders.json'); // Load from file
            $autoOrder->processQueue();
            break;
            
        case '3':
            // Monitor mode
            echo "Enter check interval (seconds): ";
            $interval = trim(fgets(STDIN));
            $autoOrder->runMonitor($interval ? intval($interval) : 60);
            break;
            
        default:
            echo "Goodbye!\n";
            break;
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}