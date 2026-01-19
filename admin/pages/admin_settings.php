<?php
// Settings Page
?>
<div class="page-header">
    <h2 class="mb-1"><i class="fas fa-cog me-2 text-primary"></i>Settings</h2>
    <p class="text-muted mb-0">System configuration and security settings</p>
</div>

<div class="row">
    <!-- Cookie Management -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-cookie me-2 text-primary"></i>Cookie Management</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="loadCookies()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <textarea class="form-control font-monospace" id="cookiesEditor" rows="15" 
                              style="font-size: 12px;" placeholder="Loading cookies..."></textarea>
                    <small class="form-text text-muted">Edit cookies JSON. Make sure it's valid JSON format.</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-gradient" onclick="saveCookies()">
                        <i class="fas fa-save me-2"></i>Save Cookies
                    </button>
                    <button class="btn btn-outline-secondary" onclick="validateCookies()">
                        <i class="fas fa-check me-2"></i>Validate JSON
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bot File Editor -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-code me-2 text-primary"></i>Bot File Editor</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="loadBotFile()">
                    <i class="fas fa-sync-alt me-1"></i>Reload
                </button>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Bot File Path</label>
                    <input type="text" class="form-control" id="botFilePath" value="<?php echo ROOT_DIR; ?>/bot/bot.php" readonly>
                </div>
                <div class="mb-3">
                    <textarea class="form-control font-monospace" id="botFileEditor" rows="20" 
                              style="font-size: 12px;" placeholder="Loading bot file..."></textarea>
                    <small class="form-text text-muted">⚠️ Warning: Editing bot file directly. Make sure you know what you're doing!</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-gradient" onclick="saveBotFile()">
                        <i class="fas fa-save me-2"></i>Save Bot File
                    </button>
                    <button class="btn btn-outline-warning" onclick="backupBotFile()">
                        <i class="fas fa-download me-2"></i>Backup Before Save
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Send Message to Users -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-paper-plane me-2 text-primary"></i>Send Message to Users</h5>
            </div>
            <div class="card-body">
                <form id="sendMessageForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Select User</label>
                            <select class="form-select" id="messageUserId" required>
                                <option value="">-- Select User --</option>
                            </select>
                            <small class="form-text text-muted">Or enter Telegram ID manually</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Or Enter Telegram ID</label>
                            <input type="text" class="form-control" id="messageTelegramId" 
                                   placeholder="e.g., 7829183790">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-image me-2"></i>Image (Optional)</label>
                        <input type="file" class="form-control" id="messageImage" accept="image/*">
                        <small class="form-text text-muted">Upload an image to send with the message (JPG, PNG, GIF)</small>
                        <div id="imagePreview" class="mt-2" style="display: none;">
                            <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                            <button type="button" class="btn btn-sm btn-danger ms-2" onclick="clearImagePreview()">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" id="messageText" rows="5" 
                                  placeholder="Enter your message here..."></textarea>
                        <small class="form-text text-muted">Message is optional if you're sending an image</small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sendToAllUsers">
                            <label class="form-check-label" for="sendToAllUsers">
                                Send to all users (broadcast)
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-paper-plane me-2"></i>Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Security Settings -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2 text-primary"></i>Security Settings</h5>
            </div>
            <div class="card-body">
                <form id="securitySettingsForm">
                    <div class="mb-3">
                        <label class="form-label">Change Admin Password</label>
                        <input type="password" class="form-control" name="new_password" placeholder="New Password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password">
                    </div>
                    <button type="submit" class="btn btn-gradient">Update Password</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Data Management -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-database me-2 text-primary"></i>Data Management</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary" onclick="backupData()">
                        <i class="fas fa-download me-1"></i>Backup All Data
                    </button>
                    <button class="btn btn-outline-warning" onclick="optimizeDatabase()">
                        <i class="fas fa-broom me-1"></i>Optimize Database
                    </button>
                    <button class="btn btn-outline-danger" onclick="clearCache()">
                        <i class="fas fa-trash me-1"></i>Clear Cache
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cookie Management
function loadCookies() {
    if (window.showLoading) {
        window.showLoading('Loading cookies...');
    }
    fetch('../api/admin_api.php?action=get_cookies')
        .then(response => response.json())
        .then(data => {
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (data.success) {
                document.getElementById('cookiesEditor').value = JSON.stringify(data.data, null, 2);
                if (window.showToast) {
                    window.showToast('success', 'Success', 'Cookies loaded successfully');
                }
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to load cookies');
                } else {
                    alert('Error: ' + (data.message || 'Failed to load cookies'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                    window.showToast('error', 'Error', 'Failed to load cookies');
            } else {
                alert('Error loading cookies');
            }
        });
}

function saveCookies() {
    const cookiesText = document.getElementById('cookiesEditor').value;
    
    try {
        // Validate JSON
        JSON.parse(cookiesText);
    } catch (e) {
        if (window.showToast) {
            window.showToast('error', 'Invalid JSON', 'Please check your JSON format: ' + e.message);
        } else {
            alert('Invalid JSON: ' + e.message);
        }
        return;
    }
    
    if (window.showLoading) {
        window.showLoading('Saving cookies...');
    }
    fetch('../api/admin_api.php?action=save_cookies', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ cookies: JSON.parse(cookiesText) })
    })
        .then(response => response.json())
        .then(data => {
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (data.success) {
                if (window.showToast) {
                    window.showToast('success', 'Success', 'Cookies saved successfully!');
                } else {
                    alert('Cookies saved successfully!');
                }
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to save cookies');
                } else {
                    alert('Error: ' + (data.message || 'Failed to save cookies'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                window.showToast('error', 'Error', 'Failed to save cookies');
            } else {
                alert('Error saving cookies');
            }
        });
}

function validateCookies() {
    const cookiesText = document.getElementById('cookiesEditor').value;
    try {
        JSON.parse(cookiesText);
        if (window.showToast) {
            window.showToast('success', 'Valid JSON', 'Cookies JSON is valid!');
        } else {
            alert('Valid JSON!');
        }
    } catch (e) {
        if (window.showToast) {
            window.showToast('error', 'Invalid JSON', 'JSON Error: ' + e.message);
        } else {
            alert('Invalid JSON: ' + e.message);
        }
    }
}

// Bot File Editor
function loadBotFile() {
    if (window.showLoading) {
        window.showLoading('Loading bot file...');
    }
    fetch('../api/admin_api.php?action=get_bot_file')
        .then(response => response.json())
        .then(data => {
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (data.success) {
                document.getElementById('botFileEditor').value = data.data.content;
                if (window.showToast) {
                    window.showToast('success', 'Success', 'Bot file loaded successfully');
                }
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to load bot file');
                } else {
                    alert('Error: ' + (data.message || 'Failed to load bot file'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                window.showToast('error', 'Error', 'Failed to load bot file');
            } else {
                alert('Error loading bot file');
            }
        });
}

function saveBotFile() {
    if (!confirm('Are you sure you want to save changes to the bot file? This may affect bot functionality.')) {
        return;
    }
    
    const content = document.getElementById('botFileEditor').value;
    
    if (window.showLoading) {
        window.showLoading('Saving bot file...');
    }
    fetch('../api/admin_api.php?action=save_bot_file', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ content: content })
    })
        .then(response => response.json())
        .then(data => {
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (data.success) {
                if (window.showToast) {
                    showToast('success', 'Success', 'Bot file saved successfully!');
                } else {
                    alert('Bot file saved successfully!');
                }
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to save bot file');
                } else {
                    alert('Error: ' + (data.message || 'Failed to save bot file'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                window.showToast('error', 'Error', 'Failed to save bot file');
            } else {
                alert('Error saving bot file');
            }
        });
}

function backupBotFile() {
    if (window.showLoading) {
        window.showLoading('Creating backup...');
    }
    fetch('../api/admin_api.php?action=backup_bot_file', {
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
                    window.showToast('success', 'Success', 'Backup created: ' + (data.filename || 'backup file'));
                } else {
                    alert('Backup created: ' + (data.filename || 'backup file'));
                }
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to create backup');
                } else {
                    alert('Error: ' + (data.message || 'Failed to create backup'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                window.showToast('error', 'Error', 'Failed to create backup');
            } else {
                alert('Error creating backup');
            }
        });
}

// Send Message to Users
function loadUsersForMessage() {
    fetch('../api/admin_api.php?action=get_users')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const select = document.getElementById('messageUserId');
                select.innerHTML = '<option value="">-- Select User --</option>';
                data.data.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.telegram_id || '';
                    option.textContent = (user.name || 'User') + ' (' + (user.telegram_id || 'N/A') + ')';
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading users:', error));
}

document.getElementById('messageUserId').addEventListener('change', function() {
    if (this.value) {
        document.getElementById('messageTelegramId').value = this.value;
    }
});

// Image preview functionality
const messageImageInput = document.getElementById('messageImage');
if (messageImageInput) {
    messageImageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (!file.type.startsWith('image/')) {
                if (window.showToast) {
                    window.showToast('error', 'Invalid File', 'Please select an image file');
                } else {
                    alert('Please select an image file');
                }
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewImg = document.getElementById('previewImg');
                const imagePreview = document.getElementById('imagePreview');
                if (previewImg) previewImg.src = e.target.result;
                if (imagePreview) imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
}

// Clear image preview function
function clearImagePreview() {
    const messageImageInput = document.getElementById('messageImage');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (messageImageInput) messageImageInput.value = '';
    if (imagePreview) imagePreview.style.display = 'none';
    if (previewImg) previewImg.src = '';
}

document.getElementById('sendMessageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const sendToAll = document.getElementById('sendToAllUsers').checked;
    const telegramId = document.getElementById('messageTelegramId').value.trim();
    const message = document.getElementById('messageText').value.trim();
    const imageFile = document.getElementById('messageImage').files[0];
    
    if (!sendToAll && !telegramId) {
        if (window.showToast) {
            showToast('warning', 'Validation Error', 'Please select a user or enter Telegram ID');
        } else {
            alert('Please select a user or enter Telegram ID');
        }
        return;
    }
    
    if (!message && !imageFile) {
        if (window.showToast) {
            showToast('warning', 'Validation Error', 'Please enter a message or upload an image');
        } else {
            alert('Please enter a message or upload an image');
        }
        return;
    }
    
    if (sendToAll && !confirm('Are you sure you want to send this message to ALL users?')) {
        return;
    }
    
    if (window.showLoading) {
        window.showLoading(sendToAll ? 'Sending message to all users...' : 'Sending message...');
    }
    
    // Use FormData for file upload
    const formData = new FormData();
    formData.append('telegram_id', sendToAll ? '' : (telegramId || ''));
    formData.append('message', message || '');
    formData.append('send_to_all', sendToAll ? '1' : '0');
    if (imageFile) {
        formData.append('image', imageFile);
    }
    
    // Debug: Log what's being sent
    console.log('Sending message:', {
        telegram_id: sendToAll ? '(all)' : telegramId,
        message: message,
        send_to_all: sendToAll,
        has_image: !!imageFile
    });
    
    fetch('../api/admin_api.php?action=send_message', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (data.success) {
                if (window.showToast) {
                    window.showToast('success', 'Success', data.message || 'Message sent successfully!');
                } else {
                    alert(data.message || 'Message sent successfully!');
                }
                document.getElementById('sendMessageForm').reset();
                document.getElementById('messageUserId').innerHTML = '<option value="">-- Select User --</option>';
                clearImagePreview();
            } else {
                if (window.showToast) {
                    window.showToast('error', 'Error', data.message || 'Failed to send message');
                } else {
                    alert('Error: ' + (data.message || 'Failed to send message'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.hideLoading) {
                window.hideLoading();
            }
            if (window.showToast) {
                window.showToast('error', 'Error', 'Failed to send message. Please try again.');
            } else {
                alert('Error sending message');
            }
        });
});

// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-load cookies immediately (silently, without toast notification)
    const cookiesEditor = document.getElementById('cookiesEditor');
    if (cookiesEditor) {
        fetch('../api/admin_api.php?action=get_cookies')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Always set the value, even if it's empty array
                    if (data.data && Array.isArray(data.data) && data.data.length > 0) {
                        cookiesEditor.value = JSON.stringify(data.data, null, 2);
                    } else {
                        // If empty, set to empty array format
                        cookiesEditor.value = '[]';
                    }
                } else {
                    // Only log error if it's not about file not found
                    if (data.message && !data.message.includes('not found')) {
                        console.warn('Failed to auto-load cookies:', data.message || 'Unknown error');
                    }
                }
            })
            .catch(error => {
                // Silently handle errors - don't show in console
                console.debug('Error auto-loading cookies:', error);
            });
    }
    
    loadBotFile();
    loadUsersForMessage();
});
</script>
