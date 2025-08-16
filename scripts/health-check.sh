#!/bin/bash

# Central Payment System - Health Check Script
# Usage: ./scripts/health-check.sh

APP_DIR="/var/www/central-payment-system"
APP_URL="https://your-domain.com"
LOG_FILE="/var/log/central-payment-system/health-check.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Counters
CHECKS_TOTAL=0
CHECKS_PASSED=0
CHECKS_FAILED=0

print_status() {
    echo -e "${BLUE}[CHECK]${NC} $1"
    echo "$(date): [CHECK] $1" >> "$LOG_FILE"
}

print_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    echo "$(date): [PASS] $1" >> "$LOG_FILE"
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
}

print_error() {
    echo -e "${RED}[FAIL]${NC} $1"
    echo "$(date): [FAIL] $1" >> "$LOG_FILE"
    CHECKS_FAILED=$((CHECKS_FAILED + 1))
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    echo "$(date): [WARN] $1" >> "$LOG_FILE"
}

check_service() {
    CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
    if systemctl is-active --quiet "$1"; then
        print_success "$1 service is running"
    else
        print_error "$1 service is not running"
    fi
}

check_port() {
    CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
    if netstat -tuln | grep -q ":$2 "; then
        print_success "$1 is listening on port $2"
    else
        print_error "$1 is not listening on port $2"
    fi
}

check_url() {
    CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$1")
    if [ "$HTTP_STATUS" = "$2" ]; then
        print_success "URL $1 returned expected status $2"
    else
        print_error "URL $1 returned status $HTTP_STATUS (expected $2)"
    fi
}

# Create log directory
mkdir -p "$(dirname "$LOG_FILE")"

echo "🔍 Central Payment System - Health Check"
echo "========================================"
echo "Date: $(date)"
echo "Server: $(hostname)"
echo ""

# 1. System Services
print_status "Checking system services..."
check_service "nginx"
check_service "php8.2-fpm"
check_service "mysql"
check_service "redis-server"
check_service "laravel-worker"

# 2. Network Ports
print_status "Checking network ports..."
check_port "HTTP" 80
check_port "HTTPS" 443
check_port "MySQL" 3306
check_port "Redis" 6379

# 3. Web Application
print_status "Checking web application..."
cd "$APP_DIR" || exit 1

# Check if Laravel is responding
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
if php artisan route:list &>/dev/null; then
    print_success "Laravel routes are accessible"
else
    print_error "Laravel routes check failed"
fi

# Check environment
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
ENV=$(php artisan env 2>/dev/null)
if [ "$ENV" = "production" ]; then
    print_success "Application environment is production"
else
    print_error "Application environment is $ENV (expected: production)"
fi

# 4. Database Connectivity
print_status "Checking database connectivity..."
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
if php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>/dev/null | grep -q "OK"; then
    print_success "Database connection is working"
else
    print_error "Database connection failed"
fi

# Check database tables
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
TABLE_COUNT=$(php artisan tinker --execute="echo DB::select('SHOW TABLES') ? count(DB::select('SHOW TABLES')) : 0;" 2>/dev/null | tail -n1)
if [ "$TABLE_COUNT" -gt 10 ]; then
    print_success "Database has $TABLE_COUNT tables"
else
    print_error "Database has insufficient tables ($TABLE_COUNT)"
fi

# 5. Cache System
print_status "Checking cache system..."
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
if php artisan cache:clear &>/dev/null; then
    print_success "Cache system is working"
else
    print_error "Cache system failed"
fi

# 6. Queue System
print_status "Checking queue system..."
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
QUEUE_SIZE=$(php artisan queue:size 2>/dev/null | grep -o '[0-9]\+' | head -n1)
if [ ! -z "$QUEUE_SIZE" ]; then
    print_success "Queue system is working (size: $QUEUE_SIZE)"
else
    print_error "Queue system check failed"
fi

# Check if queue workers are running
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
WORKER_COUNT=$(pgrep -f "php.*artisan.*queue:work" | wc -l)
if [ "$WORKER_COUNT" -gt 0 ]; then
    print_success "Queue workers are running ($WORKER_COUNT processes)"
else
    print_error "No queue workers are running"
fi

# 7. File Permissions
print_status "Checking file permissions..."
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
if [ -w "$APP_DIR/storage/logs" ]; then
    print_success "Storage logs directory is writable"
else
    print_error "Storage logs directory is not writable"
fi

CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
if [ -w "$APP_DIR/storage/framework" ]; then
    print_success "Storage framework directory is writable"
else
    print_error "Storage framework directory is not writable"
fi

# 8. SSL Certificate
print_status "Checking SSL certificate..."
if command -v openssl &> /dev/null; then
    CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
    CERT_EXPIRY=$(echo | openssl s_client -servername your-domain.com -connect your-domain.com:443 2>/dev/null | openssl x509 -noout -dates | grep notAfter | cut -d= -f2)
    if [ ! -z "$CERT_EXPIRY" ]; then
        EXPIRY_TIMESTAMP=$(date -d "$CERT_EXPIRY" +%s)
        CURRENT_TIMESTAMP=$(date +%s)
        DAYS_UNTIL_EXPIRY=$(( (EXPIRY_TIMESTAMP - CURRENT_TIMESTAMP) / 86400 ))
        
        if [ "$DAYS_UNTIL_EXPIRY" -gt 30 ]; then
            print_success "SSL certificate is valid (expires in $DAYS_UNTIL_EXPIRY days)"
        elif [ "$DAYS_UNTIL_EXPIRY" -gt 7 ]; then
            print_warning "SSL certificate expires in $DAYS_UNTIL_EXPIRY days"
        else
            print_error "SSL certificate expires in $DAYS_UNTIL_EXPIRY days"
        fi
    else
        print_error "Could not check SSL certificate"
    fi
fi

# 9. HTTP Endpoints
print_status "Checking HTTP endpoints..."
check_url "$APP_URL" "200"
check_url "$APP_URL/api/health" "200"

# 10. Disk Space
print_status "Checking disk space..."
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
DISK_USAGE=$(df "$APP_DIR" | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -lt 80 ]; then
    print_success "Disk usage is $DISK_USAGE% (healthy)"
elif [ "$DISK_USAGE" -lt 90 ]; then
    print_warning "Disk usage is $DISK_USAGE% (monitor closely)"
else
    print_error "Disk usage is $DISK_USAGE% (critical)"
fi

# 11. Memory Usage
print_status "Checking memory usage..."
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
MEMORY_USAGE=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
if [ "$MEMORY_USAGE" -lt 80 ]; then
    print_success "Memory usage is $MEMORY_USAGE% (healthy)"
elif [ "$MEMORY_USAGE" -lt 90 ]; then
    print_warning "Memory usage is $MEMORY_USAGE% (monitor closely)"
else
    print_error "Memory usage is $MEMORY_USAGE% (critical)"
fi

# 12. Error Logs
print_status "Checking for recent errors..."
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
ERROR_COUNT=$(find "$APP_DIR/storage/logs" -name "*.log" -mtime -1 -exec grep -l "ERROR\|CRITICAL\|EMERGENCY" {} \; | wc -l)
if [ "$ERROR_COUNT" -eq 0 ]; then
    print_success "No critical errors in last 24 hours"
else
    print_warning "Found $ERROR_COUNT log files with errors in last 24 hours"
fi

# Summary
echo ""
echo "🔍 Health Check Summary"
echo "======================="
echo "Total Checks: $CHECKS_TOTAL"
echo -e "Passed: ${GREEN}$CHECKS_PASSED${NC}"
echo -e "Failed: ${RED}$CHECKS_FAILED${NC}"

HEALTH_PERCENTAGE=$((CHECKS_PASSED * 100 / CHECKS_TOTAL))
echo "Health Score: $HEALTH_PERCENTAGE%"

if [ "$CHECKS_FAILED" -eq 0 ]; then
    echo -e "${GREEN}✅ System is healthy${NC}"
    exit 0
elif [ "$HEALTH_PERCENTAGE" -ge 80 ]; then
    echo -e "${YELLOW}⚠️ System has minor issues${NC}"
    exit 1
else
    echo -e "${RED}❌ System has critical issues${NC}"
    exit 2
fi