<?php
// Users Management Page
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1"><i class="fas fa-users me-2 text-primary"></i>User Management</h2>
            <p class="text-muted mb-0">Manage user accounts and balances</p>
        </div>
        <button class="btn btn-gradient" onclick="addUser()">
            <i class="fas fa-plus me-1"></i>Add User
        </button>
    </div>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Telegram ID</th>
                    <th>Username</th>
                    <th>🇵🇭 PHP Balance</th>
                    <th>🇧🇷 BR Balance</th>
                    <th>Status</th>
                    <th>Joined Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): 
                    $php_balance = $user['balance_php'] ?? 0;
                    $br_balance = $user['balance_br'] ?? 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['telegram_id'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                    <td>
                        <div class="d-flex flex-column">
                            <span class="fw-bold text-primary">₱<?php echo number_format($php_balance, 2); ?></span>
                            <small class="text-muted"><?php echo formatMMK(convertToMMK($php_balance, 'php')); ?></small>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            <span class="fw-bold text-success">R$<?php echo number_format($br_balance, 2); ?></span>
                            <small class="text-muted"><?php echo formatMMK(convertToMMK($br_balance, 'br')); ?></small>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo ($user['is_active'] ?? true) ? 'success' : 'danger'; ?>">
                            <?php echo ($user['is_active'] ?? true) ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($user['created_at'] ?? 'N/A'); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editUser('<?php echo $user['telegram_id']; ?>')" title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info me-1" onclick="viewUserDetails('<?php echo $user['telegram_id']; ?>')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('<?php echo $user['telegram_id']; ?>')" title="Delete User">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View User Details Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewUserModalLabel"><i class="fas fa-user me-2"></i>User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewUserModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editUserFromView()">Edit User</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit/Add User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="editUserTelegramId" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-id-card me-2"></i>Telegram ID</label>
                            <input type="text" class="form-control" id="editUserTelegramIdDisplay" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-user me-2"></i>Username</label>
                            <input type="text" class="form-control" id="editUserUsername" placeholder="Username">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-coins me-2 text-primary"></i>🇵🇭 PHP Balance</label>
                            <input type="number" class="form-control" id="editUserBalancePhp" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-coins me-2 text-success"></i>🇧🇷 BR Balance</label>
                            <input type="number" class="form-control" id="editUserBalanceBr" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-toggle-on me-2"></i>Status</label>
                            <select class="form-select" id="editUserStatus">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-calendar me-2"></i>Created At</label>
                            <input type="text" class="form-control" id="editUserCreatedAt" readonly>
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
// User Management Functions
let currentViewingUserId = null;

// View User Details
window.viewUserDetails = function(telegramId) {
    currentViewingUserId = telegramId;
    showLoading('Loading user details...');
    
    fetch(`../api/admin_api.php?action=get_user&telegram_id=${telegramId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const user = data.data;
                const modalBody = document.getElementById('viewUserModalBody');
                
                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Telegram ID</h6>
                            <p class="mb-0"><strong>${user.telegram_id || 'N/A'}</strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Username</h6>
                            <p class="mb-0"><strong>${user.username || 'N/A'}</strong></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">🇵🇭 PHP Balance</h6>
                            <p class="mb-0"><strong class="text-primary">₱${parseFloat(user.balance_php || 0).toFixed(2)}</strong></p>
                            <small class="text-muted">${formatMMK(convertToMMK(user.balance_php || 0, 'php'))}</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">🇧🇷 BR Balance</h6>
                            <p class="mb-0"><strong class="text-success">R$${parseFloat(user.balance_br || 0).toFixed(2)}</strong></p>
                            <small class="text-muted">${formatMMK(convertToMMK(user.balance_br || 0, 'br'))}</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Status</h6>
                            <span class="badge bg-${(user.is_active !== false) ? 'success' : 'danger'}">
                                ${(user.is_active !== false) ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Created At</h6>
                            <p class="mb-0">${user.created_at || 'N/A'}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Last Active</h6>
                            <p class="mb-0">${user.last_active || 'N/A'}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Total Topups</h6>
                            <p class="mb-0"><strong>${user.total_topups || 0}</strong></p>
                        </div>
                    </div>
                    ${user.total_commission !== undefined ? `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Total Commission</h6>
                            <p class="mb-0"><strong>$${parseFloat(user.total_commission || 0).toFixed(2)}</strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted mb-1">Total Deposited</h6>
                            <p class="mb-0"><strong>$${parseFloat(user.total_deposited || 0).toFixed(2)}</strong></p>
                        </div>
                    </div>
                    ` : ''}
                `;
                
                const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
                modal.show();
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', data.message || 'Failed to load user details');
                } else {
                    alert('Error: ' + (data.message || 'Failed to load user details'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to load user details');
            } else {
                alert('Error loading user details');
            }
        });
};

// Edit User from View Modal
window.editUserFromView = function() {
    if (currentViewingUserId) {
        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewUserModal'));
        if (viewModal) viewModal.hide();
        
        setTimeout(() => {
            editUser(currentViewingUserId);
        }, 300);
    }
};

// Edit User
window.editUser = function(telegramId) {
    showLoading('Loading user data...');
    
    fetch(`../api/admin_api.php?action=get_user&telegram_id=${telegramId}`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const user = data.data;
                
                document.getElementById('editUserTelegramId').value = user.telegram_id || '';
                document.getElementById('editUserTelegramIdDisplay').value = user.telegram_id || '';
                document.getElementById('editUserUsername').value = user.username || '';
                document.getElementById('editUserBalancePhp').value = user.balance_php || 0;
                document.getElementById('editUserBalanceBr').value = user.balance_br || 0;
                document.getElementById('editUserStatus').value = (user.is_active !== false) ? '1' : '0';
                document.getElementById('editUserCreatedAt').value = user.created_at || '';
                
                document.getElementById('editUserModalLabel').innerHTML = '<i class="fas fa-user-edit me-2"></i>Edit User';
                
                const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                modal.show();
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', data.message || 'Failed to load user data');
                } else {
                    alert('Error: ' + (data.message || 'Failed to load user data'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to load user data');
            } else {
                alert('Error loading user data');
            }
        });
};

// Delete User
window.deleteUser = function(telegramId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }
    
    showLoading('Deleting user...');
    
    fetch('../api/admin_api.php?action=delete_user', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            telegram_id: telegramId
        })
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                if (window.showToast) {
                    showToast('success', 'Success!', 'User deleted successfully!');
                } else {
                    alert('User deleted successfully!');
                }
                setTimeout(() => location.reload(), 1500);
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', data.message || 'Failed to delete user');
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete user'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to delete user. Please try again.');
            } else {
                alert('Error deleting user');
            }
        });
};

// Add User
window.addUser = function() {
    // Reset form
    document.getElementById('editUserTelegramId').value = '';
    document.getElementById('editUserTelegramIdDisplay').value = '';
    document.getElementById('editUserUsername').value = '';
    document.getElementById('editUserBalancePhp').value = '0';
    document.getElementById('editUserBalanceBr').value = '0';
    document.getElementById('editUserStatus').value = '1';
    document.getElementById('editUserCreatedAt').value = '';
    
    document.getElementById('editUserTelegramIdDisplay').removeAttribute('readonly');
    document.getElementById('editUserTelegramIdDisplay').placeholder = 'Enter Telegram ID';
    document.getElementById('editUserModalLabel').innerHTML = '<i class="fas fa-user-plus me-2"></i>Add New User';
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
};

// Handle Edit User Form Submission
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const telegramId = document.getElementById('editUserTelegramId').value || document.getElementById('editUserTelegramIdDisplay').value;
    const username = document.getElementById('editUserUsername').value.trim();
    const balancePhp = parseFloat(document.getElementById('editUserBalancePhp').value) || 0;
    const balanceBr = parseFloat(document.getElementById('editUserBalanceBr').value) || 0;
    const isActive = document.getElementById('editUserStatus').value === '1';
    
    if (!telegramId) {
        if (window.showToast) {
            showToast('warning', 'Validation Error', 'Telegram ID is required');
        } else {
            alert('Telegram ID is required');
        }
        return;
    }
    
    const isNewUser = !document.getElementById('editUserTelegramId').value;
    const action = isNewUser ? 'add_user' : 'update_user';
    
    showLoading(isNewUser ? 'Adding user...' : 'Updating user...');
    
    fetch(`../api/admin_api.php?action=${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            telegram_id: telegramId,
            username: username,
            balance_php: balancePhp,
            balance_br: balanceBr,
            is_active: isActive
        })
    })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                if (window.showToast) {
                    showToast('success', 'Success!', isNewUser ? 'User added successfully!' : 'User updated successfully!');
                } else {
                    alert(isNewUser ? 'User added successfully!' : 'User updated successfully!');
                }
                const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                if (modal) modal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                if (window.showToast) {
                    showToast('error', 'Error', data.message || 'Failed to save user');
                } else {
                    alert('Error: ' + (data.message || 'Failed to save user'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoading();
            if (window.showToast) {
                showToast('error', 'Error', 'Failed to save user. Please try again.');
            } else {
                alert('Error saving user');
            }
        });
});

// Helper functions for MMK conversion (if not already defined)
function convertToMMK(price, country) {
    const exchangeRates = {
        'php_to_mmk': 38.2,
        'brl_to_mmk': 85.5,
        'usd_to_mmk': 2100.0
    };
    
    switch (country.toLowerCase()) {
        case 'php': return price * exchangeRates.php_to_mmk;
        case 'br':
        case 'brl': return price * exchangeRates.brl_to_mmk;
        default: return price * exchangeRates.usd_to_mmk;
    }
}

function formatMMK(amount) {
    return number_format(amount, 0, '.', ',') + ' Ks';
}

function number_format(number, decimals, dec_point, thousands_sep) {
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    const n = !isFinite(+number) ? 0 : +number;
    const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
    const sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep;
    const dec = (typeof dec_point === 'undefined') ? '.' : dec_point;
    const s = (prec ? toFixed(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);
}

function toFixed(n, prec) {
    const k = Math.pow(10, prec);
    return '' + (Math.round(n * k) / k).toFixed(prec);
}
</script>
