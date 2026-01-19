<?php

// ==============================================
// 📱 TELEGRAM FUNCTIONS
// ==============================================

function sendMessage($chatId, $text, $parseMode = 'Markdown', $keyboard = null) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log errors if any
    if ($httpCode !== 200 || !empty($error)) {
        $logFile = __DIR__ . '/../bot_log.txt';
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['description'] ?? $response;
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ❌ Error sending message to {$chatId}: HTTP {$httpCode}, Error: {$error}, API Response: {$errorMsg}\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    } else {
        // Log successful sends for debugging
        $responseData = json_decode($response, true);
        if (isset($responseData['ok']) && !$responseData['ok']) {
            $logFile = __DIR__ . '/../bot_log.txt';
            $logMessage = "[" . date('Y-m-d H:i:s') . "] ⚠️ Message send returned ok=false to {$chatId}: " . ($responseData['description'] ?? 'Unknown') . "\n";
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    return $response;
}

function editMessageText($chatId, $messageId, $text, $parseMode = 'Markdown', $keyboard = null) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/editMessageText";
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log errors if any
    if ($httpCode !== 200 || !empty($error)) {
        $logFile = __DIR__ . '/../bot_log.txt';
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['description'] ?? $response;
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ❌ Error editing message {$messageId} in {$chatId}: HTTP {$httpCode}, Error: {$error}, API Response: {$errorMsg}\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    return $response;
}

function deleteMessage($chatId, $messageId) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/deleteMessage";
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function answerCallbackQuery($callbackQueryId, $text = null, $showAlert = false) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/answerCallbackQuery";
    $data = [
        'callback_query_id' => $callbackQueryId
    ];
    
    if ($text) {
        $data['text'] = $text;
        $data['show_alert'] = $showAlert;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function sendTyping($chatId) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendChatAction";
    $data = ['chat_id' => $chatId, 'action' => 'typing'];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data
    ]);
    
    curl_exec($ch);
    curl_close($ch);
}

function deleteWebhook() {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/deleteWebhook";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function getUpdates($offset) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/getUpdates?offset={$offset}&timeout=30";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Connection error: " . $error);
        return false;
    }
    
    return json_decode($response, true);
}
