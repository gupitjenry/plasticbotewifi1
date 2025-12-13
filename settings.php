<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

require_once __DIR__ . '/settings_handler.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = [
        'wifi_time' => intval($_POST['wifi_time']) * ($_POST['time_unit'] === 'hours' ? 3600 : 60),
        'ssid' => $_POST['ssid'],
        'security_mode' => $_POST['security_mode'],
        'channel' => $_POST['channel'],
        'firewall_enabled' => isset($_POST['firewall'])
    ];
    
    if (saveSettings($newSettings)) {
        $success = true;
        // Redirect to prevent form resubmission
        header('Location: settings.php?saved=1');
        exit;
    } else {
        $error = "Failed to save settings. Check file permissions.";
    }
}

// Check if redirected after save
if (isset($_GET['saved'])) {
    $success = true;
}

// Get current settings (always load from file)
$settings = getSettings();
$wifi_time_hours = floor($settings['wifi_time'] / 3600);
$wifi_time_minutes = floor(($settings['wifi_time'] % 3600) / 60);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — BottleWifi Admin</title>
    <style>
        :root {
            --bg: #f0fdf4;
            --card: #ffffff;
            --accent: #059669;
            --success: #10b981;
            --muted: #65a88a;
            --gradient-start: #34d399;
            --gradient-end: #059669;
            --border: rgba(5, 150, 105, 0.1);
            --text-primary: #064e3b;
            --text-secondary: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background-color: var(--bg);
            background-image: radial-gradient(circle at 10px 10px, rgba(147, 197, 153, 0.1) 2px, transparent 0);
            background-size: 24px 24px;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h1 {
            color: var(--accent);
            font-size: 2rem;
            font-weight: 700;
        }

        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: 2px solid var(--accent);
            font-size: 0.875rem;
            display: inline-block;
            background: transparent;
            color: var(--accent);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid var(--success);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .card {
            background: var(--card);
            border-radius: 16px;
            padding: 2rem;
            border: 4px solid var(--border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .card-title {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-help {
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .time-control {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .time-control .form-input {
            flex: 1;
        }

        .time-control select {
            width: auto;
            min-width: 120px;
        }

        .btn-save {
            width: 100%;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: 0;
            padding: 0.875rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }

        .toggle-switch input[type="checkbox"] {
            width: 48px;
            height: 24px;
            appearance: none;
            background: #d1d5db;
            border-radius: 12px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }

        .toggle-switch input[type="checkbox"]:checked {
            background: var(--accent);
        }

        .toggle-switch input[type="checkbox"]::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: all 0.3s;
        }

        .toggle-switch input[type="checkbox"]:checked::before {
            left: 26px;
        }

        .info-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item strong {
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .time-control {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Settings</h1>
            <a href="dashboard.php" class="btn">← Back to Dashboard</a>
        </div>

        <?php if (isset($success)): ?>
        <div class="success-message">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Settings saved successfully!
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="success-message" style="background: #fee2e2; color: #991b1b; border-color: #ef4444;">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="card">
                <h2 class="card-title">WiFi Session Settings</h2>

                <div class="form-group">
                    <label>Session Duration per Bottle</label>
                    <div class="time-control">
                        <input type="number" name="wifi_time" class="form-input" 
                            value="<?php echo $wifi_time_hours > 0 ? $wifi_time_hours : $wifi_time_minutes; ?>" 
                            min="1" max="999" required>
                        <select name="time_unit" class="form-input">
                            <option value="minutes" <?php echo $wifi_time_hours === 0 ? 'selected' : ''; ?>>Minutes</option>
                            <option value="hours" <?php echo $wifi_time_hours > 0 ? 'selected' : ''; ?>>Hours</option>
                        </select>
                    </div>
                    <div class="form-help">Duration of internet access given when a user drops a bottle</div>
                </div>

                <div class="form-group">
                    <label>Security Mode</label>
                    <select name="security_mode" class="form-input">
                        <option value="WPA3-Personal" <?php echo $settings['security_mode'] === 'WPA3-Personal' ? 'selected' : ''; ?>>WPA3-Personal (Recommended)</option>
                        <option value="WPA2-Personal" <?php echo $settings['security_mode'] === 'WPA2-Personal' ? 'selected' : ''; ?>>WPA2-Personal</option>
                        <option value="WPA/WPA2-Personal" <?php echo $settings['security_mode'] === 'WPA/WPA2-Personal' ? 'selected' : ''; ?>>WPA/WPA2-Personal</option>
                    </select>
                    <div class="form-help">WiFi encryption standard</div>
                </div>

                <div class="form-group">
                    <label>WiFi Channel</label>
                    <select name="channel" class="form-input">
                        <option value="Auto" <?php echo $settings['channel'] === 'Auto' ? 'selected' : ''; ?>>Auto</option>
                        <?php for($i = 1; $i <= 11; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $settings['channel'] == $i ? 'selected' : ''; ?>>Channel <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="form-help">WiFi channel selection (Auto is recommended)</div>
                </div>

                <div class="form-group">
                    <label class="toggle-switch">
                        <input type="checkbox" name="firewall" 
                            <?php echo $settings['firewall_enabled'] ? 'checked' : ''; ?>>
                        <span>Enable Firewall</span>
                    </label>
                    <div class="form-help">Controls per-device internet access via iptables</div>
                </div>

                <button type="submit" class="btn-save">💾 Save Settings</button>
            </div>
        </form>

        <div class="card">
            <h2 class="card-title">Current Configuration</h2>
            <div class="info-item">
                <strong>Session Duration:</strong> 
                <?php 
                if ($settings['wifi_time'] >= 3600) {
                    echo floor($settings['wifi_time'] / 3600) . ' hour(s)';
                } else {
                    echo floor($settings['wifi_time'] / 60) . ' minute(s)';
                }
                ?>
            </div>
            <div class="info-item">
                <strong>Security:</strong> <?php echo htmlspecialchars($settings['security_mode']); ?>
            </div>
            <div class="info-item">
                <strong>Channel:</strong> <?php echo htmlspecialchars($settings['channel']); ?>
            </div>
            <div class="info-item">
                <strong>Firewall:</strong> <?php echo $settings['firewall_enabled'] ? '✓ Enabled' : '✗ Disabled'; ?>
            </div>
        </div>
    </div>
</body>
</html>
