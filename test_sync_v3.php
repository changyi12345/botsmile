<?php
require_once __DIR__ . '/smile.php';

// Initialize SmileOne
$smile = new SmileOne();

// Games to test
$games = [
    'mobilelegends' => ['br', 'php'],
    'pubg' => ['br', 'php'],
    'hok' => ['br', 'php'],
    'magicchessgogo' => ['br', 'php']
];

echo "🚀 Starting Product Sync Test (Game by Game)...\n";
echo "================================================\n\n";

foreach ($games as $game => $countries) {
    echo "🎮 Testing Game: " . strtoupper($game) . "\n";
    echo "------------------------------------------------\n";
    
    foreach ($countries as $country) {
        echo "  🌍 Region: " . strtoupper($country) . "... ";
        
        // Fetch products
        $products = $smile->fetchProductsFromWebsite($game, $country);
        
        if (!empty($products)) {
            echo "✅ SUCCESS! Found " . count($products) . " products.\n";
            
            // Print first 3 products as sample
            $count = 0;
            foreach ($products as $p) {
                if ($count >= 3) break;
                echo "     - " . $p['name'] . " (" . $p['price'] . ")\n";
                $count++;
            }
            if (count($products) > 3) {
                echo "     ... and " . (count($products) - 3) . " more.\n";
            }
        } else {
            echo "❌ FAILED! No products found.\n";
            echo "     Error: " . $smile->getLastError() . "\n";
        }
        echo "\n";
    }
    echo "\n";
}

echo "================================================\n";
echo "🏁 Test Completed.\n";
