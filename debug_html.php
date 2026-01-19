<?php
require_once 'smile.php';

$smile = new SmileOne();
// $smile->loadCookies(); // Called in constructor

// Test MLBB BR
$url = 'https://www.smile.one/merchant/mobilelegends';
$response = $smile->makeRequest($url, 'GET');

if ($response && isset($response['body'])) {
    file_put_contents('mlbb_br.html', $response['body']);
    echo "Saved mlbb_br.html (" . strlen($response['body']) . " bytes)\n";
} else {
    echo "Failed to fetch MLBB BR\n";
}

// Test PUBG BR
$url = 'https://www.smile.one/merchant/pubgmobile';
$response = $smile->makeRequest($url, 'GET');

if ($response && isset($response['body'])) {
    file_put_contents('pubg_br.html', $response['body']);
    echo "Saved pubg_br.html (" . strlen($response['body']) . " bytes)\n";
} else {
    echo "Failed to fetch PUBG BR\n";
}
?>
