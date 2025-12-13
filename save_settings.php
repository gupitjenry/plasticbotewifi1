<?php
header('Content-Type: application/json');

$settingsFile = __DIR__ . '/settings.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $settings = [
        'ssid' => filter_var($input['ssid'], FILTER_SANITIZE_STRING),
        'sessionTime' => filter_var($input['sessionTime'], FILTER_VALIDATE_INT),
        'dailyLimit' => filter_var($input['dailyLimit'], FILTER_VALIDATE_INT),
        'minutesPerBottle' => filter_var($input['minutesPerBottle'], FILTER_VALIDATE_INT),
        'channel' => filter_var($input['channel'], FILTER_SANITIZE_STRING),
        'securityMode' => 'open'
    ];
    
    // Save settings
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
} else {
    // Return current settings
    if (file_exists($settingsFile)) {
        echo file_get_contents($settingsFile);
    } else {
        echo json_encode([
            'ssid' => 'BottleWifi',
            'sessionTime' => 60,
            'dailyLimit' => 4,
            'minutesPerBottle' => 5,
            'channel' => 'auto',
            'securityMode' => 'open'
        ]);
    }
}
?>