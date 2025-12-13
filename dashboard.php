<?php
session_start();

// --------------------------------------
// AUTH CHECK
// --------------------------------------
// Check if already logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}






// --------------------------------------
// FILE PATHS
// --------------------------------------
$recyclingFile = __DIR__ . '/recycling_data.json';
$sessionsFile  = __DIR__ . '/wifi_sessions.json';

// --------------------------------------
// LOAD RECYCLING DATA
// --------------------------------------
$recyclingData = [];
if (file_exists($recyclingFile)) {
    $raw = file_get_contents($recyclingFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $recyclingData = $decoded;
    }
}

// --------------------------------------
// LOAD WIFI SESSIONS
// --------------------------------------
$sessions = [];
if (file_exists($sessionsFile)) {
    $raw = file_get_contents($sessionsFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $sessions = $decoded;
    }
}

// --------------------------------------
// STATISTICS
// --------------------------------------
$totalBottles = is_array($recyclingData) ? count($recyclingData) : 0;
$currentTime  = time();

// --------------------------------------
// ACTIVE WIFI SESSIONS
// --------------------------------------
$activeSessions = [];
foreach ($sessions as $s) {
    if (isset($s['expires_at']) && $s['expires_at'] > $currentTime) {
        $activeSessions[] = $s;
    }
}

// --------------------------------------
// USER BOTTLE COUNTS
// --------------------------------------
$userBottleCounts = [];

foreach ($recyclingData as $entry) {
    $mac = $entry['mac'] ?? ($entry['ip'] ?? 'Unknown');
    $ip  = $entry['ip'] ?? 'Unknown';

    if (!isset($userBottleCounts[$mac])) {
        $userBottleCounts[$mac] = [
            'mac'       => $mac,
            'ip'        => $ip,
            'bottles'   => 0,
            'last_seen' => null,
        ];
    }

    $userBottleCounts[$mac]['bottles']++;

    if (isset($entry['timestamp'])) {
        $entryTime = strtotime($entry['timestamp']);
        $lastSeen  = $userBottleCounts[$mac]['last_seen']
            ? strtotime($userBottleCounts[$mac]['last_seen'])
            : 0;

        if ($entryTime > $lastSeen) {
            $userBottleCounts[$mac]['last_seen'] = $entry['timestamp'];
        }
    }
}

// SORT DESC
usort($userBottleCounts, function ($a, $b) {
    return $b['bottles'] - $a['bottles'];
});

// --------------------------------------
// LAST 7 DAYS CHART
// --------------------------------------
$dailyBottles = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyBottles[$date] = 0;
}

foreach ($recyclingData as $entry) {
    if (!isset($entry['timestamp'])) continue;
    $date = date('Y-m-d', strtotime($entry['timestamp']));
    if (isset($dailyBottles[$date])) {
        $dailyBottles[$date]++;
    }
}

$totalUsers  = count($userBottleCounts);
$activeUsers = count($activeSessions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — BottleWifi Admin</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #eff6ff;
            --card: #ffffff;
            --accent: #2563eb;
            --muted: #60a5fa;
            --gradient-start: #3b82f6;
            --gradient-end: #06b6d4;
            --border: rgba(37, 99, 235, 0.15);
            --text-primary: #1e40af;
            --text-secondary: #6b7280;
            font-family: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background-color: var(--bg);
            background-image: radial-gradient(circle, rgba(96,165,250,0.15) 2px, transparent 0);
            background-size: 24px 24px;
            padding: 2rem 1rem;
            color: var(--text-primary);
        }

        .container { max-width: 1200px; margin: 0 auto; }

        /* Header */
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

        .nav-buttons { display: flex; gap: .75rem; }

        .btn {
            padding: .5rem 1.25rem;
            border-radius: 9999px;
            text-decoration: none;
            font-weight: 500;
            transition: .2s;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: 0;
        }

        .btn-secondary {
            border: 2px solid var(--accent);
            color: var(--accent);
            background: transparent;
        }

        .btn:hover { transform: translateY(-1px); }

        /* Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card);
            border-radius: 16px;
            padding: 1.5rem;
            border: 4px solid var(--border);
            box-shadow: 0 4px 6px rgba(37,99,235,0.1);
        }

        .stat-label {
            font-size: .875rem;
            color: var(--text-secondary);
            margin-bottom: .5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }

        /* Generic card */
        .card {
            background: var(--card);
            border-radius: 16px;
            padding: 1.5rem;
            border: 4px solid var(--border);
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: .75rem;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border);
        }
        td {
            padding: .75rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
        }

        .badge {
            padding: .25rem .75rem;
            border-radius: 9999px;
            font-size: .75rem;
        }
        .badge-active {
            background: rgba(59, 130, 246, .2);
            color: var(--accent);
        }

        .time-remaining { color: var(--accent); font-weight: 600; }

        /* Chart */
        .chart-container {
            height: 200px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: .5rem;
        }

        .chart-bar {
            flex: 1;
            background: linear-gradient(180deg, var(--gradient-start), var(--gradient-end));
            border-radius: 8px 8px 0 0;
            position: relative;
            min-height: 20px;
        }

        .chart-value {
            position: absolute;
            top: -22px;
            width: 100%;
            text-align: center;
            font-size: .75rem;
            color: var(--accent);
            font-weight: 600;
        }

        .chart-label {
            position: absolute;
            bottom: -30px;
            width: 100%;
            text-align: center;
            font-size: .75rem;
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .refresh-info {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            table { font-size: .85rem; }
            th, td { padding: .5rem; }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- HEADER -->
    <div class="header">
        <h1>🍾 BottleWifi Dashboard</h1>

        <div class="nav-buttons">
            <a href="settings.php" class="btn btn-secondary">Settings</a>
            <a href="logout.php" class="btn btn-primary">Logout</a>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-label">Total Bottles</div>
            <div class="stat-value"><?= $totalBottles ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= $totalUsers ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-value"><?= $activeUsers ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Avg Bottles/User</div>
            <div class="stat-value"><?= $totalUsers ? round($totalBottles / $totalUsers, 1) : 0 ?></div>
        </div>

    </div>

    <!-- CHART -->
    <div class="card">
        <h2 class="card-title">📊 Bottles Over Last 7 Days</h2>

        <?php if ($totalBottles > 0): ?>
        <div class="chart-container">

            <?php
            $max = max($dailyBottles) ?: 1;
            foreach ($dailyBottles as $date => $count):
                $height = ($count / $max) * 100;
                $label  = date('M j', strtotime($date));
            ?>
                <div class="chart-bar" style="height: <?= $height ?>%;">
                    <div class="chart-value"><?= $count ?></div>
                    <div class="chart-label"><?= $label ?></div>
                </div>

            <?php endforeach; ?>

        </div>
        <?php else: ?>
            <div class="empty-state">No bottle data yet</div>
        <?php endif; ?>
    </div>

    <!-- ACTIVE SESSIONS -->
    <div class="card">
        <h2 class="card-title">🟢 Active WiFi Sessions</h2>

        <?php if ($activeUsers > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>MAC</th>
                    <th>IP</th>
                    <th>Time Left</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach ($activeSessions as $s):
                $remaining = max(0, $s['expires_at'] - $currentTime);
                $m = floor($remaining / 60);
                $s2 = $remaining % 60;
            ?>
                <tr>
                    <td><code><?= htmlspecialchars($s['mac']) ?></code></td>
                    <td><?= htmlspecialchars($s['ip'] ?? 'Unknown') ?></td>
                    <td class="time-remaining"><?= sprintf("%d:%02d", $m, $s2) ?></td>
                    <td><span class="badge badge-active">Active</span></td>
                </tr>
            <?php endforeach; ?>

            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">No active WiFi sessions</div>
        <?php endif; ?>
    </div>

    <!-- USER RANKINGS -->
    <div class="card">
        <h2 class="card-title">🏆 Top Users</h2>

        <?php if ($totalUsers > 0): ?>
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>MAC</th>
                <th>IP</th>
                <th>Bottles</th>
                <th>Last Seen</th>
            </tr>
            </thead>

            <tbody>
            <?php $rank = 1; foreach (array_slice($userBottleCounts, 0, 20) as $u): ?>
                <tr>
                    <td><strong><?= $rank++ ?></strong></td>
                    <td><code><?= htmlspecialchars($u['mac']) ?></code></td>
                    <td><?= htmlspecialchars($u['ip']) ?></td>
                    <td style="color: var(--accent); font-weight:700;"><?= $u['bottles'] ?></td>
                    <td><?= $u['last_seen'] ? date('M j, g:i A', strtotime($u['last_seen'])) : 'Unknown' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>

        </table>
        <?php else: ?>
            <div class="empty-state">No users yet</div>
        <?php endif; ?>
    </div>

    <div class="refresh-info">
        📡 Auto-refreshing every 30s — Last updated <?= date('g:i:s A') ?>
    </div>

</div>

<script>
setTimeout(() => location.reload(), 30000);
</script>

</body>
</html>
