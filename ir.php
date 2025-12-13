<?php
// IR Sensor Detection Endpoint with Error Logging
// Returns JSON with detection status + generates and stores verification tokens

header('Content-Type: application/json');

try {
    $scriptPath = __DIR__ . '/read_ir_sensor.py';

    if (!file_exists($scriptPath)) {
        throw new Exception("read_ir_sensor.py not found");
    }

    // Run Python with sudo for GPIO access
    $command = "sudo python3 {$scriptPath} 2>&1";
    $output = shell_exec($command);
    $output = trim($output);

    error_log("[IR] Command executed: $command");
    error_log("[IR] Raw output: $output");

    // Parse JSON output
    $data = json_decode($output, true);

    if ($data === null) {
        throw new Exception("Invalid JSON response: " . $output);
    }

    if (isset($data["error"])) {
        throw new Exception("Sensor error: " . $data["error"]);
    }

    // Only proceed if bottle detected
    if (isset($data["detected"]) && $data["detected"] === true) {
        
        // Create a verification token if Python did not create one
        if (empty($data["verification_token"])) {
            $data["verification_token"] = bin2hex(random_bytes(16));
        }

        // Store token to bottle_tokens.json
        $tokenFile = __DIR__ . '/bottle_tokens.json';
        $tokens = [];

        if (file_exists($tokenFile)) {
            $tokens = json_decode(file_get_contents($tokenFile), true) ?: [];
        }

        $token = $data["verification_token"];
        $expiresAt = time() + 10; // Token valid for 10 seconds only

        $tokens[$token] = [
            "created_at" => time(),
            "expires_at" => $expiresAt,
            "used" => false
        ];

        file_put_contents($tokenFile, json_encode($tokens, JSON_PRETTY_PRINT));

        $data["token_saved"] = true;
        $data["token_expires_at"] = $expiresAt;
    }

    echo json_encode($data);
    exit();

} catch (Exception $e) {
    error_log("[IR ERROR] " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "detected" => false,
        "error" => $e->getMessage()
    ]);
    exit();
}

