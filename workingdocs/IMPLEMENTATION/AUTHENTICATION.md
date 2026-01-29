# Authentication & Authorization

**Last Updated:** 2026-01-22  
**Purpose:** Authentication and authorization system explanation and implementation guide

## Overview

This document explains the authentication and authorization system used in the PBX3SBC Admin Panel, including how to implement role-based access control (RBAC).

---

## Authentication vs Authorization

### Authentication (Who You Are)
- **Purpose:** Verify user identity (login/logout)
- **System:** Laravel Standard Sessions (Filament built-in)
- **Package:** None (built into Laravel/Filament)

### Authorization (What You Can Do)
- **Purpose:** Control what authenticated users can access
- **System:** Filament Shield + Spatie Laravel Permission (planned)
- **Packages:** `spatie/laravel-permission`, `bezhansalleh/filament-shield`

---

## Current Implementation

### Authentication ✅ IMPLEMENTED

**Status:** ✅ Complete

**System:** Laravel Standard Sessions  
**Implementation:** Filament built-in  
**Package:** None needed

**Features:**
- ✅ Login page (`/admin/login`)
- ✅ Logout functionality
- ✅ Session-based authentication
- ✅ Password reset
- ✅ User creation (`php artisan make:filament-user`)

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
```

### Authorization ⏳ PLANNED

**Status:** ⏳ Planned, not yet implemented

**System:** Filament Shield + Spatie Permission  
**Packages:**
- `spatie/laravel-permission` - RBAC package
- `bezhansalleh/filament-shield` - Filament integration

---

## Why Laravel Sessions (Not Sanctum)?

### Filament Uses Laravel Sessions

Filament is a traditional web application (server-rendered via Livewire), not an API/SPA. Therefore:

- ✅ **Laravel Sessions** - Perfect for Filament (stateful, cookie-based)
- ❌ **Sanctum** - For APIs/SPAs (stateless, token-based)
- ❌ **Passport** - For OAuth2 servers
- ❌ **Breeze/Jetstream** - Filament has its own UI

**Key Point:** Filament uses Laravel's standard session-based authentication (the default Laravel auth system). No special authentication package like Sanctum is needed.

---

## Implementing RBAC (Future)

### Step 1: Install Packages

```bash
# Install Spatie Laravel Permission
composer require spatie/laravel-permission

# Install Filament Shield (Filament plugin)
composer require bezhansalleh/filament-shield
```

### Step 2: Publish and Run Migrations

```bash
# Publish Spatie Permission migrations
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Publish Filament Shield config
php artisan vendor:publish --tag=filament-shield-config

# Run migrations (creates roles, permissions tables)
php artisan migrate

# Install Shield
php artisan shield:install
```

This creates:
- `roles` table
- `permissions` table
- `model_has_roles` table (many-to-many: users ↔ roles)
- `role_has_permissions` table (many-to-many: roles ↔ permissions)

### Step 3: Generate Permissions for Resources

```bash
# Generate permissions for all resources
php artisan shield:generate --all

# Or generate for specific resource
php artisan shield:generate --resource=CallRouteResource
```

This creates permissions like:
- `view_call_route`
- `create_call_route`
- `update_call_route`
- `delete_call_route`

### Step 4: Use in Resources

```php
// app/Filament/Resources/CallRouteResource.php
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class CallRouteResource extends Resource
{
    use HasPageShield; // Automatically checks permissions
    
    public static function canCreate(): bool
    {
        return auth()->user()->can('create_call_route');
    }
    
    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update_call_route');
    }
    
    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete_call_route');
    }
}
```

### Example Use Cases

#### Use Case 1: Only Admin Users Can See Users
```php
public static function canViewAny(): bool
{
    return auth()->user()->can('view_user');
}
```

#### Use Case 2: Some Users Can Only Read CDR Records
```php
public static function canCreate(): bool
{
    return false; // CDRs are read-only
}

public static function canEdit(Model $record): bool
{
    return false; // CDRs are read-only
}
```

#### Use Case 3: Users Can Update But Not Add Dispatcher Destinations
```php
public static function canCreate(): bool
{
    return auth()->user()->can('create_dispatcher');
}

public static function canEdit(Model $record): bool
{
    return auth()->user()->can('update_dispatcher');
}
```

---

## Summary

### Current Stack

✅ **Authentication:** Laravel Standard Sessions (Filament built-in)  
⏳ **Authorization:** Filament Shield + Spatie Permission (planned)

### Installation Commands (When Implementing RBAC)

```bash
# Authentication: None needed (Filament handles it)

# Authorization: Install these
composer require spatie/laravel-permission
composer require bezhansalleh/filament-shield
php artisan shield:install
php artisan shield:generate --all
```

### Key Takeaway

**Filament uses Laravel's standard session-based authentication** (the default Laravel auth system). No special authentication package like Sanctum is needed because Filament is a traditional web application, not an API/SPA.

---

## Related Documentation

- `../ARCHITECTURE/PROJECT-CONTEXT.md` - Project overview
- `../QUICK-REFERENCES/CURRENT-STATE.md` - Current implementation status
- `../ARCHITECTURE/ARCHITECTURE.md` - System architecture
