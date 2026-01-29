# ISP Billing System

Sistem Manajemen ISP/RTRW Net yang komprehensif untuk pasar Indonesia. Sistem ini menyediakan manajemen end-to-end untuk siklus hidup pelanggan, billing otomatis, pemrosesan pembayaran, integrasi perangkat jaringan (Mikrotik), isolasi otomatis untuk pelanggan menunggak, monitoring jaringan via SNMP, dan notifikasi multi-channel (WhatsApp, Email).

## 🚀 Technology Stack

### Backend
- **Laravel**: 12.49.0 (Latest stable)
- **PHP**: 8.5.2 (Compatible with 8.2+)
- **Database**: MySQL 8.0+ / MariaDB 10.6+ (SQLite for development)
- **Cache & Queue**: Redis 7.0+

### Frontend
- **Blade Templates**: Laravel's templating engine
- **Tailwind CSS**: 4.0.0 (Utility-first CSS framework)
- **Alpine.js**: 3.x (Lightweight JavaScript framework)
- **Vite**: 7.0.7 (Modern build tool)

### Authentication
- **Laravel Breeze**: 2.3.8 (Simple authentication scaffolding)

## 📋 Features (Planned)

### Core Modules
- ✅ **Authentication System** (Completed)
- 🔄 **Customer Management** (Pending)
- 🔄 **Service Provisioning** (Pending)
- 🔄 **Automated Billing** (Pending)
- 🔄 **Payment Gateway Integration** (Pending)
- 🔄 **Smart Isolation System** (Pending)
- 🔄 **Network Monitoring** (Pending)
- 🔄 **Multi-Channel Notifications** (Pending)
- 🔄 **Customer Portal** (Pending)
- 🔄 **Admin Dashboard** (Pending)
- 🔄 **Ticket System** (Pending)
- 🔄 **ODP Management** (Pending)
- 🔄 **Financial Reporting** (Pending)
- 🔄 **Audit Logging** (Pending)

### Integrations
- **Mikrotik RouterOS API**: Automated PPPoE provisioning and isolation
- **Payment Gateways**: Midtrans, Xendit, Tripay
- **WhatsApp Gateway**: Fonnte or Wablas
- **SNMP**: Network device monitoring
- **Email**: Laravel Mail system

## 🛠️ Installation

### Prerequisites
- PHP 8.2 or higher
- Composer 2.x
- Node.js 18.x or higher
- MySQL 8.0+ (for production)
- Redis 7.0+ (for production)

### Quick Start

1. **Clone the repository** (or use existing installation)
   ```bash
   cd isp-billing-system
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node dependencies**
   ```bash
   npm install
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure database**
   - For development: SQLite is already configured
   - For production: Update `.env` with MySQL credentials

6. **Run migrations**
   ```bash
   php artisan migrate
   ```

7. **Build frontend assets**
   ```bash
   npm run build
   ```

8. **Start development server**
   ```bash
   php artisan serve
   ```

9. **Access the application**
   - URL: http://localhost:8000
   - Login: http://localhost:8000/login
   - Register: http://localhost:8000/register

## 📚 Documentation

- **[SETUP.md](SETUP.md)**: Detailed setup guide with system requirements
- **[CONFIGURATION.md](CONFIGURATION.md)**: Complete configuration reference
- **[Requirements](.kiro/specs/isp-billing-system/requirements.md)**: System requirements specification
- **[Design](.kiro/specs/isp-billing-system/design.md)**: System design document
- **[Tasks](.kiro/specs/isp-billing-system/tasks.md)**: Implementation task list

## 🧪 Testing

### Run Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### Code Coverage
```bash
php artisan test --coverage
```

## 🔧 Development

### Start Development Server
```bash
php artisan serve
```

### Watch Frontend Assets
```bash
npm run dev
```

### Run Queue Workers (when Redis is configured)
```bash
php artisan queue:work
```

### Clear Caches
```bash
php artisan optimize:clear
```

## 📦 Project Structure

```
isp-billing-system/
├── app/                    # Application code
│   ├── Http/              # Controllers, Middleware, Requests
│   ├── Models/            # Eloquent models
│   ├── Services/          # Business logic services
│   └── Jobs/              # Queue jobs
├── config/                # Configuration files
├── database/              # Migrations, seeders, factories
├── public/                # Public assets
├── resources/             # Views, CSS, JS
│   ├── css/              # Tailwind CSS
│   ├── js/               # Alpine.js
│   └── views/            # Blade templates
├── routes/                # Route definitions
├── storage/               # Logs, cache, uploads
├── tests/                 # Test files
└── vendor/                # Composer dependencies
```

## 🔐 Security

### Production Checklist
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Use strong database passwords
- [ ] Configure Redis with password
- [ ] Enable HTTPS/SSL
- [ ] Setup proper file permissions
- [ ] Configure firewall rules
- [ ] Enable rate limiting
- [ ] Setup backup strategy
- [ ] Configure monitoring

## 🚀 Deployment

### Production Server Requirements
- Ubuntu 22.04 LTS (recommended)
- Nginx + PHP-FPM 8.5
- MySQL 8.0+ or MariaDB 10.6+
- Redis 7.0+
- Supervisor (for queue workers)
- Let's Encrypt SSL certificate

### Deployment Steps
1. Clone repository to server
2. Install dependencies
3. Configure environment variables
4. Run migrations
5. Build frontend assets
6. Configure Nginx
7. Setup Supervisor for queue workers
8. Configure SSL certificate
9. Setup automated backups

## 📝 License

This project is proprietary software developed for ISP/RTRW Net management.

## 👥 Support

For issues or questions:
- Check documentation in `.kiro/specs/isp-billing-system/`
- Review SETUP.md and CONFIGURATION.md
- Contact system administrator

## 🎯 Current Status

**Task 1: Setup Project Laravel dan Konfigurasi Dasar** ✅ COMPLETED

### Completed
- ✅ Laravel 12.49.0 installation
- ✅ Tailwind CSS 4.0 configuration
- ✅ Alpine.js integration
- ✅ Laravel Breeze authentication
- ✅ Environment configuration
- ✅ SQLite database setup

### Next Steps
- Task 2: Database Schema dan Migrations
- Task 3: Authentication dan Authorization System
- Task 4: Customer Management Module
- And more... (see tasks.md)

---

**Built with ❤️ using Laravel 12 and Tailwind CSS 4**
