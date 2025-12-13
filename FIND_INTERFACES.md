# How to Find Your Network Interfaces

## Quick Method (Run on Raspberry Pi)

### Step 1: Find ALL interfaces
```bash
ip link show
```

**Look for:**
- `eth0` - Ethernet (usually internet)
- `wlan0` - WiFi adapter (usually WiFi hotspot)
- `wlan1` - USB WiFi dongle
- `enp*` - Virtual/other Ethernet
- `lo` - Loopback (ignore this)

### Step 2: Find which has internet
```bash
ip route
```

**Look for the line starting with `default via`**
```
default via 192.168.1.1 dev eth0 
```
The interface at the end (here: `eth0`) is your **INTERNET** interface.

### Step 3: Find your WiFi hotspot interface
```bash
cat /etc/hostapd/hostapd.conf | grep interface
```

Or if using dnsmasq:
```bash
cat /etc/dnsmasq.conf | grep interface
```

## Common Configurations

### Raspberry Pi with Ethernet + WiFi Hotspot
```
Internet: eth0
WiFi:     wlan0
```

### Raspberry Pi with 2x USB WiFi (one for internet, one for hotspot)
```
Internet: wlan0 (with WPA2 connected to router)
WiFi:     wlan1 (hostapd hotspot)
```

### Raspberry Pi Zero with USB Ethernet
```
Internet: usb0
WiFi:     wlan0
```

## Example Commands to Run

```bash
# List all interfaces
ip link show

# See which is connected to internet
ip route

# See active connections
ip addr show

# See your WiFi hotspot interface
ps aux | grep hostapd

# Check hostapd config
sudo cat /etc/hostapd/hostapd.conf | grep interface

# Check dnsmasq config
sudo cat /etc/dnsmasq.conf | grep interface
```

## Once You Know the Interfaces

Replace these in the firewall commands:

```bash
# From the template:
sudo iptables -A FORWARD -i eth0 -o wlan0 -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

# To your actual interfaces:
sudo iptables -A FORWARD -i <YOUR_INTERNET_INTERFACE> -o <YOUR_WIFI_INTERFACE> -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
```

## Example Output

When you run `ip link show`, you might see:
```
1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536
    link/loopback 00:00:00:00:00:00 brd 00:00:00:00:00:00

2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500
    link/ether aa:bb:cc:dd:ee:ff brd ff:ff:ff:ff:ff:ff

3: wlan0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500
    link/ether 11:22:33:44:55:66 brd ff:ff:ff:ff:ff:ff
```

**Here:**
- `lo` = loopback (ignore)
- `eth0` = Ethernet (likely internet)
- `wlan0` = WiFi (likely hotspot)

## Running the Helper Script

```bash
cd /var/www/html/bottle_wifi
bash find_interfaces.sh
```

This will show you everything and try to auto-detect your WiFi interface from hostapd config.
