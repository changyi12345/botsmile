# SmileOne REST API Documentation

SmileOne API ကို cookies နဲ့ user agent အသုံးပြုပြီး SmileOne website နဲ့ interact လုပ်နိုင်ပါတယ်။

## Base URL

```
http://your-domain.com/api.php
```

## Authentication

လက်ရှိမှာ authentication မလိုအပ်ပါ။ (အနာဂတ်မှာ API key support ထည့်သွင်းနိုင်ပါတယ်)

## Endpoints

### 1. API Status

**GET** `/api.php/status` or `/api.php`

API status နဲ့ available endpoints ကို ပြပေးပါတယ်။

**Response:**
```json
{
  "success": true,
  "api": "SmileOne API",
  "version": "1.0.0",
  "status": "online",
  "timestamp": "2024-01-01 12:00:00",
  "endpoints": {
    "POST /api.php/recharge": "Perform recharge",
    "GET /api.php/balance": "Get balance",
    "POST /api.php/check-role": "Check role/username",
    "GET /api.php/products": "Get products",
    "POST /api.php/sync-products": "Sync products from website",
    "GET /api.php/status": "API status"
  }
}
```

---

### 2. Recharge

**POST** `/api.php/recharge`

Recharge operation လုပ်ပါတယ်။

**Request Body:**
```json
{
  "userId": "123456789",
  "zoneId": "1234",
  "productName": "100 Diamonds",
  "country": "br",
  "requestedBy": "user123"
}
```

**Parameters:**
- `userId` (required): User ID / Player ID
- `zoneId` (optional): Zone ID (MLBB အတွက် required, Pubg/HoK အတွက် optional)
- `productName` (required): Product name (e.g., "100 Diamonds")
- `country` (optional): Country code (br, php, pubg_br, pubg_php, hok_br, hok_php)
- `requestedBy` (optional): Who requested this (default: "api")

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "success": true,
    "userId": "123456789",
    "zoneId": "1234",
    "username": "PlayerName",
    "productName": "100 Diamonds",
    "productIds": ["12345"],
    "country": "br",
    "requestedBy": "user123",
    "timestamp": "2024-01-01 12:00:00",
    "results": [...]
  },
  "message": "Recharge completed successfully"
}
```

**Response (Error):**
```json
{
  "success": false,
  "error": "Error message",
  "error_code": "RECHARGE_FAILED",
  "timestamp": "2024-01-01 12:00:00"
}
```

**cURL Example:**
```bash
curl -X POST http://your-domain.com/api.php/recharge \
  -H "Content-Type: application/json" \
  -d '{
    "userId": "123456789",
    "zoneId": "1234",
    "productName": "100 Diamonds",
    "country": "br"
  }'
```

---

### 3. Get Balance

**GET** `/api.php/balance`

Current balance ကို ယူပါတယ်။

**Query Parameters:**
- `country` (optional): Country code (br, php)

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "balance": "1000.00",
    "country": "br",
    "currency": "SmileCoins"
  },
  "message": "Balance retrieved successfully"
}
```

**cURL Example:**
```bash
curl "http://your-domain.com/api.php/balance?country=br"
```

---

### 4. Get Balance (All Countries)

**GET** `/api.php/balance-all`

All countries ရဲ့ balance ကို ယူပါတယ်။

**Response:**
```json
{
  "success": true,
  "data": {
    "br": "1000.00",
    "php": "500.00"
  },
  "message": "Balances retrieved successfully"
}
```

---

### 5. Check Role

**POST** `/api.php/check-role`

User role/username ကို check လုပ်ပါတယ်။

**Request Body:**
```json
{
  "userId": "123456789",
  "zoneId": "1234",
  "productId": "12345",
  "country": "br"
}
```

**Parameters:**
- `userId` (required): User ID
- `zoneId` (optional): Zone ID
- `productId` (required): Product ID
- `country` (optional): Country code

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "userId": "123456789",
    "zoneId": "1234",
    "username": "PlayerName",
    "productId": "12345",
    "country": "br"
  },
  "message": "Role checked successfully"
}
```

**cURL Example:**
```bash
curl -X POST http://your-domain.com/api.php/check-role \
  -H "Content-Type: application/json" \
  -d '{
    "userId": "123456789",
    "zoneId": "1234",
    "productId": "12345"
  }'
```

---

### 6. Get Products

**GET** `/api.php/products`

Available products ကို ယူပါတယ်။

**Query Parameters:**
- `country` (optional): Filter by country

**Response:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "name": "100 Diamonds",
        "price": 10.00,
        "products": ["12345"],
        "country": "br",
        "diamonds": 100
      }
    ],
    "count": 1
  },
  "message": "Products retrieved successfully"
}
```

---

### 7. Sync Products

**POST** `/api.php/sync-products`

SmileOne website ကနေ products ကို sync လုပ်ပါတယ်။

**Request Body (Optional):**
```json
{
  "game": "mobilelegends",
  "country": "br"
}
```

**Parameters:**
- `game` (optional): Game type (mobilelegends, pubg, hok) - မပေးရင် all games sync လုပ်ပါတယ်
- `country` (optional): Country code

**Response:**
```json
{
  "success": true,
  "data": {
    "products": [...],
    "count": 50
  },
  "message": "Products synced successfully"
}
```

**cURL Example:**
```bash
# Sync all products
curl -X POST http://your-domain.com/api.php/sync-products

# Sync specific game/country
curl -X POST http://your-domain.com/api.php/sync-products \
  -H "Content-Type: application/json" \
  -d '{
    "game": "mobilelegends",
    "country": "br"
  }'
```

---

### 8. Get/Set Country

**GET** `/api.php/country` - Get current country

**POST** `/api.php/country` - Set country

**Request Body (POST):**
```json
{
  "country": "br"
}
```

---

### 9. API Info

**GET** `/api.php/info`

API information နဲ့ system status ကို ယူပါတယ်။

**Response:**
```json
{
  "success": true,
  "data": {
    "api_version": "1.0.0",
    "smile_base_url": "https://www.smile.one",
    "user_agent": "Mozilla/5.0...",
    "current_country": "br",
    "cookies": {
      "loaded": true,
      "count": 5
    },
    "products": {
      "loaded": true,
      "count": 50
    },
    "webhook": {
      "enabled": true,
      "url": "https://your-webhook-url.com"
    }
  }
}
```

---

## Error Handling

All errors return JSON format:

```json
{
  "success": false,
  "error": "Error message",
  "error_code": "ERROR_CODE",
  "timestamp": "2024-01-01 12:00:00"
}
```

**Common Error Codes:**
- `VALIDATION_ERROR` - Missing or invalid parameters
- `RECHARGE_FAILED` - Recharge operation failed
- `BALANCE_ERROR` - Balance check failed
- `CHECK_ROLE_FAILED` - Role check failed
- `SYNC_FAILED` - Product sync failed
- `NOT_FOUND` - Endpoint not found

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (validation error, operation failed)
- `404` - Not Found (endpoint not found)
- `405` - Method Not Allowed
- `500` - Internal Server Error

---

## CORS Support

API supports CORS for web applications:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key
```

---

## Webhook Integration

API automatically sends webhook notifications when configured. See `webhook_example.php` for details.

**Webhook Events:**
- `recharge.success` - Recharge completed successfully
- `recharge.failed` - Recharge failed
- `balance.check.success` - Balance check successful
- `balance.check.failed` - Balance check failed
- `products.sync.completed` - Product sync completed

---

## Examples

### JavaScript (Fetch API)

```javascript
// Recharge
fetch('http://your-domain.com/api.php/recharge', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    userId: '123456789',
    zoneId: '1234',
    productName: '100 Diamonds',
    country: 'br'
  })
})
.then(response => response.json())
.then(data => console.log(data));

// Get Balance
fetch('http://your-domain.com/api.php/balance?country=br')
  .then(response => response.json())
  .then(data => console.log(data));
```

### PHP

```php
// Recharge
$data = [
    'userId' => '123456789',
    'zoneId' => '1234',
    'productName' => '100 Diamonds',
    'country' => 'br'
];

$ch = curl_init('http://your-domain.com/api.php/recharge');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

print_r($result);
```

### Python

```python
import requests

# Recharge
url = 'http://your-domain.com/api.php/recharge'
data = {
    'userId': '123456789',
    'zoneId': '1234',
    'productName': '100 Diamonds',
    'country': 'br'
}

response = requests.post(url, json=data)
result = response.json()
print(result)

# Get Balance
response = requests.get('http://your-domain.com/api.php/balance?country=br')
result = response.json()
print(result)
```

---

## Notes

1. **Cookies**: API automatically uses cookies from `cookies.json` file
2. **User Agent**: API uses configured user agent from `config.php`
3. **Rate Limiting**: Be careful with sync-products endpoint as it may trigger rate limiting
4. **Webhooks**: Configure webhook URL in `config.php` or via environment variable `WEBHOOK_URL`

---

## Support

For issues or questions, check the error logs or contact support.
