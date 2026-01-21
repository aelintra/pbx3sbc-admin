# PBX3SBC Admin Panel - Project Context

**Last Updated:** 2026-01-18  
**Purpose:** Quick onboarding document for new chat sessions and developers

## Project Overview

Laravel + Filament admin panel for managing OpenSIPS SBC (Session Border Controller) database and monitoring call activity. This is a web-based administration interface that provides a modern UI for managing domains, dispatcher destinations, CDR (Call Detail Records), and active calls.

## Technology Stack

- **Laravel 12** - PHP framework
- **Filament 3.x** - Admin panel framework (TALL stack: Tailwind CSS, Alpine.js, Livewire, Laravel)
- **MySQL/MariaDB** - Database (shared with OpenSIPS)
- **PHP 8.2+** - Runtime (8.3 recommended for Ubuntu 24.04)
- **Nginx** - Web server (auto-installed by installer)
- **PHP-FPM** - PHP FastCGI Process Manager (auto-installed by installer)

## Key Architecture Decisions

### Two-Repository Strategy

This repository (`pbx3sbc-admin`) is **separate** from the `pbx3sbc` repository:

- **pbx3sbc**: OpenSIPS SBC configuration, scripts, database schema management
- **pbx3sbc-admin**: Web-based admin panel (this repository)

Both repositories work with the **same MySQL database** (`opensips`), but have different responsibilities:
- **pbx3sbc**: Creates/manages OpenSIPS table schemas, installation scripts
- **pbx3sbc-admin**: Provides web interface for managing data in those tables

### Database Connection

- **Database Name:** `opensips`
- **Default User:** `opensips` / `opensips`
- **Connection:** Can be local (`127.0.0.1`/`localhost`) or remote (`192.168.1.58`)
- **Important:** MySQL treats `localhost` and `127.0.0.1` as different hosts - both need grants if connecting locally

### Read-Only Data Philosophy

- **CDR records are immutable** - Created by OpenSIPS, never modified/deleted by admin panel
- **Active Calls (Dialog)** - Read-only monitoring, no modification
- **Domain/Dispatcher** - Full CRUD operations

## Database Schema

### OpenSIPS Tables (Created by pbx3sbc repository)

#### `acc` Table (Call Detail Records)
- **Primary Key:** `id` (auto-increment)
- **Key Columns:**
  - `callid` - Unique Call-ID header (UUID)
  - `from_uri` - Full From SIP URI (e.g., `sip:1001@192.168.1.58`)
  - `to_uri` - Full To SIP URI (e.g., `sip:1000@192.168.1.109`)
  - `created` - Call start timestamp (DATETIME) - **Used for filtering**
  - `time` - Call end timestamp (DATETIME)
  - `duration` - Call duration in seconds
  - `ms_duration` - Call duration in milliseconds
  - `sip_code` - SIP response code (200 = success)
  - `sip_reason` - SIP response reason
- **No timestamps:** `created_at`/`updated_at` columns don't exist

#### `dialog` Table (Active Calls)
- **Primary Key:** `dlg_id` (NOT `id` - important for Filament routing!)
- **Key Columns:**
  - `callid` - Call-ID header (matches `acc.callid`)
  - `from_uri`, `to_uri` - SIP URIs
  - `state` - Dialog state (see OpenSIPS state values below)
  - `start_time` - Call start timestamp
  - `created`, `modified` - Timestamps
- **State Values (OpenSIPS):**
  - `1` = Unconfirmed
  - `2` = Early
  - `3` = Confirmed
  - `4` = Established (Active/Running calls)
  - `5` = Ended

#### `domain` Table
- SIP domains with `setid` column
- Managed via Filament Resource

#### `dispatcher` Table
- Dispatcher destinations
- Managed via Filament Resource

### Laravel Application Tables (Created by migrations)

- `users` - Admin panel users
- `migrations` - Laravel migration tracking
- `cache`, `cache_locks` - Laravel caching
- `jobs`, `job_batches`, `failed_jobs` - Queue system
- `password_reset_tokens` - Password resets
- `sessions` - Session storage

## Key Files and Structure

### Models

#### `app/Models/Cdr.php`
- Maps to `acc` table
- **Important:** `public $timestamps = false` (no created_at/updated_at)
- **Scopes:** `successful()`, `failed()`, `dateRange()`, `fromUri()`, `toUri()`, `callId()`, `durationRange()`
- **Accessors:** `formattedDuration`, `status`, `statusBadge`

#### `app/Models/Dialog.php`
- Maps to `dialog` table
- **Critical:** `protected $primaryKey = 'dlg_id'` and `public $incrementing = false`
- **Critical:** `getRouteKeyName()` returns `'dlg_id'` (required for Filament routing)
- **Scopes:** `active()` (state 4), `unconfirmed()`, `early()`, `confirmed()`, `established()`, `ended()`
- **Accessors:** `stateLabel`, `stateBadge`, `liveDuration`, `formattedLiveDuration`

#### `app/Models/Domain.php`, `app/Models/Dispatcher.php`
- Standard Filament resources with validation

### Filament Resources

#### `app/Filament/Resources/CdrResource.php`
- **Read-only** - No create/edit/delete actions
- **List View Columns:**
  - ID, From URI, To URI, Duration (formatted), Start Time, End Time, SIP Code
  - **Removed:** Call-ID column (not user-friendly)
- **Filters:**
  - Date/Time range filter (separate DatePicker + TextInput with mask for times)
  - From URI, To URI, SIP Code, Duration range
  - **Removed:** Call-ID filter (misleading to users)
- **Pagination:** `[10, 25, 50, 100]` default 25 (no "ALL" option for performance)
- **Date/Time Filter Implementation:**
  - Uses `DatePicker` for dates and `TextInput` with mask `'99:99'` for times
  - Validates time format with regex: `/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/`
  - Shows "❌ INVALID RANGE" badge if start >= end
  - Times default to `00:00` (start) and `23:59` (end)

#### `app/Filament/Resources/DialogResource.php`
- **Read-only** - Active call monitoring
- **List View Columns:**
  - Dialog ID, From URI, To URI, State (badge), Start Time, Live Duration
- **Filters:**
  - State filter (1-5 with labels)
  - Active Calls Only (toggle, default OFF)
  - From URI, To URI
- **Default Filter:** None (shows all calls by default)

#### `app/Filament/Resources/CdrResource/Pages/ViewCdr.php`
- **No delete action** - CDRs are immutable

#### `app/Filament/Resources/CdrResource/Pages/ListCdrs.php`
- **No create action** - CDRs are created by OpenSIPS

### Widgets

#### `app/Filament/Widgets/CdrStatsWidget.php`
- Dashboard statistics widget
- Shows: Total calls (today/week/month/all-time), Success rate, Average duration
- Uses `StatsOverviewWidget` base class

### Installer Script

#### `install.sh`
- **Fully automated** installation script
- **Idempotent** - Safe to run multiple times
- **Auto-installs:**
  - PHP 8.2+ (detects Ubuntu version, prioritizes PHP 8.3 for Ubuntu 24.04)
  - PHP extensions (pdo, pdo_mysql, mbstring, xml, curl, zip, bcmath, intl)
  - PHP-FPM
  - Nginx
  - Composer
- **Auto-configures:**
  - Nginx with Laravel-optimized settings
  - PHP-FPM socket detection
  - Database connection
  - File permissions
- **Features:**
  - Handles Composer lock file incompatibilities (auto-runs `composer update` if needed)
  - Detects MySQL "Host not allowed" errors and provides specific instructions
  - Non-interactive admin user creation (generates random password)
  - Command-line flags for all options
- **Usage:**
  ```bash
  sudo ./install.sh [--db-host HOST] [--db-user USER] [--db-password PASSWORD] [--db-name NAME] [--admin-name NAME] [--admin-email EMAIL] [--admin-password PASSWORD]
  ```

## Common Issues and Solutions

### Database Connection Issues

#### "Host '127.0.0.1' is not allowed to connect"
- **Cause:** MySQL user doesn't have permission for `127.0.0.1`
- **Solution:** Grant permissions for both `localhost` and `127.0.0.1`:
  ```sql
  GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'localhost' IDENTIFIED BY 'password';
  GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'127.0.0.1' IDENTIFIED BY 'password';
  FLUSH PRIVILEGES;
  ```
- **Note:** MySQL treats `localhost` (Unix socket) and `127.0.0.1` (TCP) as different hosts

### Filament Routing Errors

#### "Missing parameter: record" for Dialog resource
- **Cause:** Dialog model using default `id` primary key instead of `dlg_id`
- **Solution:** Set `protected $primaryKey = 'dlg_id'` and `getRouteKeyName()` in `Dialog` model

### Composer Lock File Incompatibility

#### "Your lock file does not contain a compatible set of packages"
- **Cause:** Lock file created with different PHP version (e.g., PHP 8.4 lock on PHP 8.3 system)
- **Solution:** Installer automatically runs `composer update` to regenerate lock file
- **Prevention:** Always commit `composer.lock` for the PHP version you're deploying

### Nginx Configuration

#### Nginx not serving Laravel correctly
- **Check:** `/etc/nginx/sites-available/pbx3sbc-admin`
- **Verify:** PHP-FPM socket path matches installed PHP version
- **Test:** `sudo nginx -t`
- **Reload:** `sudo systemctl reload nginx`

### Storage Permissions

#### "Permission denied" errors in storage/logs
- **Cause:** Web server user doesn't have write access
- **Solution:** Installer sets permissions, but if issues persist:
  ```bash
  sudo chown -R www-data:www-data storage bootstrap/cache
  sudo chmod -R 775 storage bootstrap/cache
  ```

## Development Workflow

### Local Development (Mac with Herd)

1. **Link to Herd:**
   ```bash
   herd link pbx3sbc-admin
   ```
2. **Access:** `http://pbx3sbc-admin.test/admin`
3. **Database:** Configure `.env` to point to MySQL (local or remote)

### Production Deployment

1. **Run installer:**
   ```bash
   sudo ./install.sh --db-host 192.168.1.58 --db-user opensips --db-password password --db-name opensips
   ```
2. **Access:** `http://<server-ip>/admin` (nginx configured automatically)
3. **Admin credentials:** Displayed at end of installation

### Making Changes

1. **Create Filament Resource:**
   ```bash
   php artisan make:filament-resource ModelName
   ```

2. **Clear caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

3. **Run migrations:**
   ```bash
   php artisan migrate
   ```

## UX Principles Applied

- **"Don't make me think"** - Removed confusing columns (Call-ID), clear labels
- **Performance** - Pagination limits, no "ALL" option for large datasets
- **Immutability** - No delete actions on read-only data (CDRs)
- **Validation** - Date/time range validation with visual indicators
- **Accessibility** - Clear error messages, helpful instructions

## OpenSIPS Integration Points

### CDR System
- CDRs are written by OpenSIPS CDR module
- Admin panel is read-only viewer
- No modification of CDR data

### Dialog System
- Active calls tracked in `dialog` table
- Admin panel monitors in real-time
- State values match OpenSIPS documentation

### Management Interface (MI)
- **Planned:** Optional MI integration for real-time operations
- **Current:** Database-only access
- **Config:** `OPENSIPS_MI_URL` in `.env` (optional)

## Testing Checklist

- [ ] Database connection works (local and remote)
- [ ] CDR panel displays records correctly
- [ ] Date/time filter works and validates ranges
- [ ] Active Calls panel shows current calls
- [ ] Admin user can log in
- [ ] Nginx serves application correctly
- [ ] File permissions allow logging
- [ ] Migrations run successfully

## Key Learnings

1. **Primary Key Configuration:** Always check if table uses non-standard primary key (`dlg_id` vs `id`)
2. **MySQL Host Permissions:** `localhost` and `127.0.0.1` are different hosts
3. **Composer Lock Files:** Version-specific, may need regeneration on different PHP versions
4. **OpenSIPS State Values:** State 4 = Established (active), not "Terminated"
5. **Filament Filters:** Can be complex - use reactive fields and validation callbacks
6. **Idempotent Installers:** Critical for reliable deployments
7. **Non-Interactive Installation:** Use tinker instead of interactive prompts

## Related Documentation

- `CDR-FRONTEND-SPEC.md` - Detailed CDR panel specification
- `README.md` - Installation and usage instructions
- `TWO-REPO-STRATEGY.md` - Architecture decision on repository separation
- `FILAMENT-ROLES-PERMISSIONS-GUIDE.md` - Future authorization planning

## Quick Reference Commands

```bash
# Install everything
sudo ./install.sh

# Install with custom admin user
sudo ./install.sh --admin-name "Admin" --admin-email "admin@example.com" --admin-password "password"

# Create new Filament resource
php artisan make:filament-resource ModelName

# Create new admin user
php artisan make:filament-user

# Clear all caches
php artisan config:clear && php artisan cache:clear && php artisan view:clear

# Test database connection
php artisan db:show

# Run migrations
php artisan migrate --force

# Check nginx config
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx

# Check PHP-FPM status
sudo systemctl status php8.3-fpm  # or php-fpm depending on version
```

## Environment Variables

Key `.env` variables:
```env
APP_URL=http://pbx3sbc-admin.test  # or your domain
DB_CONNECTION=mysql
DB_HOST=192.168.1.58  # or 127.0.0.1 for local
DB_PORT=3306
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=opensips
OPENSIPS_MI_URL=http://127.0.0.1:8888/mi  # Optional
```

## Next Steps / TODO

- [ ] Add roles and permissions system
- [ ] Implement OpenSIPS MI integration (optional)
- [ ] Add more statistics widgets
- [ ] Export CDR functionality
- [ ] Real-time call monitoring updates
- [ ] Multi-instance management
