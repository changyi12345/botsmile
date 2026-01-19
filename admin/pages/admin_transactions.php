<?php
// Transactions Management Page
// Ensure transactions variable is loaded
if (!isset($transactions)) {
    $transactions = readJsonFile(ROOT_DIR . '/transactions.json');
}

// Sort transactions by date, most recent first
usort($transactions, function($a, $b) {
    $dateA = $a['created_at'] ?? $a['timestamp'] ?? '0';
    $dateB = $b['created_at'] ?? $b['timestamp'] ?? '0';
    return strtotime($dateB) - strtotime($dateA);
});

// If no transactions, show empty state
if (empty($transactions)) {
    $transactions = [];
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1"><i class="fas fa-exchange-alt me-2 text-primary"></i>Transaction Management</h2>
            <p class="text-muted mb-0">View and manage all transactions</p>
        </div>
        <div>
            <button class="btn btn-outline-primary me-2" onclick="exportTransactions()">
                <i class="fas fa-download me-1"></i>Export
            </button>
            <button class="btn btn-gradient" onclick="addTransaction()">
                <i class="fas fa-plus me-1"></i>Add Transaction
            </button>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>User ID</th>
                    <th>Amount</th>
                    <th>Game/Country</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-muted mb-0">No transactions found</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $transaction): 
                    $country = strtolower($transaction['country'] ?? 'br');
                    $amount = $transaction['amount'] ?? 0;
                    
                    // Determine country badge
                    if ($country === 'pubg_br' || $country === 'pubg') {
                        $countryBadge = '<span class="badge bg-info">🎮 Pubg BR</span>';
                        $currency = 'R$';
                    } elseif ($country === 'pubg_php') {
                        $countryBadge = '<span class="badge bg-info" style="opacity: 0.9;">🎮 Pubg PHP</span>';
                        $currency = '₱';
                    } elseif ($country === 'hok_br' || $country === 'hok') {
                        $countryBadge = '<span class="badge bg-warning">⚔️ HoK BR</span>';
                        $currency = 'R$';
                    } elseif ($country === 'hok_php') {
                        $countryBadge = '<span class="badge bg-warning" style="opacity: 0.9;">⚔️ HoK PHP</span>';
                        $currency = '₱';
                    } elseif ($country === 'php') {
                        $countryBadge = '<span class="badge bg-primary">🇵🇭 PHP</span>';
                        $currency = '₱';
                    } else {
                        $countryBadge = '<span class="badge bg-success">🇧🇷 BR</span>';
                        $currency = 'R$';
                    }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($transaction['id'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($transaction['user_id'] ?? 'N/A'); ?></td>
                    <td>
                        <?php echo $currency . number_format($amount, 2); ?>
                        <br>
                        <small class="text-muted"><?php echo formatMMK(convertToMMK($amount, $country)); ?></small>
                    </td>
                    <td>
                        <?php echo $countryBadge; ?>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($transaction['type'] ?? 'N/A'); ?></small>
                    </td>
                    <td>
                        <span class="badge bg-<?php 
                            $status = $transaction['status'] ?? 'pending';
                            echo $status === 'completed' ? 'success' : 
                                 ($status === 'pending' ? 'warning' : 'danger');
                        ?>">
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($transaction['created_at'] ?? 'N/A'); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick="viewTransaction('<?php echo $transaction['id']; ?>')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editTransaction('<?php echo $transaction['id']; ?>')" title="Edit Transaction">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTransaction('<?php echo $transaction['id']; ?>')" title="Delete Transaction">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Transaction Details Modal -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1" aria-labelledby="viewTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTransactionModalLabel"><i class="fas fa-receipt me-2"></i>Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewTransactionModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editTransactionFromView()">Edit Transaction</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit/Add Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTransactionModalLabel"><i class="fas fa-edit me-2"></i>Edit Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editTransactionForm">
                    <input type="hidden" id="editTransactionId" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-hashtag me-2"></i>Transaction ID</label>
                            <input type="text" class="form-control" id="editTransactionIdDisplay" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-user me-2"></i>User ID</label>
                            <input type="text" class="form-control" id="editTransactionUserId" placeholder="Telegram User ID" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-dollar-sign me-2"></i>Amount</label>
                            <input type="number" class="form-control" id="editTransactionAmount" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-tag me-2"></i>Type</label>
                            <select class="form-select" id="editTransactionType" required>
                                <option value="topup_redeem">Topup Redeem</option>
                                <option value="purchase">Purchase</option>
                                <option value="withdrawal">Withdrawal</option>
                                <option value="deposit">Deposit</option>
                                <option value="refund">Refund</option>
                                <option value="commission">Commission</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-flag me-2"></i>Country</label>
                            <select class="form-select" id="editTransactionCountry">
                                <option value="php">🇵🇭 Philippines (PHP)</option>
                                <option value="br">🇧🇷 Brazil (BRL)</option>
                                <option value="us">🇺🇸 United States (USD)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-info-circle me-2"></i>Status</label>
                            <select class="form-select" id="editTransactionStatus">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-align-left me-2"></i>Details</label>
                        <textarea class="form-control" id="editTransactionDetails" rows="3" placeholder="Transaction details..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-calendar me-2"></i>Created At</label>
                            <input type="text" class="form-control" id="editTransactionCreatedAt" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-clock me-2"></i>Timestamp</label>
                            <input type="text" class="form-control" id="editTransactionTimestamp" readonly>
                        </div>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Transaction Management Functions
let currentViewingTransactionId = null;

// View Transaction Details
window.viewTransaction = function(transactionId) {
    currentViewingTransactionId = transactionId;
    showLoading('Loading transaction details...');
    
    fetch(`../api/admin_api.php?action=get_transaction&id=${transactionId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const transaction = data.data;
                const modalBody = document.getElementById('viewTransactionModalBody');
                
                const statusBadge = transaction.status === 'completed' ? 'success' : 
                                   (transaction.status === 'pending' ? 'warning' : 'danger');
                const countryFlag = transaction.country === 'php' ? '🇵🇭' : 
                                   (transaction.country === 'br' ? '🇧🇷' : '🇺🇸');
                const countryName = transaction.country === 'php' ? 'Philippines' : 
                                   (transaction.country === 'br' ? 'Brazil' : 'United States');
                
                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Transaction ID</h6>
                            <p class="mb-0"><strong>${transaction.id || 'N/A'}</strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">User ID</h6>
                            <p class="mb-0"><strong>${transaction.user_id || 'N/A'}</strong></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Amount</h6>
                            <p class="mb-0"><strong class="text-success">$${parseFloat(transaction.amount || 0).toFixed(2)}</strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Type</h6>
                            <p class="mb-0"><strong>${transaction.type || 'N/A'}</strong></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Country</h6>
                            <p class="mb-0"><strong>${countryFlag} ${countryName}</strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Status</h6>
                            <span class="badge bg-${statusBadge}">${transaction.status || 'pending'}</span>
                        </div>
                    </div>
                    ${transaction.details ? `
                    <div class="mb-3">
                        <h6 class="text-muted mb-1">Details</h6>
                        <p class="mb-0">${transaction.details}</p>
                    </div>
                    ` : ''}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Created At</h6>
                            <p class="mb-0">${transaction.created_at || transaction.timestamp || 'N/A'}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Timestamp</h6>
                            <p class="mb-0">${transaction.timestamp || transaction.created_at || 'N/A'}</p>
                        </div>
                    </div>
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('viewTransactionModal'));
                modal.show();
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', data.message || 'Failed to load transaction details');
                } else {
                    alert('Error: ' + (data.message || 'Failed to load transaction details'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to load transaction details');
            } else {
                alert('Error loading transaction details');
            }
        });
};

// Edit Transaction from View Modal
window.editTransactionFromView = function() {
    if (currentViewingTransactionId) {
        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewTransactionModal'));
        if (viewModal) viewModal.hide();
        
        setTimeout(() => {
            editTransaction(currentViewingTransactionId);
        }, 300);
    }
};

// Edit Transaction
window.editTransaction = function(transactionId) {
    showLoading('Loading transaction data...');
    
    fetch(`../api/admin_api.php?action=get_transaction&id=${transactionId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const transaction = data.data;
                
                document.getElementById('editTransactionId').value = transaction.id || '';
                document.getElementById('editTransactionIdDisplay').value = transaction.id || '';
                document.getElementById('editTransactionUserId').value = transaction.user_id || '';
                document.getElementById('editTransactionAmount').value = transaction.amount || 0;
                document.getElementById('editTransactionType').value = transaction.type || 'topup_redeem';
                document.getElementById('editTransactionCountry').value = transaction.country || 'php';
                document.getElementById('editTransactionStatus').value = transaction.status || 'pending';
                document.getElementById('editTransactionDetails').value = transaction.details || '';
                document.getElementById('editTransactionCreatedAt').value = transaction.created_at || transaction.timestamp || '';
                document.getElementById('editTransactionTimestamp').value = transaction.timestamp || transaction.created_at || '';
                
                document.getElementById('editTransactionModalLabel').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Transaction';
                
                const modal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
                modal.show();
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', data.message || 'Failed to load transaction data');
                } else {
                    alert('Error: ' + (data.message || 'Failed to load transaction data'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to load transaction data');
            } else {
                alert('Error loading transaction data');
            }
        });
};

// Delete Transaction
window.deleteTransaction = function(transactionId) {
    if (!confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
        return;
    }
    
    showLoading('Deleting transaction...');
    
    fetch('../api/admin_api.php?action=delete_transaction', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: transactionId
        })
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                if (window.showToast) {
                    showToast('success', 'Success!', 'Transaction deleted successfully!');
                } else {
                    alert('Transaction deleted successfully!');
                }
                setTimeout(() => location.reload(), 1500);
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', data.message || 'Failed to delete transaction');
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete transaction'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to delete transaction. Please try again.');
            } else {
                alert('Error deleting transaction');
            }
        });
};

// Add Transaction
window.addTransaction = function() {
    // Reset form
    document.getElementById('editTransactionId').value = '';
    document.getElementById('editTransactionIdDisplay').value = 'Auto-generated';
    document.getElementById('editTransactionUserId').value = '';
    document.getElementById('editTransactionAmount').value = '0';
    document.getElementById('editTransactionType').value = 'topup_redeem';
    document.getElementById('editTransactionCountry').value = 'php';
    document.getElementById('editTransactionStatus').value = 'pending';
    document.getElementById('editTransactionDetails').value = '';
    document.getElementById('editTransactionCreatedAt').value = '';
    document.getElementById('editTransactionTimestamp').value = '';
    
    document.getElementById('editTransactionModalLabel').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Transaction';
    
    const modal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
    modal.show();
};

// Export Transactions
window.exportTransactions = function() {
    showLoading('Exporting transactions...');
    
    fetch('../api/admin_api.php?action=get_transactions')
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success && data.data) {
                // Convert to CSV
                const transactions = data.data;
                let csv = 'ID,User ID,Amount,Type,Country,Status,Details,Created At,Timestamp\n';
                
                transactions.forEach(transaction => {
                    csv += `"${transaction.id || ''}","${transaction.user_id || ''}","${transaction.amount || 0}","${transaction.type || ''}","${transaction.country || ''}","${transaction.status || 'pending'}","${(transaction.details || '').replace(/"/g, '""')}","${transaction.created_at || ''}","${transaction.timestamp || ''}"\n`;
                });
                
                // Download CSV
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'transactions_' + new Date().toISOString().split('T')[0] + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                if (window.showToast) {
                    showToast('success', 'Success!', 'Transactions exported successfully!');
                } else {
                    alert('Transactions exported successfully!');
                }
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', 'Failed to export transactions');
                } else {
                    alert('Failed to export transactions');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to export transactions');
            } else {
                alert('Error exporting transactions');
            }
        });
};

// Handle Edit Transaction Form Submission
document.getElementById('editTransactionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const transactionId = document.getElementById('editTransactionId').value;
    const userId = document.getElementById('editTransactionUserId').value.trim();
    const amount = parseFloat(document.getElementById('editTransactionAmount').value) || 0;
    const type = document.getElementById('editTransactionType').value;
    const country = document.getElementById('editTransactionCountry').value;
    const status = document.getElementById('editTransactionStatus').value;
    const details = document.getElementById('editTransactionDetails').value.trim();
    
    if (!userId) {
        if (window.showToast) {
            showToast('warning', 'Validation Error', 'User ID is required');
        } else {
            alert('User ID is required');
        }
        return;
    }
    
    if (amount < 0) {
        if (window.showToast) {
            showToast('warning', 'Validation Error', 'Amount must be positive');
        } else {
            alert('Amount must be positive');
        }
        return;
    }
    
    const isNewTransaction = !transactionId;
    const action = isNewTransaction ? 'add_transaction' : 'update_transaction';
    
    showLoading(isNewTransaction ? 'Adding transaction...' : 'Updating transaction...');
    
    const payload = {
        id: transactionId || null,
        user_id: userId,
        amount: amount,
        type: type,
        country: country,
        status: status,
        details: details
    };
    
    fetch(`../api/admin_api.php?action=${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                if (window.showToast) {
                    showToast('success', 'Success!', isNewTransaction ? 'Transaction added successfully!' : 'Transaction updated successfully!');
                } else {
                    alert(isNewTransaction ? 'Transaction added successfully!' : 'Transaction updated successfully!');
                }
                const modal = bootstrap.Modal.getInstance(document.getElementById('editTransactionModal'));
                if (modal) modal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', data.message || 'Failed to save transaction');
                } else {
                    alert('Error: ' + (data.message || 'Failed to save transaction'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to save transaction. Please try again.');
            } else {
                alert('Error saving transaction');
            }
        });
});
</script>
