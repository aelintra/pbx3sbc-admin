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

The installer will **automatically install** most prerequisites for you:

- ✅ **PHP 8.2+** and required extensions (auto-installed)
- ✅ **Composer** (auto-installed)
- ⚠️ **MySQL database** - You need a MySQL/MariaDB server (local or remote)
  - The installer will prompt for database connection details
  - You can use Docker MySQL (see Option 0 below)
  - Or use an existing MySQL server
- ⚠️ **OpenSIPS database tables** - The database should have OpenSIPS tables (`domain`, `dispatcher`, `endpoint_locations`)
  - **IMPORTANT:** These must be created using scripts from the [pbx3sbc repository](https://github.com/your-org/pbx3sbc): `cd pbx3sbc && sudo ./scripts/init-database.sh`
  - This admin panel repository does NOT create OpenSIPS tables - it only creates application tables (users, etc.)
  - Docker setup (Option 0) only creates the MySQL container - you still need to initialize OpenSIPS tables separately

**Supported Operating Systems:**
- Ubuntu/Debian (apt package manager)
- RHEL/CentOS/Fedora (yum/dnf package manager)

## Installation

### Option 0: Docker MySQL Setup (For Testing/Development)

If you want to use Docker for MySQL (useful for testing or when both services run in containers):

```bash
# Start MySQL container and initialize database
./docker-setup.sh

# Enable LAN access (if connecting from other machines)
./docker-mysql-access.sh enable

# Check access status
./docker-mysql-access.sh status
```

Then configure your `.env` file:
```env
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=opensips
```

See [DOCKER-SETUP.md](DOCKER-SETUP.md) for detailed Docker setup instructions and access management.

### Option 1: Automated Installation (Recommended)

The repository includes an automated installer script that handles the entire setup process:

```bash
# Clone the repository
git clone https://github.com/your-org/pbx3sbc-admin.git
cd pbx3sbc-admin

# Run the installer
./install.sh
```

The installer will:
- **Auto-install prerequisites** (PHP 8.2+, Composer, PHP extensions) if missing
- Install PHP dependencies (Composer packages)
- Configure environment file (`.env`)
- Test database connection
- Run database migrations
- Create admin user (interactive)
- Set proper file permissions

**Note:** The installer is idempotent - it's safe to run multiple times. It will detect and install only missing prerequisites.

**Command-line options:**
```bash
./install.sh [options]

Options:
  --skip-deps              Skip PHP dependency installation (Composer packages)
  --skip-prereqs           Skip prerequisite installation (PHP, Composer, extensions)
  --skip-migrations        Skip running migrations
  --no-admin-user          Skip admin user creation
  --db-host HOST           Database host (default: prompts)
  --db-user USER           Database username (default: prompts)
  --db-password PASSWORD   Database password (default: prompts)
  --db-name NAME           Database name (default: prompts)
  --db-port PORT           Database port (default: 3306)
  --opensips-mi-url URL    OpenSIPS MI URL (optional)
```

**Example with all options:**
```bash
./install.sh \
  --db-host 192.168.1.100 \
  --db-user opensips \
  --db-password mypassword \
  --db-name opensips \
  --opensips-mi-url http://192.168.1.100:8888/mi
```

### Option 2: Manual Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-org/pbx3sbc-admin.git
   cd pbx3sbc-admin
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Set up OpenSIPS database (REQUIRED):**
   
   **IMPORTANT:** The OpenSIPS database tables (domain, dispatcher, endpoint_locations) must be created BEFORE running the admin panel.
   
   Use the pbx3sbc repository to initialize the database:
   ```bash
   cd ../pbx3sbc  # or path to pbx3sbc repository
   sudo ./scripts/init-database.sh
   ```
   
   This creates the OpenSIPS tables that both OpenSIPS and the admin panel use.

4. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Edit `.env` file:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=opensips
   DB_USERNAME=opensips
   DB_PASSWORD=your-password
   
   OPENSIPS_MI_URL=http://127.0.0.1:8888/mi
   ```

6. **Run migrations (creates application tables only):**
   ```bash
   php artisan migrate
   ```
   
   This creates Laravel application tables (users, cache, jobs, etc.). OpenSIPS tables must already exist from step 3.

7. **Create admin user:**
   ```bash
   php artisan make:filament-user
   ```

8. **Start development server:**
   ```bash
   php artisan serve
   ```

   Access the admin panel at: `http://localhost:8000/admin`

### Option 3: Using pbx3sbc Installer Script

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
- ✅ Domain Management (Filament Resource) - Complete with validation
- ✅ Dispatcher Management (Filament Resource) - Complete with validation
- 🔄 OpenSIPS MI Integration (optional, deferred)

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
