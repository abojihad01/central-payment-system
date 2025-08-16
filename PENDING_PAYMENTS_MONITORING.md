# Pending Payments Monitoring System

## Overview
This system automatically monitors and processes pending payments to ensure no payments are lost and all customers receive their subscriptions properly.

## Commands Available

### 1. Check and Process Pending Payments
```bash
php artisan payments:check-pending [options]
```

**Options:**
- `--max-age=24` : Maximum age in hours for pending payments to process (default: 24)
- `--limit=50` : Maximum number of payments to process at once (default: 50)  
- `--dry-run` : Show what would be processed without actually processing
- `--quiet` : Run silently (for cron jobs)

**Examples:**
```bash
# Check and process payments older than 24 hours
php artisan payments:check-pending

# Dry run to see what would be processed
php artisan payments:check-pending --dry-run

# Process only very old payments (7+ days)
php artisan payments:check-pending --max-age=168

# Process up to 100 payments silently (for cron)
php artisan payments:check-pending --limit=100 --quiet
```

### 3. Real-Time Payment Monitor (High Frequency)
```bash
php artisan payments:monitor-realtime [options]
```

**Options:**
- `--interval=15` : Check interval in seconds (default: 15)
- `--duration=300` : Total monitoring duration in seconds (default: 5 minutes)  
- `--max-age=0.5` : Maximum age in hours for payments to process (default: 30 minutes)

**Examples:**
```bash
# Monitor for 5 minutes, checking every 15 seconds
php artisan payments:monitor-realtime

# Monitor for 10 minutes, checking every 10 seconds
php artisan payments:monitor-realtime --interval=10 --duration=600

# Monitor very recent payments (last 15 minutes) every 30 seconds
php artisan payments:monitor-realtime --interval=30 --max-age=0.25
```

⚠️ **Warning**: Real-time monitoring creates high server load. Use only when necessary!

### 2. Generate Payments Report
```bash
php artisan payments:report [options]
```

**Options:**
- `--format=table` : Output format (table, json, csv)
- `--email=admin@example.com` : Email address to send report to (future feature)

**Examples:**
```bash
# Generate table report
php artisan payments:report

# Generate JSON report
php artisan payments:report --format=json

# Generate CSV report  
php artisan payments:report --format=csv
```

## Automated Scheduling

The system includes automatic scheduling for monitoring payments:

### Current Schedule:
1. **Every minute**: Check very recent pending payments (last 15 minutes)
2. **Every 5 minutes**: Check pending payments up to 2 hours old
3. **Daily at 2:00 AM**: Cleanup very old pending payments (7+ days)
4. **Weekly on Sunday at 8:00 AM**: Generate pending payments report

### Cron Setup

Add this line to your server's crontab:
```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

Or use individual cron jobs:
```bash
# Check pending payments every 15 minutes
*/15 * * * * cd /path/to/your/project && php artisan payments:check-pending --quiet

# Daily cleanup at 2 AM
0 2 * * * cd /path/to/your/project && php artisan payments:check-pending --max-age=168 --quiet

# Weekly report on Sunday at 8 AM  
0 8 * * 0 cd /path/to/your/project && php artisan payments:report
```

## How It Works

### Processing Logic:
1. **Finds pending payments** older than specified hours
2. **Validates payment data** and related plan/link information
3. **Creates customer record** if doesn't exist
4. **Creates subscription** with proper billing dates
5. **Updates payment status** to 'completed'
6. **Logs all activities** for monitoring

### Safety Features:
- **Without overlapping**: Prevents multiple instances running simultaneously
- **Error handling**: Continues processing even if some payments fail
- **Logging**: All operations are logged for audit trail
- **Dry run mode**: Test what would happen without making changes
- **Limits**: Process only specified number of payments at once

## Monitoring

### Logs Location:
- Laravel logs: `storage/logs/laravel.log`
- Check for: "Pending payments check" entries

### Key Metrics to Monitor:
- Number of pending payments
- Average age of pending payments
- Processing success/failure rates
- Total amount in pending status

### Alerts:
Set up alerts for:
- High number of pending payments (>50)
- Very old pending payments (>48 hours)
- High failure rate in processing
- Large amounts stuck in pending status

## Troubleshooting

### Common Issues:

1. **Payments stuck in pending**
   - Check webhook configuration
   - Verify Stripe/PayPal settings
   - Run manual processing: `php artisan payments:check-pending`

2. **Processing failures**  
   - Check logs for specific error messages
   - Verify plan and customer data integrity
   - Check database permissions

3. **Cron not running**
   - Verify crontab is set up correctly
   - Check server cron service status
   - Test manual execution

### Manual Recovery:
```bash
# Process specific payment by ID
php artisan subscription:create-for-payment {payment_id}

# Check all pending payments
php artisan payments:report

# Force process all pending (careful!)
php artisan payments:check-pending --max-age=0
```

## Best Practices

1. **Monitor regularly**: Check reports weekly
2. **Set up alerts**: Get notified of unusual activity
3. **Test in staging**: Always test processing logic before production
4. **Keep logs**: Retain logs for audit purposes
5. **Backup before processing**: Backup database before bulk operations
6. **Gradual processing**: Use limits to avoid overwhelming the system

## Extreme High-Frequency Monitoring (15 Seconds)

If you absolutely need 15-second checks (not recommended for production):

### Option 1: Using the Real-Time Monitor
```bash
# Run for 1 hour, checking every 15 seconds
php artisan payments:monitor-realtime --interval=15 --duration=3600
```

### Option 2: Using Background Script
```bash
# Run the provided script (modify path first)
./cron-15-seconds.sh &

# To stop the script
pkill -f cron-15-seconds.sh
```

### Option 3: Systemd Service (Linux)
Create `/etc/systemd/system/payments-monitor.service`:
```ini
[Unit]
Description=Real-time Payment Monitor
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/your/project
ExecStart=/path/to/your/project/cron-15-seconds.sh
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Then enable:
```bash
sudo systemctl enable payments-monitor
sudo systemctl start payments-monitor
```

⚠️ **CAUTION**: 15-second monitoring can:
- Cause high CPU usage
- Create excessive database queries
- Generate large log files
- Potentially cause webhook timeouts

## Security Considerations

- Commands run with application permissions
- No sensitive data in logs
- Payment processing is idempotent (safe to retry)
- All operations are logged for audit trail
- High-frequency monitoring requires careful resource management