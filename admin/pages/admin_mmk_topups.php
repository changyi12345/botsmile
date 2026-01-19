<?php
// MMK Top Ups Management Page

// Get bot token for JavaScript
$bot_config = readJsonFile(ROOT_DIR . '/bot_config.json');
$bot_token = $bot_config['bot_token'] ?? '';
?>
<script>
window.botToken = <?php echo json_encode($bot_token); ?>;
</script>
<div class="page-header">
    <h2 class="mb-1"><i class="fas fa-money-bill-wave me-2 text-primary"></i>MMK Top Up Requests</h2>
    <p class="text-muted mb-0">Manage MMK top up requests and confirm payments</p>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6 class="mb-1">⏳ Pending</h6>
                <h3 class="mb-0" id="pendingCount">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="mb-1">✅ Approved</h6>
                <h3 class="mb-0" id="approvedCount">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="mb-1">❌ Rejected</h6>
                <h3 class="mb-0" id="rejectedCount">0</h3>
            </div>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Top Up Requests</h5>
        <button class="btn btn-sm btn-outline-primary" onclick="loadMMKTopUps()">
            <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>User ID</th>
                    <th>Method</th>
                    <th>Amount (MMK)</th>
                    <th>PHP</th>
                    <th>BRL</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="topUpsTableBody">
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <span class="spinner-border spinner-border-sm" role="status"></span>
                        Loading...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- View Top Up Details Modal -->
<div class="modal fade" id="viewTopUpModal" tabindex="-1" aria-labelledby="viewTopUpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Top Up Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="topUpDetailsBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="rejectTopUp()" id="rejectTopUpBtn">
                    <i class="fas fa-times me-2"></i>Reject
                </button>
                <button type="button" class="btn btn-success" onclick="approveTopUp()" id="approveTopUpBtn">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentTopUpId = null;
let topUpsData = [];

// Load MMK Top Ups
function loadMMKTopUps() {
    if (window.showLoading) {
        window.showLoading('Loading top up requests...');
    }
    
    fetch('../api/admin_api.php?action=get_mmk_topups')
        .then(response => response.json())
        .then(data => {
            if (window.hideLoading) {
                window.hideLoading();
            }
            
            if (data.success) {
                topUpsData = data.data || [];
                displayTopUps(topUpsData);
                updateCounts(topUpsData);
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to load top ups');
                } else {
                    alert('Error: ' + (data.message || 'Failed to load top ups'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                window.showToast('error', 'Error', 'Failed to load top ups');
            } else {
                alert('Error loading top ups');
            }
        });
}

// Display Top Ups in Table
function displayTopUps(topUps) {
    const tbody = document.getElementById('topUpsTableBody');
    
    if (topUps.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No top up requests found</td></tr>';
        return;
    }
    
    // Sort by date, newest first
    topUps.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    
    tbody.innerHTML = topUps.map(topUp => {
        const statusBadge = {
            'pending': '<span class="badge bg-warning">Pending</span>',
            'approved': '<span class="badge bg-success">Approved</span>',
            'rejected': '<span class="badge bg-danger">Rejected</span>'
        }[topUp.status] || '<span class="badge bg-secondary">Unknown</span>';
        
        const methodIcon = topUp.method === 'wave' ? '💳' : '💳';
        const methodName = topUp.method_name || (topUp.method === 'wave' ? 'Wave Money' : 'KBZ Pay');
        
        return `
            <tr>
                <td><code>${topUp.id || 'N/A'}</code></td>
                <td><code>${topUp.telegram_id || 'N/A'}</code></td>
                <td>${methodIcon} ${methodName}</td>
                <td><strong>${formatMMK(topUp.amount_mmk || 0)}</strong></td>
                <td>PHP ${parseFloat(topUp.amount_php || 0).toFixed(2)}</td>
                <td>BRL ${parseFloat(topUp.amount_brl || 0).toFixed(2)}</td>
                <td>${statusBadge}</td>
                <td>${topUp.created_at || 'N/A'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-info me-1" onclick="viewTopUp('${topUp.id}')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${topUp.status === 'pending' ? `
                        <button class="btn btn-sm btn-outline-success me-1" onclick="approveTopUp('${topUp.id}')" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="rejectTopUp('${topUp.id}')" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

// Update Counts
function updateCounts(topUps) {
    const pending = topUps.filter(t => t.status === 'pending').length;
    const approved = topUps.filter(t => t.status === 'approved').length;
    const rejected = topUps.filter(t => t.status === 'rejected').length;
    
    document.getElementById('pendingCount').textContent = pending;
    document.getElementById('approvedCount').textContent = approved;
    document.getElementById('rejectedCount').textContent = rejected;
}

// Format MMK
function formatMMK(amount) {
    return parseFloat(amount).toLocaleString('en-US', {maximumFractionDigits: 0}) + ' Ks';
}

// View Top Up Details
function viewTopUp(topUpId) {
    const topUp = topUpsData.find(t => t.id === topUpId);
    if (!topUp) {
        if (window.showToast) {
            window.showToast('error', 'Error', 'Top up request not found');
        } else {
            alert('Top up request not found');
        }
        return;
    }
    
    currentTopUpId = topUpId;
    
    const methodIcon = topUp.method === 'wave' ? '💳' : '💳';
    const methodName = topUp.method_name || (topUp.method === 'wave' ? 'Wave Money' : 'KBZ Pay');
    
    const detailsHTML = `
        <div class="mb-3">
            <h6><i class="fas fa-info-circle me-2"></i>Request Information</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Request ID:</strong></td>
                    <td><code>${topUp.id || 'N/A'}</code></td>
                </tr>
                <tr>
                    <td><strong>User ID:</strong></td>
                    <td><code>${topUp.telegram_id || 'N/A'}</code></td>
                </tr>
                <tr>
                    <td><strong>Payment Method:</strong></td>
                    <td>${methodIcon} ${methodName}</td>
                </tr>
                <tr>
                    <td><strong>Phone Number:</strong></td>
                    <td><code>${topUp.payment_phone || 'N/A'}</code></td>
                </tr>
                <tr>
                    <td><strong>Amount (MMK):</strong></td>
                    <td><strong class="text-primary">${formatMMK(topUp.amount_mmk || 0)}</strong></td>
                </tr>
                <tr>
                    <td><strong>PHP Equivalent:</strong></td>
                    <td>PHP ${parseFloat(topUp.amount_php || 0).toFixed(2)}</td>
                </tr>
                <tr>
                    <td><strong>BRL Equivalent:</strong></td>
                    <td>BRL ${parseFloat(topUp.amount_brl || 0).toFixed(2)}</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td>
                        ${topUp.status === 'pending' ? '<span class="badge bg-warning">Pending</span>' : ''}
                        ${topUp.status === 'approved' ? '<span class="badge bg-success">Approved</span>' : ''}
                        ${topUp.status === 'rejected' ? '<span class="badge bg-danger">Rejected</span>' : ''}
                    </td>
                </tr>
                <tr>
                    <td><strong>Created At:</strong></td>
                    <td>${topUp.created_at || 'N/A'}</td>
                </tr>
                ${topUp.approved_at ? `
                <tr>
                    <td><strong>Approved At:</strong></td>
                    <td>${topUp.approved_at}</td>
                </tr>
                ` : ''}
                ${topUp.rejected_at ? `
                <tr>
                    <td><strong>Rejected At:</strong></td>
                    <td>${topUp.rejected_at}</td>
                </tr>
                ` : ''}
            </table>
        </div>
        ${topUp.photo_file_id ? `
        <div class="mb-3">
            <h6><i class="fas fa-image me-2"></i>Payment Screenshot</h6>
            <div class="text-center">
                <img id="paymentScreenshot_${topUp.id}" src="" alt="Payment Screenshot" class="img-fluid img-thumbnail" style="max-width: 100%; max-height: 500px; display: none;" onerror="this.style.display='none'; document.getElementById('screenshotError_${topUp.id}').style.display='block';">
                <div id="screenshotError_${topUp.id}" style="display: none;" class="alert alert-warning">
                    <small>⚠️ Unable to load image. Photo File ID: <code>${topUp.photo_file_id}</code></small>
                </div>
                <p class="text-muted mt-2"><small>Photo File ID: <code>${topUp.photo_file_id}</code></small></p>
                <button class="btn btn-sm btn-primary mt-2" onclick="loadPaymentScreenshot('${topUp.id}', '${topUp.photo_file_id}')">
                    <i class="fas fa-eye me-1"></i>Load Screenshot
                </button>
            </div>
        </div>
        ` : ''}
    `;
    
    document.getElementById('topUpDetailsBody').innerHTML = detailsHTML;
    
    // Show/hide action buttons based on status
    const approveBtn = document.getElementById('approveTopUpBtn');
    const rejectBtn = document.getElementById('rejectTopUpBtn');
    if (topUp.status === 'pending') {
        approveBtn.style.display = 'inline-block';
        rejectBtn.style.display = 'inline-block';
    } else {
        approveBtn.style.display = 'none';
        rejectBtn.style.display = 'none';
    }
    
    // Initialize modal
    if (!window.viewTopUpModalInstance) {
        window.viewTopUpModalInstance = new bootstrap.Modal(document.getElementById('viewTopUpModal'));
    }
    window.viewTopUpModalInstance.show();
    
    // Auto-load screenshot if available
    if (topUp.photo_file_id) {
        setTimeout(() => {
            loadPaymentScreenshot(topUp.id, topUp.photo_file_id);
        }, 300); // Small delay to ensure modal is visible
    }
}

// Get Bot Token (helper function)
function getBotToken() {
    // This will be set from PHP or fetched via API
    return window.botToken || '';
}

// Approve Top Up
function approveTopUp(topUpId) {
    const id = topUpId || currentTopUpId;
    if (!id) {
        if (window.showToast) {
            window.showToast('error', 'Error', 'No top up ID selected');
        } else {
            alert('No top up ID selected');
        }
        return;
    }
    
    if (!confirm('Are you sure you want to approve this top up request? Balance will be added to user account.')) {
        return;
    }
    
    if (window.showLoading) {
        window.showLoading('Approving top up...');
    }
    
    fetch('../api/admin_api.php?action=approve_mmk_topup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ topup_id: id })
    })
        .then(response => response.json())
        .then(data => {
            if (window.hideLoading) {
                window.hideLoading();
            }
            
            if (data.success) {
                if (window.showToast) {
                    window.showToast('success', 'Success', data.message || 'Top up approved successfully!');
                } else {
                    alert(data.message || 'Top up approved successfully!');
                }
                
                // Close modal if open
                if (window.viewTopUpModalInstance) {
                    window.viewTopUpModalInstance.hide();
                }
                
                // Reload top ups
                loadMMKTopUps();
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to approve top up');
                } else {
                    alert('Error: ' + (data.message || 'Failed to approve top up'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                window.showToast('error', 'Error', 'Failed to approve top up');
            } else {
                alert('Error approving top up');
            }
        });
}

// Reject Top Up
function rejectTopUp(topUpId) {
    const id = topUpId || currentTopUpId;
    if (!id) {
        if (window.showToast) {
            window.showToast('error', 'Error', 'No top up ID selected');
        } else {
            alert('No top up ID selected');
        }
        return;
    }
    
    const reason = prompt('Enter rejection reason (optional):');
    if (reason === null) {
        return; // User cancelled
    }
    
    if (window.showLoading) {
        window.showLoading('Rejecting top up...');
    }
    
    fetch('../api/admin_api.php?action=reject_mmk_topup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            topup_id: id,
            reason: reason || ''
        })
    })
        .then(response => response.json())
        .then(data => {
            if (window.hideLoading) {
                window.hideLoading();
            }
            
            if (data.success) {
                if (window.showToast) {
                    window.showToast('success', 'Success', data.message || 'Top up rejected');
                } else {
                    alert(data.message || 'Top up rejected');
                }
                
                // Close modal if open
                if (window.viewTopUpModalInstance) {
                    window.viewTopUpModalInstance.hide();
                }
                
                // Reload top ups
                loadMMKTopUps();
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to reject top up');
                } else {
                    alert('Error: ' + (data.message || 'Failed to reject top up'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                window.showToast('error', 'Error', 'Failed to reject top up');
            } else {
                alert('Error rejecting top up');
            }
        });
}

// Load Payment Screenshot
function loadPaymentScreenshot(topUpId, photoFileId) {
    if (!photoFileId) {
        if (window.showToast) {
            window.showToast('error', 'Error', 'No photo file ID available');
        } else {
            alert('No photo file ID available');
        }
        return;
    }
    
    const imgElement = document.getElementById(`paymentScreenshot_${topUpId}`);
    const errorElement = document.getElementById(`screenshotError_${topUpId}`);
    const loadButton = imgElement?.parentElement?.querySelector('button');
    
    if (!imgElement) return;
    
    // Hide button and show loading
    if (loadButton) {
        loadButton.disabled = true;
        loadButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';
    }
    
    // Get file path from Telegram API
    fetch(`../api/admin_api.php?action=get_telegram_file&file_id=${encodeURIComponent(photoFileId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.file_path) {
                // Construct full URL
                const botToken = window.botToken || '';
                if (botToken) {
                    const imageUrl = `https://api.telegram.org/file/bot${botToken}/${data.file_path}`;
                    imgElement.src = imageUrl;
                    imgElement.style.display = 'block';
                    if (errorElement) errorElement.style.display = 'none';
                    if (loadButton) {
                        loadButton.style.display = 'none';
                    }
                    
                    // Handle image load error
                    imgElement.onerror = function() {
                        this.style.display = 'none';
                        if (errorElement) {
                            errorElement.innerHTML = `<small>⚠️ Failed to load image. <a href="${imageUrl}" target="_blank" class="text-primary">Click here to open</a></small>`;
                            errorElement.style.display = 'block';
                        }
                        if (loadButton) {
                            loadButton.style.display = 'inline-block';
                            loadButton.disabled = false;
                            loadButton.innerHTML = '<i class="fas fa-eye me-1"></i>Load Screenshot';
                        }
                    };
                } else {
                    if (errorElement) {
                        errorElement.innerHTML = '<small>⚠️ Bot token not configured. Cannot load image.</small>';
                        errorElement.style.display = 'block';
                    }
                    if (loadButton) {
                        loadButton.disabled = false;
                        loadButton.innerHTML = '<i class="fas fa-eye me-1"></i>Load Screenshot';
                    }
                }
            } else {
                if (errorElement) {
                    errorElement.innerHTML = `<small>⚠️ ${data.message || 'Failed to load image'}. Photo File ID: <code>${photoFileId}</code></small>`;
                    errorElement.style.display = 'block';
                }
                if (loadButton) {
                    loadButton.disabled = false;
                    loadButton.innerHTML = '<i class="fas fa-eye me-1"></i>Load Screenshot';
                }
            }
        })
        .catch(error => {
            console.error('Error loading screenshot:', error);
            if (errorElement) {
                errorElement.innerHTML = `<small>⚠️ Error loading image. Photo File ID: <code>${photoFileId}</code></small>`;
                errorElement.style.display = 'block';
            }
            if (loadButton) {
                loadButton.disabled = false;
                loadButton.innerHTML = '<i class="fas fa-eye me-1"></i>Load Screenshot';
            }
        });
}

// Load on page load
document.addEventListener('DOMContentLoaded', function() {
    loadMMKTopUps();
    
    // Auto-refresh every 30 seconds
    setInterval(loadMMKTopUps, 30000);
});
</script>
