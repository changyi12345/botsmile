# SmileOne Cookies နဲ့ User Agent အသုံးပြုပုံ

## အနှစ်ချုပ်

SmileOne system က **cookies** နဲ့ **user agent** ကို automatically အသုံးပြုပါတယ်။ Balance fetching နဲ့ products syncing အတွက် SmileOne website နဲ့ interact လုပ်တဲ့အခါ cookies နဲ့ user agent ကို အလိုအလျောက် ပို့ပေးပါတယ်။

## Cookies နဲ့ User Agent ကို အသုံးပြုပုံ

### 1. SmileOne Class (`smile.php`)

SmileOne class က cookies နဲ့ user agent ကို automatically အသုံးပြုပါတယ်:

```php
// Cookies ကို load လုပ်တယ်
private function loadCookies() {
    // cookies.json file ကနေ cookies ကို load လုပ်တယ်
}

// HTTP headers ကို build လုပ်တယ် (cookies နဲ့ user agent ပါဝင်တယ်)
private function buildHeaders($additionalHeaders = []) {
    $headers = [
        'User-Agent' => USER_AGENT,  // config.php က user agent
        'Cookie' => $this->buildCookieHeader(),  // cookies
        // ... other headers
    ];
}

// HTTP request လုပ်တယ် (cookies နဲ့ user agent အလိုအလျောက် သုံးတယ်)
private function makeRequest($url, $method = 'GET', $data = null, $options = []) {
    // Cookies နဲ့ user agent ကို automatically ပို့ပေးတယ်
}
```

### 2. Balance Fetching

**BR Balance** နဲ့ **PHP Balance** ကို fetch လုပ်တဲ့အခါ:

```php
// admin/api/admin_api.php - get_smile_balance action

// BR Balance
$smile = new SmileOne();
$smile->setCountry('br');
$balance_br = $smile->getBalance();  // Cookies နဲ့ user agent အလိုအလျောက် သုံးတယ်

// PHP Balance  
$smile_php = new SmileOne('php');
$balance_php = $smile_php->getBalance();  // Cookies နဲ့ user agent အလိုအလျောက် သုံးတယ်
```

**Dashboard** မှာ balance ကို display လုပ်တယ်:
- `admin/pages/admin_dashboard_main.php` - Balance display
- JavaScript က `admin_api.php?action=get_smile_balance` ကို call လုပ်တယ်
- Balance က cookies နဲ့ user agent သုံးပြီး SmileOne website ကနေ fetch လုပ်တယ်

### 3. Products Syncing

**Game Products Management** မှာ products ကို sync လုပ်တဲ့အခါ:

```php
// admin/api/admin_api.php - sync_products action

$smile = new SmileOne();  // Cookies နဲ့ user agent အလိုအလျောက် load လုပ်တယ်
$products = $smile->syncAllProducts();  // Cookies နဲ့ user agent သုံးပြီး products fetch လုပ်တယ်
```

**Products Page** (`admin/pages/admin_products.php`):
- "Sync from SmileOne" button ကို click လုပ်တဲ့အခါ
- JavaScript က `admin_api.php?action=sync_products` ကို call လုပ်တယ်
- Products က cookies နဲ့ user agent သုံးပြီး SmileOne website ကနေ fetch လုပ်တယ်

## Configuration

### Cookies File
- **Location**: `cookies.json` (root directory)
- **Format**: JSON array of cookie objects
- **Example**:
```json
[
  {
    "name": "session_id",
    "value": "abc123..."
  },
  {
    "name": "csrf_token",
    "value": "xyz789..."
  }
]
```

### User Agent
- **Location**: `config.php`
- **Constant**: `USER_AGENT`
- **Default**: `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36...`

## အလုပ်လုပ်ပုံ

### Balance Fetching Flow

1. **Dashboard Load** → JavaScript က balance fetch လုပ်တယ်
2. **API Call** → `admin_api.php?action=get_smile_balance`
3. **SmileOne Class** → Cookies နဲ့ user agent သုံးပြီး SmileOne website ကို request လုပ်တယ်
4. **Response** → Balance data ကို return လုပ်တယ်
5. **Display** → Dashboard မှာ balance ကို show လုပ်တယ်

### Products Syncing Flow

1. **User Click** → "Sync from SmileOne" button ကို click လုပ်တယ်
2. **API Call** → `admin_api.php?action=sync_products`
3. **SmileOne Class** → Cookies နဲ့ user agent သုံးပြီး SmileOne website ကနေ products fetch လုပ်တယ်
4. **Save** → Products ကို `products.json` file ထဲမှာ save လုပ်တယ်
5. **Display** → Products page မှာ products ကို show လုပ်တယ်

## Verification

### Cookies ကို verify လုပ်ပုံ:

```php
// SmileOne class က cookies load လုပ်ထားတာကို check လုပ်တယ်
$smile = new SmileOne();
$reflection = new ReflectionClass($smile);
$cookiesProperty = $reflection->getProperty('cookies');
$cookiesProperty->setAccessible(true);
$loadedCookies = $cookiesProperty->getValue($smile);

if (empty($loadedCookies)) {
    // Cookies not loaded
} else {
    // Cookies loaded: count($loadedCookies) cookies
}
```

### User Agent ကို verify လုပ်ပုံ:

```php
// config.php က user agent ကို check လုပ်တယ်
echo USER_AGENT;  // User agent string ကို show လုပ်တယ်
```

## Error Handling

### Cookies Issues:
- **Cookies file not found**: `cookies.json` file မရှိရင်
- **Invalid cookies**: Cookies format မမှန်ရင်
- **Cookies expired**: Session expired ဖြစ်ရင်

### User Agent:
- User agent က `config.php` က automatically သုံးတယ်
- All requests မှာ user agent က automatically ပို့ပေးတယ်

## Logging

All requests မှာ cookies နဲ့ user agent usage ကို log လုပ်တယ်:

```
✅ makeRequest: Sending 5 cookies for URL: https://www.smile.one/...
🔄 Fetching BR balance with cookies and user agent...
✅ BR balance fetched successfully: 1000.00
```

## Summary

✅ **Balance Fetching**: Cookies နဲ့ user agent အလိုအလျောက် သုံးတယ်
✅ **Products Syncing**: Cookies နဲ့ user agent အလိုအလျောက် သုံးတယ်
✅ **All SmileOne Requests**: Cookies နဲ့ user agent အလိုအလျောက် သုံးတယ်

**No additional configuration needed!** SmileOne class က automatically cookies နဲ့ user agent ကို အသုံးပြုပါတယ်။
