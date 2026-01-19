<?php
require_once __DIR__ . '/smile.php';

// Initialize SmileOne with PUBG Brazil context
$smile = new SmileOne('pubg_br');

// Test Player ID provided by user
$userId = '123456789';
$zoneId = ''; // PUBG doesn't use zone ID
$productId = '22594'; // Example product ID from products.json (checking earlier output)

echo "Testing PUBG Role Check...\n";
// echo "Endpoint: " . $smile->getEndpointForCountry('checkrole') . "\n"; 

$result = $smile->checkRole($userId, $zoneId, $productId);

if ($result) {
    echo "SUCCESS: Found username: " . $result . "\n";
} else {
    echo "FAILED: " . $smile->getLastError() . "\n";
}
