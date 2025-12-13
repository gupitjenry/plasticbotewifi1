<?php
/**
 * One-Time Database Setup Script
 * 
 * HOW TO USE:
 * 1. Upload this file to /var/www/html/ on your Raspberry Pi (via GitHub or SD card)
 * 2. Open browser and go to: http://10.6.6.1/setup_database.php
 * 3. Click "Initialize Database"
 * 4. Delete this file after setup is complete for security
 */

$setupComplete = false;
$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initialize'])) {
    $baseDir = __DIR__;
    
    // Define database files
    $files = [
        'recycling_data.json' => '[]',
        'wifi_sessions.json' => '[]',
        'bottle_tokens.json' => '{}'
    ];
    
    foreach ($files as $filename => $defaultContent) {
        $filePath = $baseDir . '/' . $filename;
        
        // Check if file already exists
        if (file_exists($filePath)) {
            // Try to make it writable if it isn't
            if (!is_writable($filePath)) {
                @chmod($filePath, 0664);
                if (is_writable($filePath)) {
                    $success[] = "✓ {$filename} already exists (fixed permissions)";
                } else {
                    $errors[] = "⚠ {$filename} exists but cannot make writable (need sudo)";
                }
            } else {
                $success[] = "✓ {$filename} already exists and is writable";
            }
            continue;
        }
        
        // Try to create the file
        $result = @file_put_contents($filePath, $defaultContent);
        
        if ($result !== false) {
            // Try to set permissions
            @chmod($filePath, 0664);
            $success[] = "✓ Created {$filename} successfully";
        } else {
            // More detailed error
            $dir_writable = is_writable($baseDir);
            $errors[] = "✗ Failed to create {$filename} - Directory " . ($dir_writable ? "IS" : "NOT") . " writable";
        }
    }
    
    // Final check - verify all files are accessible
    foreach (array_keys($files) as $filename) {
        $filePath = $baseDir . '/' . $filename;
        if (file_exists($filePath) && !is_writable($filePath)) {
            $errors[] = "⚠ {$filename} is not writable by web server";
        }
    }
    
    $setupComplete = true;
}

// Check current status
$baseDir = __DIR__;
$fileStatus = [];
foreach (['recycling_data.json', 'wifi_sessions.json', 'bottle_tokens.json'] as $filename) {
    $filePath = $baseDir . '/' . $filename;
    $fileStatus[$filename] = [
        'exists' => file_exists($filePath),
        'writable' => file_exists($filePath) ? is_writable($filePath) : false,
        'size' => file_exists($filePath) ? filesize($filePath) : 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup — BottleWifi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f0fdf4;
            --card: #ffffff;
            --accent: #059669;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --border: rgba(5, 150, 105, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            min-height: 100vh;
            background-color: var(--bg);
            background-image: radial-gradient(circle at 10px 10px, rgba(147, 197, 153, 0.1) 2px, transparent 0);
            background-size: 24px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .container {
            background: var(--card);
            border-radius: 24px;
            padding: 2rem;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 4px solid var(--border);
        }

        h1 {
            color: var(--accent);
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .subtitle {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }

        .status-section {
            background: #f9fafb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .status-title {
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .file-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border: 1px solid var(--border);
        }

        .file-name {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: #374151;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .message-box {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .success-box {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }

        .error-box {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }

        .message-box ul {
            margin: 0.5rem 0 0 1.5rem;
        }

        .btn {
            width: 100%;
            background: linear-gradient(90deg, #34d399, #059669);
            color: white;
            border: 0;
            padding: 0.875rem 1.5rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 1rem;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
        }

        .warning-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            color: #92400e;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .warning-box strong {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .links {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .link:hover {
            text-decoration: underline;
        }

        code {
            background: #f3f4f6;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Setup</h1>
        <p class="subtitle">Initialize JSON database files for BottleWifi system</p>

        <?php if ($setupComplete): ?>
            <?php if (count($success) > 0): ?>
                <div class="message-box success-box">
                    <strong>✓ Setup Successful!</strong>
                    <ul>
                        <?php foreach ($success as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (count($errors) > 0): ?>
                <div class="message-box error-box">
                    <strong>✗ Errors Occurred:</strong>
                    <ul>
                        <?php foreach ($errors as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="status-section">
            <div class="status-title">📋 Current File Status</div>
            <?php foreach ($fileStatus as $filename => $status): ?>
                <div class="file-status">
                    <span class="file-name"><?php echo $filename; ?></span>
                    <?php if ($status['exists'] && $status['writable']): ?>
                        <span class="badge badge-success">✓ Ready (<?php echo $status['size']; ?> bytes)</span>
                    <?php elseif ($status['exists'] && !$status['writable']): ?>
                        <span class="badge badge-warning">⚠ Not Writable</span>
                    <?php else: ?>
                        <span class="badge badge-error">✗ Missing</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php 
        $allReady = true;
        foreach ($fileStatus as $status) {
            if (!$status['exists'] || !$status['writable']) {
                $allReady = false;
                break;
            }
        }
        ?>

        <?php if (!$allReady): ?>
            <form method="POST">
                <button type="submit" name="initialize" class="btn">
                    🚀 Initialize Database Files
                </button>
            </form>
        <?php else: ?>
            <button class="btn" disabled>
                ✓ Database Already Initialized
            </button>
        <?php endif; ?>

        <div class="warning-box">
            <strong>⚠️ Security Notice:</strong>
            After successful setup, delete this file for security:
            <br><br>
            SSH: <code>sudo rm /var/www/html/setup_database.php</code>
            <br>
            Or remove it manually via SD card
        </div>

        <div class="links">
            <a href="index.php" class="link">← Back to Portal</a>
            <a href="admin-login.php" class="link">Admin Dashboard →</a>
        </div>
    </div>
</body>
</html>
