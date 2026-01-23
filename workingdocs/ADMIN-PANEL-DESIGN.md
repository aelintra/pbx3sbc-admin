# OpenSIPS Admin Panel - Design Document

**Date:** January 2026  
**Last Updated:** 2026-01-22  
**Version:** 1.0  
**Status:** Implementation in Progress - Core features complete

## Executive Summary

This document outlines the design for a modern replacement of the OpenSIPS Control Panel (OCP). The goal is to create a clean, maintainable, extensible web application that can grow with requirements while avoiding technical debt from patching upstream code.

## Design Principles

1. **Separation of Concerns** - Clean API/UI separation
2. **Extensibility** - Easy to add new features/modules over time
3. **Multi-Instance Ready** - Architecture supports managing multiple OpenSIPS servers
4. **Modern UX** - Intuitive, responsive interface
5. **No Core Modifications** - OpenSIPS core remains untouched
6. **Maintainability** - Clean code, good documentation, testable

## Architecture Overview

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  Laravel Application                         │
│  Framework: Laravel 12 + Filament (TALL Stack)              │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Filament Admin Panel (Livewire + Alpine.js)           │ │
│  │  - Domain Management (Filament Resource)               │ │
│  │  - Dispatcher Management (Filament Resource)           │ │
│  │  - Service Management (Filament Resource)              │ │
│  │  - Remote API Integration (Filament Resource)          │ │
│  │  - S3/Minio Object Management (Future - Filament Resource)│ │
│  │  - Authentication/Authorization (Filament)             │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Laravel Backend                                        │ │
│  │  - Eloquent Models (Domain, Dispatcher, etc.)          │ │
│  │  - Service Classes (OpenSIPS MI, System Services)      │ │
│  │  - HTTP Client (Remote APIs)                           │ │
│  │  - Storage Facade (S3/Minio)                           │ │
│  │  - Process Facade (System Commands)                    │ │
│  └────────────────────────────────────────────────────────┘ │
└───────────────┬──────────────────────┬──────────────────────┘
                │                      │
        ┌───────┴──────┐      ┌───────┴──────┐
        │   MySQL DB   │      │  OpenSIPS MI │
        │   (local)    │      │  (HTTP/JSON) │
        └──────────────┘      └──────────────┘
                │
        ┌───────┴──────┐
        │  S3/Minio    │
        │  (Object     │
        │   Storage)   │
        └──────────────┘
```

### Deployment Evolution

**Phase 1: Colocated (Initial)**
- Single Laravel application (Filament integrated)
- Direct database access
- Single OpenSIPS instance
- Single server deployment

**Phase 2: Multi-Instance (Future)**
- Laravel application can manage multiple OpenSIPS instances
- Instance configuration management in database
- Service classes route to appropriate instances
- Load balancing/failover for admin panel access

## Technical Stack Recommendations

### Option A: Node.js Stack (Recommended)

**Backend:**
- **Runtime:** Node.js 18+ LTS
- **Framework:** Express.js or Fastify
- **Database:** MySQL2 or Prisma ORM
- **Authentication:** JWT + bcrypt
- **Validation:** Joi or Zod

**Frontend:**
- **Framework:** React 18+ with TypeScript
- **State Management:** React Query / TanStack Query
- **UI Library:** Tailwind CSS + shadcn/ui or Material-UI
- **HTTP Client:** Axios or Fetch API
- **Build Tool:** Vite

**Why Node.js:**
- Single language (JavaScript/TypeScript) for full stack
- Excellent async/await support for API calls
- Large ecosystem
- Good performance
- Easy deployment

### Option B: PHP Stack (If Prefer Existing Skills)

**Backend:**
- **Runtime:** PHP 8.2+
- **Framework:** Laravel 12 (current stable release)
- **Database:** Eloquent ORM or Doctrine
- **Authentication:** Laravel Sanctum / Passport

**Frontend:**
- **Framework:** Filament (TALL stack - Livewire + Alpine.js + Tailwind CSS)
- Integrated with Laravel (no separate frontend project)
- Server-side reactivity via Livewire

**Why PHP/Laravel/Filament:**
- Team familiarity with Laravel
- Existing PHP infrastructure
- Filament is purpose-built for admin panels
- Rapid development (faster than building custom React SPA)
- Stays in PHP/Laravel ecosystem (no separate frontend tooling)
- Good for CRUD operations (perfect for domains, dispatcher management)

### Option C: Python Stack

**Backend:**
- **Runtime:** Python 3.11+
- **Framework:** FastAPI
- **Database:** SQLAlchemy
- **Authentication:** JWT

**Frontend:**
- Same as Option A

**Why Python:**
- Excellent for data processing (future CDR/statistics)
- FastAPI is modern and fast
- Good for analytics features

**Recommendation:** Node.js (Option A) for consistency and modern tooling

**✅ SELECTED:** Option B - PHP/Laravel/Filament Stack (selected January 2026)

**Frontend Decision:** Filament (TALL stack) - See FRONTEND-OPTIONS-DETAILED-ANALYSIS.md for comparison

## Core Modules (MVP)

### 1. Authentication & Authorization ✅ IMPLEMENTED

**Status:** ✅ Complete
- ✅ User login/logout (Filament built-in)
- ✅ Session-based authentication (Laravel sessions)
- ⏳ Role-based access control (RBAC) - Planned, not yet implemented
- ✅ Password management
- ✅ User management (Filament resource)

**Implementation:**
- Filament provides authentication panels out of the box
- Uses Laravel's built-in authentication (sessions, not JWT)
- RBAC can be added via Filament Shield plugin (see `FILAMENT-ROLES-PERMISSIONS-GUIDE.md`)
- User management via Filament resource

**Database:**
- ✅ `users` table (created by Laravel migrations)
- ⏳ `roles` table (if using RBAC package - planned)
- ⏳ `permissions` table (if using RBAC package - planned)

### 2. Domain Management ✅ IMPLEMENTED

**Status:** ✅ Complete (via CallRouteResource)
- ✅ List domains (Filament table)
- ✅ Add domain (Filament form)
- ✅ Edit domain (Filament form)
- ✅ Delete domain (Filament action)
- ✅ Domain validation (Laravel Form Requests)
- ✅ Link to dispatcher set ID (explicit setid column, auto-managed)
- ✅ Reload OpenSIPS domain module (Filament action → OpenSIPS MI)

**Implementation:**
- Filament Resource (`CallRouteResource`) for unified domain + dispatcher management
- Uses Eloquent Domain model
- Form validation via Laravel Form Requests
- Custom Filament actions for OpenSIPS MI calls (reload)
- Auto-manages setid (hidden from users)

**Database:**
- `domain` table (OpenSIPS standard table, with added `setid` column)
- **Decision:** Add `setid` column to domain table (explicit mapping)
- **Rationale:** IDs are surrogate keys and should be allowed to change. Explicit setid provides flexibility and decouples domain identity from dispatcher routing.

**Schema Modification:**
```sql
ALTER TABLE domain ADD COLUMN setid INT NOT NULL DEFAULT 0;
-- Migration: Set setid to id for existing domains (one-time)
UPDATE domain SET setid = id WHERE setid = 0;
-- Add index for setid lookups
CREATE INDEX idx_domain_setid ON domain(setid);
```

**Data Model:**
```typescript
interface Domain {
  id: number;              // Surrogate key (can change)
  domain: string;
  setid: number;           // Explicit dispatcher set ID (stable routing identifier)
  attrs?: string;
  accept_subdomain: number;
  last_modified: string;
}
```

### 3. Dispatcher Management ✅ IMPLEMENTED

**Status:** ✅ Complete (via CallRouteResource and DispatcherResource)
- ✅ List dispatcher destinations
- ✅ Add destination (with set ID, auto-managed)
- ✅ Edit destination
- ✅ Delete destination
- ✅ Set state (active/inactive)
- ⏳ View health status (planned)
- ✅ Group by set ID (via CallRouteResource)
- ✅ Filter by set ID

**Implementation:**
- Unified management via `CallRouteResource` (primary interface)
- `DispatcherResource` available for direct destination management
- "Manage Destinations" modal for multi-destination domains
- OpenSIPS MI integration for reload operations

**Database:**
- ✅ `dispatcher` table (OpenSIPS standard table)

**Data Model:**
```typescript
interface DispatcherDestination {
  id: number;
  setid: number;
  destination: string;
  socket?: string;
  state: number;  // 0=active, 1=inactive, etc.
  weight: string;
  priority: number;
  attrs?: string;
  description?: string;
  probe_mode: number;
}
```

### 4. S3/Minio Object Storage Management ⏳ PLANNED

**Status:** ⏳ Planned (deferred)
- ⏳ List objects/buckets (Filament table)
- ⏳ Upload objects (Filament file upload)
- ⏳ Download objects (Filament action)
- ⏳ Delete objects (Filament action)
- ⏳ Browse directory structure
- ⏳ View object metadata (size, type, last modified)
- ⏳ Manage buckets (create, delete)

**Implementation Notes:**
- Filament Resource or custom Filament page
- Uses Laravel Storage facade with S3/Minio driver
- Filament file upload components for uploads
- Custom actions for download/delete
- Storage facade handles all S3/Minio operations

**See:** `LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md` for detailed implementation notes

### 5. Linux Service Management ⏳ PLANNED

**Status:** ⏳ Planned
- ⏳ List services (Filament table)
- ⏳ Start service (Filament action)
- ⏳ Stop service (Filament action)
- ⏳ Restart service (Filament action)
- ⏳ View service status (Filament custom column)
- ⏳ View service logs (Filament custom page/modal)

**Implementation Notes:**
- Filament Resource for service management
- Service class wrapping systemctl commands
- Uses Laravel Process facade for system commands
- Custom Filament actions for start/stop/restart
- Requires sudoers configuration or service user permissions

**Security Considerations:**
- Input validation to prevent command injection
- Sudoers file configuration for systemctl commands
- Logging/audit trail for all service operations
- Permission checks before executing commands

**See:** `LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md` for detailed implementation notes

### 6. Remote API Integration ⏳ PLANNED

**Status:** ⏳ Planned
- ⏳ List API data (Filament table)
- ⏳ Fetch data from remote APIs (Filament action)
- ⏳ Update data to remote APIs (Filament form/action)
- ⏳ API configuration management (Filament resource)
- ⏳ View API response history/logs
- ⏳ Handle API errors gracefully

**Implementation Notes:**
- Filament Resources for API data management
- Service classes for each external API
- Uses Laravel HTTP facade (Guzzle) for API calls
- Custom Filament actions for sync/fetch operations
- Background jobs (queues) for long-running API operations
- Caching to reduce API calls

**See:** `LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md` for detailed implementation notes

### 7. Multi-Instance Management ⏳ FUTURE

**Status:** ⏳ Future enhancement
- ⏳ Add/remove OpenSIPS instances
- ⏳ Instance configuration (MI endpoint, database connection)
- ⏳ Instance health monitoring
- ⏳ Route API requests to specific instances

**Implementation Notes:**
- Filament Resource for instance management
- Service classes route to appropriate instances
- Health monitoring via custom Filament widgets
- Configuration stored in database

**Database:**
- ⏳ `opensips_instances` table (new, not yet created)
- Links domains/dispatcher operations to specific instances

## Database Design

### Core Tables (OpenSIPS - Existing, with Modifications)

- `domain` - SIP domains (with added `setid` column for explicit dispatcher mapping)
- `dispatcher` - Dispatcher destinations
- `version` - Schema version tracking

**Domain Table Modification:**
```sql
-- Add setid column to domain table
ALTER TABLE domain ADD COLUMN setid INT NOT NULL DEFAULT 0;

-- Migration: Set setid to id for existing domains (one-time)
UPDATE domain SET setid = id WHERE setid = 0;

-- Add index for setid lookups (performance)
CREATE INDEX idx_domain_setid ON domain(setid);
```

**Note:** While this modifies the OpenSIPS schema, it's an additive change that doesn't break existing functionality. The domain module doesn't use the setid column - it's purely for our routing logic.

### Application Tables (New)

```sql
-- Users and authentication
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  email VARCHAR(255),
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Roles (optional, for RBAC)
CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) UNIQUE NOT NULL,
  description TEXT
);

-- User roles (many-to-many)
CREATE TABLE user_roles (
  user_id INT UNSIGNED,
  role_id INT UNSIGNED,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- OpenSIPS Instances (future multi-instance support)
CREATE TABLE opensips_instances (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  description TEXT,
  mi_url VARCHAR(255) NOT NULL,  -- e.g., "http://192.168.1.58:8888/mi"
  db_host VARCHAR(255),
  db_port INT,
  db_name VARCHAR(64),
  db_user VARCHAR(64),
  db_password VARCHAR(255),  -- Encrypted
  enabled BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Extensibility Considerations

**Future Tables (as needed):**
- `cdr` / `acc` - Call Detail Records (OpenSIPS accounting)
- `trunks` - SIP trunk configuration
- `ddi` - Direct Dial-In numbers
- `statistics` - Aggregated statistics
- `alarms` / `events` - Monitoring and alerts

**Design Pattern:**
- Each new feature gets its own table(s)
- API endpoints follow RESTful conventions
- Frontend modules are loosely coupled

## Filament Resource Design

### Resource Structure

Each module (Domain, Dispatcher, etc.) is implemented as a Filament Resource:

- **Table** - Lists records with sorting, filtering, pagination
- **Form** - Create/edit forms with validation
- **Actions** - Custom actions (reload, start/stop services, etc.)
- **Filters** - Table filters (by setid, status, etc.)
- **Relations** - Relationship management if needed

### Authentication

- Laravel session-based authentication (Filament built-in)
- Login/logout handled by Filament
- Session management by Laravel
- CSRF protection built-in

### OpenSIPS MI Integration

**Service Class:**
```php
class OpenSIPSMIService {
  public function call(string $command, array $params = []): array
  public function domainReload(): void
  public function dispatcherReload(): void
  public function dispatcherSetState(int $setid, string $destination, int $state): void
  // ... more methods
}
```

**Implementation:**
- Laravel HTTP facade for HTTP POST to OpenSIPS MI endpoint
- JSON-RPC format
- Error handling and logging
- Used by Filament actions/resources
- Support for multiple instances (future)

## Filament Application Structure

### Laravel Project Structure

```
app/
├── Filament/
│   ├── Resources/
│   │   ├── DomainResource.php
│   │   ├── DispatcherResource.php
│   │   ├── S3ObjectResource.php (or custom page)
│   │   ├── ServiceResource.php
│   │   └── RemoteApiResource.php
│   ├── Pages/
│   │   └── Dashboard.php (custom dashboard)
│   └── Widgets/
│       └── ServiceStatusWidget.php
├── Models/
│   ├── Domain.php
│   ├── Dispatcher.php
│   └── User.php
├── Services/
│   ├── OpenSIPSMIService.php
│   ├── SystemService.php
│   └── ExternalApiService.php
└── Http/
    └── Requests/
        ├── StoreDomainRequest.php
        └── UpdateDomainRequest.php
```

### UI/UX Considerations (Filament Built-in)

- **Responsive Design** - Filament is responsive by default
- **Dark Mode** - Filament has built-in dark mode support
- **Real-time Updates** - Livewire provides reactivity (server-side)
- **Form Validation** - Filament integrates with Laravel validation
- **Loading States** - Filament handles loading states automatically
- **Error Handling** - Filament displays validation errors and notifications
- **Confirmation Dialogs** - Filament actions support confirmations
- **Notifications** - Filament has built-in notification system

## Security Considerations

1. **Authentication**
   - Secure password hashing (bcrypt/Argon2)
   - JWT with appropriate expiration
   - HTTPS only in production

2. **Authorization**
   - Role-based access control
   - API endpoint protection
   - Database query sanitization (prepared statements)

3. **Input Validation**
   - Client-side and server-side validation
   - SQL injection prevention (ORM/prepared statements)
   - XSS prevention (sanitize output)

4. **API Security**
   - Rate limiting
   - CORS configuration
   - Request size limits

5. **Database**
   - Encrypted connections
   - Credential management (environment variables)
   - Backup and recovery procedures

## Deployment Architecture

### Phase 1: Colocated (Single Server)

```
Server: 192.168.1.58
├── OpenSIPS (port 5060)
├── MySQL (port 3306)
├── Admin Panel (Laravel + Filament) (port 80/443, nginx + PHP-FPM)
│   └── Single application (no separate frontend/backend)
└── S3/Minio (optional, separate service)
```

### Phase 2: Multi-Instance (Future)

```
Admin Panel Server (Laravel + Filament)
├── Application (port 80/443, nginx + PHP-FPM)
│   ├── Instance Manager (Laravel service)
│   └── Routing Layer (Laravel service classes)
│       ├── OpenSIPS Instance 1 (192.168.1.58)
│       ├── OpenSIPS Instance 2 (192.168.1.59)
│       └── OpenSIPS Instance N
└── Shared Services
    ├── MySQL (database)
    └── S3/Minio (object storage)
```

## Development Roadmap

### Phase 1: MVP ✅ COMPLETE
- [x] Project setup (Laravel + Filament)
- [x] Authentication module (Filament built-in)
- [x] Domain management (Filament Resource - via CallRouteResource)
- [x] Dispatcher management (Filament Resource - via CallRouteResource)
- [x] OpenSIPS MI integration (Service class)
- [x] CDR/Accounting module (CDR viewing)
- [x] Active Calls monitoring (Dialog resource)
- [x] Testing and documentation

### Phase 2: Enhancement ⏳ IN PROGRESS
- [x] Advanced table features (search, filters, pagination - Filament built-in)
- [x] Statistics/dashboards (CDR stats widget)
- [ ] Linux service management (Filament Resource)
- [ ] Remote API integration (Filament Resource/Service)
- [ ] Health monitoring (Filament widgets)
- [ ] Performance optimization

### Phase 3: Future Features ⏳ PLANNED
- [ ] Trunking management
- [ ] DDI management
- [ ] Multi-instance support
- [ ] Advanced analytics
- [ ] Alerting/notifications
- [ ] S3/Minio object storage management (for long-term statistics, logs, and traces)
- [ ] Roles and permissions system (RBAC)

## Migration Strategy

### From Current Control Panel

1. **Data Migration**
   - Export domains from current panel
   - Export dispatcher entries
   - Import into new system
   - Verify data integrity

2. **User Migration**
   - Create user accounts in new system
   - Set up authentication
   - Configure permissions

3. **Deployment**
   - Run new panel on different port initially
   - Test thoroughly
   - Switch over when ready
   - Keep old panel as backup for short period

4. **Cleanup**
   - Remove old control panel
   - Clean up unused tables/config

## Technology Choices Summary

**Selected Stack:**
- **Backend/Frontend:** Laravel 12 + Filament (integrated)
- **Database:** MySQL (existing OpenSIPS database)
- **Authentication:** Laravel sessions (Filament built-in)
- **UI Framework:** Filament (Livewire + Alpine.js + Tailwind CSS)
- **Package Manager:** Composer (PHP)
- **Additional Requirements:**
  - Laravel Storage facade (S3/Minio)
  - Laravel Process facade (system service management)
  - Laravel HTTP facade (remote API integration)

## Design Decisions

1. **Domain → Set ID Mapping:**
   - **Decision:** Add `setid` column to domain table
   - **Rationale:** IDs are surrogate keys and should be allowed to change. Explicit setid column provides:
     - Decoupling of domain identity from routing
     - Flexibility to change set IDs independently
     - Better alignment with best practices (explicit over implicit)
     - Easier to understand and maintain
   - **Implementation:** Modify domain table schema to add `setid INT NOT NULL` column
   - **Migration:** Existing domains will need setid values assigned (can default to id initially)

2. **Database Access:**
   - Direct access from API (Phase 1)
   - Separate database service layer (Phase 2 multi-instance)
   - Consider connection pooling

3. **Real-time Updates:**
   - WebSocket for live stats?
   - Polling sufficient initially?
   - Server-Sent Events (SSE)?

4. **Deployment:**
   - Docker containers?
   - Systemd services?
   - Cloud deployment considerations?

## Success Criteria

- ✅ Modern, intuitive user interface
- ✅ All MVP features working
- ✅ No modifications to OpenSIPS core
- ✅ Clean, maintainable codebase
- ✅ Extensible architecture for future features
- ✅ Production-ready security
- ✅ Good documentation
- ✅ Performance meets requirements

## Next Steps

1. Review and approve design
2. Decide on tech stack
3. Set up development environment
4. Create project repository
5. Begin Phase 1 development
6. Regular reviews and iterations

---

**Document Owner:** Development Team  
**Last Updated:** January 2026  
**Status:** Draft - Awaiting Review

