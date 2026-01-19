<?php
/**
 * Quick Access Test
 * Test if files are accessible
 */

echo "=== File Access Test ===\n\n";

$files_to_test = [
    'index.php',
    'admin/admin_dashboard.php',
    'admin/admin_api.php',
    'admin/admin_config.php',
    'admin/admin_header.php',
    'admin/admin_footer.php',
    'admin/admin_login.php'
];

foreach ($files_to_test as $file) {
    if (file_exists($file)) {
        $syntax_check = shell_exec("php -l \"$file\" 2>&1");
        if (strpos($syntax_check, 'No syntax errors') !== false) {
            echo "✓ $file - OK\n";
        } else {
            echo "✗ $file - Syntax Error\n";
            echo "  $syntax_check\n";
        }
    } else {
        echo "✗ $file - Not Found\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "\nTo start server:\n";
echo "1. cd C:\\Users\\Lenovo\\Downloads\\smileone\n";
echo "2. php -S 127.0.0.1:8000\n";
echo "\nThen access: http://127.0.0.1:8000\n";
