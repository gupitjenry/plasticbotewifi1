#!/usr/bin/env python3
"""
WiFi Portal Control Script for Raspberry Pi
Manages WiFi access based on bottle detection
Usage: python3 wifi_control.py <grant|revoke> <mac_address> <duration>
"""

import subprocess
import sys
import json
from datetime import datetime, timedelta

def grant_wifi_access(mac_address, duration_minutes=5):
    """
    Grant WiFi access to a SPECIFIC device for specified duration
    Each device must drop a bottle individually to get internet access
    SECURITY: 'all' access is disabled - must specify a device MAC address
    """
    try:
        # SECURITY FIX: Prevent granting access to all devices
        if mac_address == 'all' or not mac_address:
            return {
                'success': False,
                'error': 'Must specify a valid MAC address. Bulk access is not allowed for security.',
                'security_note': 'Each device must drop a bottle individually to get access'
            }
        
        # Validate MAC address format 
        if not validate_mac_address(mac_address):
            return {
                'success': False,
                'error': f'Invalid MAC address format: {mac_address}'
            }
        
        # CRITICAL: Check if IP forwarding is enabled
        ip_forward_check = subprocess.run(
            "sysctl net.ipv4.ip_forward",
            shell=True, capture_output=True, text=True
        )
        if "= 0" in ip_forward_check.stdout:
            return {
                'success': False,
                'error': 'IP forwarding is disabled. Internet routing will not work.',
                'fix': 'Run: sudo sysctl -w net.ipv4.ip_forward=1',
                'severity': 'CRITICAL'
            }
        
        # CRITICAL: Check if NAT/MASQUERADE is configured
        nat_check = subprocess.run(
            "sudo iptables -t nat -L POSTROUTING -n",
            shell=True, capture_output=True, text=True
        )
        if "MASQUERADE" not in nat_check.stdout:
            return {
                'success': False,
                'error': 'NAT/MASQUERADE not configured. Devices cannot access internet.',
                'fix': 'Run: bash fix_internet.sh',
                'severity': 'CRITICAL',
                'details': 'Internet routing requires NAT to be configured on the gateway interface'
            }
        
        # Check if this MAC already has access
        check_cmd = f"sudo iptables -C FORWARD -m mac --mac-source {mac_address} -j ACCEPT 2>&1"
        check_result = subprocess.run(check_cmd, shell=True, capture_output=True, text=True)
        
        if check_result.returncode == 0:
            return {
                'success': False,
                'error': 'This device already has WiFi access',
                'mac': mac_address,
                'note': 'Wait for current session to expire or revoke manually'
            }
        
        # Grant access to SPECIFIC MAC address ONLY (per-device control)
        cmd = f"sudo iptables -A FORWARD -m mac --mac-source {mac_address} -j ACCEPT"
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
        
        if result.returncode != 0:
            return {
                'success': False,
                'error': result.stderr or 'iptables command failed'
            }
        
        # Schedule automatic revocation after duration
        revoke_cmd = f"sudo iptables -D FORWARD -m mac --mac-source {mac_address} -j ACCEPT"
        
        # Use 'at' scheduler if available, otherwise log for manual removal
        at_result = subprocess.run(
            f"echo '{revoke_cmd}' | sudo at now + {duration_minutes} minutes 2>&1",
            shell=True, capture_output=True, text=True
        )
        
        return {
            'success': True,
            'message': f'WiFi access granted for {duration_minutes} minutes',
            'mac': mac_address,
            'expires_at': (datetime.now() + timedelta(minutes=duration_minutes)).isoformat(),
            'revoke_scheduled': at_result.returncode == 0,
            'note': 'Internet access enabled for THIS DEVICE ONLY. Other devices must drop their own bottle.',
            'mode': 'per_device'
        }
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

def validate_mac_address(mac):
    """Validate MAC address format"""
    import re
    pattern = r'^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$'
    return bool(re.match(pattern, mac))

def revoke_wifi_access(mac_address):
    """Revoke WiFi access for a device"""
    try:
        cmd = f"sudo iptables -D FORWARD -m mac --mac-source {mac_address} -j ACCEPT"
        subprocess.run(cmd, shell=True, check=True)
        
        return {
            'success': True,
            'message': f'WiFi access revoked for {mac_address}'
        }
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

def get_connected_devices():
    """Get list of connected WiFi devices"""
    try:
        # This varies by WiFi setup (hostapd, dnsmasq, etc.)
        # Example for dnsmasq:
        cmd = "cat /var/lib/misc/dnsmasq.leases"
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
        
        devices = []
        for line in result.stdout.strip().split('\n'):
            if line:
                parts = line.split()
                if len(parts) >= 4:
                    devices.append({
                        'mac': parts[1],
                        'ip': parts[2],
                        'hostname': parts[3]
                    })
        
        return {
            'success': True,
            'devices': devices,
            'count': len(devices)
        }
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({
            'error': 'Usage: wifi_control.py <grant|revoke|list> [mac_address] [duration]'
        }))
        sys.exit(1)
    
    action = sys.argv[1]
    
    if action == 'grant' and len(sys.argv) >= 3:
        mac = sys.argv[2]
        duration = int(sys.argv[3]) if len(sys.argv) > 3 else 5
        result = grant_wifi_access(mac, duration)
        print(json.dumps(result))
    
    elif action == 'revoke' and len(sys.argv) >= 3:
        mac = sys.argv[2]
        result = revoke_wifi_access(mac)
        print(json.dumps(result))
    
    elif action == 'list':
        result = get_connected_devices()
        print(json.dumps(result))
    
    else:
        print(json.dumps({'error': 'Invalid action'}))
        sys.exit(1)
