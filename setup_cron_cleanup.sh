#!/bin/bash
# Setup automatic session cleanup every minute

echo "Setting up automatic session cleanup..."

# Add cron job to run cleanup every minute
CRON_CMD="* * * * * /usr/bin/php /var/www/html/cleanup_sessions.php >> /var/log/bottle_wifi_cleanup.log 2>&1"

# Check if cron job already exists
(crontab -l 2>/dev/null | grep -F "cleanup_sessions.php") && echo "Cron job already exists" || (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -

echo "✓ Cron job added to run cleanup every minute"
echo ""
echo "Check logs with: sudo tail -f /var/log/bottle_wifi_cleanup.log"
echo ""
echo "Manual cleanup: php cleanup_sessions.php"
