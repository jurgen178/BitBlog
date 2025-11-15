<?php
// Enter your strong password here:
$password = "";

// Generate hash
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Your password: $password\n";
echo "Hash for Config.php: $hash\n";
echo "\nReplace in Config.php:\n";
echo "public const ADMIN_PASSWORD_HASH = '$hash';\n";
?>