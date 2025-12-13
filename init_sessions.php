<?php
/**
 * Emergency WiFi Session Database Initializer
 * Creates wifi_sessions.json if missing
 * Usage: http://10.6.6.1/init_sessions.php
 */

header('Content-Type: text/html; charset=utf-8');

$sessionsFile = __DIR__ . '/wifi_sessions.json';
$success = false;
$error = null;

// ----------------------------
// File creation logic
// ----------------------------
if (!file_exists($sessionsFile)) {

    // Attempt to create an empty JSON array
    $result = @file_put_contents($sessionsFile, json_encode([], JSON_PRETTY_PRINT));

    if ($result !== false) {
        // Set file permission
        @chmod($sessionsFile, 0664);
        $success = true;
    } else {
        $error = "Failed to create wifi_sessions.json. Directory may not be writable.";
    }

} else {
    // File already exists
    $success = true;
    $error = "File already exists. No action needed.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialize WiFi Sessions File</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 650px;
            margin: 50px auto;
            padding: 20px;
            background: #f0fdf4;
        }

        .box {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .success {
            color: #16a34a;
            font-size: 26px;
            margin-bottom: 10px;
        }

        .error {
            color: #dc2626;
            font-size: 22px;
        }

        .info {
            color: #4b5563;
            margin-top: 15px;
            line-height: 1.5;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 22px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
        }

        code {
            background: #f3f4f6;
            padding: 3px 6px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="box">

        <?php if ($success): ?>
            <div class="success">✓ Success!</div>
            <p>
                The file <code>wifi_sessions.json</code> is now ready.
            </p>

            <p class="info">
                Location:  
                <code><?php echo htmlspecialchars($sessionsFile); ?></code>
                <br>
                The WiFi reward system can now log and track user sessions.
            </p>

            <a href="dashboard.php">Open Dashboard</a>

        <?php else: ?>
            <div class="error">✗ Failed</div>
            <p><?php echo htmlspecialchars($error); ?></p>

            <p class="info">
                <strong>Auto-fix:</strong><br>
                The file will also be automatically created the moment a user drops a bottle and the system grants WiFi access via  
                <code>hardware_control.php</code>.
            </p>

            <a href="dashboard.php">Return to Dashboard</a>
        <?php endif; ?>

    </div>
</body>
</html>
