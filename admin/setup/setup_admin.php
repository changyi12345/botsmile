<?php
// Setup script for SmileOne Admin System
// Run this script once to initialize the admin system

echo "<!DOCTYPE html>\n";
echo "<html lang=\"en\">\n";
echo "<head>\n";
echo "    <meta charset=\"UTF-8\">\n";
echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
echo "    <title>SmileOne Admin Setup</title>\n";
echo "    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n";
echo "</head>\n";
echo "<body>\n";
echo "<div class=\"container mt-5\">\n";

$setup_complete = false;

if ($_POST && isset($_POST['setup_admin'])) {
    $admin_username = $_POST['admin_username'] ?? 'admin';
    $admin_password = $_POST['admin_password'] ?? 'admin123';
    
    try {
        // Create default admin user
        $admin_data = [
            [
                'telegram_id' => 7829183790,
                'username' => $admin_username,
                'password' => password_hash($admin_password, PASSWORD_DEFAULT),
                'role' => 'super_admin',
                'created_at' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ]
        ];
        
        file_put_contents('../assets/admins.json', json_encode($admin_data, JSON_PRETTY_PRINT));
        
        // Create sample users if users.json doesn't exist
        if (!file_exists('../users.json')) {
            $sample_users = [
                [
                    'telegram_id' => 123456789,
                    'username' => 'demo_user',
                    'first_name' => 'Demo',
                    'balance' => 100.00,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            file_put_contents('../users.json', json_encode($sample_users, JSON_PRETTY_PRINT));
        }
        
        // Create sample transactions if transactions.json doesn't exist
        if (!file_exists('../transactions.json')) {
            $sample_transactions = [
                [
                    'id' => 'TXN001',
                    'user_id' => 123456789,
                    'amount' => 50.00,
                    'type' => 'purchase',
                    'status' => 'completed',
                    'description' => 'Sample transaction',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            file_put_contents('../transactions.json', json_encode($sample_transactions, JSON_PRETTY_PRINT));
        }
        
        // Create sample products if products.json doesn't exist
        if (!file_exists('../products.json')) {
            $sample_products = [
                [
                    'id' => 'PROD001',
                    'name' => 'Diamonds 100',
                    'price' => 10.00,
                    'category' => 'game_currency',
                    'description' => '100 diamonds for Mobile Legends',
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            file_put_contents('../products.json', json_encode($sample_products, JSON_PRETTY_PRINT));
        }
        
        // Create sample commissions if commissions.json doesn't exist
        if (!file_exists('../commissions.json')) {
            $sample_commissions = [
                [
                    'id' => 'COMM001',
                    'user_id' => 123456789,
                    'amount' => 5.00,
                    'percentage' => 10,
                    'transaction_id' => 'TXN001',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            file_put_contents('../commissions.json', json_encode($sample_commissions, JSON_PRETTY_PRINT));
        }
        
        $setup_complete = true;
        
        echo "<div class='alert alert-success'>\n";
        echo "    <h4><i class=\"fas fa-check-circle\"></i> Setup Complete!</h4>\n";
        echo "    <p>Your SmileOne admin system has been successfully initialized.</p>\n";
        echo "    <hr>\n";
        echo "    <h5>Admin Credentials:</h5>\n";
        echo "    <ul>\n";
        echo "        <li><strong>Username:</strong> {$admin_username}</li>\n";
        echo "        <li><strong>Password:</strong> {$admin_password}</li>\n";
        echo "    </ul>\n";
        echo "    <div class='mt-3'>\n";
        echo "        <a href='../auth/admin_login.php' class='btn btn-primary'>Go to Admin Login</a>\n";
        echo "        <button onclick='window.print()' class='btn btn-outline-secondary ms-2'>Print Credentials</button>\n";
        echo "    </div>\n";
        echo "</div>\n";
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>\n";
        echo "    <h4><i class=\"fas fa-exclamation-triangle\"></i> Setup Failed!</h4>\n";
        echo "    <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        echo "</div>\n";
    }
}

if (!$setup_complete) {
    echo "<div class='row justify-content-center'>\n";
    echo "    <div class='col-md-8'>\n";
    echo "        <div class='card'>\n";
    echo "            <div class='card-header bg-primary text-white'>\n";
    echo "                <h4 class='mb-0'><i class=\"fas fa-cog\"></i> SmileOne Admin Setup</h4>\n";
    echo "            </div>\n";
    echo "            <div class='card-body'>\n";
    echo "                <p class='text-muted'>This setup will initialize your SmileOne admin system with default settings and sample data.</p>\n";
    echo "                \n";
    echo "                <form method='POST'>\n";
    echo "                    <div class='mb-3'>\n";
    echo "                        <label for='admin_username' class='form-label'>Admin Username</label>\n";
    echo "                        <input type='text' class='form-control' id='admin_username' name='admin_username' value='admin' required>\n";
    echo "                        <div class='form-text'>The username for your admin account.</div>\n";
    echo "                    </div>\n";
    echo "                    \n";
    echo "                    <div class='mb-3'>\n";
    echo "                        <label for='admin_password' class='form-label'>Admin Password</label>\n";
    echo "                        <input type='password' class='form-control' id='admin_password' name='admin_password' value='admin123' required>\n";
    echo "                        <div class='form-text'>Choose a strong password for your admin account.</div>\n";
    echo "                    </div>\n";
    echo "                    \n";
    echo "                    <div class='alert alert-warning'>\n";
    echo "                        <h6><i class=\"fas fa-exclamation-triangle\"></i> Important Notes:</h6>\n";
    echo "                        <ul class='mb-0'>\n";
    echo "                            <li>Change the default password after first login</li>\n";
    echo "                            <li>This will create sample data for testing</li>\n";
    echo "                            <li>Make sure your JSON files are writable</li>\n";
    echo "                            <li>Delete this setup file after completion</li>\n";
    echo "                        </ul>\n";
    echo "                    </div>\n";
    echo "                    \n";
    echo "                    <button type='submit' name='setup_admin' class='btn btn-primary'>\n";
    echo "                        <i class=\"fas fa-play\"></i> Initialize Admin System\n";
    echo "                    </button>\n";
    echo "                </form>\n";
    echo "            </div>\n";
    echo "        </div>\n";
    echo "    </div>\n";
    echo "</div>\n";
}

echo "</div>\n";
echo "</body>\n";
echo "</html>\n";
?>