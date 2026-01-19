<?php
/**
 * API Test Script
 * Test admin/admin_api.php endpoints
 */

// Test API endpoint
$base_url = 'http://127.0.0.1:8000/admin/admin_api.php';

echo "=== Admin API Test ===\n\n";

// Test 1: Check if API is accessible
echo "Test 1: Checking API accessibility...\n";
$ch = curl_init($base_url . '?action=test');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 || $http_code == 401 || $http_code == 400) {
    echo "✓ API is accessible (HTTP $http_code)\n";
    if ($response) {
        $data = json_decode($response, true);
        if ($data) {
            echo "  Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
    }
} else {
    echo "✗ API not accessible (HTTP $http_code)\n";
}

echo "\n";

// Test 2: Check API structure
echo "Test 2: Checking API file...\n";
if (file_exists('admin/admin_api.php')) {
    echo "✓ admin/admin_api.php exists\n";
    
    $content = file_get_contents('admin/admin_api.php');
    if (strpos($content, 'json_encode') !== false) {
        echo "✓ API returns JSON\n";
    }
    if (strpos($content, 'session_start') !== false) {
        echo "✓ API uses session authentication\n";
    }
    if (strpos($content, 'get_bot_status') !== false) {
        echo "✓ Bot status endpoint exists\n";
    }
} else {
    echo "✗ admin/admin_api.php not found\n";
}

echo "\n";

// Test 3: Syntax check
echo "Test 3: Checking PHP syntax...\n";
$output = [];
$return_var = 0;
exec('php -l admin/admin_api.php 2>&1', $output, $return_var);
if ($return_var === 0) {
    echo "✓ PHP syntax is valid\n";
} else {
    echo "✗ PHP syntax errors found:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}

echo "\n";
echo "=== Test Complete ===\n";
echo "\n";
echo "To test API endpoints:\n";
echo "1. Start server: php -S 127.0.0.1:8000\n";
echo "2. Test endpoint: curl http://127.0.0.1:8000/admin/admin_api.php?action=get_bot_status\n";
echo "3. Or use browser: http://127.0.0.1:8000/admin/admin_api.php?action=get_bot_status\n";
