#!/bin/bash
# Find WiFi Interface - Helper Script

echo "==============================================="
echo "Finding Your Network Interfaces"
echo "==============================================="
echo ""

echo "All network interfaces:"
ip link show
echo ""

echo "==============================================="
echo "Active Interfaces:"
echo "==============================================="
echo ""

# Show interface details
echo "Interface status:"
ip addr show
echo ""

echo "==============================================="
echo "Default route (Internet interface):"
echo "==============================================="
echo ""
ip route | grep default
echo ""

echo "==============================================="
echo "WiFi interfaces (wlan*):"
echo "==============================================="
echo ""
ip link show | grep wlan
echo ""

echo "==============================================="
echo "Bluetooth/Other wireless:"
echo "==============================================="
echo ""
iwconfig 2>/dev/null || echo "(iwconfig not found - may not have wireless info available)"
echo ""

echo "==============================================="
echo "To see which interface has internet:"
echo "==============================================="
echo ""
echo "Run: ip route"
echo "The interface after 'default via' is your internet interface"
echo ""
echo "To see which interface is your WiFi hotspot:"
echo "Run: dmesg | grep -i wireless"
echo "Or check /etc/hostapd/hostapd.conf (if using hostapd)"
echo ""

# Try to identify hostapd config
if [ -f /etc/hostapd/hostapd.conf ]; then
    echo "Found hostapd config! Your WiFi interface is:"
    grep "^interface=" /etc/hostapd/hostapd.conf
fi
