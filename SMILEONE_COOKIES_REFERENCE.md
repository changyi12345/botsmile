# SmileOne Cookies Reference

SmileOne website သုံးတဲ့ cookies စာရင်းနဲ့ အရေးကြီးတဲ့ cookies များ။

## အရေးကြီးတဲ့ Cookies (Authentication & Session)

### Session Cookies (အရေးအကြီးဆုံး)

| Cookie Name | Provider | Purpose | Expiry | Required |
|------------|----------|---------|--------|----------|
| `PHPSESSID` | smile.one | Preserves user session state across page requests | Session | ✅ **Critical** |
| `_csrf` | smile.one | Prevents cross-site request forgery (CSRF protection) | Session | ✅ **Critical** |

### Cloudflare Cookies

| Cookie Name | Provider | Purpose | Expiry | Required |
|------------|----------|---------|--------|----------|
| `__cf_bm` | smile.one | Distinguishes between humans and bots | 1 day | ⚠️ Important |

### Preference Cookies

| Cookie Name | Provider | Purpose | Expiry | Required |
|------------|----------|---------|--------|----------|
| `country` | smile.one | Determines preferred country-setting | Persistent | ⚠️ Recommended |
| `lang` | smile.one | Determines preferred language | Persistent | ⚠️ Recommended |
| `idlang` | smile.one | Necessary for correct display of website's language | Persistent | ⚠️ Recommended |
| `langhtml` | smile.one | Necessary for correct display of selected country's flag | Persistent | ⚠️ Recommended |
| `website_path` | smile.one | Necessary for proper country redirection | 2 days | ⚠️ Recommended |
| `cc_cookie` | smile.one | Stores user's cookie consent state | 1 year | ⚠️ Optional |

### Analytics Cookies (Optional - Not Required for API)

- `_ga`, `_gat`, `_gid` - Google Analytics
- `collect` - Google Analytics tracking

### Marketing Cookies (Optional - Not Required for API)

- `_fbp`, `_gcl_au` - Facebook/Google Ads
- `IDE`, `test_cookie` - Google DoubleClick

### Third-party Cookies (Optional - Not Required for API)

- YouTube cookies (if videos are embedded)
- LiveChat cookies (chat functionality)

## အရေးကြီးတဲ့ Cookies များ (API အတွက်)

### Minimum Required Cookies:

1. **`PHPSESSID`** - Session ID, authentication အတွက် လိုအပ်တယ်
2. **`_csrf`** - CSRF token, security အတွက် လိုအပ်တယ်

### Highly Recommended Cookies:

3. **`__cf_bm`** - Cloudflare bot management, request ကို block မဖြစ်အောင် လိုအပ်တယ်
4. **`country`** - Country preference, proper content display အတွက်
5. **`lang`** - Language preference
6. **`website_path`** - Country redirection အတွက်

## Cookies.json Format

```json
[
  {
    "name": "PHPSESSID",
    "value": "your_session_id_here",
    "domain": ".smile.one",
    "path": "/"
  },
  {
    "name": "_csrf",
    "value": "your_csrf_token_here",
    "domain": ".smile.one",
    "path": "/"
  },
  {
    "name": "__cf_bm",
    "value": "cloudflare_bot_management_value",
    "domain": ".smile.one",
    "path": "/"
  },
  {
    "name": "country",
    "value": "br",
    "domain": ".smile.one",
    "path": "/"
  }
]
```

## Cookie ရယူပုံ

### Browser ကနေ Cookies ယူနည်း:

1. **Chrome/Edge:**
   - F12 → Application tab → Cookies → https://www.smile.one
   - Cookies ကို copy လုပ်ပြီး `cookies.json` file ထဲမှာ save လုပ်ပါ

2. **Firefox:**
   - F12 → Storage tab → Cookies → https://www.smile.one
   - Cookies ကို copy လုပ်ပြီး `cookies.json` file ထဲမှာ save လုပ်ပါ

3. **Browser Extension:**
   - "Cookie Editor" extension သုံးပြီး cookies export လုပ်နိုင်တယ်

### Important Notes:

⚠️ **Session Cookies** (`PHPSESSID`, `_csrf`) က **Session-based** ဖြစ်တယ် - browser close လုပ်တဲ့အခါ expire ဖြစ်နိုင်တယ်။

⚠️ **Cloudflare Cookie** (`__cf_bm`) က 1 day expiry ရှိတယ် - daily update လုပ်ဖို့ လိုတယ်။

✅ **Preference Cookies** (`country`, `lang`) က persistent ဖြစ်တယ် - တစ်ခါ set လုပ်ပြီးရင် ရေရှည် အသုံးပြုနိုင်တယ်။

## Troubleshooting

### Session Expired Error:

**Problem:** `PHPSESSID` or `_csrf` cookie expired or invalid

**Solution:**
1. SmileOne website မှာ login လုပ်ပါ
2. New cookies ကို copy လုပ်ပြီး `cookies.json` file ကို update လုပ်ပါ
3. အထူးသဖြင့် `PHPSESSID` နဲ့ `_csrf` cookies ကို update လုပ်ပါ

### Cloudflare Blocked:

**Problem:** `__cf_bm` cookie missing or invalid

**Solution:**
1. `__cf_bm` cookie ကို include လုပ်ပါ
2. User-Agent ကို real browser ရဲ့ user agent နဲ့ match လုပ်ပါ
3. Request headers ကို proper format နဲ့ ပို့ပါ

### Country/Language Issues:

**Problem:** Wrong country or language displayed

**Solution:**
1. `country` cookie ကို set လုပ်ပါ (e.g., "br", "php")
2. `lang` cookie ကို set လုပ်ပါ
3. `website_path` cookie ကို proper value နဲ့ set လုပ်ပါ

## Cookie Update Best Practices

1. **Regular Updates:** Session cookies ကို regularly update လုပ်ပါ
2. **Full Cookie Set:** Minimum required cookies သာမက recommended cookies ကိုလည်း include လုပ်ပါ
3. **Proper Domain:** Cookies ကို `.smile.one` domain နဲ့ set လုပ်ပါ
4. **Secure Cookies:** HTTPS-only cookies ကို properly handle လုပ်ပါ

## Testing Cookies

Cookies က valid ဖြစ်မဖြစ် test လုပ်ဖို့:

```php
// Test if cookies are loaded
$smile = new SmileOne();
$balance = $smile->getBalance();
if ($balance === false) {
    echo "Error: " . $smile->getLastError();
    // Check if it's a session expired error
    // If yes, update cookies.json with new cookies
}
```

## Summary

**Required Cookies:**
- `PHPSESSID` (Session) ✅
- `_csrf` (Session) ✅

**Highly Recommended:**
- `__cf_bm` (Cloudflare) ⚠️
- `country` (Preference) ⚠️
- `lang` (Preference) ⚠️

**Optional:**
- Analytics cookies (not needed for API)
- Marketing cookies (not needed for API)
- Third-party cookies (not needed for API)
