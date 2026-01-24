# Authentication vs Authorization Clarification

**Date:** January 2026  
**Topic:** Which Laravel authentication system to use with Filament

## Key Distinction

### Authentication (Who You Are)
- **Purpose:** Verify user identity (login/logout)
- **Examples:** Sanctum, Passport, Laravel Sessions, Breeze, Jetstream

### Authorization (What You Can Do)
- **Purpose:** Control what authenticated users can access
- **Examples:** Policies, Gates, Spatie Permission, Filament Shield

---

## For Filament Admin Panel: Laravel Sessions 

### Filament's Built-in Authentication

Filament provides authentication out of the box:
- Login page (`/admin/login`)
- Logout functionality
- Session-based authentication
- Password reset
- User creation (`php artisan make:filament-user`)

No additional packages needed for basic authentication.

---

## Authentication Stack for This Project

### Layer 1: Authentication (Who You Are)

**System:** Laravel Standard Sessions  
**Package:** None (built into Laravel/Filament)

```php
// Filament handles this automatically
// Users log in via /admin/login
// Sessions managed by Laravel
// No API tokens needed
```

### Layer 2: Authorization (What You Can Do)

**System:** Filament Shield + Spatie Laravel Permission  
**Packages:**
- `spatie/laravel-permission` - RBAC package
- `bezhansalleh/filament-shield` - Filament integration

```php
// Controls permissions/roles
// Who can view/edit/delete resources
// Resource-level, action-level, field-level permissions
```

---

## Comparison of Laravel Authentication Systems

### 1. Laravel Sessions (What We're Using)

**Use Case:** Traditional web applications, server-rendered apps (Filament)

**Characteristics:**
- Stateful (sessions stored on server)
- Cookie-based
- Built into Laravel
- Perfect for Filament

**Installation:**
```bash
# No installation needed - built into Laravel
# Filament handles it automatically
```

**Example:**
```php
// User logs in → Session created
// Session cookie sent to browser
// Subsequent requests include session cookie
// Laravel validates session
```

### 2. Sanctum (NOT Using)

**Use Case:** API authentication, SPAs, mobile apps

**Characteristics:**
- Stateless (API tokens)
- Token-based authentication
- For APIs/SPAs
- NOT for Filament

**When to use:**
- React/Vue SPA frontend (separate from Laravel)
- Mobile app API
- Third-party API access

**Example:**
```php
// User logs in → API token returned
// Token stored in localStorage/cookie
// Token sent in Authorization header
// Laravel validates token
```

### 3. Passport (NOT Using)

**Use Case:** OAuth2 server (like Google OAuth)

**Characteristics:**
- OAuth2 implementation
- For building OAuth providers
- Overkill for admin panel
- NOT for Filament

**When to use:**
- Building an OAuth2 server
- Third-party app integration
- Complex OAuth flows

### 4. Breeze/Jetstream (NOT Using)

**Use Case:** Full authentication scaffolding for custom apps

**Characteristics:**
- Pre-built authentication UI
- Registration, login, password reset
- Two-factor authentication (Jetstream)
- Overkill for Filament (Filament has its own UI)

**When to use:**
- Custom Laravel application (not Filament)
- Need authentication scaffolding
- Starting from scratch

---

## Our Stack Breakdown

### Authentication (Who You Are)

**System:** Laravel Standard Sessions  
**Implementation:** Filament built-in  
**Package:** None  
**How it works:**
- Users log in at `/admin/login`
- Filament creates Laravel session
- Session cookie stored in browser
- Laravel validates session on each request

**Configuration:**
```php
// config/session.php (Laravel default)
'driver' => env('SESSION_DRIVER', 'file'),
'lifetime' => env('SESSION_LIFETIME', 120),
// ...
```

### Authorization (What You Can Do)

**System:** Filament Shield + Spatie Permission  
**Implementation:** Custom setup  
**Packages:**
- `spatie/laravel-permission`
- `bezhansalleh/filament-shield`

**How it works:**
- Roles and permissions stored in database
- Filament Shield checks permissions
- Resources/pages/actions respect permissions
- UI controls what users can see/do

---

## Why This Confusion?

In our earlier planning (before switching to Filament), we considered:
- React SPA frontend
- Laravel API backend
- **Sanctum** for API token authentication

But after choosing Filament:
- Single Laravel application (no separate frontend)
- Server-rendered (Livewire)
- **Laravel Sessions** for authentication
- **Filament Shield** for authorization/permissions

---

## Summary

### For This Filament Admin Panel:

✅ **Authentication:** Laravel Standard Sessions (Filament built-in)  
✅ **Authorization:** Filament Shield + Spatie Permission

❌ **NOT Using:**
- Sanctum (for APIs/SPAs)
- Passport (for OAuth2 servers)
- Breeze/Jetstream (Filament has its own UI)

### Installation Commands:

```bash
# Authentication: None needed (Filament handles it)

# Authorization: Install these
composer require spatie/laravel-permission
composer require bezhansalleh/filament-shield
php artisan shield:install
php artisan shield:generate --all
```

### Key Takeaway:

**Filament uses Laravel's standard session-based authentication** (the default Laravel auth system). No special authentication package like Sanctum is needed because Filament is a traditional web application, not an API/SPA.

Since Filament is server-rendered and uses sessions, standard Laravel authentication is perfect.
