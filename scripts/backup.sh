#!/bin/bash

# Central Payment System - Automated Backup Script
# Usage: ./scripts/backup.sh

# Configuration
BACKUP_DIR="/var/backups/central-payment-system"
APP_DIR="/var/www/central-payment-system"
DB_NAME="central_payment_system_prod"
DB_USER="payment_user"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${BLUE}[BACKUP]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Create backup directory
mkdir -p "$BACKUP_DIR"
cd "$BACKUP_DIR"

print_status "Starting backup process for Central Payment System..."

# 1. Database Backup
print_status "Creating database backup..."
if mysqldump -u "$DB_USER" -p "$DB_NAME" > "database_$DATE.sql" 2>/dev/null; then
    gzip "database_$DATE.sql"
    print_success "Database backup created: database_$DATE.sql.gz"
else
    print_error "Database backup failed!"
    exit 1
fi

# 2. Application Files Backup
print_status "Creating application files backup..."
tar -czf "application_$DATE.tar.gz" \
    -C "$APP_DIR" \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='storage/backups/*' \
    --exclude='node_modules' \
    --exclude='.git' \
    .

if [ $? -eq 0 ]; then
    print_success "Application backup created: application_$DATE.tar.gz"
else
    print_error "Application backup failed!"
    exit 1
fi

# 3. Storage Files Backup (user uploads, etc.)
print_status "Creating storage files backup..."
if [ -d "$APP_DIR/storage/app/public" ]; then
    tar -czf "storage_$DATE.tar.gz" -C "$APP_DIR" storage/app/public/
    if [ $? -eq 0 ]; then
        print_success "Storage backup created: storage_$DATE.tar.gz"
    else
        print_error "Storage backup failed!"
    fi
else
    print_status "No storage files to backup"
fi

# 4. Configuration Backup
print_status "Creating configuration backup..."
tar -czf "config_$DATE.tar.gz" \
    -C "$APP_DIR" \
    .env \
    config/ \
    2>/dev/null

if [ $? -eq 0 ]; then
    print_success "Configuration backup created: config_$DATE.tar.gz"
else
    print_error "Configuration backup failed!"
fi

# 5. SSL Certificates Backup (if exists)
if [ -d "/etc/ssl/certs" ] && [ -f "/etc/ssl/certs/your-domain.crt" ]; then
    print_status "Creating SSL certificates backup..."
    tar -czf "ssl_$DATE.tar.gz" \
        -C /etc/ssl \
        certs/your-domain.crt \
        private/your-domain.key \
        2>/dev/null
    
    if [ $? -eq 0 ]; then
        print_success "SSL certificates backup created: ssl_$DATE.tar.gz"
    fi
fi

# 6. Create backup manifest
print_status "Creating backup manifest..."
cat > "manifest_$DATE.txt" << EOF
Central Payment System - Backup Manifest
========================================
Date: $(date)
Backup ID: $DATE
Server: $(hostname)
Laravel Version: $(cd "$APP_DIR" && php artisan --version)

Backup Contents:
- Database: database_$DATE.sql.gz
- Application: application_$DATE.tar.gz
- Storage: storage_$DATE.tar.gz
- Configuration: config_$DATE.tar.gz
- SSL Certificates: ssl_$DATE.tar.gz

Backup Location: $BACKUP_DIR

Restoration Commands:
1. Database: gunzip database_$DATE.sql.gz && mysql -u $DB_USER -p $DB_NAME < database_$DATE.sql
2. Application: tar -xzf application_$DATE.tar.gz -C $APP_DIR
3. Storage: tar -xzf storage_$DATE.tar.gz -C $APP_DIR
4. Configuration: tar -xzf config_$DATE.tar.gz -C $APP_DIR

Security Note: These backups may contain sensitive information.
Store securely and delete old backups according to retention policy.
EOF

print_success "Backup manifest created: manifest_$DATE.txt"

# 7. Calculate backup sizes
print_status "Calculating backup sizes..."
TOTAL_SIZE=0
for file in *_$DATE.*; do
    if [ -f "$file" ]; then
        SIZE=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
        SIZE_MB=$((SIZE / 1024 / 1024))
        echo "  $file: ${SIZE_MB} MB"
        TOTAL_SIZE=$((TOTAL_SIZE + SIZE))
    fi
done

TOTAL_SIZE_MB=$((TOTAL_SIZE / 1024 / 1024))
print_success "Total backup size: ${TOTAL_SIZE_MB} MB"

# 8. Cleanup old backups
print_status "Cleaning up old backups (older than $RETENTION_DAYS days)..."
find "$BACKUP_DIR" -name "*_[0-9]*.*" -type f -mtime +$RETENTION_DAYS -delete
CLEANED=$(find "$BACKUP_DIR" -name "*_[0-9]*.*" -type f -mtime +$RETENTION_DAYS 2>/dev/null | wc -l)
if [ "$CLEANED" -gt 0 ]; then
    print_success "Cleaned up $CLEANED old backup files"
else
    print_status "No old backups to clean up"
fi

# 9. Upload to cloud storage (if configured)
if [ ! -z "$AWS_S3_BUCKET" ]; then
    print_status "Uploading to AWS S3..."
    aws s3 sync "$BACKUP_DIR" "s3://$AWS_S3_BUCKET/backups/central-payment-system/" \
        --exclude "*" \
        --include "*_$DATE.*" \
        --storage-class STANDARD_IA
    
    if [ $? -eq 0 ]; then
        print_success "Backup uploaded to S3"
    else
        print_error "S3 upload failed"
    fi
fi

# 10. Send notification (if configured)
if [ ! -z "$BACKUP_EMAIL" ]; then
    print_status "Sending backup notification..."
    echo "Central Payment System backup completed successfully.
    
Backup ID: $DATE
Total Size: ${TOTAL_SIZE_MB} MB
Location: $BACKUP_DIR

$(cat manifest_$DATE.txt)" | \
    mail -s "Central Payment System - Backup Completed ($DATE)" "$BACKUP_EMAIL"
fi

print_success "🎉 Backup process completed successfully!"
print_status "Backup ID: $DATE"
print_status "Location: $BACKUP_DIR"
print_status "Total Size: ${TOTAL_SIZE_MB} MB"

echo ""
print_status "Next steps:"
echo "  1. Verify backup integrity"
echo "  2. Test restoration procedure"
echo "  3. Store backup securely"
echo "  4. Update disaster recovery documentation"