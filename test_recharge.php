<?php
require_once 'smile.php';

echo "=== SmileOne Recharge Tester ===\n\n";

// Test configuration
$testUserId = "1047489910";
$testZoneId = "13194";
$testProduct = "DIAMOND 5";
$testRequester = "tester";

try {
    echo "🔍 Testing product lookup...\n";
    $smile = new SmileOne();
    
    $product = $smile->findProduct($testProduct);
    if ($product) {
        echo "✅ Found product: " . $product['name'] . "\n";
        echo "   Country: " . ($product['country'] ?? 'br') . "\n";
        echo "   ID: " . (is_array($product['id'] ?? null) ? implode(',', $product['id']) : ($product['id'] ?? 'N/A')) . "\n";
    } else {
        echo "❌ Product not found: $testProduct\n";
        exit;
    }
    
    echo "\n💰 Checking balance before recharge...\n";
    $balances = $smile->getBalanceAll();
    if ($balances) {
        foreach ($balances as $country => $balance) {
            echo "   " . strtoupper($country) . " Balance: " . $balance . "\n";
        }
    }
    
    echo "\n⚡ Attempting recharge...\n";
    echo "   User ID: $testUserId\n";
    echo "   Zone ID: $testZoneId\n";
    echo "   Product: $testProduct\n";
    echo "   Requester: $testRequester\n";
    
    $result = $smile->recharge($testUserId, $testZoneId, $testProduct, $testRequester);
    
    if ($result && $result['success']) {
        echo "\n✅ Recharge Successful!\n";
        echo "========================\n";
        echo "Username: " . ($result['username'] ?? 'N/A') . "\n";
        echo "Country: " . $result['country'] . "\n";
        echo "Product IDs: " . implode(', ', $result['productIds']) . "\n";
        echo "Timestamp: " . $result['timestamp'] . "\n";
        echo "Requested By: " . $result['requestedBy'] . "\n";
        
        echo "\nIndividual Results:\n";
        foreach ($result['results'] as $index => $res) {
            echo "  " . ($index + 1) . ". Product ID {$res['product_id']}: " . 
                 ($res['success'] ? '✅ Success' : '❌ Failed') . "\n";
        }
    } else {
        echo "\n❌ Recharge Failed!\n";
        echo "Error: " . $smile->getLastError() . "\n";
    }
    
    echo "\n💰 Checking balance after recharge...\n";
    $balancesAfter = $smile->getBalanceAll();
    if ($balancesAfter) {
        foreach ($balancesAfter as $country => $balance) {
            echo "   " . strtoupper($country) . " Balance: " . $balance . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}