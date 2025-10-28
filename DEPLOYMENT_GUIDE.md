# FlexStock Production Deployment Guide

## 🚀 Pre-Deployment Checklist

### Phase 1 & 2 Features Verification

- [x] Database hardening (indexes + foreign keys)
- [x] Atomic InventoryService with row-level locking
- [x] Document numbering system
- [x] Stock API endpoints
- [x] CustomFieldRegistry with dynamic validation
- [x] Payment slip approval workflow
- [x] Report consistency filters
- [x] RBAC middleware
- [x] Rate limiting (4-tier strategy)
- [x] CORS configuration
- [x] DemoSeeder updated
- [x] Feature tests (InventoryServiceTest example)
- [x] GitHub Actions CI/CD pipeline

## 📋 Environment Setup

### 1. Server Requirements

- **PHP**: 8.1 or 8.2
- **Database**: MySQL 8.0+
- **Cache/Queue**: Redis 7+
- **Web Server**: Nginx or Apache with SSL
- **Storage**: Min 10GB for file uploads (payment slips)

### 2. Environment Variables

Copy and configure `.env`:

```env
# Application
APP_NAME=FlexStock
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # Generate with: php artisan key:generate
APP_URL=https://api.flexstock.example.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexstock_prod
DB_USERNAME=flexstock_user
DB_PASSWORD=                  # Strong password

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# CORS
CORS_ALLOWED_ORIGINS=https://app.flexstock.com,https://admin.flexstock.com

# File Storage
FILESYSTEM_DISK=public

# Mail (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls

# Sanctum
SANCTUM_STATEFUL_DOMAINS=app.flexstock.com,admin.flexstock.com
SESSION_DOMAIN=.flexstock.com
```

## 🔧 Installation Steps

### 1. Clone & Install Dependencies

```bash
# Clone repository
git clone https://github.com/yourusername/flexstock.git
cd flexstock

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 2. Run Migrations

```bash
# Run all migrations (including Phase 1 & 2 enhancements)
php artisan migrate --force

# Verify migrations
php artisan migrate:status
```

Expected migrations:
- ✅ `2025_01_01_*` - Core tables
- ✅ `2025_10_22_052025` - Database hardening
- ✅ `2025_10_22_052236` - Document counters & payment slips

### 3. Create Storage Link

```bash
php artisan storage:link
```

This creates: `public/storage` → `storage/app/public` for payment slip access.

### 4. Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 5. Set Up Queue Worker

Create systemd service `/etc/systemd/system/flexstock-queue.service`:

```ini
[Unit]
Description=FlexStock Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/flexstock
ExecStart=/usr/bin/php /var/www/flexstock/artisan queue:work redis --sleep=3 --tries=3
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable flexstock-queue
sudo systemctl start flexstock-queue
```

## 🔒 Security Hardening

### 1. File Permissions

```bash
# Application files (read-only for web server)
find /var/www/flexstock -type f -exec chmod 644 {} \;
find /var/www/flexstock -type d -exec chmod 755 {} \;

# Writable directories
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Protect sensitive files
chmod 600 .env
chown www-data:www-data .env
```

### 2. Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name api.flexstock.example.com;

    ssl_certificate /etc/letsencrypt/live/api.flexstock.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.flexstock.example.com/privkey.pem;

    root /var/www/flexstock/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api_limit:10m rate=60r/m;
    limit_req_zone $binary_remote_addr zone=auth_limit:10m rate=5r/m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /api/login {
        limit_req zone=auth_limit burst=2 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /api/ {
        limit_req zone=api_limit burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 3. Database Security

```sql
-- Create dedicated database user
CREATE USER 'flexstock_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON flexstock_prod.* TO 'flexstock_user'@'localhost';
FLUSH PRIVILEGES;

-- Regular backups
mysqldump -u root -p flexstock_prod > backup_$(date +%Y%m%d).sql
```

## 🧪 Testing in Production

### Smoke Test Sequence

```bash
# 1. Login
curl -X POST https://api.flexstock.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.com","password":"password123"}'

# Save token
TOKEN="your_token_here"

# 2. Check products
curl https://api.flexstock.com/api/demo-sme/products \
  -H "Authorization: Bearer $TOKEN"

# 3. Adjust stock (admin only)
curl -X POST https://api.flexstock.com/api/demo-sme/stock/adjust \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 1,
    "qty_delta": 100,
    "ref_type": "OPENING",
    "notes": "Production opening stock"
  }'

# 4. Create sale order
curl -X POST https://api.flexstock.com/api/demo-sme/sale-orders \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "items": [{
      "product_id": 1,
      "qty": 2,
      "unit_price": 100.00
    }]
  }'

# 5. Confirm order
curl -X POST https://api.flexstock.com/api/demo-sme/sale-orders/1/confirm \
  -H "Authorization: Bearer $TOKEN"

# 6. Check stock movement
curl https://api.flexstock.com/api/demo-sme/stock/movements \
  -H "Authorization: Bearer $TOKEN"
```

## 📊 Monitoring

### Application Logs

```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Queue worker status
sudo systemctl status flexstock-queue

# View queue jobs
php artisan queue:monitor

# Failed jobs
php artisan queue:failed
```

### Database Performance

```sql
-- Check slow queries
SHOW PROCESSLIST;

-- Verify indexes are being used
EXPLAIN SELECT * FROM sale_orders WHERE company_id = 1 AND status = 'Confirmed' AND payment_state = 'Received';

-- Check index usage
SELECT * FROM sys.schema_unused_indexes;
```

## 🔄 Maintenance

### Regular Tasks

**Daily**:
- Check error logs: `storage/logs/laravel.log`
- Verify queue worker: `systemctl status flexstock-queue`
- Monitor disk space: `df -h`

**Weekly**:
- Database backup: `mysqldump -u root -p flexstock_prod > backup.sql`
- Clear old logs: `php artisan telescope:prune`
- Check failed jobs: `php artisan queue:failed`

**Monthly**:
- Update dependencies: `composer update` (test first!)
- Security audit: `composer audit`
- Review payment slips storage: `du -sh storage/app/public/slips/`

### Backup Strategy

```bash
#!/bin/bash
# backup.sh - Run daily via cron

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/flexstock"

# Database
mysqldump -u flexstock_user -p'password' flexstock_prod > $BACKUP_DIR/db_$DATE.sql

# Files (payment slips)
tar -czf $BACKUP_DIR/files_$DATE.tar.gz storage/app/public/slips

# Keep last 30 days
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

## 🚨 Troubleshooting

### Common Issues

**1. Payment slip upload fails**
```bash
# Check storage permissions
ls -la storage/app/public/
chmod -R 775 storage/app/public/
chown -R www-data:www-data storage/app/public/
```

**2. Queue not processing**
```bash
# Restart queue worker
sudo systemctl restart flexstock-queue

# Check logs
journalctl -u flexstock-queue -f
```

**3. CORS errors**
```bash
# Verify .env
grep CORS_ALLOWED_ORIGINS .env

# Clear config cache
php artisan config:clear
php artisan config:cache
```

**4. Negative stock despite locking**
```bash
# Check for deadlocks
SELECT * FROM information_schema.innodb_locks;

# Verify transaction isolation level
SELECT @@transaction_isolation;
```

## 📈 Scaling

### Horizontal Scaling

1. **Load Balancer**: Nginx/HAProxy in front of multiple app servers
2. **Shared Storage**: NFS or S3 for payment slips
3. **Database**: Read replicas for reports
4. **Queue Workers**: Multiple workers on separate servers

### Performance Tuning

```php
// config/database.php - Optimize connections
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
],

// config/cache.php - Use Redis for sessions
'default' => env('CACHE_DRIVER', 'redis'),
```

---

## ✅ Deployment Validation Checklist

Before going live:

- [ ] All migrations run successfully
- [ ] Storage link created
- [ ] File permissions correct
- [ ] Environment variables configured
- [ ] CORS origins set
- [ ] SSL certificates installed
- [ ] Queue worker running
- [ ] Backups configured
- [ ] Smoke tests passing
- [ ] Monitoring active
- [ ] Documentation accessible

---

**Support**: For issues, open a ticket at https://github.com/yourusername/flexstock/issues
