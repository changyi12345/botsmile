# Cookie Errors ဖြေရှင်းထားတာများ

## ပြင်ဆင်ထားတာများ

### 1. Cookie Validation System

✅ **Critical Cookies Check**
- `PHPSESSID` - Session ID validation
- `_csrf` - CSRF token validation
- Automatic validation when cookies are loaded

✅ **Recommended Cookies Check**
- `__cf_bm` - Cloudflare bot management
- `country` - Country preference
- `lang` - Language preference

### 2. Improved Error Messages

**Before:**
```
Session expired (302). Cookies may be invalid or expired.
```

**After:**
```
Session expired (HTTP 302). Your cookies (PHPSESSID, _csrf) may be invalid or expired. 
Please: 1) Login to SmileOne website, 2) Copy new cookies from browser, 3) Update cookies.json file.
```

### 3. Cookie Validation in All Methods

✅ **getBalance()** - Validates cookies before fetching balance
✅ **recharge()** - Validates cookies before recharge
✅ **fetchProductsFromWebsite()** - Validates cookies before fetching products
✅ **checkRole()** - Handles session expired errors
✅ **requestFlowId()** - Handles session expired errors
✅ **payOrder()** - Handles session expired errors

### 4. Better Session Expired Detection

✅ **HTTP 302/301 Redirect Detection**
- Detects redirects to login pages
- Checks Location header
- Checks X-Redirect header (Cloudflare)
- Checks response body for redirect URLs

✅ **Cloudflare Support**
- Detects Cloudflare headers
- Handles X-Redirect header
- Better error messages for Cloudflare issues

## Cookie Validation Features

### Automatic Validation

```php
// Cookies are automatically validated when loaded
$smile = new SmileOne();

// Validation checks:
// ✅ PHPSESSID exists
// ✅ _csrf exists
// ⚠️ __cf_bm exists (recommended)
// ⚠️ country exists (recommended)
// ⚠️ lang exists (recommended)
```

### Manual Validation

```php
// Check if critical cookies are present
if ($smile->hasCriticalCookies()) {
    // Proceed with request
} else {
    // Show error: "Critical cookies missing"
}
```

## Error Handling Improvements

### 1. Pre-Request Validation

**Before making any request:**
- ✅ Checks if cookies are loaded
- ✅ Checks if critical cookies (PHPSESSID, _csrf) exist
- ✅ Provides clear error message if missing

### 2. During Request

**HTTP 302/301 Redirect:**
- ✅ Detects redirect to login page
- ✅ Checks multiple header sources (Location, X-Redirect)
- ✅ Checks response body for redirect URLs
- ✅ Provides detailed error message with solution

### 3. Post-Request

**Error Messages:**
- ✅ Clear instructions on how to fix
- ✅ Specific cookies that need updating
- ✅ Step-by-step solution

## Common Errors & Solutions

### Error: "Critical cookies (PHPSESSID, _csrf) are missing"

**Solution:**
1. Login to SmileOne website (https://www.smile.one)
2. Open Browser DevTools (F12)
3. Go to Application/Storage → Cookies → www.smile.one
4. Copy `PHPSESSID` and `_csrf` cookies
5. Update `cookies.json` file

### Error: "Session expired (HTTP 302)"

**Solution:**
1. Your session cookies have expired
2. Follow steps above to get new cookies
3. Update `cookies.json` file with new cookies

### Error: "Cloudflare cookie (__cf_bm) missing"

**Solution:**
1. This is a warning, not critical
2. Add `__cf_bm` cookie to `cookies.json`
3. Helps prevent Cloudflare blocking

## Cookies.json Format

```json
[
  {
    "name": "PHPSESSID",
    "value": "your_session_id_here"
  },
  {
    "name": "_csrf",
    "value": "your_csrf_token_here"
  },
  {
    "name": "__cf_bm",
    "value": "cloudflare_bot_management_value"
  },
  {
    "name": "country",
    "value": "br"
  },
  {
    "name": "lang",
    "value": "en"
  }
]
```

## Validation Logs

When cookies are loaded, you'll see logs like:

```
✅ Loaded 13 cookies from cookies.json
✅ Critical cookies (PHPSESSID, _csrf) found
✅ Cloudflare cookie (__cf_bm) found
⚠️ Missing recommended cookies: country, lang
```

Or if critical cookies are missing:

```
✅ Loaded 5 cookies from cookies.json
❌ Missing critical cookies - authentication may fail
⚠️ Missing critical cookies: PHPSESSID, _csrf
⚠️ These cookies are required for authentication. Please update cookies.json
```

## Testing

### Test Cookie Validation

```php
$smile = new SmileOne();

// Check if critical cookies exist
if ($smile->hasCriticalCookies()) {
    echo "✅ Critical cookies found\n";
} else {
    echo "❌ Critical cookies missing\n";
    echo "Error: " . $smile->getLastError() . "\n";
}

// Try to get balance
$balance = $smile->getBalance();
if ($balance === false) {
    echo "Error: " . $smile->getLastError() . "\n";
}
```

## Summary

✅ **Cookie Validation** - Automatic validation on load
✅ **Better Error Messages** - Clear instructions on how to fix
✅ **Session Expired Detection** - Detects and handles expired sessions
✅ **Cloudflare Support** - Handles Cloudflare-specific headers
✅ **Comprehensive Coverage** - All methods validate cookies

**All cookie-related errors are now properly handled with clear error messages and solutions!**
