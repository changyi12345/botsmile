<?php
// Dashboard Page
?>
<div class="page-header">
    <h2 class="mb-1"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Overview</h2>
    <p class="text-muted mb-0">System statistics and recent activity</p>
</div>
<div class="row">
    <!-- Brazil Stats -->
    <div class="col-md-6">
        <div class="card stats-card bg-success mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">🇧🇷 Brazil Revenue</h5>
                        <h3>R$<?php echo number_format($br_revenue, 2); ?></h3>
                        <small><?php echo formatMMK(convertToMMK($br_revenue, 'br')); ?></small>
                        <div class="mt-2">
                            <small class="text-white-50">SmileOne Balance:</small>
                            <strong class="text-white" id="smileBalanceBR"><?php echo $smile_balance_br; ?></strong>
                        </div>
                    </div>
                    <div class="stats-icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Philippines Stats -->
    <div class="col-md-6">
        <div class="card stats-card bg-primary mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">🇵🇭 Philippines Revenue</h5>
                        <h3>₱<?php echo number_format($php_revenue, 2); ?></h3>
                        <small><?php echo formatMMK(convertToMMK($php_revenue, 'php')); ?></small>
                        <div class="mt-2">
                            <small class="text-white-50">SmileOne Balance:</small>
                            <strong class="text-white" id="smileBalancePHP"><?php echo $smile_balance_php; ?></strong>
                        </div>
                    </div>
                    <div class="stats-icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-users fa-2x text-info me-3"></i>
                <div>
                    <h6 class="card-subtitle mb-1 text-muted">Total Users</h6>
                    <h4 class="card-title mb-0"><?php echo $total_users; ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-exchange-alt fa-2x text-warning me-3"></i>
                <div>
                    <h6 class="card-subtitle mb-1 text-muted">Total Transactions</h6>
                    <h4 class="card-title mb-0"><?php echo $total_transactions; ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-box fa-2x text-danger me-3"></i>
                <div>
                    <h6 class="card-subtitle mb-1 text-muted">Total Products</h6>
                    <h4 class="card-title mb-0"><?php echo count($products); ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body d-flex align-items-center">
                <i class="fas fa-hand-holding-usd fa-2x text-secondary me-3"></i>
                <div>
                    <h6 class="card-subtitle mb-1 text-muted">Commissions</h6>
                    <h4 class="card-title mb-0">$<?php echo number_format($total_commissions, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="table-container">
    <div class="p-3 border-bottom">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_transactions as $transaction): ?>
                <tr>
                    <td><?php echo htmlspecialchars($transaction['id'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($transaction['user_id'] ?? 'N/A'); ?></td>
                    <td>$<?php echo number_format($transaction['amount'] ?? 0, 2); ?></td>
                    <td>
                        <span class="badge bg-<?php echo ($transaction['status'] ?? 'pending') === 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo htmlspecialchars($transaction['status'] ?? 'pending'); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($transaction['created_at'] ?? 'N/A'); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewTransaction('<?php echo $transaction['id']; ?>')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Balance refresh state
let balanceRefreshInterval = null;
let lastBalanceCheck = 0;
const BALANCE_CACHE_DURATION = 5 * 60 * 1000; // 5 minutes in milliseconds

// Load SmileOne Balance via AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Set initial loading state
    const brBalanceEl = document.getElementById('smileBalanceBR');
    const phpBalanceEl = document.getElementById('smileBalancePHP');
    if (brBalanceEl && brBalanceEl.textContent === 'Loading...') {
        brBalanceEl.textContent = 'Loading...';
        brBalanceEl.style.color = '#ffffff';
    }
    if (phpBalanceEl && phpBalanceEl.textContent === 'Loading...') {
        phpBalanceEl.textContent = 'Loading...';
        phpBalanceEl.style.color = '#ffffff';
    }
    
    // Check if we have cached balance data
    const cachedData = sessionStorage.getItem('smileBalanceCache');
    if (cachedData) {
        try {
            const data = JSON.parse(cachedData);
            const cacheTime = data.timestamp || 0;
            const now = Date.now();
            
            // Use cache if less than 5 minutes old
            if (now - cacheTime < BALANCE_CACHE_DURATION) {
                updateBalanceDisplay(data);
                // Schedule next refresh
                const timeUntilRefresh = BALANCE_CACHE_DURATION - (now - cacheTime);
                setTimeout(loadSmileOneBalance, timeUntilRefresh);
                return;
            }
        } catch (e) {
            // Cache invalid, proceed with fresh load
            sessionStorage.removeItem('smileBalanceCache');
        }
    }
    
    // Add a longer delay before first load to avoid immediate rate limiting (5 seconds)
    // This gives time for any previous requests to complete
    setTimeout(function() {
        loadSmileOneBalance();
    }, 5000);
    
    // Refresh balance every 5 minutes (300 seconds) to avoid rate limiting
    balanceRefreshInterval = setInterval(loadSmileOneBalance, 300000);
});

function loadSmileOneBalance() {
    // Update display to show loading state
    const brBalanceEl = document.getElementById('smileBalanceBR');
    const phpBalanceEl = document.getElementById('smileBalancePHP');
    if (brBalanceEl && !brBalanceEl.textContent.includes('Rate Limited') && !brBalanceEl.textContent.includes('Session Expired')) {
        brBalanceEl.textContent = 'Loading...';
        brBalanceEl.style.color = '#ffffff';
        brBalanceEl.title = 'Fetching balance...';
    }
    if (phpBalanceEl && !phpBalanceEl.textContent.includes('Rate Limited') && !phpBalanceEl.textContent.includes('Session Expired')) {
        phpBalanceEl.textContent = 'Loading...';
        phpBalanceEl.style.color = '#ffffff';
        phpBalanceEl.title = 'Fetching balance...';
    }
    
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 20000); // 20 second timeout (increased)
    
    fetch('../api/admin_api.php?action=get_smile_balance', {
        method: 'GET',
        signal: controller.signal,
        cache: 'no-cache',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            // Try to get error message from response
            return response.text().then(text => {
                let errorData;
                try {
                    errorData = JSON.parse(text);
                } catch (e) {
                    errorData = { success: false, message: `Server error: ${response.status}` };
                }
                throw new Error(JSON.stringify(errorData));
            });
        }
        return response.json();
    })
    .then(data => {
        lastBalanceCheck = Date.now();
        
        // Cache successful responses (even if rate limited, cache the state)
        if (data.success || (data.data && (data.data.br === 'Rate Limited' || data.data.php === 'Rate Limited'))) {
            sessionStorage.setItem('smileBalanceCache', JSON.stringify({
                ...data,
                timestamp: lastBalanceCheck
            }));
        }
        
        // If rate limited, stop auto-refresh and show manual refresh option
        const isRateLimited = (data.data?.br === 'Rate Limited' || data.data?.php === 'Rate Limited');
        if (isRateLimited && balanceRefreshInterval) {
            clearInterval(balanceRefreshInterval);
            balanceRefreshInterval = null;
            // Show message that auto-refresh is paused
            console.log('Rate limited detected. Auto-refresh paused. Please refresh manually after a few minutes.');
        }
        
        updateBalanceDisplay(data);
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Error loading SmileOne balance:', error);
        
        // Try to parse error message if it's JSON
        let errorMessage = 'Connection error';
        try {
            const errorData = JSON.parse(error.message);
            if (errorData.message) {
                errorMessage = errorData.message;
            }
        } catch (e) {
            // Not JSON, use original message
            errorMessage = error.message || 'Connection error';
        }
        
        // Show error with clickable refresh
        const brBalanceEl = document.getElementById('smileBalanceBR');
        const phpBalanceEl = document.getElementById('smileBalancePHP');
        if (brBalanceEl) {
            brBalanceEl.textContent = 'Error';
            brBalanceEl.title = errorMessage + ' (Click to retry)';
            brBalanceEl.style.color = '#dc3545';
            brBalanceEl.style.cursor = 'pointer';
            brBalanceEl.onclick = function() {
                sessionStorage.removeItem('smileBalanceCache');
                loadSmileOneBalance();
            };
        }
        if (phpBalanceEl) {
            phpBalanceEl.textContent = 'Error';
            phpBalanceEl.title = errorMessage + ' (Click to retry)';
            phpBalanceEl.style.color = '#dc3545';
            phpBalanceEl.style.cursor = 'pointer';
            phpBalanceEl.onclick = function() {
                sessionStorage.removeItem('smileBalanceCache');
                loadSmileOneBalance();
            };
        }
    });
}

// Update balance display
function updateBalanceDisplay(data) {
    if (data.success) {
        // Update BR balance
        const brBalanceEl = document.getElementById('smileBalanceBR');
        if (brBalanceEl && data.data.br !== undefined) {
            const brValue = data.data.br || 'N/A';
            // Handle different error types
            if (brValue.startsWith('Error') || brValue === 'Rate Limited' || brValue === 'Session Expired' || brValue === 'No Cookies') {
                if (brValue === 'Session Expired' || brValue === 'No Cookies') {
                    brBalanceEl.textContent = brValue;
                    brBalanceEl.title = data.errors?.br || 'Cookies expired or invalid. Please update cookies in Cookie Management.';
                    brBalanceEl.style.color = '#dc3545'; // Red for session expired
                } else if (brValue === 'Rate Limited') {
                    brBalanceEl.textContent = 'Rate Limited';
                    brBalanceEl.title = data.errors?.br || 'Too many requests. Please wait a few minutes.';
                    brBalanceEl.style.color = '#ffc107'; // Yellow for rate limit
                } else {
                    brBalanceEl.textContent = brValue;
                    brBalanceEl.title = data.errors?.br || 'Balance check failed. Click to refresh manually.';
                    brBalanceEl.style.color = '#dc3545'; // Red for error
                }
                brBalanceEl.style.cursor = 'pointer';
                brBalanceEl.onclick = function() {
                    if (brValue === 'Session Expired' || brValue === 'No Cookies') {
                        if (confirm('Cookies expired or invalid. Please update cookies in Cookie Management first. Continue anyway?')) {
                            sessionStorage.removeItem('smileBalanceCache');
                            loadSmileOneBalance();
                        }
                    } else if (brValue === 'Rate Limited') {
                        if (confirm('Rate limited. Wait a few minutes before refreshing. Continue?')) {
                            sessionStorage.removeItem('smileBalanceCache');
                            loadSmileOneBalance();
                        }
                    } else {
                        sessionStorage.removeItem('smileBalanceCache');
                        loadSmileOneBalance();
                    }
                };
            } else if (brValue === 'N/A' && data.errors?.br) {
                brBalanceEl.textContent = 'Error';
                brBalanceEl.title = data.errors.br;
                brBalanceEl.style.color = '#dc3545'; // Red for error
                brBalanceEl.style.cursor = 'pointer';
                brBalanceEl.onclick = function() { loadSmileOneBalance(); };
            } else {
                brBalanceEl.textContent = brValue;
                brBalanceEl.title = '';
                brBalanceEl.style.color = ''; // Reset color
                brBalanceEl.style.cursor = '';
                brBalanceEl.onclick = null;
            }
        }
        
        // Update PHP balance
        const phpBalanceEl = document.getElementById('smileBalancePHP');
        if (phpBalanceEl && data.data.php !== undefined) {
            const phpValue = data.data.php || 'N/A';
            // Handle different error types
            if (phpValue.startsWith('Error') || phpValue === 'Rate Limited' || phpValue === 'Session Expired' || phpValue === 'No Cookies') {
                if (phpValue === 'Session Expired' || phpValue === 'No Cookies') {
                    phpBalanceEl.textContent = phpValue;
                    phpBalanceEl.title = data.errors?.php || 'Cookies expired or invalid. Please update cookies in Cookie Management.';
                    phpBalanceEl.style.color = '#dc3545'; // Red for session expired
                } else if (phpValue === 'Rate Limited') {
                    phpBalanceEl.textContent = 'Rate Limited';
                    phpBalanceEl.title = data.errors?.php || 'Too many requests. Please wait a few minutes.';
                    phpBalanceEl.style.color = '#ffc107'; // Yellow for rate limit
                } else {
                    phpBalanceEl.textContent = phpValue;
                    phpBalanceEl.title = data.errors?.php || 'Balance check failed. Click to refresh manually.';
                    phpBalanceEl.style.color = '#dc3545'; // Red for error
                }
                phpBalanceEl.style.cursor = 'pointer';
                phpBalanceEl.onclick = function() {
                    if (phpValue === 'Session Expired' || phpValue === 'No Cookies') {
                        if (confirm('Cookies expired or invalid. Please update cookies in Cookie Management first. Continue anyway?')) {
                            sessionStorage.removeItem('smileBalanceCache');
                            loadSmileOneBalance();
                        }
                    } else if (phpValue === 'Rate Limited') {
                        if (confirm('Rate limited. Wait a few minutes before refreshing. Continue?')) {
                            sessionStorage.removeItem('smileBalanceCache');
                            loadSmileOneBalance();
                        }
                    } else {
                        sessionStorage.removeItem('smileBalanceCache');
                        loadSmileOneBalance();
                    }
                };
            } else if (phpValue === 'N/A' && data.errors?.php) {
                phpBalanceEl.textContent = 'Error';
                phpBalanceEl.title = data.errors.php;
                phpBalanceEl.style.color = '#dc3545'; // Red for error
                phpBalanceEl.style.cursor = 'pointer';
                phpBalanceEl.onclick = function() { loadSmileOneBalance(); };
            } else {
                phpBalanceEl.textContent = phpValue;
                phpBalanceEl.title = '';
                phpBalanceEl.style.color = ''; // Reset color
                phpBalanceEl.style.cursor = '';
                phpBalanceEl.onclick = null;
            }
        }
    } else {
        // Show error with message
        const brBalanceEl = document.getElementById('smileBalanceBR');
        const phpBalanceEl = document.getElementById('smileBalancePHP');
        if (brBalanceEl) {
            brBalanceEl.textContent = data.data?.br || 'Error';
            if (data.message) {
                brBalanceEl.title = data.message;
            }
            brBalanceEl.style.cursor = 'pointer';
            brBalanceEl.onclick = function() { loadSmileOneBalance(); };
        }
        if (phpBalanceEl) {
            phpBalanceEl.textContent = data.data?.php || 'Error';
            if (data.message) {
                phpBalanceEl.title = data.message;
            }
            phpBalanceEl.style.cursor = 'pointer';
            phpBalanceEl.onclick = function() { loadSmileOneBalance(); };
        }
    }
}
</script>
