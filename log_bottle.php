<?php
// Bottle recycling history tracking
// Logs each recycling event with timestamp and details

header('Content-Type: application/json');

$dataFile = 'recycling_data.json';

// Initialize empty array if file doesn't exist (dashboard expects simple array)
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_PRETTY_PRINT));
}

// Handle POST request to log a recycling event
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'log_bottle') {
        $recyclingData = json_decode(file_get_contents($dataFile), true) ?: [];
        
        // Get device MAC address
        $clientIP = $_SERVER['REMOTE_ADDR'];
        $mac = null;
        
        $arp = shell_exec("arp -n {$clientIP} 2>&1");
        if (preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $arp, $matches)) {
            $mac = strtoupper(str_replace('-', ':', $matches[0]));
        } else {
            $ipneigh = shell_exec("ip neigh show {$clientIP} 2>&1");
            if (preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $ipneigh, $matches)) {
                $mac = strtoupper(str_replace('-', ':', $matches[0]));
            }
        }
        
        // Add new bottle entry (format that dashboard expects)
        $recyclingData[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'mac' => $mac ?: 'Unknown',
            'ip' => $clientIP,
            'minutes_granted' => 5,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 100)
        ];
        
        file_put_contents($dataFile, json_encode($recyclingData, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'success' => true,
            'message' => 'Bottle logged successfully',
            'total_bottles' => count($recyclingData),
            'device_mac' => $mac
        ]);
        exit();
    }
}

// Handle GET request to retrieve statistics
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recyclingData = json_decode(file_get_contents($dataFile), true);
    
    echo json_encode([
        'total_bottles' => $recyclingData['total_bottles'],
        'total_minutes' => $recyclingData['total_minutes_distributed'],
        'session_count' => count($recyclingData['sessions']),
        'latest_sessions' => array_slice($recyclingData['sessions'], -10) // Last 10 sessions
    ]);
    exit();
}
?>
