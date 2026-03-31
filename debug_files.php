<?php
echo "<h1>MediVault Directory Diagnostic</h1>";
echo "Current Directory: " . getcwd() . "<br>";
echo "<h2>File Check:</h2>";

$paths = ['config', 'users', 'users/login.php', 'includes', 'htdocs'];

foreach ($paths as $path) {
    echo "Checking $path: " . (file_exists($path) ? "✅ EXISTS" : "❌ MISSING") . "<br>";
}

echo "<h2>Server Info:</h2>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>";
?>
