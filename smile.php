<?php
require_once __DIR__ . '/config.php';

class SmileOne {
    private $cookies = [];
    private $countryConfig;
    private $lastError = '';
    private $webhookUrl = '';
    private $webhookSecret = '';
    
    public function __construct($country = null, $webhookUrl = null) {
        $this->loadCookies();
        $this->setCountry($country);
        $this->webhookUrl = $webhookUrl ?? '';
        $this->webhookSecret = '';
    }
    
    /**
     * Load cookies from file
     */
    private function loadCookies() {
        if (!file_exists(COOKIES_FILE)) {
            $this->lastError = 'Cookies file not found: ' . COOKIES_FILE;
            error_log("❌ Cookies file not found: " . COOKIES_FILE);
            return false;
        }
        
        $content = @file_get_contents(COOKIES_FILE);
        if ($content === false) {
            $this->lastError = 'Failed to read cookies file';
            error_log("❌ Failed to read cookies file: " . COOKIES_FILE);
            return false;
        }
        
        $this->cookies = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = 'Invalid cookies JSON: ' . json_last_error_msg();
            $this->cookies = [];
            error_log("❌ Invalid cookies JSON: " . json_last_error_msg());
            return false;
        }
        
        if (empty($this->cookies) || !is_array($this->cookies)) {
            $this->lastError = 'Cookies file is empty or invalid format';
            $this->cookies = [];
            error_log("❌ Cookies file is empty or invalid format");
            return false;
        }
        
        // Validate critical cookies
        $this->validateCookies();
        
        error_log("✅ Loaded " . count($this->cookies) . " cookies from " . COOKIES_FILE);
        return true;
    }
    
    /**
     * Validate critical cookies
     */
    private function validateCookies() {
        $cookieNames = [];
        foreach ($this->cookies as $cookie) {
            if (isset($cookie['name'])) {
                $cookieNames[] = $cookie['name'];
            }
        }
        
        $criticalCookies = ['PHPSESSID', '_csrf'];
        $recommendedCookies = ['__cf_bm', 'country', 'lang'];
        
        $missingCritical = [];
        $missingRecommended = [];
        
        // Case-insensitive check for critical cookies
        foreach ($criticalCookies as $cookieName) {
            $found = false;
            foreach ($cookieNames as $name) {
                if (strcasecmp($name, $cookieName) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingCritical[] = $cookieName;
            }
        }
        
        // Case-insensitive check for recommended cookies
        foreach ($recommendedCookies as $cookieName) {
            $found = false;
            foreach ($cookieNames as $name) {
                if (strcasecmp($name, $cookieName) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingRecommended[] = $cookieName;
            }
        }
        
        if (!empty($missingCritical)) {
            error_log("⚠️ Missing critical cookies: " . implode(', ', $missingCritical));
            error_log("⚠️ These cookies are required for authentication. Please update cookies.json");
            error_log("📋 Current cookies (" . count($cookieNames) . "): " . implode(', ', array_slice($cookieNames, 0, 10)) . (count($cookieNames) > 10 ? '...' : ''));
        }
        
        if (!empty($missingRecommended)) {
            error_log("⚠️ Missing recommended cookies: " . implode(', ', $missingRecommended));
            error_log("⚠️ These cookies help prevent blocking and improve functionality");
        }
        
        // Log cookie status (case-insensitive)
        $hasPHPSESSID = false;
        $hasCSRF = false;
        $hasCFBM = false;
        
        foreach ($cookieNames as $name) {
            if (strcasecmp($name, 'PHPSESSID') === 0) {
                $hasPHPSESSID = true;
            }
            if (strcasecmp($name, '_csrf') === 0) {
                $hasCSRF = true;
            }
            if (strcasecmp($name, '__cf_bm') === 0) {
                $hasCFBM = true;
            }
        }
        
        if ($hasPHPSESSID && $hasCSRF) {
            error_log("✅ Critical cookies (PHPSESSID, _csrf) found");
        } else {
            error_log("❌ Missing critical cookies - authentication may fail");
            if ($hasPHPSESSID) {
                error_log("   ✓ PHPSESSID found");
            } else {
                error_log("   ✗ PHPSESSID missing");
            }
            if ($hasCSRF) {
                error_log("   ✓ _csrf found");
            } else {
                error_log("   ✗ _csrf missing");
            }
        }
        
        if ($hasCFBM) {
            error_log("✅ Cloudflare cookie (__cf_bm) found");
        } else {
            error_log("⚠️ Cloudflare cookie (__cf_bm) missing - may be blocked by Cloudflare");
        }
    }
    
    /**
     * Check if critical cookies are present
     */
    public function hasCriticalCookies() {
        if (empty($this->cookies) || !is_array($this->cookies)) {
            return false;
        }
        
        $cookieNames = [];
        foreach ($this->cookies as $cookie) {
            if (isset($cookie['name'])) {
                $cookieNames[] = $cookie['name'];
            }
        }
        
        // Check for critical cookies (case-insensitive lookup)
        $hasPHPSESSID = false;
        $hasCSRF = false;
        
        foreach ($cookieNames as $name) {
            if (strcasecmp($name, 'PHPSESSID') === 0) {
                $hasPHPSESSID = true;
            }
            if (strcasecmp($name, '_csrf') === 0) {
                $hasCSRF = true;
            }
        }
        
        // If validation fails, log which cookies are present
        if (!$hasPHPSESSID || !$hasCSRF) {
            $missingCookies = [];
            if (!$hasPHPSESSID) {
                $missingCookies[] = 'PHPSESSID';
            }
            if (!$hasCSRF) {
                $missingCookies[] = '_csrf';
            }
            
            error_log("❌ Missing critical cookies: " . implode(', ', $missingCookies));
            error_log("📋 Available cookies (" . count($cookieNames) . "): " . implode(', ', array_slice($cookieNames, 0, 10)) . (count($cookieNames) > 10 ? '...' : ''));
            
            // Check for similar cookie names (might be case difference)
            $similarNames = [];
            foreach ($cookieNames as $name) {
                if (stripos($name, 'csrf') !== false || stripos($name, 'session') !== false || stripos($name, 'php') !== false) {
                    $similarNames[] = $name;
                }
            }
            if (!empty($similarNames)) {
                error_log("💡 Similar cookie names found: " . implode(', ', $similarNames));
            }
        }
        
        return $hasPHPSESSID && $hasCSRF;
    }
    
    /**
     * Save cookies to file
     */
    public function saveCookies() {
        $json = json_encode($this->cookies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents(COOKIES_FILE, $json) !== false;
    }
    
    /**
     * Set country configuration
     */
    public function setCountry($country) {
        global $COUNTRY_CONFIG;
        
        if ($country && isset($COUNTRY_CONFIG[$country])) {
            $this->countryConfig = $COUNTRY_CONFIG[$country];
        } else {
            $this->countryConfig = $COUNTRY_CONFIG[DEFAULT_COUNTRY];
        }
        
        return $this->countryConfig['code'];
    }
    
    /**
     * Get current country
     */
    public function getCurrentCountry() {
        return $this->countryConfig['code'];
    }
    
    /**
     * Get endpoint for current country/game type
     * Note: HoK and Pubg may use MLBB endpoints or have their own
     */
    private function getEndpointForCountry($endpointType) {
        $country = $this->countryConfig['code'] ?? DEFAULT_COUNTRY;
        
        // Determine game type from country code
        // HoK and Pubg might use MLBB endpoints or have separate endpoints
        // Try game-specific first, but fallback to mobilelegends if 404
        $gameType = 'mobilelegends'; // Default
        
        if (strpos($country, 'pubg') === 0) {
            $gameType = 'pubgmobile'; // Try /merchant/pubgmobile/ endpoints
        } elseif (strpos($country, 'hok') === 0) {
            // HoK uses /merchant/hok/ endpoints
            $gameType = 'hok'; 
        } elseif (strpos($country, 'magicchessgogo') === 0) {
            $gameType = 'magicchessgogo'; // Magic Chess GoGo uses /merchant/magicchessgogo/ endpoints
        } else {
            $gameType = 'mobilelegends'; // MLBB uses /merchant/mobilelegends/ endpoints
        }
        
        $baseEndpoint = "/merchant/{$gameType}";
        
        switch ($endpointType) {
            case 'checkrole':
                return $baseEndpoint . '/checkrole';
            case 'query':
                return $baseEndpoint . '/query';
            case 'pay':
                return $baseEndpoint . '/pay';
            default:
                return $baseEndpoint . '/checkrole';
        }
    }
    
    /**
     * Build cookie header string
     */
    private function buildCookieHeader() {
        if (empty($this->cookies) || !is_array($this->cookies)) {
            error_log("⚠️ buildCookieHeader: No cookies available");
            return '';
        }
        
        $cookieStrings = [];
        foreach ($this->cookies as $cookie) {
            if (isset($cookie['name']) && isset($cookie['value'])) {
                $cookieStrings[] = $cookie['name'] . '=' . $cookie['value'];
            } else {
                error_log("⚠️ buildCookieHeader: Invalid cookie format: " . json_encode($cookie));
            }
        }
        
        $cookieHeader = implode('; ', $cookieStrings);
        if (empty($cookieHeader)) {
            error_log("⚠️ buildCookieHeader: No valid cookies to send");
        }
        
        return $cookieHeader;
    }
    
    /**
     * Build HTTP headers
     */
    private function buildHeaders($additionalHeaders = []) {
        // Use SmileOne site's actual user agent
        $headers = [
            'User-Agent' => USER_AGENT,
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9',
            // Don't include Accept-Encoding here - we'll handle it manually
            'Connection' => 'keep-alive',
            'Origin' => SMILE_BASE_URL,
            'Referer' => SMILE_BASE_URL . $this->countryConfig['basePath'] . '/',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        
        $cookieHeader = $this->buildCookieHeader();
        if ($cookieHeader) {
            $headers['Cookie'] = $cookieHeader;
        }
        
        // Merge additional headers (they can override defaults)
        $mergedHeaders = array_merge($headers, $additionalHeaders);
        
        return $mergedHeaders;
    }
    
    /**
     * Make HTTP request
     * Automatically uses cookies and user agent
     */
    public function makeRequest($url, $method = 'GET', $data = null, $options = []) {
        $ch = curl_init();
        
        $headers = $this->buildHeaders($options['headers'] ?? []);
        $headerArray = [];
        foreach ($headers as $key => $value) {
            // Skip Accept-Encoding from headers array, we'll handle it separately
            if (strtolower($key) !== 'accept-encoding') {
                $headerArray[] = $key . ': ' . $value;
            }
        }
        
        // Verify cookies are being sent
        $cookieHeader = $this->buildCookieHeader();
        if (empty($cookieHeader)) {
            error_log("⚠️ makeRequest: No cookies to send for URL: {$url}");
        } else {
            error_log("✅ makeRequest: Sending " . count($this->cookies) . " cookies for URL: {$url}");
        }
        
        // Check if we should follow redirects (default: true, but can be disabled for balance checks)
        $followLocation = $options['follow_location'] ?? true;
        $timeout = $options['timeout'] ?? TIMEOUT;
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_MAXREDIRS => $followLocation ? 5 : 0, // Don't follow redirects if disabled
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5, // Fail fast on DNS/connection issues (5 seconds)
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headerArray,
            CURLOPT_ENCODING => false, // Disable automatic encoding handling to avoid Brotli issues
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_HEADER => true, // Include headers in response to check for redirects
            CURLOPT_DNS_CACHE_TIMEOUT => 0, // Disable DNS cache to avoid stale DNS
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                if (is_array($data)) {
                    $data = http_build_query($data);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                if (!isset($headers['Content-Type'])) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headerArray, [
                        'Content-Type: application/x-www-form-urlencoded'
                    ]));
                }
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        
        curl_close($ch);
        
        if ($error) {
            // Handle DNS errors specifically
            if (strpos($error, 'Could not resolve host') !== false || strpos($error, 'Name or service not known') !== false) {
                $this->lastError = 'DNS Error: Could not resolve host. Please check your internet connection or DNS settings.';
                error_log("❌ DNS Error: Could not resolve host for {$url}");
            } elseif (strpos($error, 'Connection timed out') !== false || strpos($error, 'Operation timed out') !== false) {
                $this->lastError = 'Connection timeout: Server did not respond in time.';
                error_log("❌ Connection timeout for {$url}");
            } else {
                $this->lastError = 'CURL Error: ' . $error;
                error_log("❌ CURL Error: {$error} for {$url}");
            }
            return false;
        }
        
        // Parse headers and body if CURLOPT_HEADER is enabled
        $headers = [];
        $body = $response;
        
        if (strpos($response, "\r\n\r\n") !== false || strpos($response, "\n\n") !== false) {
            // Split headers and body
            $headerBodySplit = preg_split('/\r?\n\r?\n/', $response, 2);
            if (count($headerBodySplit) === 2) {
                $headerText = $headerBodySplit[0];
                $body = $headerBodySplit[1];
                
                // Parse headers (skip HTTP status line)
                // Handle both \r\n and \n line endings
                $headerLines = preg_split('/\r?\n/', $headerText);
                foreach ($headerLines as $line) {
                    $line = trim($line);
                    // Skip empty lines
                    if (empty($line)) {
                        continue;
                    }
                    // Skip HTTP status line (e.g., "HTTP/1.1 302 Found")
                    if (preg_match('/^HTTP\//', $line)) {
                        continue;
                    }
                    // Parse header line (key: value format)
                    if (strpos($line, ':') !== false) {
                        list($key, $value) = explode(':', $line, 2);
                        $key = trim($key);
                        $value = trim($value);
                        // Store header with lowercase key for case-insensitive lookup
                        $headers[strtolower($key)] = $value;
                        // Also store with original case for compatibility
                        $headers[$key] = $value;
                    }
                }
            }
        }
        
        // Check for redirect location in headers (case-insensitive)
        // Cloudflare and some servers use X-Redirect or other custom headers
        if (($httpCode == 301 || $httpCode == 302) && empty($redirectUrl)) {
            // Try standard Location header with different case variations
            $locationKeys = ['Location', 'location', 'LOCATION'];
            foreach ($locationKeys as $key) {
                if (isset($headers[$key])) {
                    $redirectUrl = trim($headers[$key]);
                    break;
                }
            }
            
            // Try lowercase key
            if (empty($redirectUrl) && isset($headers['location'])) {
                $redirectUrl = trim($headers['location']);
            }
            
            // Try Cloudflare X-Redirect header (common with Cloudflare)
            if (empty($redirectUrl)) {
                $xRedirectKeys = ['X-Redirect', 'x-redirect', 'X-REDIRECT'];
                foreach ($xRedirectKeys as $key) {
                    if (isset($headers[$key])) {
                        $redirectUrl = trim($headers[$key]);
                        error_log("🔄 makeRequest: Found redirect URL in {$key} header: {$redirectUrl}");
                        break;
                    }
                }
                // Also try lowercase
                if (empty($redirectUrl) && isset($headers['x-redirect'])) {
                    $redirectUrl = trim($headers['x-redirect']);
                    error_log("🔄 makeRequest: Found redirect URL in x-redirect header: {$redirectUrl}");
                }
            }
            
            // Try other common redirect headers
            if (empty($redirectUrl)) {
                $otherRedirectKeys = ['Redirect', 'redirect', 'REDIRECT', 'X-Location', 'x-location'];
                foreach ($otherRedirectKeys as $key) {
                    if (isset($headers[$key])) {
                        $redirectUrl = trim($headers[$key]);
                        error_log("🔄 makeRequest: Found redirect URL in {$key} header: {$redirectUrl}");
                        break;
                    }
                }
            }
        }
        
        // Manually handle gzip/deflate if needed
        if ($body && $contentType) {
            // Check if response is gzipped
            if (strpos($contentType, 'gzip') !== false || 
                (strlen($body) > 2 && substr($body, 0, 2) === "\x1f\x8b")) {
                $decompressed = @gzdecode($body);
                if ($decompressed !== false) {
                    $body = $decompressed;
                }
            }
        }
        
        // Add redirect URL to headers if found
        if ($redirectUrl) {
            $headers['Location'] = $redirectUrl;
            $headers['location'] = $redirectUrl; // Also add lowercase version
        }
        
        // Log headers for debugging redirects
        if ($httpCode == 301 || $httpCode == 302) {
            $locationFound = !empty($redirectUrl);
            error_log("🔄 makeRequest: HTTP {$httpCode} redirect. Location header found: " . ($locationFound ? 'Yes' : 'No'));
            if ($locationFound) {
                error_log("🔄 makeRequest: Redirect Location: {$redirectUrl}");
            } else {
                // Log all header keys for debugging
                $headerKeys = array_keys($headers);
                error_log("⚠️ makeRequest: No Location header found. Available headers: " . implode(', ', $headerKeys));
                
                // Check for x-redirect specifically
                if (isset($headers['x-redirect'])) {
                    error_log("⚠️ makeRequest: x-redirect header value: " . $headers['x-redirect']);
                }
                if (isset($headers['X-Redirect'])) {
                    error_log("⚠️ makeRequest: X-Redirect header value: " . $headers['X-Redirect']);
                }
            }
        }
        
        return [
            'status' => $httpCode,
            'body' => $body,
            'headers' => $headers
        ];
    }
    
    /**
     * Get balance for all countries
     */
    public function getBalanceAll() {
        $balances = [];
        $countries = ['br', 'php'];
        
        foreach ($countries as $country) {
            $this->setCountry($country);
            $balance = $this->getBalance();
            if ($balance !== false) {
                $balances[$country] = $balance;
            }
        }
        
        return $balances;
    }
    
    /**
     * Get current balance
     * Uses cookies and user agent automatically
     */
    public function getBalance() {
        global $BALANCE_SELECTORS;
        
        // Verify cookies are loaded
        if (empty($this->cookies)) {
            $this->lastError = "Cookies not loaded. Please check cookies.json file.";
            error_log("❌ getBalance: No cookies available");
            return false;
        }
        
        // Verify critical cookies are present
        if (!$this->hasCriticalCookies()) {
            $this->lastError = "Critical cookies (PHPSESSID, _csrf) are missing. Please update cookies.json with valid session cookies from SmileOne website.";
            error_log("❌ getBalance: Critical cookies missing");
            return false;
        }
        
        $country = $this->getCurrentCountry();
        $url = SMILE_BASE_URL . $this->countryConfig['basePath'] . ORDER_PAGE;
        
        // Log request details for debugging
        error_log("🔄 getBalance: Fetching from {$url} with " . count($this->cookies) . " cookies, User-Agent: " . USER_AGENT);
        
        // Don't follow redirects for balance check so we can detect login redirects
        $response = $this->makeRequest($url, 'GET', null, ['follow_location' => false]);
        if (!$response || $response['status'] !== 200) {
            $httpStatus = $response['status'] ?? 'No response';
            
            // Handle different HTTP status codes
            if ($httpStatus == 302 || $httpStatus == 301) {
                // Check if redirect is to login page (session expired)
                $location = '';
                $headers = $response['headers'] ?? [];
                
                // Try different case variations for Location header
                $locationKeys = ['Location', 'location', 'LOCATION'];
                foreach ($locationKeys as $key) {
                    if (isset($headers[$key])) {
                        $location = trim($headers[$key]);
                        break;
                    }
                }
                
                // Also check lowercase key
                if (empty($location) && isset($headers['location'])) {
                    $location = trim($headers['location']);
                }
                
                // Log the location for debugging
                if (!empty($location)) {
                    error_log("🔄 getBalance: HTTP {$httpStatus} redirect detected. Location: {$location}");
                } else {
                    error_log("⚠️ getBalance: HTTP {$httpStatus} redirect detected but Location header not found in response headers");
                    
                    // Try to extract from response body if available
                    if (!empty($response['body'])) {
                        $body = $response['body'];
                        
                        // Try to find redirect URL in meta refresh
                        if (preg_match('/<meta[^>]*http-equiv=["\']refresh["\'][^>]*content=["\'][^;]*url=([^"\']+)["\']/i', $body, $matches)) {
                            $location = $matches[1];
                            error_log("🔄 getBalance: Found redirect URL in meta refresh: {$location}");
                        } 
                        // Try JavaScript redirect
                        elseif (preg_match('/window\.location\s*=\s*["\']([^"\']+)["\']/i', $body, $matches)) {
                            $location = $matches[1];
                            error_log("🔄 getBalance: Found redirect URL in JavaScript: {$location}");
                        }
                        // Try window.location.href
                        elseif (preg_match('/window\.location\.href\s*=\s*["\']([^"\']+)["\']/i', $body, $matches)) {
                            $location = $matches[1];
                            error_log("🔄 getBalance: Found redirect URL in window.location.href: {$location}");
                        }
                        // Try document.location
                        elseif (preg_match('/document\.location\s*=\s*["\']([^"\']+)["\']/i', $body, $matches)) {
                            $location = $matches[1];
                            error_log("🔄 getBalance: Found redirect URL in document.location: {$location}");
                        }
                        // Try HTML anchor with onclick redirect
                        elseif (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $body, $matches)) {
                            $potentialLocation = $matches[1];
                            // Only use if it looks like a redirect URL (not relative path)
                            if (strpos($potentialLocation, 'http') === 0 || strpos($potentialLocation, '/') === 0) {
                                $location = $potentialLocation;
                                error_log("🔄 getBalance: Found potential redirect URL in anchor href: {$location}");
                            }
                        }
                    }
                    
                    // If still no location found, check if it's a Cloudflare challenge or similar
                    if (empty($location)) {
                        $headers = $response['headers'] ?? [];
                        // Check for Cloudflare specific headers
                        $isCloudflare = isset($headers['cf-ray']) || 
                                       (isset($headers['server']) && stripos($headers['server'], 'cloudflare') !== false) ||
                                       (isset($headers['cf-cache-status']));
                        
                        if ($isCloudflare) {
                            error_log("⚠️ getBalance: Cloudflare detected but no redirect location found. This might be a Cloudflare challenge or session issue.");
                            
                            // Check x-redirect header value specifically
                            $xRedirectValue = $headers['x-redirect'] ?? $headers['X-Redirect'] ?? '';
                            if (!empty($xRedirectValue)) {
                                $location = trim($xRedirectValue);
                                error_log("🔄 getBalance: Found redirect URL in x-redirect header: {$location}");
                            } else {
                                // For Cloudflare, 302 without Location usually means session expired
                                error_log("⚠️ getBalance: x-redirect header exists but value is empty. Session likely expired.");
                                $location = ''; // Will trigger session expired message
                            }
                        } else {
                            // For non-Cloudflare, 302 without Location usually means session expired
                            error_log("⚠️ getBalance: HTTP 302 without Location header. Session likely expired.");
                            $location = ''; // Will trigger session expired message
                        }
                    }
                }
                
                // Determine if it's a login redirect
                $isLoginRedirect = false;
                if (!empty($location)) {
                    $isLoginRedirect = (
                        stripos($location, 'login') !== false || 
                        stripos($location, 'signin') !== false || 
                        stripos($location, 'sign-in') !== false ||
                        stripos($location, '/auth/') !== false ||
                        stripos($location, '/account/login') !== false ||
                        stripos($location, '/user/login') !== false
                    );
                } else {
                    // If no location found, assume it's a login redirect (common for expired sessions)
                    $isLoginRedirect = true;
                }
                
                if ($isLoginRedirect || empty($location)) {
                    // Provide detailed error message with instructions
                    $errorMsg = "Session expired (HTTP {$httpStatus}). ";
                    $errorMsg .= "Your cookies (PHPSESSID, _csrf) may be invalid or expired. ";
                    $errorMsg .= "Please: 1) Login to SmileOne website, 2) Copy new cookies from browser, 3) Update cookies.json file.";
                    
                    $this->lastError = $errorMsg;
                    
                    if (!empty($location)) {
                        error_log("❌ getBalance: Redirected to login page: {$location}");
                    } else {
                        error_log("❌ getBalance: HTTP {$httpStatus} redirect detected (likely session expired, no Location header)");
                        error_log("💡 Solution: Update PHPSESSID and _csrf cookies in cookies.json file");
                    }
                } else {
                    $this->lastError = "Redirected (HTTP {$httpStatus}) to: " . $location . ". Cookies may need to be updated.";
                    error_log("⚠️ getBalance: HTTP {$httpStatus} redirect to: {$location}");
                }
            } elseif ($httpStatus == 429) {
                $this->lastError = "Rate limited (429). Please wait before checking balance again.";
            } else {
                $this->lastError = "Failed to fetch balance page. HTTP: " . $httpStatus;
            }
            error_log("❌ getBalance failed: HTTP {$httpStatus} - " . $this->lastError);
            
            // Send webhook notification for balance check failure
            $this->sendWebhookWithRetry('balance.check.failed', [
                'country' => $country,
                'error' => $this->lastError,
                'httpStatus' => $httpStatus,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            return false;
        }
        
        $html = $response['body'];
        
        // Simple HTML parsing using regex (alternative to DOMDocument)
        $selector = $BALANCE_SELECTORS[$country] ?? $BALANCE_SELECTORS['br'];
        
        // Convert CSS selector to simple regex pattern
        // This is a simplified version - for complex selectors, consider using simple_html_dom library
        $pattern = '/<div[^>]*class="balance-coins"[^>]*>.*?<p[^>]*>([^<]+)<\/p>.*?<p[^>]*>([^<]+)<\/p>/is';
        
        if (preg_match($pattern, $html, $matches)) {
            if (isset($matches[2])) {
                $balance = trim($matches[2]);
                
                // Send webhook notification for successful balance check
                $this->sendWebhookWithRetry('balance.check.success', [
                    'country' => $country,
                    'balance' => $balance,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                return $balance;
            }
        }
        
        // Alternative pattern for balance extraction
        $pattern2 = '/class="[^"]*balance[^"]*"[^>]*>.*?([0-9,.]+)/is';
        if (preg_match($pattern2, $html, $matches)) {
            if (isset($matches[1])) {
                $balance = trim($matches[1]);
                
                // Send webhook notification for successful balance check
                $this->sendWebhookWithRetry('balance.check.success', [
                    'country' => $country,
                    'balance' => $balance,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                return $balance;
            }
        }
        
        $this->lastError = 'Could not find balance in HTML';
        
        // Send webhook notification for balance extraction failure
        $this->sendWebhookWithRetry('balance.check.failed', [
            'country' => $country,
            'error' => $this->lastError,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return false;
    }
    
    /**
     * Check role/username (public API method)
     * For Pubg: userId is Player ID (zoneId = empty string or '0')
     * For HoK: userId is UID (zoneId = empty string or '0')
     * For MLBB: userId and zoneId are both required
     */
    public function checkRole($userId, $zoneId, $productId) {
        return $this->checkRoleInternal($userId, $zoneId, $productId);
    }
    
    /**
     * Check role/username (internal method, uses cookies and user agent automatically)
     * For Pubg: userId is Player ID (zoneId = empty string or '0')
     * For HoK: userId is UID (zoneId = empty string or '0')
     * For MLBB: userId and zoneId are both required
     */
    private function checkRoleInternal($userId, $zoneId, $productId) {
        // Special handling for MagicChessGoGo
        $country = $this->getCurrentCountry();
        error_log("🔍 checkRoleInternal called for Country: {$country}, UserID: {$userId}");

        if (strpos($country, 'magicchessgogo') === 0) {
            return $this->getMagicChessGoGoUserName($userId, $zoneId);
        }

        // Special handling for PUBG Mobile and HoK - Bypass checkrole as endpoint returns 404
        if (strpos($country, 'pubg') === 0 || strpos($country, 'hok') === 0) {
            error_log("✅ Bypassing checkRole for {$country}");
            // PUBG and HoK don't support role checking via this API or return 404
            // Return a placeholder name to allow the flow to proceed
            return "Player " . $userId;
        }

        // Get endpoint based on current country/game type
        $endpoint = $this->getEndpointForCountry('checkrole');
        $url = SMILE_BASE_URL . $this->countryConfig['basePath'] . $endpoint;
        
        $country = $this->getCurrentCountry();
        $isPubg = (strpos($country, 'pubg') === 0);
        $isHoK = (strpos($country, 'hok') === 0);
        $isMagicChess = (strpos($country, 'magicchessgogo') === 0);
        
        // For Pubg, HoK, and MagicChessGoGo, zoneId should be empty string or '0' (not used)
        if ($isPubg || $isHoK || $isMagicChess) {
            $zoneId = ''; // Pubg: Player ID only, HoK: UID only, MagicChessGoGo: UID only
        }

        // Extract CSRF token (case-insensitive)
        $csrfToken = '';
        foreach ($this->cookies as $cookie) {
            if (strcasecmp($cookie['name'], '_csrf') === 0) {
                $csrfToken = $cookie['value'];
                break;
            }
        }
        
        $data = [
            'user_id' => $userId,
            'zone_id' => $zoneId ?: '0', // Use '0' if empty for API compatibility
            'pid' => $productId,
            'checkrole' => 1,
            'pay_methond' => 'null',
            'channel_method' => 'null',
            '_csrf' => $csrfToken
        ];
        
        // Construct Referer based on game type
        // e.g. https://www.smile.one/br/merchant/mobilelegends
        $refererPath = str_replace('/checkrole', '', $endpoint);
        $referer = SMILE_BASE_URL . $this->countryConfig['basePath'] . $refererPath;
        
        // Make request without Accept-Encoding to avoid Brotli issues
        $response = $this->makeRequest($url, 'POST', $data, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'Referer' => $referer,
                'Origin' => 'https://www.smile.one',
                'X-Requested-With' => 'XMLHttpRequest'
            ]
        ]);
        
        if (!$response) {
            return false;
        }
        
        // Check HTTP status code
        if ($response['status'] !== 200) {
            // Handle redirects (session expired)
            if ($response['status'] == 302 || $response['status'] == 301) {
                $this->lastError = 'Session expired (HTTP ' . $response['status'] . '). Please update PHPSESSID and _csrf cookies in cookies.json file.';
                error_log("❌ checkRole: Session expired - HTTP {$response['status']}");
            } else {
                $this->lastError = 'HTTP ' . $response['status'] . ': Check role request failed';
            }
            return false;
        }
        
        // Try to decode JSON
        $result = json_decode($response['body'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON decode fails, log the raw response for debugging
            $this->lastError = 'JSON decode error: ' . json_last_error_msg() . ' | Response: ' . substr($response['body'], 0, 200);
            return false;
        }
        
        if ($result && isset($result['code']) && $result['code'] == 200 && isset($result['username'])) {
            return $result['username'];
        }
        
        // Get error message from response
        $errorMsg = 'Check role failed';
        if (isset($result['info'])) {
            $errorMsg = $result['info'];
        } elseif (isset($result['message'])) {
            $errorMsg = $result['message'];
        } elseif (isset($result['error'])) {
            $errorMsg = $result['error'];
        }
        
        $this->lastError = $errorMsg;
        return false;
    }
    
    /**
     * Request flow ID (uses cookies and user agent automatically)
     * For Pubg: userId is Player ID (zoneId = empty string or '0')
     * For HoK: userId is UID (zoneId = empty string or '0')
     * For MLBB: userId and zoneId are both required
     */
    private function requestFlowId($userId, $zoneId, $productId) {
        // Get endpoint based on current country/game type
        $endpoint = $this->getEndpointForCountry('query');
        $url = SMILE_BASE_URL . $this->countryConfig['basePath'] . $endpoint;
        
        $country = $this->getCurrentCountry();
        $isPubg = (strpos($country, 'pubg') === 0);
        $isHoK = (strpos($country, 'hok') === 0);
        $isMagicChess = (strpos($country, 'magicchessgogo') === 0);
        
        // For Pubg, HoK, and MagicChessGoGo, zoneId should be empty string or '0' (not used)
        if ($isPubg || $isHoK || $isMagicChess) {
            $zoneId = ''; // Pubg: Player ID only, HoK: UID only, MagicChessGoGo: UID only
        }
        
        $data = [
            'user_id' => $userId,
            'zone_id' => $zoneId ?: '0', // Use '0' if empty for API compatibility
            'pid' => $productId,
            'checkrole' => null,
            'pay_methond' => 'smilecoin',
            'channel_method' => 'smilecoin'
        ];
        
        $response = $this->makeRequest($url, 'POST', $data, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ]
        ]);
        
        if (!$response) {
            return false;
        }
        
        // Check HTTP status code for session expired
        if ($response['status'] == 302 || $response['status'] == 301) {
            $this->lastError = 'Session expired (HTTP ' . $response['status'] . '). Please update PHPSESSID and _csrf cookies in cookies.json file.';
            error_log("❌ requestFlowId: Session expired - HTTP {$response['status']}");
            return false;
        }
        
        if ($response['status'] !== 200) {
            $this->lastError = 'HTTP ' . $response['status'] . ': Request flow ID failed';
            return false;
        }
        
        $result = json_decode($response['body'], true);
        
        if ($result && isset($result['code']) && $result['code'] == 200 && isset($result['flowid'])) {
            return $result['flowid'];
        }
        
        $this->lastError = isset($result['info']) ? $result['info'] : 'Request flow ID failed';
        return false;
    }
    
    /**
     * Pay order (uses cookies and user agent automatically)
     * For Pubg: zoneId can be same as userId or placeholder
     * For HoK: zoneId is required
     */
    private function payOrder($userId, $zoneId, $productId, $flowId) {
        // Get endpoint based on current country/game type
        $endpoint = $this->getEndpointForCountry('pay');
        $url = SMILE_BASE_URL . $this->countryConfig['basePath'] . $endpoint;
        
        $country = $this->getCurrentCountry();
        $isPubg = (strpos($country, 'pubg') === 0);
        
        // For Pubg, if zoneId is empty or 'N/A', use userId as zoneId
        if ($isPubg && (empty($zoneId) || $zoneId === 'N/A')) {
            $zoneId = $userId;
        }
        
        $data = [
            'user_id' => $userId,
            'zone_id' => $zoneId,
            'product_id' => $productId,
            'flowid' => $flowId,
            'pay_methond' => 'smilecoin',
            'channel_method' => 'smilecoin',
            'email' => null,
            'coupon_id' => null
        ];
        
        // For redirect checking, disable follow location
        $ch = curl_init();
        $headers = $this->buildHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json'
        ]);
        
        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = $key . ': ' . $value;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false, // Don't follow redirects
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_TIMEOUT => TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headerArray,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HEADER => true // Get headers
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        // Check for redirect to success page
        if ($httpCode >= 300 && $httpCode < 400) {
            // Extract Location header
            $location = '';
            if (preg_match('/^Location:\s*(.*)$/mi', $response, $matches)) {
                $location = trim($matches[1]);
            }
            
            // Check if it's a session expired redirect (login page)
            if (!empty($location) && (
                stripos($location, 'login') !== false || 
                stripos($location, 'signin') !== false ||
                stripos($location, '/auth/') !== false
            )) {
                $this->lastError = 'Session expired (HTTP ' . $httpCode . '). Please update PHPSESSID and _csrf cookies in cookies.json file.';
                error_log("❌ payOrder: Session expired - redirected to login page: {$location}");
                return false;
            }
            
            // Check if redirect is to success page
            if (!empty($location)) {
                $successUrl = $this->countryConfig['success_url'];
                if (strpos($location, $successUrl) !== false) {
                    return true;
                }
            }
        }
        
        // Check for session expired (302 without success redirect)
        if ($httpCode == 302 || $httpCode == 301) {
            $this->lastError = 'Session expired (HTTP ' . $httpCode . '). Please update PHPSESSID and _csrf cookies in cookies.json file.';
            error_log("❌ payOrder: Session expired - HTTP {$httpCode}");
            return false;
        }
        
        $this->lastError = 'Payment failed or not redirected to success page. HTTP: ' . $httpCode;
        return false;
    }
    
    /**
     * Perform recharge for single product ID
     */
    private function performSingleRecharge($userId, $zoneId, $productId) {
        // Step 1: Check role
        $username = $this->checkRole($userId, $zoneId, $productId);
        if (!$username) {
            return [
                'success' => false,
                'error' => 'Check role failed: ' . $this->lastError,
                'username' => ''
            ];
        }
        
        // Step 2: Retry logic for flow ID and payment
        $retries = 0;
        $lastError = '';
        
        while ($retries < MAX_RETRIES) {
            // Step 3: Request flow ID
            $flowId = $this->requestFlowId($userId, $zoneId, $productId);
            if (!$flowId) {
                $lastError = 'Request flow ID failed: ' . $this->lastError;
                $retries++;
                usleep(RETRY_DELAY);
                continue;
            }
            
            // Step 4: Pay order
            $paymentResult = $this->payOrder($userId, $zoneId, $productId, $flowId);
            if ($paymentResult) {
                return [
                    'success' => true,
                    'username' => $username,
                    'product_id' => $productId,
                    'flow_id' => $flowId
                ];
            } else {
                $lastError = 'Payment failed: ' . $this->lastError;
                $retries++;
                usleep(RETRY_DELAY);
            }
        }
        
        return [
            'success' => false,
            'error' => $lastError ?: 'Max retries exceeded',
            'username' => $username,
            'product_id' => $productId
        ];
    }
    
    /**
     * Resolve product IDs from product data
     */
    private function resolveProductIds($product) {
        $ids = [];
        
        if (isset($product['products']) && is_array($product['products'])) {
            $ids = array_filter(array_map('trim', $product['products']));
        } elseif (isset($product['ids']) && is_array($product['ids'])) {
            $ids = array_filter(array_map('trim', $product['ids']));
        } elseif (isset($product['id'])) {
            if (is_array($product['id'])) {
                $ids = array_filter(array_map('trim', $product['id']));
            } elseif (is_string($product['id'])) {
                $ids = array_filter(array_map('trim', explode(',', $product['id'])));
            } else {
                $ids = [trim((string)$product['id'])];
            }
        }
        
        return $ids;
    }
    
    /**
     * Load product database
     */
    public function loadProducts() {
        if (!file_exists(PRODUCTS_FILE)) {
            $this->lastError = 'Products file not found';
            return [];
        }
        
        $content = file_get_contents(PRODUCTS_FILE);
        $products = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = 'Invalid products JSON: ' . json_last_error_msg();
            return [];
        }
        
        return $products;
    }
    
    /**
     * Find product by name
     */
    public function findProduct($productName) {
        $products = $this->loadProducts();
        if (empty($products)) {
            return null;
        }
        
        // 1) Exact, case-insensitive match
        foreach ($products as $product) {
            if (isset($product['name']) && strcasecmp($product['name'], $productName) === 0) {
                return $product;
            }
        }
        
        // 2) Flexible match: allow "DIAMOND 100", "100 Diamonds", "100"
        $normalized = trim($productName);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Extract the last number found as requested diamonds
        $requestedDiamonds = null;
        if (preg_match_all('/\d+/', $normalized, $matches) && !empty($matches[0])) {
            $requestedDiamonds = intval(end($matches[0]));
        }
        
        if ($requestedDiamonds !== null) {
            // Try match against explicit diamonds field first
            foreach ($products as $product) {
                if (isset($product['diamonds'])) {
                    if (intval($product['diamonds']) === $requestedDiamonds) {
                        return $product;
                    }
                }
            }
            // Fallback: match against numeric product name
            foreach ($products as $product) {
                if (isset($product['name']) && is_string($product['name'])) {
                    // If product name is numeric or contains a number, compare
                    if (ctype_digit($product['name'])) {
                        if (intval($product['name']) === $requestedDiamonds) {
                            return $product;
                        }
                    } else {
                        // Extract number from product name and compare
                        if (preg_match('/\d+/', $product['name'], $pm) && !empty($pm[0])) {
                            if (intval($pm[0]) === $requestedDiamonds) {
                                return $product;
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Main recharge function
     * For Pubg: userId is Player ID (no zoneId needed, use empty string or '0')
     * For HoK: userId is UID (no zoneId needed, use empty string or '0')
     * For MLBB: userId and zoneId are both required
     */
    public function recharge($userId, $zoneId, $productName, $requestedBy = 'system', $country = null) {
        // Verify cookies are loaded
        if (empty($this->cookies)) {
            $this->lastError = "Cookies not loaded. Please check cookies.json file.";
            error_log("❌ recharge: No cookies available");
            return false;
        }
        
        // Verify critical cookies are present
        if (!$this->hasCriticalCookies()) {
            $this->lastError = "Critical cookies (PHPSESSID, _csrf) are missing. Please update cookies.json with valid session cookies from SmileOne website.";
            error_log("❌ recharge: Critical cookies missing");
            return false;
        }
        
        // Set country first to determine game type
        if ($country) {
            $this->setCountry($country);
        }
        
        $currentCountry = $this->getCurrentCountry();
        $isPubg = (strpos($currentCountry, 'pubg') === 0);
        $isHoK = (strpos($currentCountry, 'hok') === 0);
        $isMagicChess = (strpos($currentCountry, 'magicchessgogo') === 0);
        
        // Validate inputs based on game type
        if (empty($userId) || empty($productName)) {
            $this->lastError = 'Missing userId or productName';
            return false;
        }
        
        // For Pubg, HoK, and MagicChessGoGo, zoneId is not needed (use empty string or '0')
        if ($isPubg || $isHoK || $isMagicChess) {
            $zoneId = ''; // Pubg uses Player ID only, HoK uses UID only, MagicChessGoGo uses UID only
        } elseif (empty($zoneId)) {
            // For MLBB, zoneId is required
            $this->lastError = 'Missing zoneId (required for Mobile Legends)';
            return false;
        }
        
        // Find product
        $product = $this->findProduct($productName);
        if (!$product) {
            $this->lastError = "Product '$productName' not found";
            return false;
        }
        
        // Resolve product IDs
        $productIds = $this->resolveProductIds($product);
        if (empty($productIds)) {
            $this->lastError = "Product '$productName' has no valid IDs";
            return false;
        }
        
        // Get country from product if not set
        $productCountry = $product['country'] ?? DEFAULT_COUNTRY;
        if (!$country) {
            $this->setCountry($productCountry);
        }
        
        // Perform recharge for each product ID
        $results = [];
        $username = '';
        
        foreach ($productIds as $productId) {
            $result = $this->performSingleRecharge($userId, $zoneId, $productId);
            
            if (!$result['success']) {
                // Send webhook notification for failed recharge
                $this->sendWebhookWithRetry('recharge.failed', [
                    'userId' => $userId,
                    'zoneId' => $zoneId,
                    'productName' => $productName,
                    'productId' => $productId,
                    'error' => $result['error'],
                    'country' => $this->getCurrentCountry(),
                    'requestedBy' => $requestedBy,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                $this->lastError = $result['error'];
                return false;
            }
            
            if (empty($username) && !empty($result['username'])) {
                $username = $result['username'];
            }
            
            $results[] = $result;
        }
        
        // Prepare result
        $result = [
            'success' => true,
            'userId' => $userId,
            'zoneId' => $zoneId,
            'username' => $username,
            'productName' => $productName,
            'productIds' => $productIds,
            'country' => $this->getCurrentCountry(),
            'requestedBy' => $requestedBy,
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $results
        ];
        
        // Send webhook notification for successful recharge
        $this->sendWebhookWithRetry('recharge.success', $result);
        
        return $result;
    }
    
    /**
     * Fetch products from SmileOne website API endpoint (uses cookies and user agent)
     * Tries API endpoints first, then falls back to HTML scraping
     */
    private function fetchProductsFromAPI($gameType = 'mobilelegends', $country = 'br') {
        // Try API endpoint for products list
        // SmileOne might have a product list API endpoint
        $basePath = $this->countryConfig['basePath'] ?? '';
        $endpoint = $this->getEndpointForCountry('query');
        
        // Try different possible API endpoints for product list
        // Use actual merchant URLs from SmileOne website
        $apiEndpoints = [];
        
        // Add game-specific merchant URLs based on actual website structure
        switch ($gameType) {
            case 'magicchessgogo':
                $apiEndpoints[] = SMILE_BASE_URL . $basePath . '/merchant/game/magicchessgogo';
                break;
            case 'mobilelegends':
                $apiEndpoints[] = SMILE_BASE_URL . $basePath . '/merchant/mobilelegends';
                break;
            case 'pubg':
                $apiEndpoints[] = SMILE_BASE_URL . $basePath . '/merchant/pubgmobile';
                break;
            case 'hok':
                $apiEndpoints[] = SMILE_BASE_URL . $basePath . '/merchant/hok';
                break;
        }
        
        // Also try generic API endpoints
        $apiEndpoints[] = SMILE_BASE_URL . $basePath . '/api/products?game=' . $gameType;
        $apiEndpoints[] = SMILE_BASE_URL . $basePath . '/api/product/list?game=' . $gameType;
        $apiEndpoints[] = SMILE_BASE_URL . $basePath . '/merchant/' . $gameType . '/products';
        $apiEndpoints[] = SMILE_BASE_URL . $basePath . '/merchant/' . $gameType . '/productlist';
        
        foreach ($apiEndpoints as $apiIndex => $apiUrl) {
            // Add delay between API endpoint requests to avoid rate limiting
            if ($apiIndex > 0) {
                sleep(1); // 1 second delay between API endpoint attempts
            } else {
                // Add initial delay before first API request
                sleep(1); // 1 second delay before first request
            }
            
            $response = $this->makeRequest($apiUrl, 'GET', null, ['timeout' => 10]);
            
            // Skip if DNS error - don't try other URLs
            if (!$response && strpos($this->lastError, 'DNS Error') !== false) {
                error_log("⚠️ DNS error detected, skipping remaining API endpoints");
                break;
            }
            
            // Check for rate limiting (HTTP 429)
            if ($response && $response['status'] === 429) {
                error_log("⚠️ API endpoint {$apiUrl} returned HTTP 429 (Rate Limited)");
                $this->lastError = 'Rate limited (429). Too many requests. Please wait a few minutes before syncing again.';
                // If rate limited, stop trying other endpoints
                return []; // Return empty array immediately
            }
            
            // Skip 404 errors quickly - endpoint doesn't exist
            if ($response && $response['status'] === 404) {
                error_log("⚠️ API endpoint {$apiUrl} returned HTTP 404 (Not Found) - skipping");
                continue; // Try next endpoint
            }
            
            if ($response && $response['status'] === 200) {
                error_log("✅ API endpoint {$apiUrl} returned HTTP 200");
                $data = json_decode($response['body'], true);
                
                if ($data && isset($data['products']) && is_array($data['products'])) {
                    error_log("✅ Found products array in API response: " . count($data['products']) . " products");
                    $products = [];
                    foreach ($data['products'] as $product) {
                        $productCountry = $country;
                        if ($gameType === 'pubg') {
                            $productCountry = ($country === 'br' || $country === 'php') ? 'pubg_' . $country : 'pubg_br';
                        } elseif ($gameType === 'hok') {
                            $productCountry = ($country === 'br' || $country === 'php') ? 'hok_' . $country : 'hok_br';
                        }
                        
                        $productName = $product['name'] ?? $product['title'] ?? $product['amount'] ?? '';
                        $productId = $product['pid'] ?? $product['id'] ?? $product['product_id'] ?? '';
                        $diamonds = isset($product['diamonds']) ? intval($product['diamonds']) : 
                                    (isset($product['amount']) ? intval($product['amount']) : 
                                    intval(preg_replace('/[^0-9]/', '', $productName)));
                        
                        if (!empty($productName) || !empty($productId)) {
                            $products[] = [
                                'name' => $productName ?: (string)$diamonds,
                                'price' => floatval($product['price'] ?? $product['amount'] ?? 0),
                                'products' => !empty($productId) ? [trim((string)$productId)] : [],
                                'country' => $productCountry,
                                'diamonds' => $diamonds > 0 ? $diamonds : null,
                                'fetched_at' => date('Y-m-d H:i:s')
                            ];
                        }
                    }
                    
                    if (!empty($products)) {
                        error_log("✅ fetchProductsFromAPI: Returning " . count($products) . " products from API");
                        return $products;
                    } else {
                        error_log("⚠️ API returned products array but it's empty or invalid");
                    }
                } else {
                    error_log("⚠️ API response doesn't contain products array. Response keys: " . (is_array($data) ? implode(', ', array_keys($data)) : 'not an array'));
                }
            } else {
                $status = $response['status'] ?? 'no response';
                error_log("⚠️ API endpoint {$apiUrl} returned HTTP {$status}");
            }
        }
        
        error_log("⚠️ fetchProductsFromAPI: No products found from any API endpoint");
        return [];
    }
    
    /**
     * Fetch products from SmileOne website (uses cookies and user agent)
     * Fetches products for different games and regions
     */
    public function fetchProductsFromWebsite($gameType = 'mobilelegends', $country = 'br') {
        // Set country first
        $this->setCountry($country);
        
        // Clear previous errors
        $this->clearError();
        
        // Verify cookies are available
        if (empty($this->cookies)) {
            $this->lastError = 'No cookies available. Please set cookies first.';
            error_log("❌ fetchProductsFromWebsite: No cookies available");
            return [];
        }
        
        // Verify critical cookies are present
        if (!$this->hasCriticalCookies()) {
            $this->lastError = "Critical cookies (PHPSESSID, _csrf) are missing. Please update cookies.json with valid session cookies from SmileOne website.";
            error_log("❌ fetchProductsFromWebsite: Critical cookies missing");
            return [];
        }
        
        // Initialize products array
        $products = [];
        
        // First try API endpoint
        $apiProducts = $this->fetchProductsFromAPI($gameType, $country);
        
        // Check if rate limited from API call
        if (strpos($this->getLastError(), 'Rate limited') !== false || strpos($this->getLastError(), '429') !== false) {
            error_log("❌ Rate limited during API fetch for {$gameType} ({$country})");
            return []; // Return empty array immediately, error already set
        }
        
        if (!empty($apiProducts)) {
            error_log("✅ fetchProductsFromWebsite: Found " . count($apiProducts) . " products via API for {$gameType} ({$country})");
            return $apiProducts;
        }
        
        // If API doesn't work, try HTML scraping
        // Determine URL based on game type and country
        $basePath = $this->countryConfig['basePath'] ?? '';
        $urls = [];
        
        // Use actual SmileOne website URLs for each game
        switch ($gameType) {
            case 'pubg':
                // Pubg URLs from SmileOne website: /merchant/pubgmobile
                $urls[] = SMILE_BASE_URL . $basePath . '/merchant/pubgmobile';
                break;
            case 'hok':
                // HoK URLs from SmileOne website: /merchant/hok
                $urls[] = SMILE_BASE_URL . $basePath . '/merchant/hok';
                break;
            case 'magicchessgogo':
                // MagicChessGoGo URLs from SmileOne website: /merchant/game/magicchessgogo
                $urls[] = SMILE_BASE_URL . $basePath . '/merchant/game/magicchessgogo';
                break;
            case 'mobilelegends':
            default:
                // MLBB URLs from SmileOne website: /merchant/mobilelegends
                $urls[] = SMILE_BASE_URL . $basePath . '/merchant/mobilelegends';
                break;
        }
        
        error_log("📄 fetchProductsFromWebsite: Trying HTML scraping for {$gameType} ({$country}) from " . count($urls) . " URLs");
        
        // Try each URL until we get products
        foreach ($urls as $urlIndex => $url) {
            error_log("🔍 fetchProductsFromWebsite: Trying URL " . ($urlIndex + 1) . "/" . count($urls) . ": {$url}");
            
            // Add delay between URL attempts to avoid rate limiting
            if ($urlIndex > 0) {
                sleep(2); // 2 second delay between URL attempts
            } else {
                // Add initial delay before first URL request (if API failed)
                sleep(1); // 1 second delay before first URL request
            }
            
            // Fetch the page using cookies and user agent with timeout
            $response = $this->makeRequest($url, 'GET', null, ['timeout' => 15]);
            
            // Skip if DNS error - don't try other URLs
            if (!$response && strpos($this->lastError, 'DNS Error') !== false) {
                error_log("⚠️ DNS error detected, skipping remaining URLs");
                break;
            }
            
            if (!$response) {
                error_log("⚠️ No response from {$url}, trying next URL");
                continue; // Try next URL
            }
            
            // Check for rate limiting (HTTP 429)
            if ($response['status'] === 429) {
                error_log("⚠️ HTTP 429 from {$url}, rate limited. Stopping URL attempts.");
                $this->lastError = 'Rate limited (429). Too many requests. Please wait a few minutes before syncing again.';
                break; // Stop trying other URLs if rate limited
            }
            
            // Skip 404 errors quickly - page doesn't exist
            if ($response['status'] === 404) {
                error_log("⚠️ HTTP 404 from {$url} (Not Found) - skipping");
                continue; // Try next URL
            }
            
            if ($response['status'] !== 200) {
                error_log("⚠️ HTTP {$response['status']} from {$url}, trying next URL");
                continue; // Try next URL
            }
            
            $html = $response['body'];
            
            // Log HTML length for debugging
            error_log("✅ Received HTML from {$url}: " . strlen($html) . " bytes");
            
            // Check if HTML contains product-related keywords
            $hasProductKeywords = (
                stripos($html, 'product') !== false ||
                stripos($html, 'diamond') !== false ||
                stripos($html, 'uc') !== false ||
                stripos($html, 'tokens') !== false ||
                stripos($html, 'pid') !== false ||
                stripos($html, 'price') !== false ||
                stripos($html, 'package') !== false ||
                stripos($html, 'pacote') !== false
            );
            
            if (!$hasProductKeywords) {
                error_log("⚠️ HTML from {$url} doesn't contain product keywords, trying next URL");
                continue;
            }
            
            // Log HTML snippet for debugging
            if (strlen($html) > 0) {
                $htmlSnippet = substr($html, 0, 1000);
                error_log("📄 HTML snippet (first 1000 chars): " . substr($htmlSnippet, 0, 200) . "...");
            }
            
            // Method 1: Try to extract JSON data from script tags (most reliable)
            $productsFoundInScript = false;
            if (preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $scriptMatches)) {
                error_log("📜 Found " . count($scriptMatches[1]) . " script tags, searching for product data...");
                
                foreach ($scriptMatches[1] as $scriptIndex => $scriptContent) {
                    // Try to find JSON data containing products
                    if (preg_match('/products?\s*[:=]\s*\[(.*?)\]/is', $scriptContent, $jsonMatch) ||
                        preg_match('/"products?"\s*:\s*\[(.*?)\]/is', $scriptContent, $jsonMatch)) {
                        error_log("🔍 Found products array pattern in script tag #{$scriptIndex}");
                        
                        // Try to parse as JSON
                        $jsonStr = '[' . $jsonMatch[1] . ']';
                        $jsonData = json_decode($jsonStr, true);
                        if (is_array($jsonData) && !empty($jsonData)) {
                            error_log("✅ Parsed JSON array with " . count($jsonData) . " items");
                            foreach ($jsonData as $product) {
                                if (isset($product['name']) || isset($product['title']) || isset($product['pid'])) {
                                    $productCountry = $country;
                                    if ($gameType === 'pubg') {
                                        $productCountry = ($country === 'br' || $country === 'php') ? 'pubg_' . $country : 'pubg_br';
                                    } elseif ($gameType === 'hok') {
                                        $productCountry = ($country === 'br' || $country === 'php') ? 'hok_' . $country : 'hok_br';
                                    } elseif ($gameType === 'magicchessgogo') {
                                        $productCountry = ($country === 'br' || $country === 'php') ? 'magicchessgogo_' . $country : 'magicchessgogo_br';
                                    }
                                    
                                    $productName = $product['name'] ?? $product['title'] ?? $product['amount'] ?? '';
                                    $productId = $product['pid'] ?? $product['id'] ?? $product['product_id'] ?? '';
                                    
                                    // Extract number from name if it's just a number
                                    $diamonds = isset($product['diamonds']) ? intval($product['diamonds']) : 
                                                (isset($product['amount']) ? intval($product['amount']) : 
                                                intval(preg_replace('/[^0-9]/', '', $productName)));
                                    
                                    if (!empty($productName) || !empty($productId)) {
                                        $products[] = [
                                            'name' => $productName ?: (string)$diamonds,
                                            'price' => floatval($product['price'] ?? $product['amount'] ?? 0),
                                            'products' => !empty($productId) ? [trim((string)$productId)] : [],
                                            'country' => $productCountry,
                                            'diamonds' => $diamonds > 0 ? $diamonds : null,
                                            'fetched_at' => date('Y-m-d H:i:s')
                                        ];
                                        $productsFoundInScript = true;
                                    }
                                }
                            }
                            if ($productsFoundInScript) {
                                error_log("✅ Found " . count($products) . " products from script JSON");
                            }
                        }
                    }
                    
                    // Try full JSON structure
                    if (preg_match('/<script[^>]*type="application\/json"[^>]*>(.*?)<\/script>/is', $scriptContent, $jsonMatch) ||
                        preg_match('/var\s+products?\s*=\s*(.*?);/is', $scriptContent, $jsonMatch)) {
                        error_log("🔍 Found application/json or var products in script tag #{$scriptIndex}");
                        
                        $jsonData = json_decode($jsonMatch[1], true);
                        if ($jsonData && is_array($jsonData)) {
                            $productList = isset($jsonData['products']) ? $jsonData['products'] : 
                                         (isset($jsonData['list']) ? $jsonData['list'] : $jsonData);
                            
                            if (is_array($productList) && !empty($productList)) {
                                error_log("✅ Parsed product list with " . count($productList) . " items");
                                foreach ($productList as $product) {
                                    if (is_array($product) && (isset($product['name']) || isset($product['pid']) || isset($product['id']))) {
                                        $productCountry = $country;
                                        if ($gameType === 'pubg') {
                                            $productCountry = ($country === 'br' || $country === 'php') ? 'pubg_' . $country : 'pubg_br';
                                        } elseif ($gameType === 'hok') {
                                            $productCountry = ($country === 'br' || $country === 'php') ? 'hok_' . $country : 'hok_br';
                                        } elseif ($gameType === 'magicchessgogo') {
                                            $productCountry = ($country === 'br' || $country === 'php') ? 'magicchessgogo_' . $country : 'magicchessgogo_br';
                                        }
                                        
                                        $productName = $product['name'] ?? $product['title'] ?? $product['amount'] ?? '';
                                        $productId = $product['pid'] ?? $product['id'] ?? $product['product_id'] ?? '';
                                        $diamonds = isset($product['diamonds']) ? intval($product['diamonds']) : 
                                                    (isset($product['amount']) ? intval($product['amount']) : 
                                                    intval(preg_replace('/[^0-9]/', '', $productName)));
                                        
                                        if (!empty($productName) || !empty($productId)) {
                                            $products[] = [
                                                'name' => $productName ?: (string)$diamonds,
                                                'price' => floatval($product['price'] ?? $product['amount'] ?? 0),
                                                'products' => !empty($productId) ? [trim((string)$productId)] : [],
                                                'country' => $productCountry,
                                                'diamonds' => $diamonds > 0 ? $diamonds : null,
                                                'fetched_at' => date('Y-m-d H:i:s')
                                            ];
                                            $productsFoundInScript = true;
                                        }
                                    }
                                }
                                if ($productsFoundInScript) {
                                    error_log("✅ Found " . count($products) . " products from full JSON structure");
                                }
                            }
                        }
                    }
                }
            }
            
            // Method 1.5: Combined JSON + HTML structure parsing (New SmileOne format)
            if (empty($products)) {
                error_log("📄 Trying Combined JSON + HTML parsing...");
                
                // 1. Extract the info JSON which contains prices and IDs
                // Pattern for: info = JSON.parse('...') or info = '...'
                if (preg_match('/info\s*=\s*JSON\.parse\(\s*[\'"](\{.*?\})[\'"]\s*\)/s', $html, $matches) || 
                    preg_match('/info\s*=\s*[\'"](\{.*?\})[\'"]\s*;/s', $html, $matches)) {
                    
                    $jsonStr = $matches[1];
                    // Unescape if needed (for single quotes inside the string)
                    $jsonStr = str_replace("\\'", "'", $jsonStr);
                    $infoData = json_decode($jsonStr, true);
                    
                    if ($infoData && is_array($infoData)) {
                        error_log("✅ Found info JSON with " . count($infoData) . " items");
                        
                        foreach ($infoData as $productId => $details) {
                            // Extract price from the first available payment method or 'smilecoin'
                            $price = 0;
                            // Prefer 'n129' (usually card/bank) or 'smilecoin'
                            if (isset($details['n129']['total_amount'])) {
                                $priceStr = $details['n129']['total_amount'];
                            } elseif (isset($details['smilecoin']['total_amount'])) {
                                $priceStr = $details['smilecoin']['total_amount'];
                            } else {
                                // Take the first available
                                $firstMethod = reset($details);
                                $priceStr = $firstMethod['total_amount'] ?? '0';
                            }
                            
                            // Clean price string (remove R$, PHP, commas)
                            // Handle format like "R$ 4,00" -> 4.00
                            $priceStr = preg_replace('/[^\d.,]/', '', $priceStr); // Remove currency symbols
                            $priceStr = str_replace(',', '.', $priceStr); // Convert comma to dot
                            $price = floatval($priceStr);
                            
                            // 2. Find the product name in HTML using the ID
                            // Look for <li ... id="PRODUCT_ID" ...> ... <h3>...</h3> ... </li>
                            // We need to capture the content inside h3 or strong tags
                            $namePattern = '/<li[^>]*id=["\']' . preg_quote($productId, '/') . '["\'][^>]*>.*?<h3[^>]*>(.*?)<\/h3>/is';
                            
                            if (preg_match($namePattern, $html, $nameMatch)) {
                                $rawName = $nameMatch[1];
                                $productName = trim(strip_tags($rawName));
                                
                                // Clean up name (remove extra spaces)
                                $productName = preg_replace('/\s+/', ' ', $productName);
                                
                                // Extract diamonds count for sorting/logic
                                // e.g. "Diamond×50+5" -> 50 or 55? Usually base is 50.
                                // "60 UC" -> 60
                                $diamonds = 0;
                                if (preg_match('/(\d+)/', $productName, $dMatch)) {
                                    $diamonds = intval($dMatch[1]);
                                }
                                
                                if (!empty($productName)) {
                                    $productCountry = $country;
                                    if ($gameType === 'pubg') {
                                        $productCountry = ($country === 'br' || $country === 'php') ? 'pubg_' . $country : 'pubg_br';
                                    } elseif ($gameType === 'hok') {
                                        $productCountry = ($country === 'br' || $country === 'php') ? 'hok_' . $country : 'hok_br';
                                    } elseif ($gameType === 'magicchessgogo') {
                                        $productCountry = ($country === 'br' || $country === 'php') ? 'magicchessgogo_' . $country : 'magicchessgogo_br';
                                    }
                                    
                                    $products[] = [
                                        'name' => $productName,
                                        'price' => $price,
                                        'products' => [trim((string)$productId)],
                                        'country' => $productCountry,
                                        'diamonds' => $diamonds > 0 ? $diamonds : null,
                                        'fetched_at' => date('Y-m-d H:i:s')
                                    ];
                                }
                            }
                        }
                    }
                }
                
                if (!empty($products)) {
                    error_log("✅ Found " . count($products) . " products using Combined JSON + HTML parsing");
                }
            }
            
            // Method 2: Parse HTML structure for products (SmileOne website format)
            if (empty($products)) {
                error_log("📄 Trying HTML pattern matching for SmileOne website structure...");
                
                // First, try proximity-based matching (more reliable for dynamic HTML)
                // Extract all prices and products separately, then match by proximity
                $pricePositions = [];
                $productPositions = [];
                
                // Find all prices (with various formats)
                if (preg_match_all('/(?:<em[^>]*>|_)\s*(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/?em>|(?:<em[^>]*>|_)\s*(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/?em>/is', $html, $priceMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($priceMatches[0] as $priceMatch) {
                        $priceText = $priceMatch[0];
                        $price = floatval(preg_replace('/[^0-9.,]/', '', $priceText));
                        if ($price > 0) {
                            $pricePositions[] = [
                                'pos' => $priceMatch[1],
                                'price' => $price,
                                'text' => $priceText
                            ];
                        }
                    }
                }
                
                // Find all diamond/UC/token amounts
                if (preg_match_all('/<strong[^>]*>\*\*(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*\*\*<\/strong>/is', $html, $diamondMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($diamondMatches[1] as $idx => $diamondMatch) {
                        $diamonds = intval($diamondMatch[0]);
                        if ($diamonds > 0) {
                            $productPositions[] = [
                                'pos' => $diamondMatches[0][$idx][1],
                                'diamonds' => $diamonds,
                                'bonus' => 0,
                                'type' => 'diamond'
                            ];
                        }
                    }
                }
                
                // Find bonus amounts
                if (preg_match_all('/<strong[^>]*>\*\*\+([0-9]+)[^<]*\*\*<\/strong>/is', $html, $bonusMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($bonusMatches[1] as $idx => $bonusMatch) {
                        $bonus = intval($bonusMatch[0]);
                        $bonusPos = $bonusMatches[0][$idx][1];
                        
                        // Try to match bonus with nearest product
                        foreach ($productPositions as &$product) {
                            if (abs($product['pos'] - $bonusPos) < 500) {
                                $product['bonus'] = $bonus;
                                break;
                            }
                        }
                    }
                }
                
                // Find TOKENS format (for HoK)
                if (preg_match_all('/<strong[^>]*>\*\*([0-9]+)\s*TOKENS[^<]*\*\*<\/strong>/is', $html, $tokenMatches, PREG_OFFSET_CAPTURE)) {
                    foreach ($tokenMatches[1] as $idx => $tokenMatch) {
                        $tokens = intval($tokenMatch[0]);
                        if ($tokens > 0) {
                            $productPositions[] = [
                                'pos' => $tokenMatches[0][$idx][1],
                                'diamonds' => $tokens,
                                'bonus' => 0,
                                'type' => 'token'
                            ];
                        }
                    }
                }
                
                // Match prices with products by proximity
                $maxDistance = 3000; // Max characters between price and product
                foreach ($pricePositions as $priceInfo) {
                    $closestProduct = null;
                    $closestDistance = $maxDistance;
                    
                    foreach ($productPositions as $productInfo) {
                        $distance = abs($productInfo['pos'] - $priceInfo['pos']);
                        // Product should come after price in HTML
                        if ($distance < $closestDistance && $productInfo['pos'] > $priceInfo['pos']) {
                            $closestDistance = $distance;
                            $closestProduct = $productInfo;
                        }
                    }
                    
                    if ($closestProduct && $closestProduct['diamonds'] > 0) {
                        $totalDiamonds = $closestProduct['diamonds'] + $closestProduct['bonus'];
                        $productName = (string)$totalDiamonds;
                        if ($closestProduct['bonus'] > 0) {
                            $productName = $closestProduct['diamonds'] . '+' . $closestProduct['bonus'];
                        }
                        
                        $productCountry = $country;
                        if ($gameType === 'pubg') {
                            $productCountry = ($country === 'br' || $country === 'php') ? 'pubg_' . $country : 'pubg_br';
                        } elseif ($gameType === 'hok') {
                            $productCountry = ($country === 'br' || $country === 'php') ? 'hok_' . $country : 'hok_br';
                        } elseif ($gameType === 'magicchessgogo') {
                            $productCountry = ($country === 'br' || $country === 'php') ? 'magicchessgogo_' . $country : 'magicchessgogo_br';
                        }
                        
                        $products[] = [
                            'name' => $productName,
                            'price' => $priceInfo['price'],
                            'products' => [],
                            'country' => $productCountry,
                            'diamonds' => $totalDiamonds,
                            'fetched_at' => date('Y-m-d H:i:s')
                        ];
                    }
                }
                
                if (!empty($products)) {
                    error_log("✅ Found " . count($products) . " products using proximity matching");
                }
                
                // Fallback to regex patterns if proximity matching didn't work
                if (empty($products)) {
                    $patterns = [
                    // Pattern 1: Price in <em> with underscores, then Diamond/UC/TOKENS in <strong>
                    // Format: <em>_R$ 6,25_</em>...<strong>**Diamond×78**</strong>...<strong>**+8**</strong>
                    '/<em[^>]*>_\s*(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>.*?<strong[^>]*>\*\*(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*\*\*<\/strong>(?:.*?<strong[^>]*>\*\*\+([0-9]+)[^<]*\*\*<\/strong>)?/is',
                    
                    // Pattern 2: Price first, then product name (reversed order)
                    '/<em[^>]*>_\s*(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>.*?<strong[^>]*>\*\*([0-9]+)\s*(?:TOKENS|UC)[^<]*\*\*<\/strong>/is',
                    
                    // Pattern 3: Without underscores in price
                    '/<em[^>]*>(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>.*?<strong[^>]*>(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*(?:\+([0-9]+))?[^<]*<\/strong>/is',
                    
                    // Pattern 4: With data-pid attribute
                    '/data-pid=["\']([^"\']+)["\'][^>]*>.*?<em[^>]*>_\s*(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>.*?<strong[^>]*>\*\*(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*\*\*<\/strong>(?:.*?<strong[^>]*>\*\*\+([0-9]+)[^<]*\*\*<\/strong>)?/is',
                    
                    // Pattern 5: More flexible - any order
                    '/<strong[^>]*>\*\*(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*\*\*<\/strong>(?:.*?<strong[^>]*>\*\*\+([0-9]+)[^<]*\*\*<\/strong>)?.*?<em[^>]*>_\s*(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>/is',
                    
                    // Pattern 6: List item format
                    '/<li[^>]*>.*?<em[^>]*>_\s*(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>.*?<strong[^>]*>\*\*(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*\*\*<\/strong>(?:.*?<strong[^>]*>\*\*\+([0-9]+)[^<]*\*\*<\/strong>)?/is',
                    
                    // Pattern 7: Simple format without markdown
                    '/<em[^>]*>(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>.*?<strong[^>]*>([0-9]+)\s*(?:TOKENS|UC)[^<]*<\/strong>/is',
                    
                    // Pattern 8: Fallback - any strong/em combination
                    '/<strong[^>]*>.*?(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*(?:\+([0-9]+))?[^<]*<\/strong>.*?<em[^>]*>.*?(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>/is'
                ];
                
                foreach ($patterns as $patternIndex => $pattern) {
                    if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                        error_log("✅ Pattern #{$patternIndex} matched " . count($matches) . " products");
                        foreach ($matches as $match) {
                            // Extract product ID if available (from data-pid)
                            $productId = '';
                            $diamonds = 0;
                            $bonus = 0;
                            $price = 0;
                            
                            // Pattern 4 has data-pid as first match
                            if ($patternIndex === 3 && isset($match[4]) && is_numeric($match[4])) {
                                // Pattern 4: match[1]=pid, match[2]=price, match[3]=diamonds, match[4]=bonus
                                $productId = trim($match[1] ?? '');
                                $price = floatval(str_replace([',', ' '], '', $match[2] ?? '0'));
                                $diamonds = intval($match[3] ?? 0);
                                $bonus = intval($match[4] ?? 0);
                            } elseif (isset($match[3]) && is_numeric($match[3])) {
                                // Most patterns: match[1]=price, match[2]=diamonds, match[3]=bonus (optional)
                                // Or: match[1]=diamonds, match[2]=bonus, match[3]=price (pattern 5)
                                if ($patternIndex === 4) {
                                    // Pattern 5: reversed order - diamonds first, then price
                                    $diamonds = intval($match[1] ?? 0);
                                    $bonus = intval($match[2] ?? 0);
                                    $price = floatval(str_replace([',', ' '], '', $match[3] ?? '0'));
                                } else {
                                    // Normal order: price first, then diamonds
                                    $price = floatval(str_replace([',', ' '], '', $match[1] ?? '0'));
                                    $diamonds = intval($match[2] ?? 0);
                                    $bonus = intval($match[3] ?? 0);
                                }
                            } elseif (isset($match[2]) && is_numeric($match[2])) {
                                // Two matches: price and diamonds (no bonus)
                                if (is_numeric($match[1])) {
                                    // match[1]=diamonds, match[2]=price (for TOKENS/UC)
                                    $diamonds = intval($match[1] ?? 0);
                                    $price = floatval(str_replace([',', ' '], '', $match[2] ?? '0'));
                                } else {
                                    // match[1]=price, match[2]=diamonds
                                    $price = floatval(str_replace([',', ' '], '', $match[1] ?? '0'));
                                    $diamonds = intval($match[2] ?? 0);
                                }
                            }
                            
                            // Calculate total diamonds (base + bonus)
                            $totalDiamonds = $diamonds + $bonus;
                            
                            // Create product name
                            $productName = (string)$totalDiamonds;
                            if ($bonus > 0) {
                                $productName = $diamonds . '+' . $bonus;
                            }
                            
                            // Determine product country
                            $productCountry = $country;
                            if ($gameType === 'pubg') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'pubg_' . $country : 'pubg_br';
                            } elseif ($gameType === 'hok') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'hok_' . $country : 'hok_br';
                            } elseif ($gameType === 'magicchessgogo') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'magicchessgogo_' . $country : 'magicchessgogo_br';
                            }
                            
                            if ($diamonds > 0 && $price > 0) {
                                $products[] = [
                                    'name' => $productName,
                                    'price' => $price,
                                    'products' => !empty($productId) ? [trim($productId)] : [],
                                    'country' => $productCountry,
                                    'diamonds' => $totalDiamonds,
                                    'fetched_at' => date('Y-m-d H:i:s')
                                ];
                            }
                        }
                        if (!empty($products)) {
                            error_log("✅ Found " . count($products) . " products using HTML pattern #{$patternIndex}");
                            break; // Found products, stop trying other patterns
                        }
                    }
                }
                
                // Additional pattern: Try to find products in list format
                if (empty($products)) {
                    // Pattern for list items with price and diamonds
                    if (preg_match_all('/<li[^>]*>.*?<em[^>]*>.*?(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>.*?<strong[^>]*>.*?(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*(?:\+([0-9]+))?[^<]*<\/strong>/is', $html, $listMatches, PREG_SET_ORDER)) {
                        error_log("✅ Found " . count($listMatches) . " products in list format");
                        foreach ($listMatches as $match) {
                            $price = floatval(str_replace([',', ' '], '', $match[1] ?? '0'));
                            $diamonds = intval($match[2] ?? 0);
                            $bonus = intval($match[3] ?? 0);
                            $totalDiamonds = $diamonds + $bonus;
                            
                            $productName = (string)$totalDiamonds;
                            if ($bonus > 0) {
                                $productName = $diamonds . '+' . $bonus;
                            }
                            
                            $productCountry = $country;
                            if ($gameType === 'pubg') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'pubg_' . $country : 'pubg_br';
                            } elseif ($gameType === 'hok') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'hok_' . $country : 'hok_br';
                            } elseif ($gameType === 'magicchessgogo') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'magicchessgogo_' . $country : 'magicchessgogo_br';
                            }
                            
                            if ($diamonds > 0 && $price > 0) {
                                $products[] = [
                                    'name' => $productName,
                                    'price' => $price,
                                    'products' => [],
                                    'country' => $productCountry,
                                    'diamonds' => $totalDiamonds,
                                    'fetched_at' => date('Y-m-d H:i:s')
                                ];
                            }
                        }
                    }
                }
                
                // Try alternative pattern: Extract from HTML structure with price first, then diamonds
                if (empty($products)) {
                    // Pattern: Price in <em> tag, then Diamonds in <strong> tag
                    if (preg_match_all('/<em[^>]*>.*?_(?:R\$|₱|PHP)\s*([0-9.,]+)[^<]*<\/em>.*?<strong[^>]*>.*?(?:Diamond|Diamonds|UC|TOKENS)[^<]*(?:×|x|\*)?\s*([0-9]+)[^<]*(?:\+([0-9]+))?[^<]*<\/strong>/is', $html, $altMatches, PREG_SET_ORDER)) {
                        error_log("✅ Found " . count($altMatches) . " products using alternative pattern");
                        foreach ($altMatches as $match) {
                            $price = floatval(str_replace([',', ' '], '', $match[1] ?? '0'));
                            $diamonds = intval($match[2] ?? 0);
                            $bonus = intval($match[3] ?? 0);
                            $totalDiamonds = $diamonds + $bonus;
                            
                            $productName = (string)$totalDiamonds;
                            if ($bonus > 0) {
                                $productName = $diamonds . '+' . $bonus;
                            }
                            
                            $productCountry = $country;
                            if ($gameType === 'pubg') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'pubg_' . $country : 'pubg_br';
                            } elseif ($gameType === 'hok') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'hok_' . $country : 'hok_br';
                            } elseif ($gameType === 'magicchessgogo') {
                                $productCountry = ($country === 'br' || $country === 'php') ? 'magicchessgogo_' . $country : 'magicchessgogo_br';
                            }
                            
                            if ($diamonds > 0 && $price > 0) {
                                $products[] = [
                                    'name' => $productName,
                                    'price' => $price,
                                    'products' => [],
                                    'country' => $productCountry,
                                    'diamonds' => $totalDiamonds,
                                    'fetched_at' => date('Y-m-d H:i:s')
                                ];
                            }
                        }
                    }
                }
                
                if (empty($products)) {
                    error_log("⚠️ No products found using HTML patterns. HTML length: " . strlen($html) . " bytes");
                    // Log a sample of HTML for debugging (first 500 chars)
                    error_log("📄 HTML sample: " . substr($html, 0, 500));
                }
            }
        }
            
            // If we found products, stop trying other URLs
            if (!empty($products)) {
                error_log("✅ Found products from {$url}, stopping URL iteration");
                break;
            }
        }
        
        // Remove duplicates based on name or product ID
        $uniqueProducts = [];
        $seen = [];
        foreach ($products as $product) {
            $key = ($product['name'] ?? '') . '_' . ($product['country'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueProducts[] = $product;
            }
        }
        
        if (empty($uniqueProducts)) {
            $this->lastError = 'No products found. HTML structure may have changed or products not available.';
            error_log("❌ fetchProductsFromWebsite: No products found for {$gameType} ({$country})");
        } else {
            error_log("✅ fetchProductsFromWebsite: Returning " . count($uniqueProducts) . " unique products for {$gameType} ({$country})");
        }
        
        return $uniqueProducts;
    }
    /**
     * Sync all products from SmileOne website for all games and regions
     * Uses cookies and user agent automatically
     */
    public function syncAllProducts() {
        $allProducts = [];
        $games = [
            'mobilelegends' => ['br', 'php'],
            'pubg' => ['br', 'php'],
            'hok' => ['br', 'php'],
            'magicchessgogo' => ['br', 'php']
        ];
        
        // Verify cookies are loaded
        if (empty($this->cookies)) {
            $this->lastError = 'Cookies not loaded. Please check cookies.json file.';
            error_log("❌ Sync failed: " . $this->lastError);
            return [];
        }
        
        // Log that we're using cookies and user agent
        error_log("🔄 Starting product sync with " . count($this->cookies) . " cookies and user agent: " . USER_AGENT);
        
        $totalAttempts = 0;
        $successCount = 0;
        
        // Check DNS first before trying to fetch products
        $testUrl = SMILE_BASE_URL;
        $testResponse = @file_get_contents($testUrl, false, stream_context_create([
            'http' => [
                'timeout' => 3,
                'method' => 'HEAD'
            ]
        ]));
        
        if ($testResponse === false) {
            // Try with curl to get better error message
            $ch = curl_init($testUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if (strpos($curlError, 'Could not resolve host') !== false) {
                $this->lastError = 'DNS Error: Could not resolve www.smile.one. Please check your internet connection.';
                error_log("❌ DNS resolution failed for www.smile.one: {$curlError}");
                return [];
            }
        }
        
        $rateLimited = false;
        
        // Add initial delay before first request to avoid immediate rate limiting
        // This gives time for any previous rate limit window to clear
        sleep(3); // 3 second initial delay
        
        foreach ($games as $gameType => $countries) {
            foreach ($countries as $country) {
                // Check if we were rate limited in previous iteration
                if ($rateLimited) {
                    error_log("⚠️ Rate limited detected, skipping remaining sync operations");
                    $this->lastError = 'Rate limited. Too many requests. Please wait a few minutes before syncing again.';
                    break 2; // Break out of both loops
                }
                
                $totalAttempts++;
                try {
                    // Add delay between requests to avoid rate limiting
                    if ($totalAttempts > 1) {
                        sleep(3); // 3 second delay between game/country combinations
                    }
                    
                    error_log("📦 Fetching products for {$gameType} ({$country})...");
                    
                    // Clear error before fetching
                    $this->clearError();
                    
                    $products = $this->fetchProductsFromWebsite($gameType, $country);
                    
                    // Check if rate limited immediately after fetch
                    $lastError = $this->getLastError();
                    if (strpos($lastError, 'Rate limited') !== false || strpos($lastError, '429') !== false) {
                        $rateLimited = true;
                        error_log("❌ Rate limited while fetching {$gameType} ({$country}). Stopping sync.");
                        break 2; // Break out of both loops
                    }
                    
                    // Stop if DNS error detected
                    if (empty($products) && strpos($lastError, 'DNS Error') !== false) {
                        error_log("❌ DNS error detected, stopping sync");
                        break 2; // Break out of both loops
                    }
                    
                    if (!empty($products)) {
                        $allProducts = array_merge($allProducts, $products);
                        $successCount++;
                        error_log("✅ Found " . count($products) . " products for {$gameType} ({$country})");
                    } else {
                        $errorMsg = $lastError ?: 'No products found';
                        error_log("⚠️ No products found for {$gameType} ({$country}): {$errorMsg}");
                    }
                } catch (Exception $e) {
                    error_log("❌ Error fetching products for {$gameType} {$country}: " . $e->getMessage());
                    $this->lastError = "Error for {$gameType} {$country}: " . $e->getMessage();
                    
                    // Stop if DNS error or rate limited
                    if (strpos($e->getMessage(), 'DNS') !== false || strpos($e->getMessage(), 'resolve host') !== false) {
                        break 2; // Break out of both loops
                    }
                    if (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), 'Rate limited') !== false) {
                        $rateLimited = true;
                        break 2; // Break out of both loops
                    }
                }
            }
        }
        
        error_log("✅ Sync completed: {$successCount}/{$totalAttempts} successful, Total products: " . count($allProducts));
        
        // Send webhook notification for product sync completion
        $this->sendWebhookWithRetry('products.sync.completed', [
            'totalProducts' => count($allProducts),
            'successCount' => $successCount,
            'totalAttempts' => $totalAttempts,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return $allProducts;
    }
    
    /**
     * Get last error
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Clear last error
     */
    public function clearError() {
        $this->lastError = '';
    }
    
    /**
     * Set webhook URL
     */
    public function setWebhookUrl($url) {
        $this->webhookUrl = $url;
    }
    
    /**
     * Get webhook URL
     */
    public function getWebhookUrl() {
        return $this->webhookUrl;
    }
    
    /**
     * Send webhook notification
     */
    private function sendWebhook($event, $data) {
        if (empty($this->webhookUrl)) {
            return false;
        }
        
        $payload = [
            'event' => $event,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        // Add signature if secret is set
        if (!empty($this->webhookSecret)) {
            $payload['signature'] = hash_hmac('sha256', json_encode($payload), $this->webhookSecret);
        }
        
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        $ch = curl_init($this->webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonPayload),
                'User-Agent: SmileOne-Webhook/1.0'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("⚠️ Webhook error: {$error}");
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("✅ Webhook sent successfully: {$event} (HTTP {$httpCode})");
            return true;
        } else {
            error_log("⚠️ Webhook failed: {$event} (HTTP {$httpCode})");
            return false;
        }
    }
    
    /**
     * Send webhook with retry
     */
    private function sendWebhookWithRetry($event, $data) {
        $maxRetries = 3;
        $retries = 0;
        
        while ($retries <= $maxRetries) {
            if ($this->sendWebhook($event, $data)) {
                return true;
            }
            
            if ($retries < $maxRetries) {
                sleep(1); // Wait 1 second before retry
            }
            $retries++;
        }
        
        return false;
    }

    public function getMagicChessGoGoUserName($gameId, $zoneId) {
        $url = 'https://www.smile.one/br/merchant/game/checkrole?product=magicchessgogo';
        
        error_log("MagicChessGoGo: Checking user {$gameId} ({$zoneId}) via {$url}");
        
        // Extract CSRF token (case-insensitive)
        $csrfToken = '';
        foreach ($this->cookies as $cookie) {
            if (strcasecmp($cookie['name'], '_csrf') === 0) {
                $csrfToken = $cookie['value'];
                break;
            }
        }
        
        // Payload using 'uid' and 'sid' as discovered
        $postData = [
            'uid' => $gameId,
            'sid' => $zoneId,
            'game_id' => '1701',
            'checkrole' => 1,
            '_csrf' => $csrfToken
        ];
        
        $options = [
            'headers' => [
                'Referer' => 'https://www.smile.one/merchant/game/magicchessgogo',
                'Origin' => 'https://www.smile.one',
                'X-Requested-With' => 'XMLHttpRequest'
            ]
        ];
        
        $response = $this->makeRequest($url, 'POST', $postData, $options);
        
        if (!$response || $response['status'] !== 200) {
            $this->lastError = 'Request failed with HTTP status: ' . ($response['status'] ?? 'unknown');
            error_log('MagicChessGoGo Check Error: ' . $this->lastError);
            return false;
        }
        
        $body = $response['body'];
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = 'Invalid JSON: ' . json_last_error_msg();
            error_log('MagicChessGoGo Check Error: ' . $this->lastError);
            return false;
        }
        
        if (isset($data['code']) && $data['code'] == 200) {
            $username = $data['nickname'] ?? $data['username'] ?? 'Unknown';
            error_log("✅ MagicChessGoGo: Found user: {$username}");
            return $username;
        }
        
        $this->lastError = $data['info'] ?? $data['message'] ?? 'Unknown error';
        error_log('MagicChessGoGo Check Error: ' . $this->lastError);
        return false;
    }
}
