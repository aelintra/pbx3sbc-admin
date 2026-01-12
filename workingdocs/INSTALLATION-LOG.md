# Installation Log

This document tracks the installation steps for the pbx3sbc-admin application. It will be used to create an automated installer script.

**⚠️ IMPORTANT: This is a living document.** This log should be updated as installation steps are performed or modified. When new steps are executed, commands are changed, or additional configuration is required, this document must be updated accordingly to maintain accuracy for future automated installer creation.

**Note:** This log assumes MySQL and web server (nginx/apache) are already installed and configured.

## Installation Steps

### 1. Create Laravel Project

```bash
# Create new Laravel 12 project
composer create-project laravel/laravel pbx3sbc-admin

# Navigate to project directory
cd pbx3sbc-admin
```

**Expected output:** Creates Laravel project structure with composer.json, app/, config/, database/, etc.

**Date:** January 11, 2026  
**Status:** ✅ Completed (performed in earlier session)

**Files created:**
- Laravel project structure
- `composer.json` with Laravel 12 dependencies
- `artisan` command-line tool
- Basic Laravel directory structure

### 2. Install Filament Admin Panel

```bash
# Install Filament 3.x package
composer require filament/filament:"^3.0"

# Install Filament panels (interactive - prompts for panel ID and path)
php artisan filament:install --panels

# Example interactive prompts:
# Panel ID [admin]: admin
# Path [/admin]: /admin
```

**Alternative non-interactive installation:**
```bash
# Install with default panel (ID: admin, path: /admin)
php artisan filament:install --panels --default
```

**What this does:**
- Installs Filament 3.x package and dependencies
- Creates `app/Providers/Filament/AdminPanelProvider.php`
- Publishes Filament configuration files
- Sets up panel at `/admin` path
- Enables authentication and resource discovery

**Date:** January 11, 2026  
**Status:** ✅ Completed (performed in earlier session)

**Files created/modified:**
- `app/Providers/Filament/AdminPanelProvider.php` - Panel configuration
- `vendor/filament/` - Filament package files
- `composer.json` - Adds filament/filament dependency

### 3. Create Project Structure

```bash
# Create Services directory for service classes
mkdir -p app/Services

# Create scripts directory for installation/maintenance scripts
mkdir -p scripts
```

**Date:** January 11, 2026  
**Status:** ✅ Completed

### 4. Configure Database

#### 4.1 Create Database and User

**Option 1: Interactive MySQL session**
```bash
# Connect to MySQL as root
mysql -u root

# Then run these SQL commands:
CREATE DATABASE IF NOT EXISTS opensips CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'opensips'@'localhost' IDENTIFIED BY 'rigmarole';
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Option 2: Single command (non-interactive)**
```bash
# Create database and user in one command
mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS opensips CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'opensips'@'localhost' IDENTIFIED BY 'rigmarole';
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'localhost';
FLUSH PRIVILEGES;
EOF
```

**Option 3: Using individual mysql commands**
```bash
# Create database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS opensips CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create user
mysql -u root -e "CREATE USER IF NOT EXISTS 'opensips'@'localhost' IDENTIFIED BY 'rigmarole';"

# Grant privileges
mysql -u root -e "GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'localhost';"

# Flush privileges
mysql -u root -e "FLUSH PRIVILEGES;"
```

**Verify creation:**
```bash
# List databases to verify
mysql -u root -e "SHOW DATABASES LIKE 'opensips';"

# Test user connection
mysql -u opensips -p'rigmarole' -e "SELECT USER(), DATABASE();"
```

**Date:** January 11, 2026  
**Status:** ✅ Completed  
**Credentials:**
- Database: `opensips`
- User: `opensips`
- Password: `rigmarole` (change in production!)

#### 4.2 Configure Laravel Environment

```bash
# Copy environment file (if not already done)
cp .env.example .env

# Generate application key (if not already done)
php artisan key:generate
```

**Update `.env` file with database credentials:**

**Option 1: Manual edit**
Edit `.env` file and update these lines:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=rigmarole
```

**Option 2: Using sed (non-interactive)**
```bash
# Update database configuration in .env
sed -i '' 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
sed -i '' 's/^# DB_HOST=/DB_HOST=/' .env
sed -i '' 's/^# DB_PORT=/DB_PORT=/' .env
sed -i '' 's/^# DB_DATABASE=.*/DB_DATABASE=opensips/' .env
sed -i '' 's/^# DB_USERNAME=.*/DB_USERNAME=opensips/' .env
sed -i '' 's/^# DB_PASSWORD=.*/DB_PASSWORD=rigmarole/' .env

# Note: On Linux, remove the '' after -i:
# sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
```

**Option 3: Using environment file template replacement**
```bash
# Create a temporary file with database settings
cat >> .env.tmp <<EOF
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=rigmarole
EOF

# Append to .env (after removing old DB_ lines if needed)
```

**Date:** January 11, 2026  
**Status:** ✅ Completed

#### 4.3 Test Database Connection

```bash
# Test database connection (shows connection details)
php artisan db:show

# Expected output:
# MySQL ................................................ 9.5.0
# Connection ........................................... mysql
# Database ............................................. opensips
# Host ................................................. 127.0.0.1
# Port ................................................. 3306
# Username ............................................. opensips
```

**Alternative verification methods:**
```bash
# Verify connection with tinker
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connection successful! Database: ' . DB::connection()->getDatabaseName();"

# Test with a simple query
php artisan tinker --execute="DB::select('SELECT VERSION() as version');"

# Check migration status (will fail if tables don't exist yet, but connection will be tested)
php artisan migrate:status
```

**Date:** January 11, 2026  
**Status:** ✅ Completed

### 5. Create OpenSIPS Database Tables

**Note:** This step creates the OpenSIPS tables (domain, dispatcher, endpoint_locations) that are required for the admin panel to function. This is separate from Laravel migrations.

```bash
# Option 1: Use the bash script (recommended)
./scripts/create-opensips-tables.sh opensips rigmarole

# Option 2: Use MySQL directly
mysql -u opensips -p'rigmarole' opensips < scripts/create-opensips-tables.sql
```

**Date:** January 11, 2026  
**Status:** ✅ Completed

**Verification:**
```bash
# Verify tables were created
mysql -u opensips -p'rigmarole' opensips -e "SHOW TABLES;"

# Check table structures
mysql -u opensips -p'rigmarole' opensips -e "DESCRIBE domain; DESCRIBE dispatcher; DESCRIBE endpoint_locations;"
```

### 6. Run Laravel Migrations

```bash
# Run Laravel migrations (creates users, cache, jobs, etc.)
php artisan migrate
```

**Date:** January 11, 2026  
**Status:** ✅ Completed

**Verification:**
```bash
# Check migration status
php artisan migrate:status

# Verify tables were created
mysql -u opensips -p'rigmarole' opensips -e "SHOW TABLES LIKE 'users'; SHOW TABLES LIKE 'cache'; SHOW TABLES LIKE 'jobs';"
```

### 7. Create Admin User

**Option 1: Interactive command (recommended)**
```bash
# Create Filament admin user (interactive prompts)
php artisan make:filament-user

# Example prompts:
# Name: Admin User
# Email: admin@example.com
# Password: [hidden input]
# Password confirmation: [hidden input]
```

**Option 2: Create user programmatically**
```bash
# Using tinker
php artisan tinker

# Then run:
$user = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('your-secure-password')
]);
```

**Option 3: Non-interactive creation (for scripts)**
```bash
# Create user with environment variables
php artisan tinker --execute="
\$user = \App\Models\User::create([
    'name' => env('ADMIN_NAME', 'Admin'),
    'email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'password' => bcrypt(env('ADMIN_PASSWORD', 'changeme'))
]);
echo 'Admin user created: ' . \$user->email;
"
```

**Verify user creation:**
```bash
# List users
php artisan tinker --execute="\App\Models\User::all(['name', 'email'])->toArray();"
```

**Date:** January 11, 2026  
**Status:** ✅ Completed

**Default Admin User Created:**
- Name: Admin
- Email: admin@example.com
- Password: opensips (change in production!)

**Verification:**
```bash
# Verify user was created
php artisan tinker --execute="\App\Models\User::where('email', 'admin@example.com')->first(['id', 'name', 'email', 'created_at']);"
```

### 8. Initialize Git Repository (Optional but Recommended)

```bash
# Initialize git repository
git init

# Add all files (respects .gitignore)
git add .

# Create initial commit
git commit -m "Initial commit: Laravel 12 + Filament 3.x admin panel setup

- Laravel 12 project initialized
- Filament 3.x admin panel installed and configured
- Database configuration setup (opensips database/user)
- OpenSIPS table creation scripts added
- Installation log and session summary documentation"
```

**Verify git status:**
```bash
# Check git status
git status

# View commit history
git log --oneline
```

**Date:** January 11, 2026  
**Status:** ✅ Completed

## Current Status

**Last Updated:** January 11, 2026

**Completed:**
- ✅ Laravel 12 project created
- ✅ Filament 3.x installed and configured
- ✅ Project structure created (Services directory, scripts directory)
- ✅ Database and user created
- ✅ Laravel .env configured
- ✅ Database connection tested
- ✅ OpenSIPS table creation scripts created
- ✅ OpenSIPS database tables created (domain, dispatcher, endpoint_locations)
- ✅ Laravel migrations run (users, cache, jobs, migrations tables created)
- ✅ Admin user created (admin@example.com)
- ✅ Git repository initialized with initial commit

**Pending:**
- ⏳ Run Laravel migrations
- ⏳ Create admin user

## Environment Variables Required

The following environment variables need to be set in `.env`:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=rigmarole

# OpenSIPS Management Interface (for future use)
OPENSIPS_MI_URL=http://127.0.0.1:8888/mi
```

## Files Created

- `scripts/create-opensips-tables.sql` - SQL script to create OpenSIPS tables
- `scripts/create-opensips-tables.sh` - Bash wrapper script for table creation
- `INSTALLATION-LOG.md` - This file (installation documentation)
- `app/Services/` - Directory for service classes (empty, ready for future use)
- `app/Providers/Filament/AdminPanelProvider.php` - Filament panel configuration

## Verification Commands

After installation, verify everything is working:

```bash
# 1. Check Laravel installation
php artisan --version

# 2. Check Filament installation
php artisan filament:list

# 3. Test database connection
php artisan db:show

# 4. Check if OpenSIPS tables exist
mysql -u opensips -p'rigmarole' opensips -e "SHOW TABLES LIKE 'domain';"
mysql -u opensips -p'rigmarole' opensips -e "SHOW TABLES LIKE 'dispatcher';"
mysql -u opensips -p'rigmarole' opensips -e "SHOW TABLES LIKE 'endpoint_locations';"

# 5. Check Laravel migrations status
php artisan migrate:status

# 6. Start development server and test
php artisan serve
# Then visit: http://localhost:8000/admin
```

## Notes for Automated Installer

1. **Prerequisites check:**
   - PHP 8.2+ installed
   - Composer installed
   - MySQL running and accessible
   - Git (optional, for cloning)

2. **Interactive vs Non-interactive:**
   - Database password should be prompted or provided via argument
   - Admin user creation can be interactive or accept arguments

3. **Error handling:**
   - Check if database/user already exists
   - Handle case where tables already exist (use IF NOT EXISTS)
   - Verify Laravel key is generated before running migrations

4. **Validation:**
   - Test database connection before proceeding
   - Verify table creation was successful
   - Test Filament panel is accessible after installation
