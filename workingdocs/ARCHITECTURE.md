# System Architecture

**Last Updated:** 2026-01-22  
**Purpose:** System architecture, design decisions, and two-repository strategy

## Overview

This document outlines the architecture for the PBX3SBC Admin Panel, a modern web-based administration interface for managing OpenSIPS SBC (Session Border Controller) database and monitoring call activity.

## Design Principles

1. **Separation of Concerns** - Clean API/UI separation
2. **Extensibility** - Easy to add new features/modules over time
3. **Multi-Instance Ready** - Architecture supports managing multiple OpenSIPS servers
4. **Modern UX** - Intuitive, responsive interface
5. **No Core Modifications** - OpenSIPS core remains untouched
6. **Maintainability** - Clean code, good documentation, testable

---

## Two-Repository Strategy

### Overview

We have two separate repositories with different purposes:

1. **`pbx3sbc`** - OpenSIPS SIP Edge Router configuration and deployment
2. **`pbx3sbc-admin`** - Laravel + Filament admin panel application

### Repository Separation

#### Repository 1: `pbx3sbc` (OpenSIPS Configuration)
**Purpose:** OpenSIPS SBC configuration and deployment

**Contains:**
- OpenSIPS configuration files (`config/opensips.cfg.template`)
- Database initialization scripts (`scripts/init-database.sh`)
- OpenSIPS installation scripts (`install.sh`)
- Helper scripts (`add-domain.sh`, `add-dispatcher.sh`, etc.)
- Documentation

**Key Point:** Sets up and manages the MySQL database that both OpenSIPS and the admin panel use.

#### Repository 2: `pbx3sbc-admin` (Admin Panel)
**Purpose:** Web-based admin panel application

**Contains:**
- Laravel + Filament application code
- Eloquent models (`Domain`, `Dispatcher`, etc.)
- Filament Resources
- Services (`OpenSIPSMIService`, etc.)
- Application-specific migrations (users table, etc.)
- Application documentation

**Key Point:** Connects to the same MySQL database that pbx3sbc sets up, but adds application tables (users, roles, etc.).

### Relationship Diagram

```
┌─────────────────────────────────────┐
│   pbx3sbc Repository                │
│                                     │
│   ┌─────────────────────────────┐  │
│   │  install.sh                 │  │
│   │  - Installs OpenSIPS        │  │
│   │  - Sets up MySQL database   │  │
│   └───────────┬─────────────────┘  │
│               │                     │
│   ┌───────────▼─────────────────┐  │
│   │  scripts/init-database.sh   │  │
│   │  - Creates OpenSIPS tables  │  │
│   │    (domain, dispatcher)     │  │
│   └───────────┬─────────────────┘  │
└───────────────┼─────────────────────┘
                │
                │ Creates/Manages
                │
        ┌───────▼────────┐
        │   MySQL DB     │
        │  (opensips)    │
        │                │
        │  Tables:       │
        │  - domain      │ ← Used by both
        │  - dispatcher  │ ← Used by both
        │  - users       │ ← Created by admin panel
        │  - roles       │ ← Created by admin panel
        └───────┬────────┘
                │
                │ Reads/Writes
                │
┌───────────────┼─────────────────────┐
│   pbx3sbc-admin Repository          │
│                                     │
│   ┌─────────────────────────────┐  │
│   │  Laravel Application        │  │
│   │  - Models (Domain, etc.)    │  │
│   │  - Filament Resources       │  │
│   │  - Services                 │  │
│   └─────────────────────────────┘  │
│                                     │
│   ┌─────────────────────────────┐  │
│   │  install.sh                 │  │
│   │  - Installs dependencies    │  │
│   │  - Runs migrations          │  │
│   │  - Configures .env          │  │
│   └─────────────────────────────┘  │
└─────────────────────────────────────┘
```

### Installation Options

**Recommended: Two Separate Installations**

Each repository has its own installation process:

**Step 1: Install OpenSIPS (pbx3sbc repo)**
```bash
git clone https://github.com/your-org/pbx3sbc.git
cd pbx3sbc
sudo ./install.sh
# This creates the MySQL database and OpenSIPS tables
```

**Step 2: Install Admin Panel (pbx3sbc-admin repo)**
```bash
git clone https://github.com/your-org/pbx3sbc-admin.git
cd pbx3sbc-admin
sudo ./install.sh
# This installs Laravel, connects to existing MySQL database
```

**Pros:**
- Clear separation of concerns
- Each repo can be installed/updated independently
- Easier to version and release separately
- Cleaner git history

### Database Schema Management

#### OpenSIPS Tables (Managed by pbx3sbc)
- `domain` - Created by `scripts/init-database.sh`
- `dispatcher` - Created by `scripts/init-database.sh`
- `acc` (CDR) - Created by OpenSIPS accounting module
- `dialog` - Created by OpenSIPS dialog module

**Migration Strategy:** Changes to OpenSIPS tables are managed via SQL scripts in pbx3sbc repo.

#### Application Tables (Managed by pbx3sbc-admin)
- `users` - Laravel migration
- `password_reset_tokens` - Laravel migration
- `roles`, `permissions` - Filament Shield migrations (if used)
- Any future application-specific tables

**Migration Strategy:** Laravel migrations in `database/migrations/` directory.

### Configuration Management

#### Database Credentials
- **pbx3sbc:** Stores credentials in `/etc/opensips/.mysql_credentials`
- **pbx3sbc-admin:** Uses Laravel `.env` file
- **Recommendation:** Separate `.env` files for flexibility, but installer can read from shared credentials file to auto-populate

#### OpenSIPS MI Configuration
- **pbx3sbc Repository:** Configures OpenSIPS MI module in `opensips.cfg` (HTTP endpoint, port 8888, `/mi` path)
- **pbx3sbc-admin Repository:** Contains `OpenSIPSMIService` class (HTTP client) and configuration in `.env`:
  ```env
  OPENSIPS_MI_URL=http://192.168.1.58:8888/mi
  ```

---

## High-Level Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                  Laravel Application                         │
│  Framework: Laravel 12 + Filament (TALL Stack)              │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Filament Admin Panel (Livewire + Alpine.js)           │ │
│  │  - Call Routes Management (Filament Resource)          │ │
│  │  - CDR Monitoring (Filament Resource)                  │ │
│  │  - Active Calls Monitoring (Filament Resource)         │ │
│  │  - Authentication/Authorization (Filament)             │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Laravel Backend                                        │ │
│  │  - Eloquent Models (Domain, Dispatcher, Cdr, Dialog)  │ │
│  │  - Service Classes (OpenSIPS MI)                       │ │
│  │  - HTTP Client (Remote APIs)                           │ │
│  └────────────────────────────────────────────────────────┘ │
└───────────────┬──────────────────────┬──────────────────────┘
                │                      │
        ┌───────┴──────┐      ┌───────┴──────┐
        │   MySQL DB   │      │  OpenSIPS MI │
        │   (shared)   │      │  (HTTP/JSON) │
        └──────────────┘      └──────────────┘
```

### Technology Stack

**Selected:** PHP/Laravel/Filament Stack

- **Runtime:** PHP 8.2+ (8.3 recommended for Ubuntu 24.04)
- **Framework:** Laravel 12
- **Admin Panel:** Filament 3.x (TALL stack: Tailwind CSS, Alpine.js, Livewire, Laravel)
- **Database:** MySQL/MariaDB (shared with OpenSIPS)
- **Web Server:** Nginx (auto-installed by installer)
- **PHP-FPM:** PHP FastCGI Process Manager (auto-installed by installer)

**Why PHP/Laravel/Filament:**
- Filament is purpose-built for admin panels
- Rapid development (faster than building custom React SPA)
- Stays in PHP/Laravel ecosystem (no separate frontend tooling)
- Good for CRUD operations (perfect for domains, dispatcher management)
- Server-side reactivity via Livewire

---

## Database Design

### Core Tables (OpenSIPS - Existing, with Modifications)

#### `domain` Table
- SIP domains with `setid` column for explicit dispatcher mapping
- **Modification:** Added `setid` column (additive change, doesn't break existing functionality)
- **Schema:**
  ```sql
  ALTER TABLE domain ADD COLUMN setid INT NOT NULL DEFAULT 0;
  CREATE INDEX idx_domain_setid ON domain(setid);
  ```

#### `dispatcher` Table
- Dispatcher destinations
- Linked to domains via `setid` (not a foreign key, but used as relationship identifier)

#### `acc` Table (CDR)
- Call Detail Records
- Read-only (created by OpenSIPS)
- No timestamps (`created_at`/`updated_at`)

#### `dialog` Table
- Active calls tracking
- Primary key: `dlg_id` (NOT `id`)
- State values: 1=Unconfirmed, 2=Early, 3=Confirmed, 4=Established, 5=Ended

### Application Tables (Created by Migrations)

- `users` - Admin panel users
- `migrations` - Laravel migration tracking
- `cache`, `cache_locks` - Laravel caching
- `jobs`, `job_batches`, `failed_jobs` - Queue system
- `password_reset_tokens` - Password resets
- `sessions` - Session storage

### Future Tables (Planned)

- `opensips_instances` - Multi-instance management
- `roles`, `permissions` - RBAC (if implemented)
- `statistics` - Aggregated statistics
- `alarms` / `events` - Monitoring and alerts

---

## Core Modules

### 1. Authentication & Authorization ✅ IMPLEMENTED

**Status:** ✅ Complete
- ✅ User login/logout (Filament built-in)
- ✅ Session-based authentication (Laravel sessions)
- ⏳ Role-based access control (RBAC) - Planned, not yet implemented
- ✅ Password management
- ✅ User management (Filament resource)

### 2. Call Routes Management ✅ IMPLEMENTED

**Status:** ✅ Complete (via CallRouteResource)
- ✅ Unified Domain + Dispatcher management
- ✅ Auto-managed `setid` field (hidden from users, auto-generated)
- ✅ Multi-destination support
- ✅ OpenSIPS MI integration (domain_reload, dispatcher_reload)

**Implementation:**
- `CallRouteResource` uses `Domain` model as primary entity
- Auto-generates unique setid for each new domain
- "Manage Destinations" action redirects to Destinations panel filtered by setid

### 3. CDR and Active Calls Monitoring ✅ IMPLEMENTED

**Status:** ✅ Complete
- ✅ Read-only CDR panel with comprehensive filters
- ✅ Active Calls monitoring (Dialog resource)
- ✅ CDR statistics widget on dashboard
- ✅ Date/time filter with validation

### 4. OpenSIPS MI Integration ✅ IMPLEMENTED

**Service Class:** `OpenSIPSMIService`
- `domainReload()` - Reload domain module
- `dispatcherReload()` - Reload dispatcher module
- `dispatcherSetState()` - Set dispatcher destination state
- Graceful degradation (MI failures don't break operations)

---

## Deployment Architecture

### Phase 1: Colocated (Current)
- Single Laravel application (Filament integrated)
- Direct database access
- Single OpenSIPS instance
- Single server deployment

### Phase 2: Multi-Instance (Future)
- Laravel application can manage multiple OpenSIPS instances
- Instance configuration management in database
- Service classes route to appropriate instances
- Load balancing/failover for admin panel access

---

## Security Considerations

1. **Authentication**
   - Secure password hashing (bcrypt/Argon2)
   - Session-based authentication (Laravel sessions)
   - HTTPS only in production

2. **Authorization**
   - Role-based access control (planned)
   - Permission checks on resources/actions

3. **Input Validation**
   - Laravel Form Requests for validation
   - SQL injection prevention (Eloquent ORM)
   - XSS protection (Filament built-in)

4. **System Commands** (Future)
   - Input validation to prevent command injection
   - Sudoers file configuration for systemctl commands
   - Logging/audit trail for all service operations

---

## Related Documentation

- `PROJECT-CONTEXT.md` - Complete project overview and quick reference
- `CURRENT-STATE.md` - Current implementation status
- `UX-DESIGN-DECISIONS.md` - UX design decisions and rationale
- `CODE-QUALITY.md` - Code review and best practices
