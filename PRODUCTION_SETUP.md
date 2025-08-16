# 🚀 Central Payment System - Production Setup Guide

## 📋 Overview
This guide provides comprehensive instructions for deploying the Central Payment System to production environment.

## ⚠️ Prerequisites
- Ubuntu 20.04+ or CentOS 8+ server
- Minimum 4GB RAM, 2 CPU cores
- 50GB+ storage space
- Domain name with SSL certificate
- Database server (MySQL 8.0+)
- Redis server

## 🔧 Server Setup

### 1. Install Required Software
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2 and extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-redis php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath

# Install Nginx
sudo apt install -y nginx

# Install MySQL
sudo apt install -y mysql-server-8.0

# Install Redis
sudo apt install -y redis-server

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Create Application User
```bash
sudo useradd -m -s /bin/bash payment-system
sudo usermod -a -G www-data payment-system
```

### 3. Database Setup
```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p << 'EOF'
CREATE DATABASE central_payment_system_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'payment_user'@'localhost' IDENTIFIED BY 'SECURE_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON central_payment_system_prod.* TO 'payment_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

## 📂 Application Deployment

### 1. Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/your-repo/central-payment-system.git
sudo chown -R payment-system:www-data central-payment-system
cd central-payment-system
```

### 2. Environment Configuration
```bash
# Copy production environment file
cp .env.production .env

# Edit environment variables
nano .env

# Required changes:
# - APP_URL=https://your-domain.com
# - DB_PASSWORD=your_secure_password
# - STRIPE_KEY=pk_live_...
# - STRIPE_SECRET=sk_live_...
# - PAYPAL_CLIENT_ID=live_client_id
# - PAYPAL_CLIENT_SECRET=live_secret
```

### 3. Install Dependencies
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
npm ci --production

# Build assets
npm run build

# Generate application key
php artisan key:generate

# Set permissions
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs storage/framework storage/app
```

### 4. Database Migration
```bash
# Run migrations
php artisan migrate --force

# Create production admin user
php artisan db:seed --class=ProductionAdminSeeder --force
```

### 5. Cache Optimization
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## 🔒 Security Configuration

### 1. SSL Certificate
```bash
# Install Certbot for Let's Encrypt
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

### 2. Firewall Setup
```bash
# Configure UFW
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable

# Block direct access to sensitive ports
sudo ufw deny 3306  # MySQL
sudo ufw deny 6379  # Redis
```

### 3. File Permissions
```bash
# Set secure permissions
find /var/www/central-payment-system -type f -exec chmod 644 {} \;
find /var/www/central-payment-system -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/central-payment-system/storage
chmod -R 775 /var/www/central-payment-system/bootstrap/cache
chmod 600 /var/www/central-payment-system/.env
```

## ⚙️ Service Configuration

### 1. Nginx Configuration
```bash
# Copy Nginx configuration
sudo cp deployment/nginx.conf /etc/nginx/sites-available/central-payment-system
sudo ln -s /etc/nginx/sites-available/central-payment-system /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test and reload Nginx
sudo nginx -t
sudo systemctl reload nginx
```

### 2. PHP-FPM Configuration
```bash
# Edit PHP-FPM pool configuration
sudo nano /etc/php/8.2/fpm/pool.d/www.conf

# Recommended changes:
# pm = dynamic
# pm.max_children = 20
# pm.start_servers = 5
# pm.min_spare_servers = 3
# pm.max_spare_servers = 10
# pm.max_requests = 1000

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 3. Queue Worker Service
```bash
# Copy service file
sudo cp deployment/laravel-worker.service /etc/systemd/system/

# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable laravel-worker
sudo systemctl start laravel-worker
```

### 4. Redis Configuration
```bash
# Edit Redis configuration
sudo nano /etc/redis/redis.conf

# Recommended changes:
# bind 127.0.0.1
# requireauth your_redis_password
# maxmemory 256mb
# maxmemory-policy allkeys-lru

# Restart Redis
sudo systemctl restart redis-server
```

## 📊 Monitoring & Logging

### 1. Log Rotation
```bash
# Create logrotate configuration
sudo tee /etc/logrotate.d/central-payment-system << 'EOF'
/var/www/central-payment-system/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    copytruncate
}
EOF
```

### 2. Cron Jobs
```bash
# Add Laravel scheduler
echo "* * * * * cd /var/www/central-payment-system && php artisan schedule:run >> /dev/null 2>&1" | sudo crontab -u payment-system -

# Add backup script
echo "0 2 * * * /var/www/central-payment-system/scripts/backup.sh" | sudo crontab -u payment-system -

# Add health check
echo "*/5 * * * * /var/www/central-payment-system/scripts/health-check.sh" | sudo crontab -u payment-system -
```

### 3. Health Monitoring
```bash
# Make scripts executable
chmod +x scripts/health-check.sh
chmod +x scripts/backup.sh

# Test health check
./scripts/health-check.sh
```

## 🔄 Deployment Process

### Automated Deployment
```bash
# Use the deployment script
./deploy.sh

# Or manual deployment:
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci --production && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.2-fpm
sudo systemctl restart laravel-worker
```

## 🧪 Testing Production Setup

### 1. Basic Functionality Test
```bash
# Test web server
curl -I https://your-domain.com

# Test API endpoints
curl https://your-domain.com/api/health

# Test admin login
# Navigate to https://your-domain.com/admin
```

### 2. Payment Gateway Tests
```bash
# Test Stripe webhook
curl -X POST https://your-domain.com/webhooks/stripe \
  -H "Content-Type: application/json" \
  -d '{"test": true}'

# Test PayPal webhook
curl -X POST https://your-domain.com/webhooks/paypal \
  -H "Content-Type: application/json" \
  -d '{"test": true}'
```

### 3. Queue System Test
```bash
# Check queue worker status
sudo systemctl status laravel-worker

# Test job processing
php artisan queue:work --once
```

## 🔐 Security Checklist

- [ ] SSL certificate installed and configured
- [ ] Environment variables secured (.env file permissions)
- [ ] Database credentials are strong
- [ ] Payment gateway webhooks configured
- [ ] Admin user has strong password
- [ ] Two-factor authentication enabled
- [ ] Firewall configured
- [ ] Security headers configured in Nginx
- [ ] File permissions properly set
- [ ] Debug mode disabled (APP_DEBUG=false)
- [ ] Error reporting minimized
- [ ] Rate limiting configured

## 📈 Performance Optimization

### 1. Database Optimization
```bash
# Create database indexes
php artisan db:seed --class=DatabaseIndexSeeder
```

### 2. Caching
```bash
# Configure Redis caching
echo "CACHE_DRIVER=redis" >> .env
echo "SESSION_DRIVER=redis" >> .env
echo "QUEUE_CONNECTION=redis" >> .env

# Clear and rebuild cache
php artisan cache:clear
php artisan config:cache
```

### 3. Asset Optimization
```bash
# Enable Gzip compression in Nginx (already configured)
# Configure CDN for static assets (optional)
```

## 🆘 Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R payment-system:www-data /var/www/central-payment-system
   chmod -R 775 storage bootstrap/cache
   ```

2. **Database Connection Issues**
   ```bash
   # Test database connection
   php artisan tinker --execute="DB::connection()->getPdo();"
   ```

3. **Queue Worker Not Processing Jobs**
   ```bash
   sudo systemctl restart laravel-worker
   sudo systemctl status laravel-worker
   ```

4. **SSL Certificate Issues**
   ```bash
   sudo certbot renew --dry-run
   sudo nginx -t
   ```

## 📞 Support & Maintenance

### Daily Tasks
- Monitor system health via health-check script
- Review error logs
- Check queue processing

### Weekly Tasks
- Review backup integrity
- Update dependencies (if needed)
- Security patch updates

### Monthly Tasks
- SSL certificate renewal check
- Performance review
- Security audit

## 🔄 Backup & Recovery

### Automated Backups
```bash
# Backups are automatically created daily at 2 AM
# Location: /var/backups/central-payment-system/
# Retention: 30 days
```

### Manual Backup
```bash
./scripts/backup.sh
```

### Recovery Process
```bash
# Database recovery
gunzip database_YYYYMMDD_HHMMSS.sql.gz
mysql -u payment_user -p central_payment_system_prod < database_YYYYMMDD_HHMMSS.sql

# Application recovery
tar -xzf application_YYYYMMDD_HHMMSS.tar.gz -C /var/www/central-payment-system
```

---

## 📞 Emergency Contacts

- **System Administrator**: [Your Contact]
- **Database Administrator**: [Your Contact]
- **Payment Gateway Support**: 
  - Stripe: https://support.stripe.com
  - PayPal: https://www.paypal.com/support

## 📚 Additional Resources

- [Laravel Production Deployment](https://laravel.com/docs/deployment)
- [Nginx Security Guide](https://nginx.org/en/docs/http/nginx_security.html)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)

---

**⚠️ Important**: Always test deployments in staging environment before production!