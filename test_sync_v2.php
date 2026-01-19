<?php
require_once __DIR__ . '/smile.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$smile = new SmileOne();
// $smile->loadCookies(); // Called in constructor

$games = [
    'mobilelegends' => ['br'],
    'pubg' => ['br']
];

foreach ($games as $game => $countries) {
    foreach ($countries as $country) {
        echo "Testing $game ($country)...\n";
        $products = $smile->fetchProductsFromWebsite($game, $country);
        echo "Found " . count($products) . " products.\n";
        
        if (!empty($products)) {
            echo "Sample product:\n";
            print_r($products[0]);
        } else {
            echo "No products found. Last error: " . $smile->getLastError() . "\n";
        }
        echo "----------------------------------------\n";
    }
}
