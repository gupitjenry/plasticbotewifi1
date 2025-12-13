# Hardware Setup Guide

## 📦 Components Needed

- Raspberry Pi 3/4/5
- IR Sensor Module (with DO/AO pins)
- Breadboard & jumper wires (optional)

## 🔌 GPIO Pin Configuration

### IR Sensor
```
IR Sensor DO (Digital Out) → GPIO 2 (or test pins 2, 3, 4, 17, 22, 27)
IR Sensor GND → Ground
IR Sensor VCC → 5V
```

The IR sensor has a built-in red LED that blinks when an object is detected.

## 🚀 Installation Steps

### 1. Install Python Dependencies
```bash
sudo apt-get update
sudo apt-get install python3-rpi.gpio python3-pip
pip3 install RPi.GPIO
```

### 2. Configure GPIO Permissions
```bash
# Add www-data user to gpio group (for Apache)
sudo usermod -a -G gpio www-data

# Restart Apache
sudo systemctl restart apache2
```

### 3. Test IR Sensor
```bash
# Run Python script directly
python3 /var/www/html/bottle_wifi/read_ir_sensor.py

# Should output JSON like:
# {"detected": false, "pin": 2, "status": "waiting"}
```

### 4. Configure Python Script
Update GPIO pin in `read_ir_sensor.py` if different from default:

**read_ir_sensor.py (Line 13):**
```python
IR_PIN = 2  # Change if your sensor is on different pin
```

## 🧪 Testing Workflow

1. **Test IR Sensor Alone:**
   ```bash
   gpio read 2
   # Point bottle at sensor, should show "1" when detected
   ```

2. **Test via Python Script:**
   ```bash
   python3 read_ir_sensor.py
   # Should output JSON
   ```

3. **Test Full Portal:**
   - Open http://localhost:8000/index.php
   - Click "Start Recycling"
   - Point bottle at sensor
   - Watch the countdown end and WiFi granted message appear
   - IR sensor's red LED will blink when object is detected

## ⚠️ Troubleshooting

### IR Sensor Not Working
```bash
# Check if GPIO is accessible
gpio readall

# Test your specific pin
gpio read 2
gpio read 3
gpio read 4
# etc.
```

### Python Script Permission Denied
```bash
# Check file permissions
ls -la read_ir_sensor.py
chmod +x read_ir_sensor.py

# Try running with sudo
sudo python3 read_ir_sensor.py
```

### Sensor Not Polling in Website
```bash
# Check PHP error log
tail -f /var/log/apache2/error.log

# Verify PHP can execute shell commands
php -r "echo shell_exec('echo test');"
```

## 🔐 Security Notes

- Never run `gpio` commands as root unless necessary
- Test all hardware connections before powering on
- Ensure proper power supply (5V) for IR sensor

## 📁 Hardware Scripts

- **read_ir_sensor.py** - IR detection polling

