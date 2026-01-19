<?php
require_once 'smile.php';

// Set verbose logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing product fetching for MLBB, PUBG, and HoK...\n";
echo "=================================================\n";

$smile = new SmileOne();
$smile->loadCookies();

$games = ['mobilelegends', 'pubg', 'hok'];
$countries = ['br', 'php']; // Testing both Brazil and Philippines

foreach ($games as $game) {
    foreach ($countries as $country) {
        // Skip some combinations if not needed, but good to test all
        // PUBG and HoK often have different endpoints/structures
        
        echo "\nFetching products for Game: $game, Country: $country...\n";
        
        $products = $smile->fetchProductsFromWebsite($game, $country);
        
        if (empty($products)) {
            echo "❌ FAILED: No products found for $game ($country).\n";
            echo "Last Error: " . $smile->getLastError() . "\n";
        } else {
            echo "✅ SUCCESS: Found " . count($products) . " products for $game ($country).\n";
            // Print first product to verify structure
            print_r($products[0]);
        }
    }
}
?>
