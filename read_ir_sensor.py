#!/usr/bin/env python3
"""
E3F-DS100C4 NPN NO Proximity Sensor
LOW  = object detected
HIGH = no object
Outputs ONE-SHOT detection with debouncing
"""

import RPi.GPIO as GPIO
import time
import json
import uuid
import sys

# ---------------------
# CONFIG
# ---------------------
SENSOR_PIN = 17
DEBOUNCE_READS = 5
DEBOUNCE_DELAY = 0.01     # 10ms → total debounce ~50ms
STATE_FILE = "/tmp/ir_prev_state.txt"


# ---------------------
# GPIO SETUP
# ---------------------
GPIO.setmode(GPIO.BCM)
GPIO.setup(SENSOR_PIN, GPIO.IN, pull_up_down=GPIO.PUD_UP)


# ---------------------
# Helpers
# ---------------------
def read_sensor_debounced():
    readings = []
    for _ in range(DEBOUNCE_READS):
        readings.append(GPIO.input(SENSOR_PIN))
        time.sleep(DEBOUNCE_DELAY)

    # return stable value (0=detected, 1=no object)
    return 0 if readings.count(0) > readings.count(1) else 1


def load_prev_state():
    try:
        with open(STATE_FILE, "r") as f:
            return int(f.read().strip())
    except:
        return 1    # default HIGH (no object)


def save_prev_state(state):
    try:
        with open(STATE_FILE, "w") as f:
            f.write(str(state))
    except:
        pass


# ---------------------
# MAIN LOGIC
# ---------------------
try:
    prev = load_prev_state()
    current = read_sensor_debounced()

    # ONE-SHOT detection: only trigger when HIGH → LOW
    if prev == 1 and current == 0:
        detected = True
        verification_token = str(uuid.uuid4())
    else:
        detected = False
        verification_token = None

    # Save the state for next run
    save_prev_state(current)

    # Output JSON
    data = {
        "detected": detected,
        "status": "bottle_detected" if detected else "waiting",
        "gpio_state": "LOW" if current == 0 else "HIGH",
        "sensor_type": "E3F-DS100C4 NPN NO",
        "verification_token": verification_token,
        "pin": SENSOR_PIN
    }

    print(json.dumps(data, separators=(",", ":")))
    sys.stdout.flush()

finally:
    GPIO.cleanup()
