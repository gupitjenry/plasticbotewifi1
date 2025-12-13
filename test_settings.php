<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing Settings</h1>";

require_once __DIR__ . '/settings_handler.php';

echo "<h2>Reading Settings:</h2>";
$settings = getSettings();
echo "<pre>";
print_r($settings);
echo "</pre>";

echo "<h2>File Check:</h2>";
$file = __DIR__ . '/wifi_settings.json';
echo "File path: $file<br>";
echo "File exists: " . (file_exists($file) ? 'YES' : 'NO') . "<br>";
echo "File readable: " . (is_readable($file) ? 'YES' : 'NO') . "<br>";
echo "File writable: " . (is_writable($file) ? 'YES' : 'NO') . "<br>";

if (file_exists($file)) {
    echo "<h2>File Contents:</h2>";
    echo "<pre>";
    echo htmlspecialchars(file_get_contents($file));
    echo "</pre>";
}
?>
