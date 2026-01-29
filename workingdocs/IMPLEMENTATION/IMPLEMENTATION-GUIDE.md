# Implementation Guide

**Last Updated:** 2026-01-22  
**Purpose:** Laravel/Filament implementation patterns, multi-table operations, and extensibility guide

## Overview

This guide provides implementation patterns and examples for working with Laravel 12 and Filament 3.x in the PBX3SBC Admin Panel.

---

## Technology Stack

- **Runtime:** PHP 8.2+ (8.3 recommended for Ubuntu 24.04)
- **Framework:** Laravel 12
- **Admin Panel:** Filament 3.x (TALL stack: Tailwind CSS, Alpine.js, Livewire, Laravel)
- **Database:** MySQL/MariaDB (shared with OpenSIPS)
- **ORM:** Eloquent (Laravel's built-in ORM)
- **Authentication:** Laravel sessions (Filament built-in)

---

## Project Structure

```
app/
├── Filament/
│   ├── Resources/
│   │   ├── CallRouteResource.php
│   │   ├── CdrResource.php
│   │   └── DialogResource.php
│   ├── Pages/
│   │   └── Dashboard.php
│   └── Widgets/
│       └── CdrStatsWidget.php
├── Models/
│   ├── Domain.php
│   ├── Dispatcher.php
│   ├── Cdr.php
│   └── Dialog.php
├── Services/
│   └── OpenSIPSMIService.php
└── Http/
    └── Requests/
```

---

## Eloquent Models for OpenSIPS Tables

### Key Configuration Points

1. **Table Names:** Use `protected $table` to map to OpenSIPS table names
2. **No Timestamps:** Set `public $timestamps = false` (OpenSIPS tables don't use Laravel timestamps)
3. **Primary Keys:** Check if table uses non-standard primary key (e.g., `dlg_id` vs `id`)
4. **Fillable Fields:** Define `$fillable` array for mass assignment protection

### Example: Domain Model

```php
// app/Models/Domain.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $table = 'domain';
    
    public $timestamps = false;
    
    protected $fillable = [
        'domain',
        'setid',
        'attrs',
        'accept_subdomain',
        'last_modified',
    ];
    
    protected $casts = [
        'accept_subdomain' => 'integer',
        'setid' => 'integer',
        'last_modified' => 'datetime',
    ];
    
    // Relationships
    public function dispatchers(): HasMany
    {
        return $this->hasMany(Dispatcher::class, 'setid', 'setid');
    }
}
```

### Example: Dialog Model (Non-Standard Primary Key)

```php
// app/Models/Dialog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dialog extends Model
{
    protected $table = 'dialog';
    
    protected $primaryKey = 'dlg_id';  // NOT 'id'
    
    public $incrementing = false;
    
    public $timestamps = false;
    
    public function getRouteKeyName()
    {
        return 'dlg_id';  // Required for Filament routing
    }
}
```

---

## Filament Resources

### Creating a Resource

```bash
php artisan make:filament-resource ModelName
```

This creates:
- `app/Filament/Resources/ModelNameResource.php`
- `app/Filament/Resources/ModelNameResource/Pages/ListModelNames.php`
- `app/Filament/Resources/ModelNameResource/Pages/CreateModelName.php`
- `app/Filament/Resources/ModelNameResource/Pages/EditModelName.php`

### Basic Resource Structure

```php
// app/Filament/Resources/ExampleResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\ExampleResource\Pages;
use App\Models\Example;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExampleResource extends Resource
{
    protected static ?string $model = Example::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-example';
    
    protected static ?string $navigationLabel = 'Examples';
    
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
            ->filters([
                // Filters
            ])
            ->actions([
                // Actions
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExamples::route('/'),
            'create' => Pages\CreateExample::route('/create'),
            'edit' => Pages\EditExample::route('/{record}/edit'),
        ];
    }
}
```

---

## Multi-Table Operations

### Pattern: Unified Resource (CallRouteResource)

**Use Case:** Managing related data across multiple tables (Domain + Dispatcher)

**Approach:** Use one model as primary entity, handle related data in page lifecycle hooks

**Example:**
```php
// CallRouteResource uses Domain model as primary
// Dispatchers are managed via relationship in afterCreate/afterSave hooks

protected function afterCreate(): void
{
    $domain = $this->record;
    $formData = $this->form->getState();
    
    if (!empty($formData['destination'])) {
        DB::transaction(function () use ($domain, $formData) {
            $domain->dispatchers()->create([
                'destination' => $formData['destination'],
                'weight' => $formData['weight'] ?? '1',
                'priority' => $formData['priority'] ?? 0,
                'state' => $formData['state'] ?? 0,
                'description' => $formData['description'] ?? '',
            ]);
        });
    }
}
```

### Pattern: Transactions

Always wrap multi-table operations in transactions:

```php
DB::transaction(function () use ($data) {
    // Create domain
    $domain = Domain::create([...]);
    
    // Create dispatchers
    foreach ($data['destinations'] as $dest) {
        Dispatcher::create([
            'setid' => $domain->setid,
            'destination' => $dest['destination'],
            // ...
        ]);
    }
});
```

### Pattern: Eager Loading

Prevent N+1 queries by eager loading relationships:

```php
// In Resource table definition
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn (Builder $query) => $query->with('dispatchers'))
        ->columns([
            // Use model accessors that leverage eager-loaded relationships
            Tables\Columns\TextColumn::make('dispatchers_count'),
            Tables\Columns\TextColumn::make('dispatchers_list'),
        ]);
}
```

---

## Form Patterns

### Reactive Forms

Use `live()` (not `reactive()`) for reactive form behavior:

```php
Forms\Components\Radio::make('domain_type')
    ->options([...])
    ->live()  // ✅ Use live() in Filament 3.x
    ->afterStateUpdated(function ($state, callable $set) {
        // Update other fields based on selection
    })
```

### Conditional Field Visibility

```php
Forms\Components\Select::make('domain_select')
    ->visible(fn (callable $get, $livewire) => 
        !($livewire instanceof EditRecord)
        && !($livewire instanceof ViewRecord)
        && $get('domain_type') === 'existing'
    )
```

### Validation

```php
Forms\Components\TextInput::make('domain')
    ->required()
    ->maxLength(64)
    ->unique(ignoreRecord: true)
    ->rules([
        'regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
    ])
    ->validationMessages([
        'regex' => 'The domain must be a valid domain name (e.g., example.com).',
    ])
```

---

## Page Lifecycle Hooks

### Create Page

```php
class CreateExample extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Modify data before creation
        return $data;
    }
    
    protected function handleRecordCreation(array $data): Model
    {
        // Custom creation logic (optional)
        return parent::handleRecordCreation($data);
    }
    
    protected function afterCreate(): void
    {
        // Post-creation logic (notifications, related records, etc.)
    }
    
    protected function getRedirectUrl(): string
    {
        // Custom redirect after creation
        return static::getResource()::getUrl('index');
    }
}
```

### Edit Page

```php
class EditExample extends EditRecord
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Modify data before form is filled
        return $data;
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Modify data before saving
        return $data;
    }
    
    protected function afterSave(): void
    {
        // Post-save logic
    }
}
```

---

## Extensibility: Adding New Resources

### Step 1: Create Model

```bash
php artisan make:model NewModel
```

```php
// app/Models/NewModel.php
class NewModel extends Model
{
    protected $table = 'new_table';
    public $timestamps = false;
    // ...
}
```

### Step 2: Create Resource

```bash
php artisan make:filament-resource NewModel
```

### Step 3: Configure Resource

- Define form schema
- Define table columns
- Add filters
- Add actions
- Configure navigation

**Key Point:** Each new table gets its own Resource. Resources are automatically discovered by Filament - no routing configuration needed.

---

## Service Classes

### Pattern: OpenSIPS MI Service

```php
// app/Services/OpenSIPSMIService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenSIPSMIService
{
    protected string $miUrl;
    
    public function __construct()
    {
        $this->miUrl = config('opensips.mi_url', 'http://127.0.0.1:8888/mi');
    }
    
    public function domainReload(): void
    {
        try {
            $this->call('domain_reload');
            Log::info('OpenSIPS domain module reloaded successfully');
        } catch (\Exception $e) {
            Log::warning('OpenSIPS domain reload failed', ['error' => $e->getMessage()]);
        }
    }
}
```

**Usage in Resources:**
```php
$miService = app(OpenSIPSMIService::class);
$miService->domainReload();
```

---

## Best Practices

### 1. Use Relationships

```php
// ✅ Good: Use relationship
$domain->dispatchers()->create([...]);

// ❌ Avoid: Direct queries
Dispatcher::where('setid', $domain->setid)->create([...]);
```

### 2. Eager Load Relationships

```php
// ✅ Good: Eager load
$query->with('dispatchers')

// ❌ Avoid: N+1 queries
// Accessing $record->dispatchers in table column without eager loading
```

### 3. Use Model Accessors

```php
// In Model
public function getDispatchersCountAttribute(): int
{
    if ($this->relationLoaded('dispatchers')) {
        return $this->dispatchers->count();
    }
    return $this->dispatchers()->count();
}

// In Resource
Tables\Columns\TextColumn::make('dispatchers_count')
```

### 4. Wrap Multi-Table Operations in Transactions

```php
DB::transaction(function () use ($data) {
    // Multiple database operations
});
```

### 5. Use Config, Not Env

```php
// ✅ Good
config('opensips.mi_url')

// ❌ Avoid
env('OPENSIPS_MI_URL')
```

### 6. Graceful Error Handling

```php
try {
    $miService->domainReload();
} catch (\Exception $e) {
    Log::warning('OpenSIPS MI reload failed', ['error' => $e->getMessage()]);
    // Don't break the operation - graceful degradation
}
```

---

## Common Patterns

### Read-Only Resources

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\TextInput::make('field')
            ->disabled(),  // All fields disabled
    ]);
}

public static function table(Table $table): Table
{
    return $table
        ->actions([
            Tables\Actions\ViewAction::make(),  // Only view, no edit/delete
        ])
        ->bulkActions([
            // No bulk actions
        ]);
}

public static function getPages(): array
{
    return [
        'index' => Pages\ListExamples::route('/'),
        'view' => Pages\ViewExample::route('/{record}'),
        // No create/edit pages
    ];
}
```

### Auto-Refresh Tables

```php
public static function table(Table $table): Table
{
    return $table
        ->poll('30s')  // Auto-refresh every 30 seconds
        // ...
}
```

### Custom Redirects

```php
protected function getRedirectUrl(): string
{
    return DispatcherResource::getUrl('index', [
        'tableFilters' => [
            'setid' => ['value' => $this->record->setid],
        ],
    ]);
}
```

---

## Related Documentation

- `../ARCHITECTURE/PROJECT-CONTEXT.md` - Project overview and database schema
- `CODE-QUALITY.md` (this folder) - Code review and best practices
- `../ARCHITECTURE/ARCHITECTURE.md` - System architecture
- `UX-DESIGN-DECISIONS.md` - UX design decisions
