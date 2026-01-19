<?php
require_once 'smile.php';

echo "=== SmileOne Balance Checker ===\n\n";

try {
    $smile = new SmileOne();
    
    echo "Getting balances for all countries...\n";
    
    $balances = $smile->getBalanceAll();
    
    if ($balances) {
        echo "\n✅ Balance Check Successful:\n";
        echo "===========================\n";
        foreach ($balances as $country => $balance) {
            echo strtoupper($country) . ": " . $balance . "\n";
        }
    } else {
        echo "\n❌ Failed to get balances\n";
        echo "Error: " . $smile->getLastError() . "\n";
    }
    
    // Test single country
    echo "\n--- Testing BR Balance ---\n";
    $smile->setCountry('br');
    $brBalance = $smile->getBalance();
    echo "BR Balance: " . ($brBalance ? $brBalance : 'Failed: ' . $smile->getLastError()) . "\n";
    
    echo "\n--- Testing PHP Balance ---\n";
    $smile->setCountry('php');
    $phpBalance = $smile->getBalance();
    echo "PHP Balance: " . ($phpBalance ? $phpBalance : 'Failed: ' . $smile->getLastError()) . "\n";
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}