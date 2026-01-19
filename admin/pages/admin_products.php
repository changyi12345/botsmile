<?php
// Products Management Page
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1"><i class="fas fa-box me-2 text-primary"></i>Game Products Management</h2>
            <p class="text-muted mb-0">Manage packages for all games (Brazil, Philippines, Pubg, HoK)</p>
        </div>
        <div>
            <button class="btn btn-outline-primary me-2" onclick="syncProducts()">
                <i class="fas fa-sync-alt me-1"></i>Sync from SmileOne
            </button>
            <button class="btn btn-gradient" onclick="addProduct()">
                <i class="fas fa-plus me-1"></i>Add Package
            </button>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="mb-1">🇧🇷 Brazil</h6>
                <h3 class="mb-0"><?php echo count($br_products); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="mb-1">🇵🇭 Philippines</h6>
                <h3 class="mb-0"><?php echo count($php_products); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="mb-1">🎮 Pubg BR</h6>
                <h3 class="mb-0"><?php echo count($pubg_br_products); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white" style="opacity: 0.85;">
            <div class="card-body">
                <h6 class="mb-1">🎮 Pubg PHP</h6>
                <h3 class="mb-0"><?php echo count($pubg_php_products); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6 class="mb-1">⚔️ HoK BR</h6>
                <h3 class="mb-0"><?php echo count($hok_br_products); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white" style="opacity: 0.85;">
            <div class="card-body">
                <h6 class="mb-1">⚔️ HoK PHP</h6>
                <h3 class="mb-0"><?php echo count($hok_php_products); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-purple text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body">
                <h6 class="mb-1">♟️ MagicChessGoGo BR</h6>
                <h3 class="mb-0"><?php echo count($magicchessgogo_br_products); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-purple text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); opacity: 0.85;">
            <div class="card-body">
                <h6 class="mb-1">♟️ MagicChessGoGo PHP</h6>
                <h3 class="mb-0"><?php echo count($magicchessgogo_php_products); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3"><i class="fas fa-filter me-2"></i>Filter by Game</h5>
        <div class="btn-group w-100" role="group" aria-label="Game Filter">
            <button type="button" class="btn btn-primary active filter-btn" data-filter="all" onclick="filterProducts('all')">
                <i class="fas fa-layer-group me-1"></i>All
            </button>
            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="mlbb" onclick="filterProducts('mlbb')">
                <i class="fas fa-mobile-alt me-1"></i>MLBB
            </button>
            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="pubg" onclick="filterProducts('pubg')">
                <i class="fas fa-gamepad me-1"></i>PUBG
            </button>
            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="hok" onclick="filterProducts('hok')">
                <i class="fas fa-khanda me-1"></i>HoK
            </button>
            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="magicchess" onclick="filterProducts('magicchess')">
                <i class="fas fa-chess me-1"></i>Magic Chess
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
                    <th>Name</th>
                    <th>Original Price</th>
                    <th>MMK Price</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $index => $product): 
                    $product_id = $product['id'] ?? 'PROD' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                    $country = strtolower($product['country'] ?? 'br');
                    
                    // Determine product type and display name based on country
                    if ($country === 'pubg_br' || $country === 'pubg') {
                        $product_name = $product['name'] . ' UC';
                        $flag = '🎮';
                        $region_name = ($country === 'pubg_br') ? 'Pubg (Brazil)' : 'Pubg';
                        $badge_class = 'bg-info';
                    } elseif ($country === 'pubg_php') {
                        $product_name = $product['name'] . ' UC';
                        $flag = '🎮';
                        $region_name = 'Pubg (Philippines)';
                        $badge_class = 'bg-info';
                    } elseif ($country === 'hok_br' || $country === 'hok') {
                        $product_name = $product['name'] . ' Diamonds';
                        $flag = '⚔️';
                        $region_name = ($country === 'hok_br') ? 'HoK (Brazil)' : 'HoK';
                        $badge_class = 'bg-warning';
                    } elseif ($country === 'hok_php') {
                        $product_name = $product['name'] . ' Diamonds';
                        $flag = '⚔️';
                        $region_name = 'HoK (Philippines)';
                        $badge_class = 'bg-warning';
                    } elseif ($country === 'magicchessgogo_br' || $country === 'magicchessgogo') {
                        $product_name = $product['name'] . ' Diamonds';
                        $flag = '♟️';
                        $region_name = ($country === 'magicchessgogo_br') ? 'MagicChessGoGo (Brazil)' : 'MagicChessGoGo';
                        $badge_class = 'bg-purple';
                    } elseif ($country === 'magicchessgogo_php') {
                        $product_name = $product['name'] . ' Diamonds';
                        $flag = '♟️';
                        $region_name = 'MagicChessGoGo (Philippines)';
                        $badge_class = 'bg-purple';
                    } elseif ($country === 'php') {
                        $product_name = $product['name'] . ' Diamonds';
                        $flag = '🇵🇭';
                        $region_name = 'Philippines';
                        $badge_class = 'bg-info';
                    } else {
                        $product_name = $product['name'] . ' Diamonds';
                        $flag = '🇧🇷';
                        $region_name = 'Brazil';
                        $badge_class = 'bg-primary';
                    }
                    
                    $product_category = $flag . ' ' . $region_name;
                    $product_status = 'active';

                    // Determine filter category
                    $filter_category = 'mlbb'; // Default
                    if (strpos($country, 'pubg') !== false) {
                        $filter_category = 'pubg';
                    } elseif (strpos($country, 'hok') !== false) {
                        $filter_category = 'hok';
                    } elseif (strpos($country, 'magicchess') !== false) {
                        $filter_category = 'magicchess';
                    }
                ?>
                <tr class="product-row" data-category="<?php echo $filter_category; ?>">
                    <td><?php echo htmlspecialchars($product_id); ?></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="me-2 fs-5"><?php echo $flag; ?></span>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($product_name); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($region_name); ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            <span class="fw-bold text-success">$<?php echo number_format($product['price'] ?? 0, 2); ?></span>
                            <small class="text-muted"><?php echo getCurrencySymbol($country); ?></small>
                        </div>
                    </td>
                    <td>
                        <?php 
                        // Use stored MMK price if available, otherwise calculate
                        $mmk_price = isset($product['mmk_price']) && $product['mmk_price'] !== null && $product['mmk_price'] !== '' 
                            ? floatval($product['mmk_price']) 
                            : convertToMMK($product['price'] ?? 0, $country);
                        ?>
                        <span class="fw-bold text-primary"><?php echo formatMMK($mmk_price); ?></span>
                    </td>
                    <td>
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($product_category); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-success">
                            <?php echo htmlspecialchars($product_status); ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick="viewProduct('<?php echo $index; ?>')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editProduct('<?php echo $product_id; ?>')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct('<?php echo $product_id; ?>')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Product Details Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel"><i class="fas fa-gem me-2"></i>Package Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="productModalBody">
                <!-- Product details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editProductFromModal()">Edit Package</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit/Add Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Diamond Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editProductForm">
                    <input type="hidden" id="editProductIndex" value="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-gem me-2"></i>Amount (Diamonds/UC)</label>
                                <input type="number" class="form-control" id="editProductName" required placeholder="e.g., 100">
                                <small class="form-text text-muted">Enter diamonds for Brazil/Philippines/HoK, or UC for Pubg</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-dollar-sign me-2"></i>Original Price</label>
                                <input type="number" class="form-control" id="editProductPrice" step="0.01" required placeholder="0.00">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-flag me-2"></i>Game/Country</label>
                                <select class="form-select" id="editProductCountry" onchange="updateMMKPrice()">
                                    <option value="br">🇧🇷 Brazil (BRL)</option>
                                    <option value="php">🇵🇭 Philippines (PHP)</option>
                                    <option value="pubg_br">🎮 Pubg UC (Brazil - BRL)</option>
                                    <option value="pubg_php">🎮 Pubg UC (Philippines - PHP)</option>
                                    <option value="hok_br">⚔️ HoK (Brazil - BRL)</option>
                                    <option value="hok_php">⚔️ HoK (Philippines - PHP)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-money-bill me-2"></i>MMK Price</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="editProductMMKPrice" step="1" placeholder="Enter MMK price">
                                    <button class="btn btn-outline-secondary" type="button" onclick="updateMMKPrice()" title="Auto-calculate from Original Price">
                                        <i class="fas fa-calculator"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Enter MMK price manually or click calculator to auto-calculate</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-list me-2"></i>SmileOne Product IDs</label>
                                <input type="text" class="form-control" id="editProductIds" placeholder="e.g., 22590,22591 (comma separated)">
                                <small class="form-text text-muted">Enter product IDs separated by commas</small>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
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
// Pass products data to JavaScript (use window object to avoid conflicts)
window.productsData = <?php echo json_encode($products); ?>;
window.exchangeRates = <?php echo json_encode($exchange_rates); ?>;

// Edit product from modal
window.editProductFromModal = function() {
    if (typeof window.currentProductIndex !== 'undefined' && window.currentProductIndex !== null) {
        const product = window.productsData[window.currentProductIndex];
        const productId = product.id || 'PROD' + String(window.currentProductIndex + 1).padStart(3, '0');
        
        // Close view modal first
        if (window.productModalInstance) {
            window.productModalInstance.hide();
        }
        
        // Wait for modal to close, then open edit modal
        setTimeout(function() {
            if (typeof window.editProduct === 'function') {
                window.editProduct(productId);
            }
        }, 300);
    }
};

// Filter Products Function
window.filterProducts = function(category) {
    // Update active button state
    document.querySelectorAll('.filter-btn').forEach(btn => {
        if (btn.dataset.filter === category) {
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-primary', 'active');
        } else {
            btn.classList.remove('btn-primary', 'active');
            btn.classList.add('btn-outline-primary');
        }
    });

    // Filter table rows
    const rows = document.querySelectorAll('.product-row');
    rows.forEach(row => {
        if (category === 'all' || row.dataset.category === category) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
};
</script>
