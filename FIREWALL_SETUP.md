# BottleWifi - Per-Device Internet Access Setup Guide

## Problem
Devices connected to Bottle_WiFi have internet access WITHOUT dropping a bottle. This is a security issue.

## Solution
Configure the firewall to:
1. **Block all traffic by default** (DROP policy)
2. **Only allow devices with bottle-donated sessions** to access internet
3. **Add MAC-based rules dynamically** when bottles are detected

## Setup Steps (Run on Raspberry Pi)

### Step 1: Check Current Firewall Status
```bash
sudo iptables -L FORWARD -n
```

**You should see:**
```
Chain FORWARD (policy DROP)
target     prot opt source               destination
ACCEPT     all  --  0.0.0.0/0            0.0.0.0/0            ctstate RELATED,ESTABLISHED
ACCEPT     all  --  0.0.0.0/0            0.0.0.0/0            ctstate NEW,ESTABLISHED,RELATED
```

**If you see `policy ACCEPT`**, continue to Step 2.

### Step 2: Run the Firewall Setup Script
```bash
cd /var/www/html/bottle_wifi
bash setup_firewall.sh
```

Or manually run these commands:

```bash
# Enable IP forwarding
sudo sysctl -w net.ipv4.ip_forward=1

# Set FORWARD policy to DROP (block all by default)
sudo iptables -P FORWARD DROP

# Allow established connections back
sudo iptables -A FORWARD -i eth0 -o wlan0 -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
sudo iptables -A FORWARD -i wlan0 -o eth0 -m conntrack --ctstate NEW,ESTABLISHED,RELATED -j ACCEPT

# Configure NAT
sudo iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
```

**Replace `eth0` with your internet interface and `wlan0` with your WiFi interface if different.**

### Step 3: Verify Firewall is Blocking
```bash
sudo iptables -L FORWARD -n
```

Should show:
```
policy DROP
```

### Step 4: Test the System

#### Test 1: Device WITHOUT Bottle (Should be BLOCKED)
1. Connect device to Bottle_WiFi
2. Try to browse internet
3. **Result: ❌ NO INTERNET** ✓ Correct!

#### Test 2: Device WITH Bottle (Should get ACCESS)
1. Open `http://<raspberry-pi-ip>/index.php`
2. Click "Start Recycling"
3. Drop a bottle
4. **Result: ✅ INTERNET ACCESS GRANTED** ✓ Correct!

### Step 5: Make Rules Persistent (Optional but Recommended)

Without this, iptables rules are lost on reboot.

```bash
sudo apt-get install iptables-persistent

# Save current rules
sudo netfilter-persistent save

# Verify saved
sudo netfilter-persistent reload
```

## How It Works Now

### Before (INSECURE):
```
All connected devices → Internet access automatically ❌
```

### After (SECURE):
```
Device connects → NO internet (firewall blocks)
Device drops bottle → Session created with bottle_donated=true
System grants internet → iptables rule added for that MAC
Device has internet → For 5 minutes ✅
```

## Troubleshooting

### Issue: Still have internet without bottle
**Solution:** 
- Check firewall policy: `sudo iptables -L FORWARD -n`
- Should show `policy DROP`
- If `policy ACCEPT`, run `sudo iptables -P FORWARD DROP`

### Issue: Even with bottle, no internet
**Solution:**
- Check if session was created: `cat device_sessions.json`
- Look for `bottle_donated: true`
- Check iptables rule added: `sudo iptables -L FORWARD -v -n | grep MAC`

### Issue: Rules are lost after reboot
**Solution:**
- Install iptables-persistent: `sudo apt-get install iptables-persistent`
- Save rules: `sudo netfilter-persistent save`

## Network Interface Names

Common names (use `ip link show` to find yours):
- **eth0** - Ethernet (internet)
- **wlan0** - Built-in WiFi
- **wlan1** - USB WiFi dongle
- **enp0s3** - Virtual Ethernet (VirtualBox)

## Key Files

- `session_manager.php` - Tracks bottle donations
- `ir.php` - Creates sessions when bottle detected
- `hardware_control.php` - Grants WiFi access (after bottle verified)
- `setup_firewall.sh` - Firewall configuration script

## Security Features

✅ Default DROP policy blocks all traffic
✅ Per-device MAC filtering
✅ Session-based bottle verification
✅ Time-limited access (5 minutes per bottle)
✅ Firewall checks on every WiFi grant request
