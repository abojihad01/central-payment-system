#!/bin/bash

# Central Payment System - Production Deployment Script
# Usage: ./deploy.sh

echo "🚀 Starting Central Payment System Production Deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then 
    print_error "Don't run this script as root!"
    exit 1
fi

# Step 1: Backup current deployment
print_status "Creating backup of current deployment..."
if [ -d "storage/backups" ]; then
    mkdir -p storage/backups
fi
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
tar -czf storage/backups/deployment_backup_$BACKUP_DATE.tar.gz \
    --exclude='storage/backups' \
    --exclude='node_modules' \
    --exclude='.git' \
    . 2>/dev/null

if [ $? -eq 0 ]; then
    print_success "Backup created: storage/backups/deployment_backup_$BACKUP_DATE.tar.gz"
else
    print_warning "Backup creation failed, continuing anyway..."
fi

# Step 2: Pull latest code
print_status "Pulling latest code from repository..."
git pull origin main
if [ $? -ne 0 ]; then
    print_error "Git pull failed!"
    exit 1
fi

# Step 3: Install/Update dependencies
print_status "Installing/updating Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
if [ $? -ne 0 ]; then
    print_error "Composer install failed!"
    exit 1
fi

print_status "Installing/updating NPM dependencies..."
npm ci --production
if [ $? -ne 0 ]; then
    print_error "NPM install failed!"
    exit 1
fi

# Step 4: Build frontend assets
print_status "Building production assets..."
npm run build
if [ $? -ne 0 ]; then
    print_error "Asset build failed!"
    exit 1
fi

# Step 5: Environment setup
print_status "Setting up production environment..."
if [ ! -f ".env" ]; then
    if [ -f ".env.production" ]; then
        cp .env.production .env
        print_status "Copied .env.production to .env"
    else
        print_error "No .env file found! Please create one based on .env.production"
        exit 1
    fi
fi

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env; then
    print_status "Generating application key..."
    php artisan key:generate --force
fi

# Step 6: Database operations
print_status "Running database migrations..."
php artisan migrate --force
if [ $? -ne 0 ]; then
    print_error "Database migration failed!"
    exit 1
fi

# Step 7: Clear and cache configuration
print_status "Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

print_status "Caching configuration for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Step 8: Set proper permissions
print_status "Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs
chmod -R 775 storage/framework
chmod -R 775 storage/app

# Step 9: Restart services
print_status "Restarting services..."

# Restart PHP-FPM (adjust service name as needed)
if systemctl is-active --quiet php8.2-fpm; then
    sudo systemctl reload php8.2-fpm
    print_success "PHP-FPM reloaded"
elif systemctl is-active --quiet php-fpm; then
    sudo systemctl reload php-fpm
    print_success "PHP-FPM reloaded"
fi

# Restart queue workers
if systemctl is-active --quiet laravel-worker; then
    sudo systemctl restart laravel-worker
    print_success "Laravel worker restarted"
else
    print_warning "Laravel worker service not found. You may need to restart queue workers manually."
fi

# Restart web server (Nginx)
if systemctl is-active --quiet nginx; then
    sudo systemctl reload nginx
    print_success "Nginx reloaded"
fi

# Step 10: Health check
print_status "Performing health check..."
sleep 5

# Check if the application is responding
if command -v curl &> /dev/null; then
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost)
    if [ "$HTTP_STATUS" = "200" ]; then
        print_success "Application is responding (HTTP $HTTP_STATUS)"
    else
        print_warning "Application returned HTTP $HTTP_STATUS"
    fi
else
    print_warning "curl not available, skipping HTTP health check"
fi

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connected successfully';" 2>/dev/null
if [ $? -eq 0 ]; then
    print_success "Database connection verified"
else
    print_warning "Database connection check failed"
fi

# Check queue worker
if pgrep -f "php.*artisan.*queue:work" > /dev/null; then
    print_success "Queue worker is running"
else
    print_warning "Queue worker may not be running"
fi

# Step 11: Final steps
print_status "Running post-deployment tasks..."

# Create symbolic link for storage (if not exists)
if [ ! -L "public/storage" ]; then
    php artisan storage:link
    print_success "Storage symbolic link created"
fi

# Run any custom artisan commands
if [ -f "artisan" ]; then
    # Create production admin user
    php artisan db:seed --class=ProductionAdminSeeder --force 2>/dev/null
    
    # Clear expired sessions
    php artisan session:gc
    
    # Update fraud detection rules
    php artisan fraud:update-rules 2>/dev/null
fi

print_success "🎉 Deployment completed successfully!"
print_status "Deployment Summary:"
echo "  - Backup: storage/backups/deployment_backup_$BACKUP_DATE.tar.gz"
echo "  - Environment: $(php artisan env)"
echo "  - Laravel Version: $(php artisan --version)"
echo "  - PHP Version: $(php -v | head -n1)"

print_warning "⚠️  Remember to:"
echo "  1. Update payment gateway webhooks if URLs changed"
echo "  2. Test critical payment flows"
echo "  3. Monitor error logs for the next 24 hours"
echo "  4. Verify SSL certificates are valid"
echo "  5. Test fraud detection rules"

echo ""
print_success "🚀 Central Payment System is now live in production!"