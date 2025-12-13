<?php
/**
 * Security Test Script
 * Tests if the Bottle WiFi system is properly secured
 */

header('Content-Type: application/json');

$results = [];
$passed = 0;
$failed = 0;
$warnings = 0;

// ============================================
// Test 1: Firewall Default Policy
// ============================================
echo "Running Security Tests...\n\n";

$firewallCheck = shell_exec("sudo iptables -L FORWARD -n | grep 'Chain FORWARD' 2>&1");
if (strpos($firewallCheck, 'policy DROP') !== false) {
    $results[] = [
        'test' => 'Firewall Default Policy',
        'status' => 'PASS',
        'message' => 'Default policy is DROP - all traffic blocked by default',
        'severity' => 'GOOD'
    ];
    $passed++;
} else if (strpos($firewallCheck, 'policy ACCEPT') !== false) {
    $results[] = [
        'test' => 'Firewall Default Policy',
        'status' => 'FAIL',
        'message' => 'Default policy is ACCEPT - ALL DEVICES HAVE INTERNET!',
        'fix' => 'Run: bash fix_firewall.sh',
        'severity' => 'CRITICAL'
    ];
    $failed++;
} else {
    $results[] = [
        'test' => 'Firewall Default Policy',
        'status' => 'WARNING',
        'message' => 'Could not read firewall policy',
        'details' => $firewallCheck,
        'severity' => 'WARNING'
    ];
    $warnings++;
}

// ============================================
// Test 2: Blanket Access Rules
// ============================================
$forwardRules = shell_exec("sudo iptables -L FORWARD -n 2>&1");
$blanketAccept = false;

// Check for rules that accept all traffic without MAC filtering
if (preg_match('/ACCEPT\s+all\s+--\s+\*\s+\*\s+0\.0\.0\.0\/0\s+0\.0\.0\.0\/0(?!\s+MAC)/', $forwardRules)) {
    $blanketAccept = true;
}

if (!$blanketAccept) {
    $results[] = [
        'test' => 'Blanket Accept Rules',
        'status' => 'PASS',
        'message' => 'No blanket ACCEPT rules found',
        'severity' => 'GOOD'
    ];
    $passed++;
} else {
    $results[] = [
        'test' => 'Blanket Accept Rules',
        'status' => 'FAIL',
        'message' => 'Found ACCEPT rule without MAC filtering - devices can bypass bottle detection!',
        'fix' => 'Run: bash fix_firewall.sh',
        'severity' => 'CRITICAL'
    ];
    $failed++;
}

// ============================================
// Test 3: Token System
// ============================================
$tokenFile = __DIR__ . '/bottle_tokens.json';
if (!file_exists($tokenFile)) {
    // Create empty token file
    file_put_contents($tokenFile, json_encode([], JSON_PRETTY_PRINT));
    $results[] = [
        'test' => 'Token System',
        'status' => 'WARNING',
        'message' => 'Token file created. System ready.',
        'severity' => 'INFO'
    ];
    $warnings++;
} else {
    $tokens = json_decode(file_get_contents($tokenFile), true);
    if (is_array($tokens)) {
        $activeTokens = 0;
        $expiredTokens = 0;
        $currentTime = time();
        
        foreach ($tokens as $tokenData) {
            if ($tokenData['expires_at'] > $currentTime) {
                $activeTokens++;
            } else {
                $expiredTokens++;
            }
        }
        
        $results[] = [
            'test' => 'Token System',
            'status' => 'PASS',
            'message' => 'Token system operational',
            'details' => "Active: $activeTokens, Expired: $expiredTokens",
            'severity' => 'GOOD'
        ];
        $passed++;
    } else {
        $results[] = [
            'test' => 'Token System',
            'status' => 'WARNING',
            'message' => 'Token file exists but contains invalid data',
            'fix' => 'Reset file: echo \'{}\' > bottle_tokens.json',
            'severity' => 'WARNING'
        ];
        $warnings++;
    }
}

// ============================================
// Test 4: Session Tracking
// ============================================
$sessionFile = __DIR__ . '/wifi_sessions.json';
if (!file_exists($sessionFile)) {
    file_put_contents($sessionFile, json_encode([], JSON_PRETTY_PRINT));
    $results[] = [
        'test' => 'Session Tracking',
        'status' => 'WARNING',
        'message' => 'Session file created. System ready.',
        'severity' => 'INFO'
    ];
    $warnings++;
} else {
    $sessions = json_decode(file_get_contents($sessionFile), true);
    if (is_array($sessions)) {
        $activeSessions = 0;
        $expiredSessions = 0;
        $currentTime = time();
        
        foreach ($sessions as $session) {
            if ($session['expires_at'] > $currentTime) {
                $activeSessions++;
            } else {
                $expiredSessions++;
            }
        }
        
        $results[] = [
            'test' => 'Session Tracking',
            'status' => 'PASS',
            'message' => 'Session tracking operational',
            'details' => "Active: $activeSessions, Expired: $expiredSessions",
            'severity' => 'GOOD'
        ];
        $passed++;
    } else {
        $results[] = [
            'test' => 'Session Tracking',
            'status' => 'WARNING',
            'message' => 'Session file exists but contains invalid data',
            'fix' => 'Reset file: echo \'[]\' > wifi_sessions.json',
            'severity' => 'WARNING'
        ];
        $warnings++;
    }
}

// ============================================
// Test 5: IR Sensor
// ============================================
$sensorScript = __DIR__ . '/read_ir_sensor.py';
if (file_exists($sensorScript)) {
    $pythonCheck = shell_exec("python3 --version 2>&1");
    if ($pythonCheck && strpos($pythonCheck, 'Python 3') !== false) {
        $results[] = [
            'test' => 'IR Sensor Script',
            'status' => 'PASS',
            'message' => 'Sensor script exists and Python3 is available',
            'details' => trim($pythonCheck),
            'severity' => 'GOOD'
        ];
        $passed++;
    } else {
        $results[] = [
            'test' => 'IR Sensor Script',
            'status' => 'WARNING',
            'message' => 'Sensor script exists but Python3 not found',
            'fix' => 'Install: sudo apt-get install python3',
            'severity' => 'WARNING'
        ];
        $warnings++;
    }
} else {
    $results[] = [
        'test' => 'IR Sensor Script',
        'status' => 'FAIL',
        'message' => 'Sensor script not found',
        'severity' => 'ERROR'
    ];
    $failed++;
}

// ============================================
// Test 6: File Permissions
// ============================================
$filesCheck = [
    'bottle_tokens.json' => ['read' => true, 'write' => true],
    'wifi_sessions.json' => ['read' => true, 'write' => true],
    'hardware_control.php' => ['read' => true, 'write' => false],
    'ir.php' => ['read' => true, 'write' => false]
];

$permissionErrors = [];
foreach ($filesCheck as $file => $perms) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        if ($perms['read'] && !is_readable($path)) {
            $permissionErrors[] = "$file is not readable";
        }
        if ($perms['write'] && !is_writable($path)) {
            $permissionErrors[] = "$file is not writable";
        }
    }
}

if (empty($permissionErrors)) {
    $results[] = [
        'test' => 'File Permissions',
        'status' => 'PASS',
        'message' => 'All required files have correct permissions',
        'severity' => 'GOOD'
    ];
    $passed++;
} else {
    $results[] = [
        'test' => 'File Permissions',
        'status' => 'WARNING',
        'message' => 'Some permission issues detected',
        'details' => implode(', ', $permissionErrors),
        'fix' => 'Run: sudo chown www-data:www-data *.json',
        'severity' => 'WARNING'
    ];
    $warnings++;
}

// ============================================
// Summary
// ============================================
$overallStatus = 'SECURE';
if ($failed > 0) {
    $overallStatus = 'CRITICAL - IMMEDIATE ACTION REQUIRED';
} elseif ($warnings > 2) {
    $overallStatus = 'NEEDS ATTENTION';
}

// ============================================
// Output Results
// ============================================
echo "\n==============================================\n";
echo "BOTTLE WIFI SECURITY TEST RESULTS\n";
echo "==============================================\n\n";

foreach ($results as $result) {
    $statusEmoji = [
        'PASS' => '✅',
        'FAIL' => '❌',
        'WARNING' => '⚠️'
    ];
    
    $emoji = $statusEmoji[$result['status']] ?? '❓';
    
    echo "$emoji {$result['test']}: {$result['status']}\n";
    echo "   {$result['message']}\n";
    
    if (isset($result['details'])) {
        echo "   Details: {$result['details']}\n";
    }
    
    if (isset($result['fix'])) {
        echo "   Fix: {$result['fix']}\n";
    }
    
    echo "\n";
}

echo "==============================================\n";
echo "SUMMARY\n";
echo "==============================================\n";
echo "Overall Status: $overallStatus\n";
echo "Tests Passed: $passed\n";
echo "Tests Failed: $failed\n";
echo "Warnings: $warnings\n";
echo "\n";

if ($failed > 0) {
    echo "⚠️  CRITICAL ISSUES DETECTED!\n";
    echo "Run 'bash fix_firewall.sh' immediately to secure the system.\n";
} elseif ($warnings > 0) {
    echo "⚠️  Some warnings detected. Review and fix as needed.\n";
} else {
    echo "✅ System is properly secured!\n";
}

echo "\n==============================================\n";
?>

