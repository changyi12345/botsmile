<?php
/**
 * Test SmileOne API Endpoints
 * 
 * This script tests all API endpoints
 */

$baseUrl = 'http://127.0.0.1:8000/api.php';

echo "=== SmileOne API Test ===\n\n";

// Test 1: API Status
echo "Test 1: API Status\n";
echo "-------------------\n";
$ch = curl_init($baseUrl . '/status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "✓ API is online\n";
    echo "  Version: " . ($data['version'] ?? 'unknown') . "\n";
    echo "  Endpoints: " . count($data['endpoints'] ?? []) . "\n";
} else {
    echo "✗ API not accessible (HTTP $httpCode)\n";
}
echo "\n";

// Test 2: Get API Info
echo "Test 2: Get API Info\n";
echo "-------------------\n";
$ch = curl_init($baseUrl . '/info');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✓ API Info retrieved\n";
        echo "  Current Country: " . ($data['data']['current_country'] ?? 'unknown') . "\n";
        echo "  Cookies Loaded: " . ($data['data']['cookies']['loaded'] ? 'Yes' : 'No') . "\n";
        echo "  Cookies Count: " . ($data['data']['cookies']['count'] ?? 0) . "\n";
        echo "  Products Count: " . ($data['data']['products']['count'] ?? 0) . "\n";
    }
} else {
    echo "✗ Failed to get API info (HTTP $httpCode)\n";
}
echo "\n";

// Test 3: Get Balance
echo "Test 3: Get Balance\n";
echo "-------------------\n";
$ch = curl_init($baseUrl . '/balance?country=br');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✓ Balance retrieved\n";
        echo "  Balance: " . ($data['data']['balance'] ?? 'N/A') . "\n";
        echo "  Country: " . ($data['data']['country'] ?? 'N/A') . "\n";
    } else {
        echo "✗ Balance check failed: " . ($data['error'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "✗ Failed to get balance (HTTP $httpCode)\n";
    if ($response) {
        $data = json_decode($response, true);
        echo "  Error: " . ($data['error'] ?? 'Unknown error') . "\n";
    }
}
echo "\n";

// Test 4: Get Products
echo "Test 4: Get Products\n";
echo "-------------------\n";
$ch = curl_init($baseUrl . '/products');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✓ Products retrieved\n";
        echo "  Count: " . ($data['data']['count'] ?? 0) . "\n";
        if (isset($data['data']['products'][0])) {
            $firstProduct = $data['data']['products'][0];
            echo "  First Product: " . ($firstProduct['name'] ?? 'N/A') . "\n";
        }
    }
} else {
    echo "✗ Failed to get products (HTTP $httpCode)\n";
}
echo "\n";

// Test 5: Check Role (Example - will fail if invalid data)
echo "Test 5: Check Role (Example)\n";
echo "-------------------\n";
echo "⚠️  Skipping - requires valid userId, zoneId, and productId\n";
echo "   Example request:\n";
echo "   POST $baseUrl/check-role\n";
echo "   {\n";
echo "     \"userId\": \"123456789\",\n";
echo "     \"zoneId\": \"1234\",\n";
echo "     \"productId\": \"12345\"\n";
echo "   }\n";
echo "\n";

// Test 6: Recharge (Example - will fail if invalid data)
echo "Test 6: Recharge (Example)\n";
echo "-------------------\n";
echo "⚠️  Skipping - requires valid userId, zoneId, and productName\n";
echo "   Example request:\n";
echo "   POST $baseUrl/recharge\n";
echo "   {\n";
echo "     \"userId\": \"123456789\",\n";
echo "     \"zoneId\": \"1234\",\n";
echo "     \"productName\": \"100 Diamonds\",\n";
echo "     \"country\": \"br\"\n";
echo "   }\n";
echo "\n";

echo "=== Test Complete ===\n\n";
echo "To test with actual data:\n";
echo "1. Make sure cookies.json exists with valid cookies\n";
echo "2. Start server: php -S 127.0.0.1:8000\n";
echo "3. Run this script: php test_api_endpoints.php\n";
echo "4. Or test manually with curl:\n";
echo "   curl \"$baseUrl/balance?country=br\"\n";
echo "   curl \"$baseUrl/products\"\n";
echo "\n";
