// Admin Panel JavaScript Functions

// Product Management Functions - Use window object for global scope
window.currentProductIndex = null;
window.productsData = window.productsData || [];

// Load products data
window.loadProductsData = function() {
    fetch('../api/admin_api.php?action=get_products')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.productsData = data.data;
            }
        })
        .catch(error => console.error('Error loading products:', error));
};

// Initialize productsData from inline script if available
if (typeof window.productsData === 'undefined') {
    window.productsData = [];
}

// Modal instances (reused to avoid accessibility issues)
window.productModalInstance = null;
window.editProductModalInstance = null;

// Initialize modals once
function initModals() {
    const productModalEl = document.getElementById('productModal');
    const editProductModalEl = document.getElementById('editProductModal');
    
    if (productModalEl && !productModalInstance) {
        productModalInstance = new bootstrap.Modal(productModalEl, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
    }
    
    if (editProductModalEl && !editProductModalInstance) {
        editProductModalInstance = new bootstrap.Modal(editProductModalEl, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
    }
}

// View Product Details
window.viewProduct = function(index) {
    window.currentProductIndex = index;
    
    const products = window.productsData || [];
    const product = products[index];
    
    if (!product) {
        alert('Product not found');
        return;
    }
    
    const country = product.country || 'br';
    let flag, regionName, currency, productType;
    if (country === 'pubg_br') {
        flag = '🎮';
        regionName = 'Pubg (Brazil)';
        currency = 'BRL';
        productType = 'UC';
    } else if (country === 'pubg_php') {
        flag = '🎮';
        regionName = 'Pubg (Philippines)';
        currency = 'PHP';
        productType = 'UC';
    } else if (country === 'hok_br') {
        flag = '⚔️';
        regionName = 'HoK (Brazil)';
        currency = 'BRL';
        productType = 'Diamonds';
    } else if (country === 'hok_php') {
        flag = '⚔️';
        regionName = 'HoK (Philippines)';
        currency = 'PHP';
        productType = 'Diamonds';
    } else if (country === 'php') {
        flag = '🇵🇭';
        regionName = 'Philippines';
        currency = 'PHP';
        productType = 'Diamonds';
    } else {
        flag = '🇧🇷';
        regionName = 'Brazil';
        currency = 'BRL';
        productType = 'Diamonds';
    }
    const pricePerUnit = product.name ? (parseFloat(product.price) / parseInt(product.name)).toFixed(3) : '0';
    
    const modalBody = document.getElementById('productModalBody');
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="d-flex align-items-center mb-3">
                    <span class="fs-2 me-3">${flag}</span>
                    <div>
                        <h5 class="mb-0">${product.name} ${productType}</h5>
                        <small class="text-muted">${regionName} ${country === 'pubg' || country === 'hok' ? 'Game' : 'Region'}</small>
                    </div>
                </div>
                
                <h6><i class="fas fa-dollar-sign me-2"></i>Price</h6>
                <p class="fw-bold text-success fs-5">${currency} ${parseFloat(product.price || 0).toFixed(2)}</p>
                
                <h6><i class="fas fa-tag me-2"></i>Price per ${productType}</h6>
                <p>${currency} ${pricePerUnit} per ${productType.toLowerCase()}</p>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-list me-2"></i>SmileOne Product IDs</h6>
                <div class="mb-3">
                    ${product.products && product.products.length > 0 
                        ? product.products.map(id => `<span class="badge bg-secondary me-1 mb-1">${id}</span>`).join('') 
                        : '<span class="text-muted">N/A</span>'}
                </div>
                
                <h6><i class="fas fa-calculator me-2"></i>Package Details</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-gem text-primary me-2"></i>${product.name} ${productType.toLowerCase()}</li>
                    <li><i class="fas fa-users text-success me-2"></i>${country === 'pubg' || country === 'hok' ? 'Available in ' : 'Popular in '}${regionName}</li>
                    <li><i class="fas fa-clock text-warning me-2"></i>Instant delivery</li>
                </ul>
                
                <span class="badge bg-success">✅ Active</span>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <h6><i class="fas fa-chart-line me-2"></i>Market Analysis</h6>
                <div class="bg-light p-3 rounded">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="fw-bold text-primary">${product.name}</div>
                            <small class="text-muted">${productType}</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-success">${currency} ${parseFloat(product.price || 0).toFixed(2)}</div>
                            <small class="text-muted">Price</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-warning">${regionName}</div>
                            <small class="text-muted">Market</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Initialize modal if not already done
    if (!window.productModalInstance) {
        initModals();
    }
    
    if (window.productModalInstance) {
        window.productModalInstance.show();
    }
}

// Edit Product
window.editProduct = function(productId) {
    const products = window.productsData || [];
    
    // Find product by ID or index
    let index = -1;
    if (typeof productId === 'string' && productId.startsWith('PROD')) {
        // Extract index from PROD001 format
        const num = parseInt(productId.replace('PROD', ''));
        index = num - 1;
    } else {
        // Try to find by ID
        index = products.findIndex(p => {
            const pId = p.id || 'PROD' + String(products.indexOf(p) + 1).padStart(3, '0');
            return pId === productId;
        });
    }
    
    if (index === -1 || !products[index]) {
        alert('Product not found');
        return;
    }
    
    const product = products[index];
    
    // Update modal title
    const modalTitle = document.querySelector('#editProductModal .modal-title');
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Diamond Package';
    }
    
    // Update save button
    const saveBtn = document.querySelector('#editProductForm button[type="submit"]');
    if (saveBtn) {
        saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Changes';
    }
    
    document.getElementById('editProductIndex').value = index;
    document.getElementById('editProductName').value = product.name || '';
    document.getElementById('editProductPrice').value = product.price || 0;
    document.getElementById('editProductCountry').value = product.country || 'br';
    document.getElementById('editProductIds').value = product.products ? product.products.join(', ') : '';
    
    // Load MMK price if exists, otherwise calculate
    if (product.mmk_price !== undefined && product.mmk_price !== null && product.mmk_price !== '') {
        // Remove " Ks" suffix if present and convert to number
        const mmkValue = String(product.mmk_price).replace(/[^0-9.]/g, '');
        document.getElementById('editProductMMKPrice').value = mmkValue;
    } else {
        updateMMKPrice();
    }
    
    // Initialize modal if not already done
    if (!window.editProductModalInstance) {
        initModals();
    }
    
    if (window.editProductModalInstance) {
        window.editProductModalInstance.show();
    }
}

// Update MMK Price based on currency (auto-calculate)
window.updateMMKPrice = function() {
    const price = parseFloat(document.getElementById('editProductPrice').value) || 0;
    const country = document.getElementById('editProductCountry').value;
    
    const exchangeRates = window.exchangeRates || {
        brl_to_mmk: 85.5,
        php_to_mmk: 38.2,
        usd_to_mmk: 2100.0
    };
    
    let mmkPrice = 0;
    // Handle pubg_br, pubg_php, hok_br, hok_php
    if (country.indexOf('pubg_br') === 0 || country.indexOf('hok_br') === 0) {
        mmkPrice = price * exchangeRates.brl_to_mmk;
    } else if (country.indexOf('pubg_php') === 0 || country.indexOf('hok_php') === 0) {
        mmkPrice = price * exchangeRates.php_to_mmk;
    } else {
        switch(country) {
            case 'br':
            case 'brl':
                mmkPrice = price * exchangeRates.brl_to_mmk;
                break;
            case 'php':
                mmkPrice = price * exchangeRates.php_to_mmk;
                break;
            default:
                mmkPrice = price * exchangeRates.usd_to_mmk;
        }
    }
    
    // Set as number only (no " Ks" suffix) so user can edit
    document.getElementById('editProductMMKPrice').value = Math.round(mmkPrice);
}

// Save Product Edit
window.saveProductEdit = function() {
    const index = parseInt(document.getElementById('editProductIndex').value);
    const name = document.getElementById('editProductName').value;
    const price = parseFloat(document.getElementById('editProductPrice').value);
    const country = document.getElementById('editProductCountry').value;
    const productIds = document.getElementById('editProductIds').value;
    const mmkPrice = parseFloat(document.getElementById('editProductMMKPrice').value) || null;
    
    if (!name || !price || price <= 0) {
        alert('Please fill in all required fields with valid values');
        return;
    }
    
    const productData = {
        name: name,
        price: price,
        country: country,
        products: productIds ? productIds.split(',').map(id => id.trim()).filter(id => id) : [],
        mmk_price: mmkPrice
    };
    
    fetch('../api/admin_api.php?action=edit_product', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            index: index,
            product: productData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (window.showToast) {
                showToast('success', 'Success!', 'Product updated successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                alert('Product updated successfully!');
                location.reload();
            }
        } else {
            if (window.showToast) {
                showToast('error', 'Error', data.message || 'Failed to update product');
            } else {
                alert('Error: ' + (data.message || 'Failed to update product'));
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.showToast) {
            showToast('error', 'Error', 'Failed to update product. Please try again.');
        } else {
            alert('Error updating product');
        }
    });
}

// Delete Product
function deleteProduct(productId) {
    if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        return;
    }
    
    // Find product index
    let index = -1;
    if (typeof productId === 'string' && productId.startsWith('PROD')) {
        const num = parseInt(productId.replace('PROD', ''));
        index = num - 1;
    } else {
        index = productsData.findIndex(p => (p.id || p.name) === productId);
    }
    
    if (index === -1) {
        alert('Product not found');
        return;
    }
    
    // Remove from array
    productsData.splice(index, 1);
    
    // Save to file via API
    fetch('../api/admin_api.php?action=delete_product', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            index: index
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (window.showToast) {
                showToast('success', 'Success!', 'Product deleted successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                alert('Product deleted successfully!');
                location.reload();
            }
        } else {
            if (window.showToast) {
                showToast('error', 'Error', data.message || 'Failed to delete product');
            } else {
                alert('Error: ' + (data.message || 'Failed to delete product'));
            }
            // Reload products data
            loadProductsData();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.showToast) {
            showToast('error', 'Error', 'Failed to delete product. Please try again.');
        } else {
            alert('Error deleting product');
        }
    });
}

// Add New Product
window.addProduct = function() {
    // Reset form
    document.getElementById('editProductIndex').value = '';
    document.getElementById('editProductName').value = '';
    document.getElementById('editProductPrice').value = '';
    document.getElementById('editProductCountry').value = 'br';
    document.getElementById('editProductIds').value = '';
    document.getElementById('editProductMMKPrice').value = '';
    
    // Change modal title
    const modalTitle = document.querySelector('#editProductModal .modal-title');
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fas fa-plus me-2"></i>Add New Package';
    }
    
    // Change save button text
    const saveBtn = document.querySelector('#editProductForm button[type="submit"]');
    if (saveBtn) {
        saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Add Package';
    }
    
    // Initialize modal if not already done
    if (!window.editProductModalInstance) {
        initModals();
    }
    
    if (window.editProductModalInstance) {
        window.editProductModalInstance.show();
    }
}

// Save New Product
function saveNewProduct() {
    const name = document.getElementById('editProductName').value;
    const price = parseFloat(document.getElementById('editProductPrice').value);
    const country = document.getElementById('editProductCountry').value;
    const productIds = document.getElementById('editProductIds').value;
    const mmkPrice = parseFloat(document.getElementById('editProductMMKPrice').value) || null;
    
    if (!name || !price || price <= 0) {
        alert('Please fill in all required fields with valid values');
        return;
    }
    
    const productData = {
        name: name,
        price: price,
        country: country,
        products: productIds ? productIds.split(',').map(id => id.trim()).filter(id => id) : [],
        mmk_price: mmkPrice
    };
    
    fetch('../api/admin_api.php?action=add_product', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product: productData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (window.showToast) {
                showToast('success', 'Success!', 'Product added successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                alert('Product added successfully!');
                location.reload();
            }
        } else {
            if (window.showToast) {
                showToast('error', 'Error', data.message || 'Failed to add product');
            } else {
                alert('Error: ' + (data.message || 'Failed to add product'));
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.showToast) {
            showToast('error', 'Error', 'Failed to add product. Please try again.');
        } else {
            alert('Error adding product');
        }
    });
}

// Sync Products from SmileOne
window.syncProducts = function() {
    if (!confirm('This will sync products from SmileOne website using cookies and user agent. This may take a few minutes. Continue?')) {
        return;
    }
    
    // Show loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Syncing...';
    btn.disabled = true;
    
    fetch('../api/admin_api.php?action=sync_products', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            // If response is not OK, try to get error message
            return response.text().then(text => {
                let errorData;
                try {
                    errorData = JSON.parse(text);
                } catch (e) {
                    errorData = { success: false, message: `Server error: ${response.status} ${response.statusText}` };
                }
                throw new Error(JSON.stringify(errorData));
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const message = data.count ? 
                `Products synced successfully! Found ${data.count} new products. Total: ${data.total || data.count} products.` :
                'Products synced successfully!';
            if (window.showToast) {
                showToast('success', 'Success!', message);
                setTimeout(() => location.reload(), 2000);
            } else {
                alert(message);
                location.reload();
            }
        } else {
            // Show detailed error message
            const errorMsg = data.message || data.error || 'Failed to sync products';
            if (window.showToast) {
                showToast('error', 'Error', errorMsg);
            } else {
                alert('Error: ' + errorMsg);
            }
        }
    })
    .catch(error => {
        console.error('Error syncing products:', error);
        
        // Try to parse error message
        let errorMessage = 'Failed to sync products. Please check cookies and try again.';
        try {
            const errorData = JSON.parse(error.message);
            if (errorData.message) {
                errorMessage = errorData.message;
            }
        } catch (e) {
            // Not JSON, use default message
        }
        
        if (window.showToast) {
            showToast('error', 'Error', errorMessage);
        } else {
            alert('Error: ' + errorMessage);
        }
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Handle form submission for edit/add
document.addEventListener('DOMContentLoaded', function() {
    loadProductsData();
    
    // Initialize modals
    initModals();
    
    // Handle edit product form
    const editProductForm = document.getElementById('editProductForm');
    if (editProductForm) {
        editProductForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const index = document.getElementById('editProductIndex').value;
            if (index === '') {
                saveNewProduct();
            } else {
                saveProductEdit();
            }
        });
    }
    
    // Don't auto-update MMK price on input change (allow manual editing)
    // User can click calculator button to auto-calculate if needed
});
