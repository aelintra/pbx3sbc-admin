# PBX3sbc Admin Panel

Laravel + Filament admin panel for managing OpenSIPS SBC database and configuration.

## Overview

This is the web-based administration interface for the PBX3sbc OpenSIPS SIP Edge Router. It provides a modern, user-friendly interface for managing domains, dispatcher destinations, and other OpenSIPS configuration via the MySQL database.

## Technology Stack

- **Laravel 12** - PHP framework
- **Filament 3.x** - Admin panel framework (TALL stack: Tailwind CSS, Alpine.js, Livewire, Laravel)
- **MySQL** - Database (shared with OpenSIPS)
- **PHP 8.2+** - Runtime

## Prerequisites

Before installing this admin panel, you must have:

1. **OpenSIPS installed and configured** - See [pbx3sbc repository](https://github.com/your-org/pbx3sbc) for OpenSIPS installation
2. **MySQL database** - The OpenSIPS database must be set up with the required tables (`domain`, `dispatcher`, etc.)
3. **PHP 8.2+** with required extensions:
   - php-mysql
   - php-xml
   - php-mbstring
   - php-curl
   - php-zip
   - php-bcmath
   - php-intl
4. **Composer** - PHP package manager

## Installation

### Option 1: Manual Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-org/pbx3sbc-admin.git
   cd pbx3sbc-admin
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Edit `.env` file:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=opensips
   DB_USERNAME=opensips
   DB_PASSWORD=your-password
   
   OPENSIPS_MI_URL=http://127.0.0.1:8888/mi
   ```

5. **Run migrations:**
   ```bash
   php artisan migrate
   ```

6. **Create admin user:**
   ```bash
   php artisan make:filament-user
   ```

7. **Start development server:**
   ```bash
   php artisan serve
   ```

   Access the admin panel at: `http://localhost:8000/admin`

### Option 2: Using pbx3sbc Installer Script

The `pbx3sbc` repository includes an installer script that can set up this admin panel:

```bash
cd pbx3sbc
sudo ./install-admin-panel.sh
```

See the [pbx3sbc repository](https://github.com/your-org/pbx3sbc) for more details.

## Development Setup (Mac with Herd)

If you're using Laravel Herd on Mac:

1. **Link the project to Herd:**
   ```bash
   cd /Users/jeffstokoe/GiT/pbx3sbc-admin
   herd link pbx3sbc-admin
   ```

2. **Access via Herd:**
   - The site will be available at: `http://pbx3sbc-admin.test`
   - Or use the IP: `http://127.0.0.1:8000`

3. **Configure database in `.env`:**
   - Ensure MySQL is running (via Herd or standalone)
   - Update database credentials in `.env`

## Database Connection

This admin panel connects to the **same MySQL database** that OpenSIPS uses. The database should contain:

- **OpenSIPS tables** (created by pbx3sbc installation):
  - `domain` - SIP domains with `setid` column
  - `dispatcher` - Dispatcher destinations
  - `endpoint_locations` - Endpoint registration tracking (custom table)

- **Application tables** (created by Laravel migrations):
  - `users` - Admin panel users
  - `password_reset_tokens` - Password reset tokens
  - `cache`, `jobs`, etc. - Laravel framework tables

**Important:** Do not modify OpenSIPS table structure from this application. Schema changes to OpenSIPS tables should be managed via scripts in the `pbx3sbc` repository.

## Features (Planned/In Development)

### MVP Features
- ✅ Authentication (Filament built-in)
- 🔄 Domain Management (Filament Resource)
- 🔄 Dispatcher Management (Filament Resource)
- 🔄 OpenSIPS MI Integration

### Future Features
- Service Management (Linux systemd services)
- S3/Minio Object Storage Management
- Remote API Integration
- Multi-Instance Management
- Statistics and Monitoring

## Project Structure

```
pbx3sbc-admin/
├── app/
│   ├── Filament/
│   │   ├── Resources/          # Filament Resources (Domain, Dispatcher, etc.)
│   │   ├── Pages/              # Custom Filament pages
│   │   └── Widgets/            # Filament widgets (stats, charts, etc.)
│   ├── Models/                 # Eloquent models
│   │   ├── Domain.php
│   │   ├── Dispatcher.php
│   │   └── User.php
│   ├── Services/               # Service classes
│   │   ├── OpenSIPSMIService.php
│   │   ├── SystemService.php
│   │   └── ExternalApiService.php
│   └── Http/
│       └── Requests/           # Form Request validation
├── database/
│   └── migrations/             # Laravel migrations (users, etc.)
├── config/                     # Laravel configuration
└── resources/                  # Views, CSS, JS
```

## OpenSIPS MI Integration

The admin panel communicates with OpenSIPS via the Management Interface (MI) over HTTP/JSON. Configuration is in `.env`:

```env
OPENSIPS_MI_URL=http://127.0.0.1:8888/mi
```

The `OpenSIPSMIService` class handles all MI communication.

## Development Workflow

1. **Make changes to Filament Resources:**
   ```bash
   php artisan make:filament-resource Domain
   ```

2. **Test locally:**
   ```bash
   php artisan serve
   # Or use Herd if configured
   ```

3. **Run migrations:**
   ```bash
   php artisan migrate
   ```

4. **Clear cache if needed:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

## Relationship to pbx3sbc Repository

This repository is **separate** from the `pbx3sbc` repository:

- **pbx3sbc** - OpenSIPS SBC configuration, scripts, database schema management
- **pbx3sbc-admin** - Web-based admin panel (this repository)

Both repositories work with the same MySQL database, but have different responsibilities:

- **pbx3sbc**: Creates/manages OpenSIPS table schemas, installation scripts
- **pbx3sbc-admin**: Provides web interface for managing data in those tables

See the [TWO-REPO-STRATEGY.md](../pbx3sbc/workingdocs/TWO-REPO-STRATEGY.md) document in the pbx3sbc repository for more details on how the two repositories work together.

## Documentation

- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Filament Documentation](https://filamentphp.com/docs)
- [OpenSIPS Documentation](https://opensips.org/documentation/)

## License

See [LICENSE](LICENSE) file.

## Support

For issues related to:
- **OpenSIPS installation/configuration**: See [pbx3sbc repository](https://github.com/your-org/pbx3sbc)
- **Admin panel issues**: Open an issue in this repository
