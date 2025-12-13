<?php
/**
 * hardware_control.php
 * Controls buzzer, relay, and IR sensor for BottleWifi
 * Handles bottle detection → WiFi reward → session logging
 */

header('Content-Type: application/json');

// ---------- FILE PATHS ----------
$sessionFile = __DIR__ . '/wifi_sessions.json';
$recyclingFile = __DIR__ . '/recycling_data.json';
$settingsFile = __DIR__ . '/wifi_settings.json';

// ---------- AUTOCREATE FILES IF MISSING ----------
if (!file_exists($sessionFile)) {
    file_put_contents($sessionFile, "[]");
    @chmod($sessionFile, 0664);
}
if (!file_exists($recyclingFile)) {
    file_put_contents($recyclingFile, "[]");
}
if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode([
        "wifi_time" => 3600,
        "ssid" => "BottleWifi",
        "security_mode" => "WPA3-Personal",
        "channel" => "Auto",
        "firewall_enabled" => true
    ], JSON_PRETTY_PRINT));
}

// ---------- LOAD SETTINGS ----------
$settings = json_decode(file_get_contents($settingsFile), true);
$wifiTime = isset($settings['wifi_time']) ? intval($settings['wifi_time']) : 3600;

// ---------- GET ACTION ----------
$action = $_GET['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(["error" => "No action provided"]);
    exit;
}

// ---------- HELPER: RUN PYTHON ----------
function run_python($script, $args = "")
{
    $path = __DIR__ . "/$script";
    if (!file_exists($path)) {
        return ["error" => "$script not found"];
    }

    $cmd = "sudo python3 $path $args 2>&1";
    $output = shell_exec($cmd);
    $trimmed = trim($output);

    error_log("CMD: $cmd");
    error_log("PYTHON OUTPUT: $trimmed");

    $json = json_decode($trimmed, true);
    return $json ?: ["error" => "Invalid JSON: $trimmed"];
}

// ---------- LOAD EXISTING DATA ----------
$sessions = json_decode(file_get_contents($sessionFile), true) ?: [];
$recycling = json_decode(file_get_contents($recyclingFile), true) ?: [];

// ---------- CLIENT DEVICE INFO ----------
$clientIP  = $_SERVER['REMOTE_ADDR'] ?? "Unknown";
$clientMAC = shell_exec("arp -an | grep \"$clientIP\" | awk '{print $4}'");
$clientMAC = $clientMAC ? trim($clientMAC) : "Unknown";

// ---------- ACTION HANDLERS ----------

// 1️⃣ **CHECK IR SENSOR**
if ($action === "check_ir") {
    $result = run_python("read_ir_sensor.py");

    if (isset($result['error'])) {
        http_response_code(500);
        echo json_encode($result);
        exit;
    }

    echo json_encode($result);
    exit;
}

// 2️⃣ **ACTIVATE BUZZER**
if ($action === "buzzer") {
    $result = run_python("buzzer.py");

    echo json_encode($result);
    exit;
}

// 3️⃣ **OPEN RELAY (DOOR / TRASH FLAP)**
if ($action === "open") {
    $result = run_python("open_relay.py");

    echo json_encode($result);
    exit;
}

// 4️⃣ **BOTTLE DETECTED → ADD SESSION & LOG EVENT**
if ($action === "reward") {

    // Add bottle to recycling logs
    $entry = [
        "mac" => $clientMAC,
        "ip" => $clientIP,
        "timestamp" => date("Y-m-d H:i:s")
    ];
    $recycling[] = $entry;
    file_put_contents($recyclingFile, json_encode($recycling, JSON_PRETTY_PRINT));

    // Generate WiFi session
    $expiresAt = time() + $wifiTime;
    $session = [
        "mac" => $clientMAC,
        "ip" => $clientIP,
        "expires_at" => $expiresAt
    ];

    // Remove expired sessions
    $sessions = array_filter($sessions, function ($s) {
        return isset($s['expires_at']) && $s['expires_at'] > time();
    });

    // Insert new session
    $sessions[] = $session;
    file_put_contents($sessionFile, json_encode(array_values($sessions), JSON_PRETTY_PRINT));

    echo json_encode([
        "success" => true,
        "reward_time" => $wifiTime,
        "expires_at" => $expiresAt,
        "mac" => $clientMAC,
        "ip" => $clientIP
    ]);
    exit;
}

// 5️⃣ **UNKNOWN ACTION**
echo json_encode(["error" => "Unknown action"]);
exit;

?>
