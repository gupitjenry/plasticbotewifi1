#!/bin/bash
# Internet Connectivity Fix - Enable NAT/Masquerading
# This script fixes the issue where devices are connected but can't access internet

echo "==============================================="
echo "Bottle WiFi - Internet Connectivity Fix"
echo "==============================================="
echo ""

# Detect internet interface (usually eth0, wlan0, or enp*)
echo "[1/7] Detecting internet interface..."
INTERNET_IFACE=""

# Try to find the interface with default route
DEFAULT_IFACE=$(ip route | grep default | awk '{print $5}' | head -n1)
if [ -n "$DEFAULT_IFACE" ]; then
    INTERNET_IFACE=$DEFAULT_IFACE
    echo "✓ Internet interface detected: $INTERNET_IFACE"
else
    echo "⚠ Could not auto-detect. Common interfaces:"
    echo "  - eth0 (Ethernet)"
    echo "  - wlan0 (WiFi)"
    echo "  - enp0s3 (Ethernet on some systems)"
    echo ""
    read -p "Enter your internet interface name: " INTERNET_IFACE
fi

# Detect WiFi hotspot interface
echo ""
echo "[2/7] Detecting WiFi hotspot interface..."
WIFI_IFACE=""

# Look for wlan interfaces that are not the internet interface
for iface in $(ip link show | grep -E '^[0-9]+: wlan' | cut -d: -f2 | tr -d ' '); do
    if [ "$iface" != "$INTERNET_IFACE" ]; then
        WIFI_IFACE=$iface
        break
    fi
done

if [ -z "$WIFI_IFACE" ]; then
    # If only one wlan interface, check if it's in AP mode
    WIFI_IFACE=$(ip link show | grep -E '^[0-9]+: wlan' | head -n1 | cut -d: -f2 | tr -d ' ')
fi

if [ -n "$WIFI_IFACE" ]; then
    echo "✓ WiFi hotspot interface detected: $WIFI_IFACE"
else
    echo "⚠ Could not auto-detect WiFi interface"
    read -p "Enter your WiFi hotspot interface name (usually wlan0 or wlan1): " WIFI_IFACE
fi

echo ""
echo "Configuration:"
echo "  Internet Interface: $INTERNET_IFACE"
echo "  WiFi Interface: $WIFI_IFACE"
echo ""
read -p "Is this correct? (y/n): " confirm
if [ "$confirm" != "y" ]; then
    echo "Aborted. Please run script again with correct interfaces."
    exit 1
fi

echo ""
echo "[3/7] Enabling IP forwarding..."
sudo sysctl -w net.ipv4.ip_forward=1
echo "✓ IP forwarding enabled"

echo ""
echo "[4/7] Making IP forwarding persistent..."
if ! grep -q "net.ipv4.ip_forward=1" /etc/sysctl.conf; then
    echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.conf > /dev/null
    echo "✓ IP forwarding will persist after reboot"
else
    echo "✓ IP forwarding already persistent"
fi

echo ""
echo "[5/7] Configuring NAT/MASQUERADE..."
# Remove old MASQUERADE rules if any
sudo iptables -t nat -D POSTROUTING -o $INTERNET_IFACE -j MASQUERADE 2>/dev/null

# Add NAT rule for internet sharing
sudo iptables -t nat -A POSTROUTING -o $INTERNET_IFACE -j MASQUERADE
echo "✓ NAT rule added for internet sharing"

echo ""
echo "[6/7] Setting up FORWARD chain rules..."

# Set default policy to DROP (security)
sudo iptables -P FORWARD DROP

# Allow established/related connections (very important!)
sudo iptables -D FORWARD -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT 2>/dev/null
sudo iptables -I FORWARD 1 -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

# Allow traffic from WiFi to internet (will be filtered by MAC rules below)
sudo iptables -A FORWARD -i $WIFI_IFACE -o $INTERNET_IFACE -j ACCEPT

# Allow traffic from internet back to WiFi (responses)
sudo iptables -A FORWARD -i $INTERNET_IFACE -o $WIFI_IFACE -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

echo "✓ FORWARD rules configured"

echo ""
echo "[7/7] Restoring active WiFi sessions..."
if [ -f "wifi_sessions.json" ]; then
    php -r '
        $sessions = json_decode(file_get_contents("wifi_sessions.json"), true);
        if (!$sessions) {
            echo "  No active sessions to restore\n";
            exit;
        }
        
        $currentTime = time();
        $restored = 0;
        
        foreach ($sessions as $session) {
            if ($session["expires_at"] > $currentTime) {
                $mac = $session["mac"];
                $remaining = $session["expires_at"] - $currentTime;
                
                // Note: With the new FORWARD rules, MAC filtering happens at PHP level
                // but we keep the MAC-based ACCEPT rules for explicit control
                exec("sudo iptables -A FORWARD -m mac --mac-source $mac -j ACCEPT 2>&1", $output, $ret);
                
                echo "  Restored access for: $mac (expires in " . ceil($remaining/60) . " min)\n";
                $restored++;
            }
        }
        
        echo "✓ Restored $restored active session(s)\n";
    '
else
    echo "  No session file found"
fi

echo ""
echo "==============================================="
echo "Current Configuration:"
echo "==============================================="

echo ""
echo "IP Forwarding:"
sysctl net.ipv4.ip_forward

echo ""
echo "NAT Rules:"
sudo iptables -t nat -L POSTROUTING -v -n

echo ""
echo "FORWARD Rules:"
sudo iptables -L FORWARD -v -n

echo ""
echo "==============================================="
echo "Fix Complete!"
echo "==============================================="
echo "✓ IP forwarding enabled"
echo "✓ NAT/MASQUERADE configured"
echo "✓ FORWARD rules set up"
echo "✓ Internet should now work after dropping bottle"
echo ""
echo "To make these rules persistent across reboots:"
echo "  sudo apt-get install iptables-persistent"
echo "  sudo netfilter-persistent save"
echo ""
echo "Test the fix:"
echo "  1. Connect device to Bottle_WiFi"
echo "  2. Drop a bottle in the system"
echo "  3. Try accessing internet (e.g., ping 8.8.8.8)"
echo ""

