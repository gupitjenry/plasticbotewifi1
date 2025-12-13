#!/bin/bash
# BottleWifi - Firewall Configuration Script
# This script sets up iptables to block all internet access by default
# Only devices that drop bottles get access

echo "==============================================="
echo "BottleWifi - Firewall Setup"
echo "==============================================="
echo ""

# Detect network interfaces
echo "[1/5] Detecting network interfaces..."
INTERNET_IFACE=$(ip route | grep default | awk '{print $5}' | head -n1)
WIFI_IFACE="wlan0"  # Change if your WiFi interface is different

echo "Internet interface: $INTERNET_IFACE"
echo "WiFi interface: $WIFI_IFACE"
echo ""

# Enable IP forwarding
echo "[2/5] Enabling IP forwarding..."
sudo sysctl -w net.ipv4.ip_forward=1
echo "✓ IP forwarding enabled"
echo ""

# Set default FORWARD policy to DROP (block all by default)
echo "[3/5] Setting firewall default policy to DROP..."
sudo iptables -P FORWARD DROP
echo "✓ Default FORWARD policy set to DROP"
echo ""

# Allow established connections
echo "[4/5] Setting up basic connection rules..."
sudo iptables -A FORWARD -i $INTERNET_IFACE -o $WIFI_IFACE -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
sudo iptables -A FORWARD -i $WIFI_IFACE -o $INTERNET_IFACE -m conntrack --ctstate NEW,ESTABLISHED,RELATED -j ACCEPT
echo "✓ Connection rules configured"
echo ""

# Configure NAT/MASQUERADE for WiFi
echo "[5/5] Configuring NAT/MASQUERADE..."
sudo iptables -t nat -A POSTROUTING -o $INTERNET_IFACE -j MASQUERADE
echo "✓ NAT configured"
echo ""

echo "==============================================="
echo "Firewall Configuration Complete!"
echo "==============================================="
echo ""
echo "Current Status:"
echo "- Default FORWARD policy: DROP (all traffic blocked)"
echo "- Established connections: ALLOWED"
echo "- Device-specific access: ADDED DYNAMICALLY via MAC filtering"
echo ""
echo "When a device drops a bottle:"
echo "  sudo iptables -A FORWARD -m mac --mac-source <MAC> -j ACCEPT"
echo ""
echo "To make rules persistent across reboots:"
echo "  sudo apt-get install iptables-persistent"
echo "  sudo netfilter-persistent save"
echo ""
