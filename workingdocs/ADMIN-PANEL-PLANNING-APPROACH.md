# Admin Panel Planning Approach

**Date:** January 2026  
**Purpose:** Refine ADMIN-PANEL-DESIGN.md into actionable implementation plan

## Overview

The ADMIN-PANEL-DESIGN.md document provides excellent high-level architecture and design principles, but needs refinement into specific, actionable tasks with clear dependencies, acceptance criteria, and implementation details.

## Planning Approach Framework

### Phase 1: Pre-Development Decisions (Day 1)

Before writing code, we need to finalize key technical decisions:

#### 1.1 Technology Stack Finalization
- [x] **Decision:** ✅ CONFIRMED - Laravel 12 (PHP 8.2+) + Filament (TALL stack)
- [ ] **Action:** Set up version requirements (PHP 8.2+, Laravel 12, Filament 3.x)
- [ ] **Action:** Install Filament (`composer require filament/filament:"^3.0"`)
- [ ] **Action:** Authentication uses Laravel sessions (Filament built-in, no Sanctum needed)
- [ ] **Output:** Documented tech stack decision with versions

#### 1.2 Database Schema Review
- [ ] **Action:** Verify current `domain` table schema (confirm `setid` column exists)
- [ ] **Action:** Verify current `dispatcher` table schema (confirm all columns)
- [ ] **Action:** Create migration script for new application tables (`users`, `roles`, etc.)
- [ ] **Action:** Test migration on development database
- [ ] **Output:** Database schema document + migration scripts

#### 1.3 OpenSIPS MI Integration Research
- [ ] **Action:** Test OpenSIPS MI HTTP endpoint (verify format, authentication)
- [ ] **Action:** Document MI command syntax for:
  - `domain_reload`
  - `dispatcher_reload`
  - `dispatcher_set_state`
  - `dispatcher_list`
- [ ] **Action:** Create test scripts to verify MI connectivity
- [ ] **Output:** MI integration guide with examples

#### 1.4 Deployment Strategy
- [ ] **Decision:** Deployment method (systemd, Docker, or direct)
- [ ] **Action:** Define port allocation (single Laravel app port, typically 80/443)
- [ ] **Action:** Define nginx configuration if needed
- [ ] **Action:** Define environment variable strategy
- [ ] **Output:** Deployment guide

### Phase 2: Task Breakdown (Days 2-3)

Break down the MVP roadmap into specific, testable tasks:

#### 2.1 Project Setup & Scaffolding

**Subtasks:**
- [ ] Initialize Laravel project with Filament
  - Create Laravel 12 project: `composer create-project laravel/laravel:^12.0 admin-panel`
  - Configure database connection (MySQL - OpenSIPS database)
  - Set up environment variables (.env)
  - Install Filament: `php artisan filament:install --panels`
  - Create admin panel: `php artisan make:filament-user`
  - Set up logging (Laravel's built-in logging)
  - Create project structure (Models, Filament Resources, Services, Requests)

- [ ] Configure Filament
  - Set up admin panel branding
  - Configure navigation
  - Set up user authentication (Filament handles this)
  - Configure theme (dark mode, colors, etc.)

- [ ] Set up development environment
  - Configure Laravel to run on port 8000: `php artisan serve`
  - Hot reload works automatically with Livewire (Filament)
  - Development scripts (composer scripts)
  - README with setup instructions

**Acceptance Criteria:**
- ✅ Laravel application starts on port 8000
- ✅ Filament admin panel accessible at `/admin`
- ✅ Can create admin user and log in
- ✅ Can connect to MySQL database (OpenSIPS database)
- ✅ Hot reload works (Livewire handles this automatically)
- ✅ Filament installed and configured

#### 2.2 Authentication Module

**Subtasks:**
- [ ] Database schema for authentication
  - Create `users` table migration
  - Create `roles` table migration (if RBAC needed)
  - Create `user_roles` table migration (if RBAC needed)

- [ ] Authentication setup (Filament built-in)
  - Create admin user: `php artisan make:filament-user`
  - Filament handles login/logout pages
  - Filament uses Laravel session authentication
  - Password hashing (Laravel's Hash facade uses bcrypt by default)
  - Filament panel is protected by default

- [ ] Optional: RBAC setup (if needed)
  - Install Filament Shield: `composer require bezhansalleh/filament-shield`
  - Configure roles and permissions
  - Set up role-based access to resources

**Acceptance Criteria:**
- ✅ Admin user can be created
- ✅ User can log in at `/admin/login`
- ✅ Admin panel is protected (requires authentication)
- ✅ User can log out
- ✅ Session persists on page refresh
- ✅ Optional: Roles and permissions configured (if RBAC needed)

#### 2.3 Domain Management (Filament Resource)

**Subtasks:**
- [ ] Create Domain Eloquent Model
  - Configure table name, fillable fields
  - Set timestamps to false (OpenSIPS table doesn't use Laravel timestamps)

- [ ] Create Filament DomainResource
  - Generate: `php artisan make:filament-resource Domain`
  - Configure table columns (domain, setid, etc.)
  - Configure form fields (domain input, setid input, etc.)
  - Add form validation rules
  - Add table filters (by setid, etc.)
  - Add custom action for "Reload OpenSIPS" (calls OpenSIPS MI)

- [ ] OpenSIPS MI integration
  - Create `OpenSIPSMIService` class (Laravel service)
  - Implement `domainReload()` method
  - Use Laravel's Http facade for HTTP requests
  - Error handling and logging (Laravel's Log facade)
  - Called from Filament action after create/update/delete

- [ ] Form Request Validation (optional)
  - Create `StoreDomainRequest` and `UpdateDomainRequest`
  - Validate domain format, setid, etc.
  - Use in Filament Resource form

**Acceptance Criteria:**
- ✅ Filament resource lists all domains in table
- ✅ Can create new domain with setid via Filament form
- ✅ Can edit existing domain via Filament form
- ✅ Can delete domain via Filament action
- ✅ Domain validation (format, uniqueness) works
- ✅ OpenSIPS domain module reloads after changes (via Filament action)
- ✅ Filament shows success/error notifications
- ✅ Table supports filtering, sorting, pagination (Filament built-in)

#### 2.4 Dispatcher Management (Filament Resource)

**Subtasks:**
- [ ] Create Dispatcher Eloquent Model
  - Configure table name, fillable fields
  - Set timestamps to false

- [ ] Create Filament DispatcherResource
  - Generate: `php artisan make:filament-resource Dispatcher`
  - Configure table columns (setid, destination, state, etc.)
  - Configure form fields (destination input, setid select, etc.)
  - Add form validation rules (SIP URI format, etc.)
  - Add table filter for setid
  - Add custom table column for state (badge/icon)
  - Add custom actions:
    - "Set Active" / "Set Inactive" (updates state, calls OpenSIPS MI)
    - "Reload OpenSIPS" (calls dispatcher_reload)

- [ ] OpenSIPS MI integration for dispatcher
  - Add methods to `OpenSIPSMIService` class
  - Implement `dispatcherReload()` method
  - Implement `dispatcherSetState()` method
  - Implement `dispatcherList()` method (for future stats/widget)

- [ ] Form Request Validation (optional)
  - Create `StoreDispatcherRequest` and `UpdateDispatcherRequest`
  - Validate destination format (SIP URI), setid, etc.

**Acceptance Criteria:**
- ✅ Filament resource lists all dispatcher destinations in table
- ✅ Can filter by setid (Filament table filter)
- ✅ Can create new destination with setid via Filament form
- ✅ Can edit destination via Filament form
- ✅ Can delete destination via Filament action
- ✅ Can toggle active/inactive state via Filament action
- ✅ OpenSIPS dispatcher module reloads after changes
- ✅ Filament shows success/error notifications
- ✅ Table supports filtering, sorting, pagination (Filament built-in)

#### 2.5 S3/Minio Object Storage Management

**Subtasks:**
- [ ] Configure Laravel Storage for Minio
  - Add Minio disk to `config/filesystems.php`
  - Configure S3-compatible settings (endpoint, credentials)
  - Test connection

- [ ] Create Filament Resource or Custom Page for S3 Objects
  - Option A: Filament Resource (if storing object metadata in DB)
  - Option B: Custom Filament Page (for direct S3/Minio access)
  - List objects/buckets in table
  - Upload objects (Filament file upload component)
  - Download objects (Filament action)
  - Delete objects (Filament action)
  - Browse directory structure

- [ ] Implement Storage Service
  - Service class wrapping Storage facade operations
  - Handle errors gracefully
  - Log operations

**Acceptance Criteria:**
- ✅ Can list objects/buckets in Filament
- ✅ Can upload objects via Filament form
- ✅ Can download objects via Filament action
- ✅ Can delete objects via Filament action
- ✅ Operations use Laravel Storage facade

**See:** LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md for implementation details

#### 2.6 Linux Service Management

**Subtasks:**
- [ ] Create SystemService class
  - Wrap systemctl commands using Laravel Process facade
  - Methods: startService(), stopService(), restartService(), getStatus()
  - Input validation (prevent command injection)
  - Error handling and logging

- [ ] Configure sudoers (security setup)
  - Create sudoers file for PHP user
  - Allow systemctl commands (specific services or all)
  - Test sudo configuration

- [ ] Create Filament Resource for Services
  - List services (table view)
  - Custom actions: Start, Stop, Restart
  - Custom column showing status (active/inactive badge)
  - View logs action (optional)

- [ ] Security considerations
  - Validate service names (prevent command injection)
  - Log all service operations
  - Permission checks

**Acceptance Criteria:**
- ✅ Can list services in Filament table
- ✅ Can start service via Filament action
- ✅ Can stop service via Filament action
- ✅ Can restart service via Filament action
- ✅ Service status displayed in table
- ✅ All operations logged
- ✅ Input validation prevents command injection

**See:** LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md for implementation details

#### 2.7 Remote API Integration

**Subtasks:**
- [ ] Create ExternalApiService class(es)
  - Service class for each external API
  - Use Laravel HTTP facade for API calls
  - Handle authentication (tokens, keys, etc.)
  - Error handling and retries
  - Optional: Caching to reduce API calls

- [ ] Create Filament Resource for API Data
  - List API data (table view)
  - Fetch/sync action (calls external API)
  - Update action (sends data to external API)
  - View API response history/logs

- [ ] Background Jobs (optional, for long-running operations)
  - Create Laravel jobs for API operations
  - Use queues for async processing
  - Show job status in Filament

- [ ] API Configuration Management
  - Store API endpoints, keys, etc. in database or config
  - Filament resource for managing API configurations

**Acceptance Criteria:**
- ✅ Can fetch data from remote APIs via Filament action
- ✅ Can update data to remote APIs via Filament form/action
- ✅ API errors handled gracefully
- ✅ Operations use Laravel HTTP facade
- ✅ Optional: Background jobs for long-running operations
- ✅ Optional: Caching reduces API calls

**See:** LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md for implementation details

#### 2.8 Filament Configuration & UI Customization

**Subtasks:**
- [ ] Configure Filament panel
  - Branding (logo, colors, name)
  - Navigation (organize resources in sidebar)
  - Theme customization (colors, dark mode)
  - Custom dashboard (optional Filament page)

- [ ] Filament navigation setup
  - Add Domain resource to navigation
  - Add Dispatcher resource to navigation
  - Organize navigation groups if needed

- [ ] Custom Filament dashboard (optional)
  - Create dashboard page: `php artisan make:filament-page Dashboard`
  - Add widgets for stats (domain count, dispatcher count, etc.)
  - Custom dashboard is optional - Filament has default dashboard

**Acceptance Criteria:**
- ✅ Filament admin panel is accessible at `/admin`
- ✅ Navigation shows Domain and Dispatcher resources
- ✅ Branding is configured (logo, colors)
- ✅ UI is clean and modern (Filament default)
- ✅ Responsive on mobile/tablet/desktop (Filament built-in)
- ✅ Dark mode works (Filament built-in)

### Phase 3: Implementation Order & Dependencies

#### Recommended Implementation Sequence:

1. **Week 1: Foundation**
   - Project setup (backend + frontend)
   - Database migrations (auth tables)
   - Basic authentication (login/logout)
   - Basic UI layout

2. **Week 2: Domain Management**
   - Domain API endpoints
   - OpenSIPS MI client (basic)
   - Domain frontend (list, add, edit, delete)
   - Integration testing

3. **Week 3: Dispatcher Management**
   - Dispatcher API endpoints
   - OpenSIPS MI dispatcher methods
   - Dispatcher frontend (list, add, edit, delete, state toggle)
   - Integration testing

4. **Week 4: Polish & Testing**
   - Error handling improvements
   - UI/UX refinements
   - End-to-end testing
   - Documentation
   - Deployment preparation

#### Dependencies:

```
Project Setup (Laravel + Filament)
    ↓
Database Migrations (users table)
    ↓
Filament Installation & Configuration
    ↓
OpenSIPS MI Service (basic)
    ↓
Domain Model + Filament Resource ← ────┐
    ↓                                    │
Dispatcher Model + Filament Resource ← Uses OpenSIPS MI Service
    ↓
Additional Requirements:
  - S3/Minio Resource
  - Service Management Resource
  - Remote API Integration
```

### Phase 4: Detailed Task Specifications

For each major task, create a detailed specification:

#### Task Specification Template:

```markdown
## Task: [Task Name]

**Priority:** High/Medium/Low  
**Estimated Time:** X hours/days  
**Dependencies:** [List dependencies]

### Description
[Brief description]

### Acceptance Criteria
- [ ] Criterion 1
- [ ] Criterion 2
- [ ] Criterion 3

### Technical Details
- Filament Resource: `DomainResource`
- Database changes: None (uses existing domain table)
- OpenSIPS MI: Calls `domain_reload` via Filament action
- Eloquent Model: `Domain`

### Testing Requirements
- Unit tests: [What to test]
- Integration tests: [What to test]
- Manual testing: [What to verify]

### Notes/Considerations
- [Any special considerations]
```

### Phase 5: Risk Assessment & Mitigation

Identify potential risks and mitigation strategies:

1. **OpenSIPS MI Integration Complexity**
   - **Risk:** MI endpoint format/syntax unclear
   - **Mitigation:** Research and test MI early (Phase 1.3)

2. **Database Schema Changes**
   - **Risk:** Existing data conflicts
   - **Mitigation:** Test migrations on copy of production data

3. **Authentication Security**
   - **Risk:** Security vulnerabilities
   - **Mitigation:** Use established libraries, security review

4. **Time Overruns**
   - **Risk:** MVP takes longer than 4 weeks
   - **Mitigation:** Prioritize core features, defer enhancements

### Phase 6: Success Metrics

Define how to measure success:

- [ ] All MVP features working (domain + dispatcher CRUD)
- [ ] User can complete all core workflows without errors
- [ ] API response times < 200ms (p95)
- [ ] Zero security vulnerabilities (basic security audit)
- [ ] Code coverage > 70% (if unit tests implemented)
- [ ] Documentation complete (API docs, setup guide)

## Next Steps

1. **Review this planning approach** - Does this structure work?
2. **Finalize Phase 1 decisions** - Make the key technical decisions
3. **Create detailed task specifications** - For each task in Phase 2
4. **Set up project tracking** - Use GitHub Issues, project board, or similar
5. **Begin Phase 1 implementation** - Start with pre-development decisions

## Laravel-Specific Notes

See `LARAVEL-IMPLEMENTATION-GUIDE.md` for detailed Laravel implementation guidance, including:
- Project structure
- Eloquent model configuration for OpenSIPS tables
- Sanctum authentication setup
- OpenSIPS MI service implementation
- Form Request validation
- Deployment considerations

## Questions to Resolve

Before starting implementation, clarify:

1. **Authentication:** Do we need RBAC from the start, or can we start with simple admin users?
2. **OpenSIPS MI:** What's the exact endpoint URL and authentication method? (Research in Phase 1.3)
3. **Database:** ✅ Confirmed - `domain` table already has `setid` column (from opensipspure branch)
4. **Deployment:** Where will this run? Same server as OpenSIPS? (Phase 1.4)
5. **UI Library:** shadcn/ui, Material-UI, or another? (affects setup time)
6. **Testing:** What level of testing is required for MVP? (Laravel has built-in PHPUnit)
7. **Laravel Version:** ✅ Laravel 12 (confirmed - current stable release)
8. **Filament Version:** Filament 3.x (latest stable)
9. **Additional Requirements:** ✅ S3/Minio, Service Management, Remote APIs - All supported (see LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md)

---

**Recommendation:** Start with Phase 1 (Pre-Development Decisions) and work through systematically. Once Phase 1 is complete, create detailed task specifications for Phase 2 tasks, then begin implementation.
