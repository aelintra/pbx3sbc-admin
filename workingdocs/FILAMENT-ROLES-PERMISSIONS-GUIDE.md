# Filament Roles & Permissions Guide

**Date:** January 2026  
**Topic:** Role-Based Access Control (RBAC) in Filament/Laravel

## Answer: YES - Full Support for Roles and Permissions

Laravel and Filament have excellent support for roles and permissions through:

1. **Laravel Policies** - Built-in authorization system
2. **Spatie Laravel Permission** - Popular package for RBAC
3. **Filament Shield** - Filament integration with Spatie Permission

---

## Overview of Authorization Methods

### Option 1: Filament Shield (Recommended for Admin Panels)

**Filament Shield** is a Filament plugin that integrates with **Spatie Laravel Permission** to provide:
- Role-based access control
- Permission-based resource/page access
- Granular permissions (create, read, update, delete)
- User-friendly permission management UI

### Option 2: Laravel Policies (Built-in)

Laravel's built-in authorization system using Policies and Gates. More manual setup, but fully customizable.

### Option 3: Custom Authorization (Manual)

Custom authorization logic in resources/pages. Most flexible but requires more code.

**Recommendation:** Use **Filament Shield + Spatie Permission** for the best balance of features and ease of use.

---

## Implementation: Filament Shield + Spatie Permission

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

### Step 3: Configure Shield

Shield automatically generates permissions for all Filament resources. Configure in `config/filament-shield.php`:

```php
return [
    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        'guard_name' => 'web',
    ],
    // ...
];
```

### Step 4: Generate Permissions for Resources

Shield can auto-generate permissions for resources:

```bash
# Generate permissions for all resources
php artisan shield:generate --all

# Or generate for specific resource
php artisan shield:generate --resource=DomainResource
```

This creates permissions like:
- `view_domain`
- `create_domain`
- `update_domain`
- `delete_domain`

---

## Example: Implementing Your Use Cases

### Use Case 1: Only Admin Users Can See Users

#### Option A: Using Shield Permissions

```php
// app/Filament/Resources/UserResource.php
namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Resources\Pages\ListRecords;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class UserResource extends Resource
{
    // Shield automatically handles permissions based on resource name
    // Permission: view_user, create_user, update_user, delete_user
    
    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_user');
    }
    
    // Or use Shield's trait (simpler)
    use HasPageShield; // Automatically checks permissions
}
```

#### Option B: Using Roles Directly

```php
public static function canViewAny(): bool
{
    return auth()->user()->hasRole('admin');
    // or
    return auth()->user()->hasAnyRole(['admin', 'super_admin']);
}
```

### Use Case 2: Some Users Can Only Read CDR Records

```php
// app/Filament/Resources/CdrResource.php
class CdrResource extends Resource
{
    use HasPageShield; // Auto-handles permissions
    
    public static function canCreate(): bool
    {
        // CDRs are read-only for everyone (or specific permission)
        return false;
        // Or: return auth()->user()->can('create_cdr');
    }
    
    public static function canEdit(Model $record): bool
    {
        // CDRs are read-only
        return false;
    }
    
    public static function canDelete(Model $record): bool
    {
        // CDRs are read-only
        return false;
    }
}
```

Then assign permissions:
- Users with `view_cdr` permission can see CDR records
- Users without this permission cannot see the resource at all

### Use Case 3: Users Can Update But Not Add Dispatcher Destinations

```php
// app/Filament/Resources/DispatcherResource.php
class DispatcherResource extends Resource
{
    use HasPageShield;
    
    public static function canCreate(): bool
    {
        // Only admins can create
        return auth()->user()->can('create_dispatcher');
    }
    
    public static function canEdit(Model $record): bool
    {
        // Users with update permission can edit
        return auth()->user()->can('update_dispatcher');
    }
    
    public static function canDelete(Model $record): bool
    {
        // Only admins can delete
        return auth()->user()->can('delete_dispatcher');
    }
}
```

**Permission Setup:**
- Create role: "Operator"
- Assign permissions: `view_dispatcher`, `update_dispatcher` (but NOT `create_dispatcher`)
- Result: Operators can view and edit dispatchers, but cannot create new ones

---

## Complete Permission Setup Example

### Step 1: Create Roles

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Create roles
$adminRole = Role::create(['name' => 'admin']);
$operatorRole = Role::create(['name' => 'operator']);
$viewerRole = Role::create(['name' => 'viewer']);
```

### Step 2: Create Permissions (or use Shield to generate)

Shield generates permissions automatically, but you can create custom ones:

```php
// Standard resource permissions (auto-generated by Shield)
Permission::create(['name' => 'view_domain']);
Permission::create(['name' => 'create_domain']);
Permission::create(['name' => 'update_domain']);
Permission::create(['name' => 'delete_domain']);

Permission::create(['name' => 'view_dispatcher']);
Permission::create(['name' => 'create_dispatcher']);
Permission::create(['name' => 'update_dispatcher']);
Permission::create(['name' => 'delete_dispatcher']);

// Custom permissions
Permission::create(['name' => 'reload_opensips']);
Permission::create(['name' => 'manage_services']);
```

### Step 3: Assign Permissions to Roles

```php
// Admin: Full access
$adminRole->givePermissionTo([
    'view_domain', 'create_domain', 'update_domain', 'delete_domain',
    'view_dispatcher', 'create_dispatcher', 'update_dispatcher', 'delete_dispatcher',
    'view_user', 'create_user', 'update_user', 'delete_user',
    'reload_opensips', 'manage_services',
]);

// Operator: Can view and update, but not create/delete
$operatorRole->givePermissionTo([
    'view_domain', 'update_domain',
    'view_dispatcher', 'update_dispatcher',
    'view_cdr', // Read-only access to CDRs
]);

// Viewer: Read-only access
$viewerRole->givePermissionTo([
    'view_domain',
    'view_dispatcher',
    'view_cdr',
]);
```

### Step 4: Assign Roles to Users

```php
$user = User::find(1);
$user->assignRole('admin');

$operator = User::find(2);
$operator->assignRole('operator');

$viewer = User::find(3);
$viewer->assignRole('viewer');
```

---

## Implementing in Filament Resources

### Example: Domain Resource with Permissions

```php
// app/Filament/Resources/DomainResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\DomainResource\Pages;
use App\Models\Domain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class DomainResource extends Resource
{
    use HasPageShield; // Auto-handles page permissions
    
    protected static ?string $model = Domain::class;
    
    // Shield automatically generates permissions:
    // - view_domain
    // - create_domain
    // - update_domain
    // - delete_domain
    
    public static function form(Form $form): Form
    {
        return $form->schema([
            // Form fields
        ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Table columns
            ])
            ->actions([
                // Custom action with permission check
                Tables\Actions\Action::make('reload')
                    ->label('Reload OpenSIPS')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()->can('reload_opensips'))
                    ->action(function (Domain $record) {
                        app(\App\Services\OpenSIPSMIService::class)->domainReload();
                    }),
                    
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }
}
```

### Example: Custom Page with Permission Check

```php
// app/Filament/Pages/SystemServices.php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class SystemServices extends Page
{
    use HasPageShield; // Checks 'view_system_services' permission
    
    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static string $view = 'filament.pages.system-services';
    protected static ?string $navigationLabel = 'System Services';
    
    // Page only visible to users with 'view_system_services' permission
}
```

### Example: Hide Navigation Items Based on Permissions

```php
// In Resource or Page class
protected static ?string $navigationGroup = 'Routing';

public static function shouldRegisterNavigation(): bool
{
    // Only show in navigation if user has permission
    return auth()->user()->can('view_domain');
}
```

---

## Custom Actions with Permission Checks

### Example: Reload OpenSIPS Action (Admin Only)

```php
Tables\Actions\Action::make('reload')
    ->label('Reload OpenSIPS')
    ->icon('heroicon-o-arrow-path')
    ->requiresConfirmation()
    ->visible(fn () => auth()->user()->can('reload_opensips'))
    ->action(function () {
        app(\App\Services\OpenSIPSMIService::class)->domainReload();
    }),
```

### Example: Service Management Actions (Role-Based)

```php
Tables\Actions\Action::make('start')
    ->label('Start Service')
    ->icon('heroicon-o-play')
    ->visible(fn () => auth()->user()->hasRole('admin'))
    ->action(function ($record) {
        app(\App\Services\SystemService::class)->startService($record->name);
    }),

Tables\Actions\Action::make('stop')
    ->label('Stop Service')
    ->icon('heroicon-o-stop')
    ->visible(fn () => auth()->user()->hasRole('admin'))
    ->action(function ($record) {
        app(\App\Services\SystemService::class)->stopService($record->name);
    }),
```

---

## Permission Management UI (Filament Shield)

Filament Shield provides a built-in UI for managing roles and permissions:

```bash
php artisan shield:generate --all
```

This creates:
- **Roles Resource** - Manage roles (`/admin/roles`)
- **Permissions Resource** - View permissions (`/admin/permissions`)
- **Users Resource** - Manage users with role assignment (`/admin/users`)

Users with appropriate permissions can manage roles and assign them to users through the Filament interface.

---

## Common Permission Patterns

### Pattern 1: Resource-Level Permissions

```php
// app/Filament/Resources/DomainResource.php
use HasPageShield;

// Automatically handles:
// - view_domain (can see list)
// - create_domain (can create)
// - update_domain (can edit)
// - delete_domain (can delete)
```

### Pattern 2: Action-Level Permissions

```php
// In table actions
Tables\Actions\Action::make('special_action')
    ->visible(fn () => auth()->user()->can('special_permission'))
    ->action(function () {
        // Action logic
    }),
```

### Pattern 3: Field-Level Permissions

```php
// In form schema
Forms\Components\TextInput::make('setid')
    ->disabled(fn () => !auth()->user()->can('update_domain_setid'))
    ->visible(fn () => auth()->user()->can('view_domain_setid')),
```

### Pattern 4: Page-Level Permissions

```php
// Custom pages
use HasPageShield;

// Checks 'view_page_name' permission automatically
```

---

## Recommended Permission Structure

### Standard Roles

1. **Super Admin**
   - All permissions

2. **Admin**
   - Full access to domains, dispatchers
   - User management
   - System operations (reload OpenSIPS, manage services)

3. **Operator**
   - View domains, dispatchers
   - Update domains, dispatchers
   - Cannot create or delete
   - View CDRs (read-only)

4. **Viewer**
   - View domains, dispatchers, CDRs (read-only)
   - No create, update, delete permissions

### Permission Granularity

**Resource Permissions (Auto-generated by Shield):**
- `view_{resource}` - Can see the resource
- `create_{resource}` - Can create records
- `update_{resource}` - Can edit records
- `delete_{resource}` - Can delete records

**Custom Permissions:**
- `reload_opensips` - Can reload OpenSIPS modules
- `manage_services` - Can start/stop system services
- `view_statistics` - Can view statistics dashboard
- `export_cdr` - Can export CDR records

---

## Summary

### Yes, Full Support for:

✅ **Roles** - Create roles (admin, operator, viewer, etc.)  
✅ **Permissions** - Granular permissions (view, create, update, delete)  
✅ **Resource-Level** - Control access to entire resources  
✅ **Action-Level** - Control access to specific actions  
✅ **Page-Level** - Control access to custom pages  
✅ **Field-Level** - Control visibility/editing of form fields  

### Recommended Stack:

- **Spatie Laravel Permission** - RBAC package
- **Filament Shield** - Filament integration
- **Laravel Policies** - For complex authorization logic (optional)

### Your Use Cases:

1. ✅ **Only admin users can see users** → Use `view_user` permission
2. ✅ **Some users can only read CDR records** → Use `view_cdr` permission, disable create/update/delete
3. ✅ **Users can update but not add dispatchers** → Use `update_dispatcher` permission, disable `create_dispatcher`

**Conclusion:** Laravel/Filament has excellent RBAC support through Filament Shield + Spatie Permission. All your use cases are easily achievable!
