# ISP Billing System - Setup Guide

## System Requirements

### Required Software
- **PHP**: 8.5.2 (or 8.2+) ✅ Installed
- **Composer**: 2.x ✅ Installed
- **Node.js**: 18.x or higher ✅ Installed
- **MySQL**: 8.0+ or MariaDB 10.6+ ⚠️ **Required for production**
- **Redis**: 7.0+ ⚠️ **Required for production**

### PHP Extensions Required
- OpenSSL
- PDO
- Mbstring
- Tokenizer
- XML
- Ctype
- JSON
- BCMath
- Fileinfo
- SNMP (for network monitoring)

## Installation Steps

### 1. Laravel Installation ✅ COMPLETED
```bash
composer create-project laravel/laravel isp-billing-system "12.*"
```

### 2. Frontend Dependencies ✅ COMPLETED
```bash
cd isp-billing-system
npm install
npm install alpinejs
```

### 3. Authentication Setup ✅ COMPLETED
```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
```

### 4. Database Configuration ⚠️ PENDING

#### For Development (Current Setup)
Currently using SQLite for development. The database file is located at:
```
database/database.sqlite
```

#### For Production (Required)
Install MySQL 8.0+ and create the database:

```sql
CREATE DATABASE isp_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'isp_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON isp_billing.* TO 'isp_user'@'localhost';
FLUSH PRIVILEGES;
```

Then update `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=isp_billing
DB_USERNAME=isp_user
DB_PASSWORD=secure_password
```

### 5. Redis Configuration ⚠️ PENDING

#### Windows Installation
Download and install Redis from:
- https://github.com/microsoftarchive/redis/releases
- Or use WSL2 with Redis

#### Linux Installation
```bash
sudo apt update
sudo apt install redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

#### Configuration
Once Redis is installed, update `.env`:
```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

### 6. Environment Configuration ✅ COMPLETED

The `.env` file has been configured with:
- Application name: "ISP Billing System"
- Timezone: Asia/Jakarta
- Locale: Indonesian (id)
- Cache prefix: isp_billing

### 7. Additional Dependencies (To be installed in next tasks)

#### Mikrotik Integration
```bash
composer require evilfreelancer/routeros-api-php
```

#### Payment Gateways
```bash
composer require midtrans/midtrans-php
composer require xendit/xendit-php
# Tripay uses HTTP client (Guzzle already included)
```

#### PDF & Excel Export
```bash
composer require barryvdh/laravel-dompdf
composer require maatwebsite/laravel-excel
```

#### Testing Framework
```bash
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
```

## Current Status

### ✅ Completed
1. Laravel 12.49.0 installed
2. Tailwind CSS 4.0 configured
3. Alpine.js installed and configured
4. Laravel Breeze authentication installed
5. Environment variables configured
6. SQLite database setup for development

### ⚠️ Pending (User Action Required)
1. **MySQL Installation**: Install MySQL 8.0+ for production use
2. **Redis Installation**: Install Redis 7.0+ for caching and queues
3. **PHP SNMP Extension**: Required for network monitoring features

### 📋 Next Steps (Upcoming Tasks)
1. Create database migrations for all tables
2. Setup Eloquent models with relationships
3. Implement role-based access control
4. Install additional dependencies (Mikrotik, Payment Gateways, etc.)

## Running the Application

### Development Server
```bash
php artisan serve
```

Access the application at: http://localhost:8000

### Build Frontend Assets
```bash
# Development
npm run dev

# Production
npm run build
```

### Run Migrations
```bash
php artisan migrate
```

### Run Tests
```bash
php artisan test
```

## Troubleshooting

### MySQL Connection Issues
- Verify MySQL service is running
- Check credentials in `.env`
- Ensure database exists
- Check firewall settings

### Redis Connection Issues
- Verify Redis service is running: `redis-cli ping` (should return PONG)
- Check Redis configuration in `.env`
- Ensure PHP Redis extension is installed: `php -m | grep redis`

### Permission Issues (Linux/Mac)
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Security Notes

### Production Checklist
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate new `APP_KEY`: `php artisan key:generate`
- [ ] Configure proper database credentials
- [ ] Setup Redis with password
- [ ] Configure HTTPS/SSL
- [ ] Setup proper file permissions
- [ ] Configure firewall rules
- [ ] Setup backup strategy
- [ ] Configure queue workers with Supervisor
- [ ] Setup monitoring and logging

## Support

For issues or questions, refer to:
- Laravel Documentation: https://laravel.com/docs/12.x
- Project Requirements: `.kiro/specs/isp-billing-system/requirements.md`
- Project Design: `.kiro/specs/isp-billing-system/design.md`
- Task List: `.kiro/specs/isp-billing-system/tasks.md`
