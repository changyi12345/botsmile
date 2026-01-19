<?php
// Fetch the page content
$url = 'https://www.smile.one/merchant/game/magicchessgogo';
$html = file_get_contents($url);

if ($html) {
    // Find all input fields
    if (preg_match_all('/<input[^>]+>/', $html, $matches)) {
        print_r($matches[0]);
    }
}
