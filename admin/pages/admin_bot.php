<?php
// Bot Management Page

// Load bot configuration
$bot_config_file = ROOT_DIR . '/bot_config.json';
$bot_config = readJsonFile($bot_config_file);
if (empty($bot_config)) {
    // Default configuration
    $bot_config = [
        'bot_token' => '',
        'admin_ids' => [],
        'check_interval' => 30,
        'max_retries' => 3,
        'min_topup' => 10,
        'max_topup' => 10000
    ];
}

// Load additional data for bot management
$orders = readJsonFile(ROOT_DIR . '/orders.json') ?: [];
$topup_codes = readJsonFile(ROOT_DIR . '/topup_codes.json') ?: [];
$commission_rules = readJsonFile(ROOT_DIR . '/commission_rules.json') ?: [];
$admins = readJsonFile(__DIR__ . '/../assets/admins.json') ?: [];

// Filter pending orders
$pending_orders = array_filter($orders, function($order) {
    return ($order['status'] ?? 'pending') === 'pending';
});

// Get active topup codes
$active_codes = array_filter($topup_codes, function($code) {
    return ($code['used'] ?? false) === false;
});

// Bot statistics
$bot_stats = [
    'total_orders' => count($orders),
    'pending_orders' => count($pending_orders),
    'completed_orders' => count(array_filter($orders, function($o) { return ($o['status'] ?? '') === 'completed'; })),
    'active_codes' => count($active_codes),
    'total_admins' => count($admins),
    'total_commission' => array_sum(array_column($commissions, 'amount'))
];

// Quick bot status check (lightweight for fast page load)
// Heavy operations moved to async JavaScript to prevent page blocking
$bot_running = false;
$bot_pid = null;
if (file_exists(ROOT_DIR . '/bot/bot.pid')) {
    $bot_pid = trim(@file_get_contents(ROOT_DIR . '/bot/bot.pid'));
    // Just check if PID file exists, don't verify process (done via API)
    $bot_running = !empty($bot_pid) && is_numeric($bot_pid);
}

// Skip log reading during page load (too slow, done via API)
$bot_log = '';
$last_log_time = 'N/A';
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1"><i class="fas fa-robot me-2 text-primary"></i>Bot Management</h2>
            <p class="text-muted mb-0">Monitor and control your Telegram bot</p>
        </div>
        <div>
            <button class="btn btn-outline-success me-2" onclick="startBot()" id="startBotBtn">
                <i class="fas fa-play me-1"></i>Start Bot
            </button>
            <button class="btn btn-outline-danger me-2" onclick="stopBot()" id="stopBotBtn">
                <i class="fas fa-stop me-1"></i>Stop Bot
            </button>
            <button class="btn btn-outline-info me-2" onclick="refreshBotStatus()" id="refreshStatusBtn">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
            <button class="btn btn-outline-warning me-2" onclick="deleteWebhook()" id="deleteWebhookBtn">
                <i class="fas fa-unlink me-1"></i>Delete Webhook
            </button>
            <button class="btn btn-gradient" onclick="viewBotLogs()">
                <i class="fas fa-file-alt me-1"></i>View Logs
            </button>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal -->
<div id="confirmModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div class="modal-header" style="border-bottom: none; padding: 25px 25px 10px;">
                <h5 class="modal-title" id="confirmModalLabel" style="font-weight: 600; color: #333;">
                    <i id="confirmModalIcon" class="fas me-2"></i>
                    <span id="confirmModalTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="margin: 0;"></button>
            </div>
            <div class="modal-body" style="padding: 20px 25px;">
                <p id="confirmModalMessage" style="margin: 0; color: #666; font-size: 15px; line-height: 1.6;"></p>
            </div>
            <div class="modal-footer" style="border-top: none; padding: 15px 25px 25px; gap: 10px;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px; padding: 8px 20px; font-weight: 500;">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" id="confirmModalConfirmBtn" class="btn" style="border-radius: 8px; padding: 8px 20px; font-weight: 500; border: none;">
                    <i class="fas fa-check me-1"></i>Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<style>
#confirmModal .modal-content {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#confirmModal .modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(3px);
}

#confirmModal .btn-close {
    opacity: 0.6;
    transition: opacity 0.2s;
}

#confirmModal .btn-close:hover {
    opacity: 1;
}

#confirmModal .btn {
    transition: all 0.2s;
}

#confirmModal .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

#confirmModal .btn:active {
    transform: translateY(0);
}
</style>

<!-- Bot Status Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stat-card <?php echo $bot_running ? 'bg-success' : 'bg-danger'; ?> text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 text-white-50">Bot Status</h6>
                        <h3 class="mb-0 fw-bold" id="botStatusText"><?php echo $bot_running ? 'Running' : 'Stopped'; ?></h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="fas fa-<?php echo $bot_running ? 'check-circle' : 'times-circle'; ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 text-white-50">Pending Orders</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $bot_stats['pending_orders']; ?></h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 text-white-50">Active Codes</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $bot_stats['active_codes']; ?></h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="fas fa-key"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 text-white-50">Total Orders</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $bot_stats['total_orders']; ?></h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Bot Status & Control -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>Bot Status & Control</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><strong>Status:</strong></span>
                        <span class="badge bg-<?php echo $bot_running ? 'success' : 'danger'; ?>" id="statusBadge">
                            <?php echo $bot_running ? 'Running' : 'Stopped'; ?>
                        </span>
                    </div>
                    <?php if ($bot_pid): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><strong>Process ID:</strong></span>
                        <span class="text-muted" id="botPid"><?php echo htmlspecialchars($bot_pid); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><strong>Last Activity:</strong></span>
                        <span class="text-muted" id="lastActivity"><?php echo $last_log_time; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><strong>Completed Orders:</strong></span>
                        <span><?php echo $bot_stats['completed_orders']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>Total Admins:</strong></span>
                        <span><?php echo $bot_stats['total_admins']; ?></span>
                    </div>
                </div>
                
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-success" onclick="startBot()" id="startBtn" <?php echo $bot_running ? 'disabled' : ''; ?>>
                        <i class="fas fa-play me-2"></i>Start Bot
                    </button>
                    <button class="btn btn-danger" onclick="stopBot()" id="stopBtn" <?php echo !$bot_running ? 'disabled' : ''; ?>>
                        <i class="fas fa-stop me-2"></i>Stop Bot
                    </button>
                    <button class="btn btn-outline-primary" onclick="refreshBotStatus()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Status
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bot Configuration -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-cog me-2 text-primary"></i>Bot Configuration</h5>
            </div>
            <div class="card-body">
                <form id="botConfigForm">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-key me-2"></i>Telegram Bot API Token</label>
                        <input type="text" class="form-control" name="bot_token" id="botToken" 
                               value="<?php echo htmlspecialchars($bot_config['bot_token'] ?? ''); ?>" 
                               placeholder="Enter your Telegram Bot Token">
                        <small class="form-text text-muted">
                            Get your bot token from <a href="https://t.me/BotFather" target="_blank">@BotFather</a>
                        </small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user-shield me-2"></i>Admin Telegram IDs</label>
                        <textarea class="form-control" name="admin_ids" id="adminIds" rows="3" 
                                  placeholder="Enter Admin IDs, one per line (e.g., 7829183790)"><?php 
                            if (!empty($bot_config['admin_ids']) && is_array($bot_config['admin_ids'])) {
                                echo htmlspecialchars(implode("\n", $bot_config['admin_ids']));
                            }
                        ?></textarea>
                        <small class="form-text text-muted">
                            Enter Telegram User IDs (numbers only), one per line. Get your ID from <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>
                        </small>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-clock me-2"></i>Check Interval (seconds)</label>
                        <input type="number" class="form-control" name="check_interval" 
                               value="<?php echo htmlspecialchars($bot_config['check_interval'] ?? 30); ?>" 
                               min="5" max="300">
                        <small class="form-text text-muted">How often the bot checks for new orders (5-300 seconds)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-redo me-2"></i>Max Retries</label>
                        <input type="number" class="form-control" name="max_retries" 
                               value="<?php echo htmlspecialchars($bot_config['max_retries'] ?? 3); ?>" 
                               min="1" max="10">
                        <small class="form-text text-muted">Maximum retry attempts for failed orders</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-dollar-sign me-2"></i>Minimum Topup Amount</label>
                        <input type="number" class="form-control" name="min_topup" 
                               value="<?php echo htmlspecialchars($bot_config['min_topup'] ?? 10); ?>" 
                               min="1" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-dollar-sign me-2"></i>Maximum Topup Amount</label>
                        <input type="number" class="form-control" name="max_topup" 
                               value="<?php echo htmlspecialchars($bot_config['max_topup'] ?? 10000); ?>" 
                               min="1" step="0.01">
                    </div>
                    <button type="submit" class="btn btn-gradient w-100">
                        <i class="fas fa-save me-2"></i>Save Configuration
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Pending Orders -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-clock me-2 text-primary"></i>Pending Orders</h5>
        <span class="badge bg-warning fs-6"><?php echo count($pending_orders); ?> Pending</span>
    </div>
    <div class="card-body">
        <?php if (empty($pending_orders)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-check-circle fa-3x mb-3"></i>
                <p>No pending orders</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User ID</th>
                            <th>Product</th>
                            <th>Amount</th>
                            <th>Game ID</th>
                            <th>Zone ID</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $pending_orders_array = array_values($pending_orders);
                        foreach (array_slice($pending_orders_array, 0, 10) as $order): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['user_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['product_name'] ?? 'N/A'); ?></td>
                            <td>$<?php echo number_format($order['price'] ?? 0, 2); ?></td>
                            <td><?php echo htmlspecialchars($order['game_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['zone_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['created_at'] ?? 'N/A'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="processOrder('<?php echo $order['id']; ?>')">
                                    <i class="fas fa-play"></i> Process
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Active Topup Codes -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-key me-2 text-primary"></i>Active Topup Codes</h5>
        <span class="badge bg-info fs-6"><?php echo count($active_codes); ?> Active</span>
    </div>
    <div class="card-body">
        <?php if (empty($active_codes)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-key fa-3x mb-3"></i>
                <p>No active topup codes</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Country</th>
                            <th>Amount</th>
                            <th>Commission</th>
                            <th>Created</th>
                            <th>Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $active_codes_array = array_values($active_codes);
                        foreach (array_slice($active_codes_array, 0, 10) as $code): 
                        ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($code['code'] ?? 'N/A'); ?></code></td>
                            <td>
                                <span class="badge bg-<?php echo ($code['country'] ?? 'br') === 'php' ? 'primary' : 'success'; ?>">
                                    <?php echo strtoupper($code['country'] ?? 'BR'); ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($code['amount'] ?? 0, 2); ?></td>
                            <td><?php echo number_format(($code['commission_rate'] ?? 0) * 100, 1); ?>%</td>
                            <td><?php echo htmlspecialchars($code['created_at'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($code['expires_at'] ?? 'Never'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Commission Rules -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-percentage me-2 text-primary"></i>Commission Rules</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>🇵🇭 Philippines (PHP)</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Min Amount</th>
                                <th>Max Amount</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $php_rules = $commission_rules['php'] ?? [];
                            foreach ($php_rules as $rule): 
                            ?>
                            <tr>
                                <td>₱<?php echo number_format($rule['min_amount'] ?? 0, 2); ?></td>
                                <td>₱<?php echo number_format($rule['max_amount'] ?? 0, 2); ?></td>
                                <td><?php echo number_format(($rule['rate'] ?? 0) * 100, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h6>🇧🇷 Brazil (BRL)</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Min Amount</th>
                                <th>Max Amount</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $br_rules = $commission_rules['br'] ?? [];
                            foreach ($br_rules as $rule): 
                            ?>
                            <tr>
                                <td>R$<?php echo number_format($rule['min_amount'] ?? 0, 2); ?></td>
                                <td>R$<?php echo number_format($rule['max_amount'] ?? 0, 2); ?></td>
                                <td><?php echo number_format(($rule['rate'] ?? 0) * 100, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bot Logs Preview -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-file-alt me-2 text-primary"></i>Recent Bot Logs</h5>
        <button class="btn btn-sm btn-outline-primary" onclick="viewBotLogs()">
            <i class="fas fa-external-link-alt me-1"></i>View Full Logs
        </button>
    </div>
            <div class="card-body">
                <div class="bg-dark text-light p-3 rounded" style="font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">
                    <pre class="mb-0" id="botLogPreview">Loading logs...</pre>
                </div>
            </div>
</div>

<!-- Bot Logs Modal -->
<div class="modal fade" id="botLogsModal" tabindex="-1" aria-labelledby="botLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="botLogsModalLabel"><i class="fas fa-file-alt me-2"></i>Bot Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="bg-dark text-light p-3 rounded" style="font-family: monospace; font-size: 12px; max-height: 500px; overflow-y: auto;">
                    <pre class="mb-0" id="botLogsContent">Loading logs...</pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="refreshBotLogs()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal -->
<div id="confirmModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div class="modal-header" style="border-bottom: none; padding: 25px 25px 10px;">
                <h5 class="modal-title" id="confirmModalLabel" style="font-weight: 600; color: #333;">
                    <i id="confirmModalIcon" class="fas me-2"></i>
                    <span id="confirmModalTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="margin: 0;"></button>
            </div>
            <div class="modal-body" style="padding: 20px 25px;">
                <p id="confirmModalMessage" style="margin: 0; color: #666; font-size: 15px; line-height: 1.6;"></p>
            </div>
            <div class="modal-footer" style="border-top: none; padding: 15px 25px 25px; gap: 10px;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px; padding: 8px 20px; font-weight: 500;">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" id="confirmModalConfirmBtn" class="btn" style="border-radius: 8px; padding: 8px 20px; font-weight: 500; border: none;">
                    <i class="fas fa-check me-1"></i>Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<style>
#confirmModal .modal-content {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#confirmModal .modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(3px);
}

#confirmModal .btn-close {
    opacity: 0.6;
    transition: opacity 0.2s;
}

#confirmModal .btn-close:hover {
    opacity: 1;
}

#confirmModal .btn {
    transition: all 0.2s;
}

#confirmModal .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

#confirmModal .btn:active {
    transform: translateY(0);
}
</style>

<script>
// Bot Management Functions
window.startBot = function() {
    showConfirmModal(
        'Start Bot',
        'Are you sure you want to start the bot?',
        'fas fa-play',
        'btn-success',
        function() {
            // User confirmed - proceed with starting bot
            startBotConfirmed();
        }
    );
}

window.startBotConfirmed = function() {
    
    const btn = document.getElementById('startBtn');
    const originalText = btn.innerHTML;
    const startBotBtn = document.getElementById('startBotBtn');
    
    // Show loading
    showLoading('Starting bot...');
    setButtonLoading(btn, true);
    setButtonLoading(startBotBtn, true);
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Starting...';
    if (startBotBtn) startBotBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Starting...';
    
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout for start
    
    fetch('../api/admin_api.php?action=start_bot', {
        method: 'POST',
        signal: controller.signal,
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('success', 'Success!', 'Bot started successfully!');
            
            // Immediately update status to Running
            const statusText = document.getElementById('botStatusText');
            const statusBadge = document.getElementById('statusBadge');
            const statusCard = document.querySelector('.card.bg-success, .card.bg-danger, .card.bg-warning');
            
            if (statusText) statusText.textContent = 'Running';
            if (statusBadge) {
                statusBadge.textContent = 'Running';
                statusBadge.className = 'badge bg-success';
            }
            if (statusCard) {
                statusCard.className = 'card stat-card bg-success text-white';
                const icon = statusCard.querySelector('.fa-check-circle, .fa-times-circle');
                if (icon) {
                    icon.className = 'fas fa-check-circle';
                }
            }
            
            // Update buttons
            const stopBtn = document.getElementById('stopBtn');
            const stopBotBtn = document.getElementById('stopBotBtn');
            if (startBtn) startBtn.disabled = true;
            if (stopBtn) stopBtn.disabled = false;
            if (startBotBtn) startBotBtn.disabled = true;
            if (stopBotBtn) stopBotBtn.disabled = false;
            
            // Refresh status after a short delay to get latest info
            setTimeout(() => {
                refreshBotStatus();
            }, 500);
        } else {
            showToast('error', 'Error', data.message || 'Failed to start bot');
            if (btn) {
                btn.innerHTML = originalText;
                setButtonLoading(btn, false);
            }
            if (startBotBtn) {
                startBotBtn.innerHTML = '<i class="fas fa-play me-1"></i>Start Bot';
                setButtonLoading(startBotBtn, false);
            }
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Error starting bot:', error);
        hideLoading();
        
        let errorMessage = 'Failed to start bot';
        if (error.name === 'AbortError') {
            errorMessage = 'Request timeout. Please check server connection.';
        } else if (error.message.includes('Failed to fetch') || error.message.includes('ERR_CONNECTION_REFUSED')) {
            errorMessage = 'Cannot connect to server. Please check if PHP server is running on port 8000.';
        } else {
            errorMessage += ': ' + error.message;
        }
        
        showToast('error', 'Error', errorMessage);
        if (btn) {
            btn.innerHTML = originalText;
            setButtonLoading(btn, false);
        }
        if (startBotBtn) {
            startBotBtn.innerHTML = '<i class="fas fa-play me-1"></i>Start Bot';
            setButtonLoading(startBotBtn, false);
        }
    });
};

window.stopBot = function() {
    showConfirmModal(
        'Stop Bot',
        'Are you sure you want to stop the bot?',
        'fas fa-stop',
        'btn-danger',
        function() {
            // User confirmed - proceed with stopping bot
            stopBotConfirmed();
        }
    );
}

window.stopBotConfirmed = function() {
    
    const btn = document.getElementById('stopBtn');
    const originalText = btn ? btn.innerHTML : '';
    const stopBotBtn = document.getElementById('stopBotBtn');
    
    // Immediately update UI to show stopping state
    const statusText = document.getElementById('botStatusText');
    const statusBadge = document.getElementById('statusBadge');
    const statusCard = document.querySelector('.card.bg-success, .card.bg-danger');
    
    if (statusText) statusText.textContent = 'Stopping...';
    if (statusBadge) {
        statusBadge.textContent = 'Stopping...';
        statusBadge.className = 'badge bg-warning';
    }
    if (statusCard) {
        statusCard.className = 'card stat-card bg-warning text-white';
    }
    
    // Show loading
    showLoading('Stopping bot...');
    setButtonLoading(btn, true);
    setButtonLoading(stopBotBtn, true);
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Stopping...';
    if (stopBotBtn) stopBotBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Stopping...';
    
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
    
    fetch('../api/admin_api.php?action=stop_bot', {
        method: 'POST',
        signal: controller.signal,
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('success', 'Success!', 'Bot stopped successfully!');
            
            // Immediately update status to Stopped
            if (statusText) statusText.textContent = 'Stopped';
            if (statusBadge) {
                statusBadge.textContent = 'Stopped';
                statusBadge.className = 'badge bg-danger';
            }
            if (statusCard) {
                statusCard.className = 'card stat-card bg-danger text-white';
                const icon = statusCard.querySelector('.fa-check-circle, .fa-times-circle');
                if (icon) {
                    icon.className = 'fas fa-times-circle';
                }
            }
            
            // Update buttons
            const startBtn = document.getElementById('startBtn');
            const startBotBtn = document.getElementById('startBotBtn');
            if (startBtn) startBtn.disabled = false;
            if (stopBtn) stopBtn.disabled = true;
            if (startBotBtn) startBotBtn.disabled = false;
            if (stopBotBtn) {
                stopBotBtn.disabled = true;
                stopBotBtn.innerHTML = '<i class="fas fa-stop me-1"></i>Stop Bot';
                setButtonLoading(stopBotBtn, false);
            }
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-stop me-2"></i>Stop Bot';
                setButtonLoading(btn, false);
            }
            
            // Clear PID
            const pidEl = document.getElementById('botPid');
            if (pidEl) pidEl.textContent = 'N/A';
            
            // Refresh status after a short delay to get latest info
            setTimeout(() => {
                refreshBotStatus();
            }, 500);
        } else {
            showToast('error', 'Error', data.message || 'Failed to stop bot');
            if (btn) {
                btn.innerHTML = originalText;
                setButtonLoading(btn, false);
            }
            if (stopBotBtn) {
                stopBotBtn.innerHTML = '<i class="fas fa-stop me-1"></i>Stop Bot';
                setButtonLoading(stopBotBtn, false);
            }
            // Refresh status to get correct state
            refreshBotStatus();
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Error stopping bot:', error);
        hideLoading();
        
        let errorMessage = 'Failed to stop bot';
        if (error.name === 'AbortError') {
            errorMessage = 'Request timeout. Please check server connection.';
        } else if (error.message.includes('Failed to fetch') || error.message.includes('ERR_CONNECTION_REFUSED')) {
            errorMessage = 'Cannot connect to server. Please check if PHP server is running on port 8000.';
        } else {
            errorMessage += ': ' + error.message;
        }
        
        showToast('error', 'Error', errorMessage);
        if (btn) {
            btn.innerHTML = originalText;
            setButtonLoading(btn, false);
        }
        if (stopBotBtn) {
            stopBotBtn.innerHTML = '<i class="fas fa-stop me-1"></i>Stop Bot';
            setButtonLoading(stopBotBtn, false);
        }
        // Refresh status to get correct state
        refreshBotStatus();
    });
};

window.refreshBotStatus = function() {
    // Prevent multiple simultaneous refreshes
    if (refreshInProgress) {
        return;
    }
    
    refreshInProgress = true;
    // Prevent overlapping requests - don't cancel, just skip if already in progress
    if (refreshInProgress) {
        return;
    }
    
    refreshInProgress = true;
    
    // Show loading indicator
    const statusText = document.getElementById('botStatusText');
    const statusBadge = document.getElementById('statusBadge');
    const refreshBtn = document.getElementById('refreshStatusBtn');
    
    if (refreshBtn) {
        const icon = refreshBtn.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-sync-alt fa-spin me-1';
        }
        refreshBtn.disabled = true;
    }
    
    if (statusText && statusBadge) {
        statusText.textContent = 'Checking...';
        statusBadge.textContent = 'Checking...';
        statusBadge.className = 'badge bg-secondary';
    }
    
    // Create AbortController for timeout (longer timeout to avoid Cloudflare cancellation)
    // Only use timeout as last resort, don't cancel aggressively
    const controller = new AbortController();
    currentRefreshController = controller;
    const timeoutId = setTimeout(() => {
        // Only abort if this is still the current request
        if (currentRefreshController === controller && refreshInProgress) {
            controller.abort();
            console.warn('Bot status check timed out after 8 seconds');
        }
    }, 8000); // 8 second timeout - longer to avoid premature cancellation
    
    fetch('../api/admin_api.php?action=get_bot_status', {
        signal: controller.signal,
        cache: 'no-cache'
    })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            clearTimeout(timeoutId);
            currentRefreshController = null;
            consecutiveErrors = 0; // Reset error counter on success
            
            if (data.success) {
                const isRunning = data.data.running;
                const status = isRunning ? 'Running' : 'Stopped';
                const statusClass = isRunning ? 'success' : 'danger';
                const statusIcon = isRunning ? 'check-circle' : 'times-circle';
                
                // Update status card
                if (statusText) {
                    statusText.textContent = status;
                }
                if (statusBadge) {
                    statusBadge.textContent = status;
                    statusBadge.className = 'badge bg-' + statusClass;
                }
                
                // Update status card background color
                const statusCard = document.querySelector('.card.bg-success, .card.bg-danger');
                if (statusCard) {
                    statusCard.className = 'card stat-card bg-' + (isRunning ? 'success' : 'danger') + ' text-white';
                    const icon = statusCard.querySelector('.fa-check-circle, .fa-times-circle');
                    if (icon) {
                        icon.className = 'fas fa-' + statusIcon;
                    }
                }
                
                // Update control buttons
                const startBtn = document.getElementById('startBtn');
                const stopBtn = document.getElementById('stopBtn');
                const startBotBtn = document.getElementById('startBotBtn');
                const stopBotBtn = document.getElementById('stopBotBtn');
                
                if (startBtn) startBtn.disabled = isRunning;
                if (stopBtn) stopBtn.disabled = !isRunning;
                if (startBotBtn) startBotBtn.disabled = isRunning;
                if (stopBotBtn) stopBotBtn.disabled = !isRunning;
                
                // Update last activity
                const lastActivityEl = document.getElementById('lastActivity');
                if (lastActivityEl) {
                    if (data.data.last_activity && data.data.last_activity !== 'N/A') {
                        lastActivityEl.textContent = data.data.last_activity;
                    } else if (data.data.last_log) {
                        // Fallback to extracting from last_log if available
                        const logLines = data.data.last_log.split('\n');
                        const lastLine = logLines[logLines.length - 2] || logLines[logLines.length - 1];
                        if (lastLine) {
                            const timeMatch = lastLine.match(/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/);
                            if (timeMatch) {
                                lastActivityEl.textContent = timeMatch[1];
                            }
                        }
                    }
                }
                
                // Update PID if available
                if (data.data.pid) {
                    const pidEl = document.getElementById('botPid');
                    if (pidEl) {
                        pidEl.textContent = data.data.pid;
                    }
                } else {
                    // Clear PID if bot is stopped
                    const pidEl = document.getElementById('botPid');
                    if (pidEl) {
                        pidEl.textContent = 'N/A';
                    }
                }
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Error refreshing bot status:', error);
            
            consecutiveErrors++;
            
            // Check if it's a connection error
            const isConnectionError = error.name === 'AbortError' || 
                                    error.message.includes('Failed to fetch') || 
                                    error.message.includes('ERR_CONNECTION_REFUSED') ||
                                    error.message.includes('NetworkError');
            
            if (isConnectionError) {
                if (statusText) statusText.textContent = 'Connection Error';
                if (statusBadge) {
                    statusBadge.textContent = 'Connection Error';
                    statusBadge.className = 'badge bg-warning';
                }
                
                // Show toast notification only on first error
                if (consecutiveErrors === 1) {
                    showToast('warning', 'Connection Error', 'Cannot connect to server. Please check if PHP server is running on port 8000.');
                }
                
                // Disable auto-refresh after 3 consecutive errors
                if (consecutiveErrors >= 3 && refreshIntervalId) {
                    clearInterval(refreshIntervalId);
                    refreshIntervalId = null;
                    showToast('error', 'Auto-refresh Disabled', 'Auto-refresh disabled due to connection errors. Please check server status.');
                }
            } else {
                // Other errors
                if (statusText) statusText.textContent = 'Error';
                if (statusBadge) {
                    statusBadge.textContent = 'Error';
                    statusBadge.className = 'badge bg-danger';
                }
            }
        })
        .finally(() => {
            refreshInProgress = false;
            
            // Reset refresh button
            const refreshBtn = document.getElementById('refreshStatusBtn');
            if (refreshBtn) {
                const icon = refreshBtn.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-sync-alt me-1';
                }
                refreshBtn.disabled = false;
            }
        });
};

window.deleteWebhook = function() {
    if (!confirm('Are you sure you want to delete the webhook? This is needed to fix HTTP 409 errors when using polling mode.')) {
        return;
    }
    
    if (window.showLoading) {
        window.showLoading('Deleting webhook...');
    }
    
    const btn = document.getElementById('deleteWebhookBtn');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...';
        btn.disabled = true;
    }
    
    fetch('../api/admin_api.php?action=delete_webhook', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (window.hideLoading) {
            window.hideLoading();
        }
        if (data.success) {
            if (window.showToast) {
                window.showToast('success', 'Success', 'Webhook deleted successfully! The bot should now work in polling mode.');
            } else {
                alert('Webhook deleted successfully!');
            }
            // Refresh bot status after deleting webhook
            setTimeout(() => refreshBotStatus(), 1000);
        } else {
            if (window.showToast) {
                window.showToast('error', 'Error', data.message || 'Failed to delete webhook');
            } else {
                alert('Error: ' + (data.message || 'Failed to delete webhook'));
            }
        }
    })
    .catch(error => {
        console.error('Error deleting webhook:', error);
        if (window.hideLoading) {
            window.hideLoading();
        }
        if (window.showToast) {
            window.showToast('error', 'Error', 'Failed to delete webhook. Please try again.');
        } else {
            alert('Error deleting webhook');
        }
    })
    .finally(() => {
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
};

window.viewBotLogs = function() {
    const modal = new bootstrap.Modal(document.getElementById('botLogsModal'));
    refreshBotLogs();
    modal.show();
};

window.refreshBotLogs = function() {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
    
    fetch('../api/admin_api.php?action=get_bot_logs', {
        method: 'GET',
        signal: controller.signal,
        cache: 'no-cache'
    })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                document.getElementById('botLogsContent').textContent = data.data.logs || 'No logs available';
            } else {
                document.getElementById('botLogsContent').textContent = 'Error loading logs: ' + (data.message || 'Unknown error');
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Error loading logs:', error);
            
            let errorMessage = 'Error loading logs';
            if (error.name === 'AbortError') {
                errorMessage = 'Request timeout. Please check server connection.';
            } else if (error.message.includes('Failed to fetch') || error.message.includes('ERR_CONNECTION_REFUSED')) {
                errorMessage = 'Cannot connect to server. Please check if PHP server is running.';
            }
            
            document.getElementById('botLogsContent').textContent = errorMessage;
        });
};

// Save Bot Configuration
// Track if refresh is in progress to prevent overlapping requests
let refreshInProgress = false;
let refreshIntervalId = null;
let currentRefreshController = null; // Store current request controller to cancel if needed
let consecutiveErrors = 0; // Track consecutive errors to disable auto-refresh if needed

document.addEventListener('DOMContentLoaded', function() {
    const botConfigForm = document.getElementById('botConfigForm');
    if (botConfigForm) {
        botConfigForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveBotConfig();
        });
    }
    
    // Load bot logs preview asynchronously (non-blocking)
    loadBotLogsPreview();
    
    // Initial status refresh (delayed to let page render first)
    setTimeout(function() {
        refreshBotStatus();
    }, 500); // Wait 500ms for page to render
    
    // Auto-refresh bot status every 120 seconds (2 minutes) to reduce load
    // Only set one interval
    if (!refreshIntervalId) {
        refreshIntervalId = setInterval(function() {
            if (!refreshInProgress && consecutiveErrors < 5) {
                refreshBotStatus();
            }
        }, 120000); // Refresh every 120 seconds (2 minutes)
    }
});

// Load bot logs preview asynchronously
function loadBotLogsPreview() {
    fetch('../api/admin_api.php?action=get_bot_logs')
        .then(response => response.json())
        .then(data => {
            const previewEl = document.getElementById('botLogPreview');
            if (previewEl) {
                if (data.success && data.data.logs) {
                    const logs = data.data.logs;
                    const lines = logs.split('\n');
                    const lastLines = lines.slice(-20).join('\n');
                    previewEl.textContent = lastLines || 'No logs available';
                } else {
                    previewEl.textContent = 'No logs available';
                }
            }
        })
        .catch(error => {
            console.error('Error loading logs preview:', error);
            const previewEl = document.getElementById('botLogPreview');
            if (previewEl) {
                previewEl.textContent = 'Error loading logs';
            }
        });
}

window.saveBotConfig = function() {
    const form = document.getElementById('botConfigForm');
    const formData = new FormData(form);
    
    // Get form values
    const config = {
        bot_token: document.getElementById('botToken').value.trim(),
        admin_ids: document.getElementById('adminIds').value.trim(),
        check_interval: parseInt(formData.get('check_interval')) || 30,
        max_retries: parseInt(formData.get('max_retries')) || 3,
        min_topup: parseFloat(formData.get('min_topup')) || 10,
        max_topup: parseFloat(formData.get('max_topup')) || 10000
    };
    
    // Validate
    if (!config.bot_token) {
        alert('Please enter a Bot API Token');
        return;
    }
    
    if (!config.admin_ids) {
        alert('Please enter at least one Admin ID');
        return;
    }
    
    // Show loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    submitBtn.disabled = true;
    
    // Save configuration
    fetch('../api/admin_api.php?action=save_bot_config', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(config)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bot configuration saved successfully!');
            // Optionally reload the page to show updated values
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to save configuration'));
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving configuration');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
};

window.processOrder = function(orderId) {
    if (!confirm('Process this order now?')) {
        return;
    }
    
    showLoading('Processing order...');
    
    fetch('../api/admin_api.php?action=process_order', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: orderId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('success', 'Success!', 'Order processed successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('error', 'Error', data.message || 'Failed to process order');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        hideLoading();
        showToast('error', 'Error', 'Failed to process order. Please try again.');
    });
};

// Note: Auto-refresh is handled in the DOMContentLoaded event above
// This prevents duplicate intervals

// Custom Confirmation Modal Function
function showConfirmModal(title, message, icon, confirmBtnClass, onConfirm) {
    const modal = document.getElementById('confirmModal');
    const modalTitle = document.getElementById('confirmModalTitle');
    const modalMessage = document.getElementById('confirmModalMessage');
    const modalIcon = document.getElementById('confirmModalIcon');
    const confirmBtn = document.getElementById('confirmModalConfirmBtn');
    
    // Set modal content
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    modalIcon.className = icon + ' me-2';
    
    // Reset and style confirm button
    confirmBtn.className = 'btn ' + confirmBtnClass;
    confirmBtn.style.border = 'none';
    confirmBtn.style.borderRadius = '8px';
    confirmBtn.style.padding = '8px 20px';
    confirmBtn.style.fontWeight = '500';
    
    // Remove previous event listeners by cloning
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    // Add new event listener to the cloned button
    document.getElementById('confirmModalConfirmBtn').addEventListener('click', function() {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
        if (onConfirm && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
    
    // Show modal using Bootstrap 5
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}
</script>
