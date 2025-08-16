#!/bin/bash

# Real-time Payment Monitoring Script
# This script checks pending payments every 15 seconds
# WARNING: Use this only when absolutely necessary - high server load!

PROJECT_PATH="/path/to/your/central-payment-system"
LOG_FILE="/var/log/realtime-payments.log"

# Function to log with timestamp
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> $LOG_FILE
}

# Function to check payments
check_payments() {
    cd $PROJECT_PATH
    php artisan payments:check-pending --max-age=0.25 --limit=5 --quiet >> $LOG_FILE 2>&1
    
    if [ $? -eq 0 ]; then
        log_message "Payment check completed successfully"
    else
        log_message "ERROR: Payment check failed"
    fi
}

# Main loop
log_message "Starting real-time payment monitoring (15 second intervals)"

while true; do
    check_payments
    sleep 15
done