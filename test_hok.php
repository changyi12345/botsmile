<?php
require_once __DIR__ . '/smile.php';

echo "Testing HoK Bypass...\n";

// Test 1: Direct instantiation with hok_br
echo "Test 1: new SmileOne('hok_br')\n";
$smile = new SmileOne('hok_br');
$country = $smile->getCurrentCountry();
echo "Current Country: " . $country . "\n";

if ($country !== 'hok_br') {
    echo "❌ Country mismatch! Expected hok_br, got $country\n";
    echo "Dumping Country Config:\n";
    print_r($GLOBALS['COUNTRY_CONFIG']);
} else {
    echo "✅ Country matched.\n";
}

// Check bypass logic
$isHoK = (strpos($country, 'hok') === 0);
echo "Is HoK (strpos check): " . ($isHoK ? "YES" : "NO") . "\n";

// Test checkRole
echo "Calling checkRole...\n";
$result = $smile->checkRole('123456', '0', '100'); // Dummy data
echo "Result: " . $result . "\n";

if ($result === "Player 123456") {
    echo "✅ Bypass WORKING!\n";
} else {
    echo "❌ Bypass FAILED!\n";
    // Check if it tried to make a request (simulated by error log or just inference)
}
