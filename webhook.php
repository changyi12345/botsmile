<?php
/**
 * Webhook Example for SmileOne
 * 
 * This example shows how to use webhooks with SmileOne
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smile.php';

// Method 1: Set webhook URL via constructor
$smile = new SmileOne('br', 'https://polls-findlaw-boating-south.trycloudflare.com/receive');

// Method 2: Set webhook URL after instantiation
$smile = new SmileOne('br');
$smile->setWebhookUrl('https://polls-findlaw-boating-south.trycloudflare.com/receive');

// Method 3: Set webhook URL via environment variable
// Set WEBHOOK_URL environment variable before running:
// export WEBHOOK_URL=https://polls-findlaw-boating-south.trycloudflare.com/receive
// Or in Windows:
// set WEBHOOK_URL=https://polls-findlaw-boating-south.trycloudflare.com/receive

// Example: Recharge with webhook notification
$result = $smile->recharge('123456789', '1234', '100 Diamonds', 'user123');
if ($result && $result['success']) {
    echo "Recharge successful! Webhook notification sent.\n";
} else {
    echo "Recharge failed: " . $smile->getLastError() . "\n";
}

// Example: Check balance with webhook notification
$balance = $smile->getBalance();
if ($balance !== false) {
    echo "Balance: {$balance}\n";
    echo "Webhook notification sent.\n";
} else {
    echo "Failed to get balance: " . $smile->getLastError() . "\n";
}

/**
 * Webhook Event Types:
 * 
 * 1. recharge.success - Sent when recharge is successful
 *    Data includes: userId, zoneId, username, productName, productIds, country, etc.
 * 
 * 2. recharge.failed - Sent when recharge fails
 *    Data includes: userId, zoneId, productName, productId, error, country, etc.
 * 
 * 3. balance.check.success - Sent when balance check is successful
 *    Data includes: country, balance, timestamp
 * 
 * 4. balance.check.failed - Sent when balance check fails
 *    Data includes: country, error, httpStatus (if available), timestamp
 * 
 * 5. products.sync.completed - Sent when product sync is completed
 *    Data includes: totalProducts, successCount, totalAttempts, timestamp
 */

/**
 * Webhook Payload Format:
 * 
 * {
 *   "event": "recharge.success",
 *   "timestamp": "2024-01-01 12:00:00",
 *   "data": {
 *     "userId": "123456789",
 *     "zoneId": "1234",
 *     "username": "PlayerName",
 *     "productName": "100 Diamonds",
 *     "productIds": ["12345"],
 *     "country": "br",
 *     "requestedBy": "user123",
 *     "timestamp": "2024-01-01 12:00:00",
 *     "results": [...]
 *   },
 *   "signature": "hmac_sha256_signature_if_secret_set"
 * }
 */

/**
 * Example Webhook Receiver (PHP):
 * 
 * <?php
 * // webhook_receiver.php
 * 
 * $secret = 'your-webhook-secret'; // Should match WEBHOOK_SECRET
 * 
 * $payload = file_get_contents('php://input');
 * $data = json_decode($payload, true);
 * 
 * // Verify signature if secret is set
 * if (!empty($secret) && isset($data['signature'])) {
 *     $expectedSignature = hash_hmac('sha256', json_encode([
 *         'event' => $data['event'],
 *         'timestamp' => $data['timestamp'],
 *         'data' => $data['data']
 *     ]), $secret);
 *     
 *     if ($data['signature'] !== $expectedSignature) {
 *         http_response_code(401);
 *         die('Invalid signature');
 *     }
 * }
 * 
 * // Process webhook event
 * switch ($data['event']) {
 *     case 'recharge.success':
 *         // Handle successful recharge
 *         error_log("Recharge successful: " . json_encode($data['data']));
 *         break;
 *         
 *     case 'recharge.failed':
 *         // Handle failed recharge
 *         error_log("Recharge failed: " . json_encode($data['data']));
 *         break;
 *         
 *     case 'balance.check.success':
 *         // Handle balance check
 *         error_log("Balance: " . $data['data']['balance']);
 *         break;
 *         
 *     default:
 *         error_log("Unknown event: " . $data['event']);
 * }
 * 
 * http_response_code(200);
 * echo json_encode(['status' => 'ok']);
 * ?>
 */
