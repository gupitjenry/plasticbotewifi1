<?php
header("Content-Type: application/json");

// ---------------------------
// Default WiFi Settings
// ---------------------------
$DEFAULT_SETTINGS = [
    'wifi_time' => 3600,                 // default 1 hour
    'ssid' => 'BottleWifi',
    'security_mode' => 'WPA3-Personal',
    'channel' => 'Auto',
    'firewall_enabled' => true
];

// Path to settings file
$SETTINGS_FILE = __DIR__ . '/wifi_settings.json';


// ---------------------------
// Read settings from file
// ---------------------------
function getSettings()
{
    global $SETTINGS_FILE, $DEFAULT_SETTINGS;

    if (!file_exists($SETTINGS_FILE)) {
        // Create file with defaults
        file_put_contents($SETTINGS_FILE, json_encode($DEFAULT_SETTINGS, JSON_PRETTY_PRINT));
        return $DEFAULT_SETTINGS;
    }

    $content = file_get_contents($SETTINGS_FILE);

    if ($content === false) {
        error_log("Error: Unable to read $SETTINGS_FILE");
        return $DEFAULT_SETTINGS;
    }

    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        error_log("Error: Invalid JSON inside $SETTINGS_FILE");
        return $DEFAULT_SETTINGS;
    }

    // Combine existing settings with defaults
    return array_merge($DEFAULT_SETTINGS, $decoded);
}


// ---------------------------
// Save updated settings
// ---------------------------
function saveSettings($settings)
{
    global $SETTINGS_FILE;

    $cleanSettings = json_encode($settings, JSON_PRETTY_PRINT);

    if ($cleanSettings === false) {
        error_log("Error: JSON encoding failed");
        return false;
    }

    $result = file_put_contents($SETTINGS_FILE, $cleanSettings);

    if ($result === false) {
        error_log("Error: Failed to write to $SETTINGS_FILE");
        return false;
    }

    return true;
}


// ---------------------------
// API Handler
// ---------------------------

// GET = Load settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'settings' => getSettings()
    ]);
    exit;
}

// POST = Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Invalid JSON input"]);
        exit;
    }

    $success = saveSettings($input);

    if (!$success) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Failed to save settings"]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => "WiFi settings saved successfully",
        'settings' => $input
    ]);
    exit;
}

// Invalid method
http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => "Invalid request method. Use GET or POST."
]);
?>
