#!/bin/bash
# Comprehensive Internet Connectivity Diagnostic Tool
# Identifies why devices can't access internet after dropping bottle

echo "==============================================="
echo "Bottle WiFi - Internet Connectivity Diagnostic"
echo "==============================================="
echo ""

ISSUES_FOUND=0

# Check 1: IP Forwarding
echo "[1/8] Checking IP forwarding..."
IP_FORWARD=$(sysctl -n net.ipv4.ip_forward)
if [ "$IP_FORWARD" = "1" ]; then
    echo "✓ IP forwarding is enabled"
else
    echo "✗ IP forwarding is DISABLED"
    echo "  This prevents the Raspberry Pi from routing traffic"
    echo "  Fix: sudo sysctl -w net.ipv4.ip_forward=1"
    ((ISSUES_FOUND++))
fi

echo ""
echo "[2/8] Checking NAT/MASQUERADE configuration..."
NAT_RULES=$(sudo iptables -t nat -L POSTROUTING -n)
if echo "$NAT_RULES" | grep -q "MASQUERADE"; then
    echo "✓ NAT/MASQUERADE is configured"
    echo "$NAT_RULES" | grep MASQUERADE | while read line; do
        echo "  Rule: $line"
    done
else
    echo "✗ NAT/MASQUERADE is NOT configured"
    echo "  This is the most common reason for no internet access"
    echo "  Without NAT, devices can't access external internet"
    echo "  Fix: Run fix_internet.sh script"
    ((ISSUES_FOUND++))
fi

echo ""
echo "[3/8] Checking internet interface..."
DEFAULT_ROUTE=$(ip route | grep default)
if [ -n "$DEFAULT_ROUTE" ]; then
    echo "✓ Default route found:"
    echo "  $DEFAULT_ROUTE"
    INTERNET_IFACE=$(echo "$DEFAULT_ROUTE" | awk '{print $5}')
    echo "  Internet interface: $INTERNET_IFACE"
    
    # Test internet connectivity
    if ping -c 1 -W 2 8.8.8.8 > /dev/null 2>&1; then
        echo "✓ Raspberry Pi can reach internet"
    else
        echo "✗ Raspberry Pi CANNOT reach internet"
        echo "  Check your internet connection"
        ((ISSUES_FOUND++))
    fi
else
    echo "✗ No default route found"
    echo "  Raspberry Pi is not connected to internet"
    ((ISSUES_FOUND++))
fi

echo ""
echo "[4/8] Checking FORWARD chain rules..."
FORWARD_POLICY=$(sudo iptables -L FORWARD -n | grep "Chain FORWARD" | grep -o "policy [A-Z]*" | cut -d' ' -f2)
echo "  Default policy: $FORWARD_POLICY"

if [ "$FORWARD_POLICY" = "DROP" ]; then
    echo "✓ Default policy is DROP (secure)"
else
    echo "⚠ Default policy is $FORWARD_POLICY"
    echo "  For security, it should be DROP"
fi

# Check for ESTABLISHED,RELATED rule
if sudo iptables -L FORWARD -n | grep -q "ESTABLISHED,RELATED"; then
    echo "✓ ESTABLISHED,RELATED connections allowed"
else
    echo "✗ No ESTABLISHED,RELATED rule found"
    echo "  This is CRITICAL - return traffic won't work"
    echo "  Fix: sudo iptables -I FORWARD 1 -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT"
    ((ISSUES_FOUND++))
fi

echo ""
echo "[5/8] Checking WiFi interface..."
WIFI_IFACES=$(ip link show | grep -E '^[0-9]+: wlan' | cut -d: -f2 | tr -d ' ')
if [ -n "$WIFI_IFACES" ]; then
    echo "✓ WiFi interfaces found:"
    for iface in $WIFI_IFACES; do
        IP_ADDR=$(ip addr show $iface | grep "inet " | awk '{print $2}')
        echo "  $iface - $IP_ADDR"
    done
else
    echo "✗ No WiFi interfaces found"
    echo "  Check if WiFi hotspot is running"
    ((ISSUES_FOUND++))
fi

echo ""
echo "[6/8] Checking active WiFi sessions..."
if [ -f "wifi_sessions.json" ]; then
    ACTIVE_SESSIONS=$(php -r '
        $sessions = json_decode(file_get_contents("wifi_sessions.json"), true);
        if (!$sessions) { echo "0"; exit; }
        $currentTime = time();
        $active = 0;
        foreach ($sessions as $session) {
            if ($session["expires_at"] > $currentTime) {
                $active++;
                $mac = $session["mac"];
                $ip = $session["ip"] ?? "unknown";
                $remaining = ceil(($session["expires_at"] - $currentTime) / 60);
                echo "MAC: $mac | IP: $ip | Time left: {$remaining}min\n";
            }
        }
    ')
    if [ -n "$ACTIVE_SESSIONS" ]; then
        echo "✓ Active sessions found:"
        echo "$ACTIVE_SESSIONS"
    else
        echo "  No active sessions"
    fi
else
    echo "  No session file found"
fi

echo ""
echo "[7/8] Checking firewall rules for active MACs..."
if [ -f "wifi_sessions.json" ]; then
    php -r '
        $sessions = json_decode(file_get_contents("wifi_sessions.json"), true);
        if (!$sessions) { exit; }
        $currentTime = time();
        
        foreach ($sessions as $session) {
            if ($session["expires_at"] > $currentTime) {
                $mac = $session["mac"];
                
                // Check if firewall rule exists
                exec("sudo iptables -C FORWARD -m mac --mac-source $mac -j ACCEPT 2>&1", $output, $ret);
                
                if ($ret == 0) {
                    echo "✓ Firewall rule exists for: $mac\n";
                } else {
                    echo "✗ NO firewall rule for: $mac (should have access!)\n";
                }
            }
        }
    '
else
    echo "  No sessions to check"
fi

echo ""
echo "[8/8] Testing end-to-end connectivity..."
if [ -f "wifi_sessions.json" ]; then
    # Get a device with active session
    DEVICE_IP=$(php -r '
        $sessions = json_decode(file_get_contents("wifi_sessions.json"), true);
        if (!$sessions) { exit; }
        $currentTime = time();
        foreach ($sessions as $session) {
            if ($session["expires_at"] > $currentTime && isset($session["ip"])) {
                echo $session["ip"];
                exit;
            }
        }
    ')
    
    if [ -n "$DEVICE_IP" ]; then
        echo "  Testing device: $DEVICE_IP"
        
        # Check if device is reachable
        if ping -c 1 -W 1 $DEVICE_IP > /dev/null 2>&1; then
            echo "✓ Device is reachable from Raspberry Pi"
        else
            echo "✗ Device is NOT reachable"
            echo "  Check WiFi connection"
        fi
    else
        echo "  No active device IP to test"
    fi
else
    echo "  No sessions to test"
fi

echo ""
echo "==============================================="
echo "SUMMARY"
echo "==============================================="

if [ $ISSUES_FOUND -eq 0 ]; then
    echo "✓ No critical issues found"
    echo ""
    echo "If devices still can't access internet:"
    echo "1. Check DNS resolution on device"
    echo "2. Try: ping 8.8.8.8 (test connectivity)"
    echo "3. Try: nslookup google.com (test DNS)"
    echo "4. Check device firewall settings"
else
    echo "✗ Found $ISSUES_FOUND critical issue(s)"
    echo ""
    echo "RECOMMENDED FIX:"
    echo "  bash fix_internet.sh"
fi

echo ""
echo "Full firewall dump:"
echo "===================="
echo ""
echo "NAT Table:"
sudo iptables -t nat -L -n -v
echo ""
echo "FORWARD Chain:"
sudo iptables -L FORWARD -n -v
echo ""

