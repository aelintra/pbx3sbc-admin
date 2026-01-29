# Laravel/Filament Project Rules & Guidelines

**Purpose:** Rules and patterns for maintaining consistency across Laravel/Filament projects  
**Based on:** PBX3SBC Admin Panel implementation patterns  
**Last Updated:** 2026-01-22

## Overview

This document defines rules, patterns, and best practices for Laravel/Filament projects to ensure consistency, maintainability, and code quality. Use this as a reference when starting new projects or reviewing existing code.

---

## Configuration & Environment

### ✅ DO: Use `config()` helper
```php
// ✅ Good
$miUrl = config('opensips.mi_url', 'http://127.0.0.1:8888/mi');

// ❌ Bad
$miUrl = env('OPENSIPS_MI_URL', 'http://127.0.0.1:8888/mi');
```

**Rule:** Never use `env()` directly in application code. Always use `config()` which works with `php artisan config:cache`.

**Exception:** Only use `env()` in config files themselves:
```php
// config/opensips.php
return [
    'mi_url' => env('OPENSIPS_MI_URL', 'http://127.0.0.1:8888/mi'),
];
```

---

## Eloquent Models

### ✅ DO: Configure table names explicitly
```php
protected $table = 'domain';  // Explicit table name
```

### ✅ DO: Disable timestamps for external tables
```php
public $timestamps = false;  // If table doesn't have created_at/updated_at
```

### ✅ DO: Define fillable arrays
```php
protected $fillable = ['domain', 'setid', 'attrs', 'accept_subdomain'];
```

### ✅ DO: Handle non-standard primary keys
```php
protected $primaryKey = 'dlg_id';  // If not 'id'
public $incrementing = false;  // If not auto-increment

public function getRouteKeyName()
{
    return 'dlg_id';  // Required for Filament routing
}
```

### ✅ DO: Use relationships instead of direct queries
```php
// ✅ Good: Use relationship
$domain->dispatchers()->create([...]);
$domain->dispatchers()->delete();

// ❌ Bad: Direct queries
Dispatcher::where('setid', $domain->setid)->create([...]);
```

### ✅ DO: Use model accessors with eager loading
```php
// In Model
public function getDispatchersCountAttribute(): int
{
    if ($this->relationLoaded('dispatchers')) {
        return $this->dispatchers->count();
    }
    return $this->dispatchers()->count();
}

// In Resource table
->modifyQueryUsing(fn (Builder $query) => $query->with('dispatchers'))
->columns([
    Tables\Columns\TextColumn::make('dispatchers_count'),  // Uses accessor
])
```

### ✅ DO: Use query scopes
```php
// In Model
public function scopeActive($query)
{
    return $query->where('state', 4);
}

// Usage
Dialog::active()->get();
```

---

## Filament Resources

### ✅ DO: Use proper resource structure
```php
class ExampleResource extends Resource
{
    protected static ?string $model = Example::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-example';
    
    protected static ?string $navigationLabel = 'Examples';
    
    protected static ?string $navigationGroup = 'Group Name';  // Optional grouping
    
    protected static ?int $navigationSort = 1;  // Sort order
    
    public static function form(Form $form): Form { }
    public static function table(Table $table): Table { }
    public static function getPages(): array { }
}
```

### ✅ DO: Use `live()` for reactive forms (Filament 3.x)
```php
// ✅ Good: Filament 3.x
Forms\Components\Radio::make('type')
    ->live()
    ->afterStateUpdated(function ($state, callable $set) {
        // ...
    })

// ❌ Bad: Deprecated in Filament 3.x
->reactive()
```

### ✅ DO: Eager load relationships in tables
```php
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn (Builder $query) => $query->with('relationship'))
        ->columns([...]);
}
```

### ✅ DO: Use proper pagination limits
```php
->paginated([10, 25, 50, 100])  // Reasonable options
->defaultPaginationPageOption(25)  // Default
// ❌ Never include "ALL" option for performance
```

### ✅ DO: Use polling for real-time data
```php
->poll('30s')  // For CDR records
->poll('5s')   // For active calls
```

### ✅ DO: Hide resources from navigation when appropriate
```php
public static function shouldRegisterNavigation(): bool
{
    return false;  // Hidden - use another resource instead
}
```

---

## Filament Pages

### ✅ DO: Use lifecycle hooks properly
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
        // Post-creation logic
    }
    
    protected function getRedirectUrl(): string
    {
        // Custom redirect
        return static::getResource()::getUrl('index');
    }
}
```

### ✅ DO: Disable "Create and create another" when not needed
```php
protected static bool $canCreateAnother = false;
```

### ✅ DO: Use proper form actions
```php
protected function getFormActions(): array
{
    return [
        Actions\CreateAction::make(),
        // Don't include "Create and create another" if $canCreateAnother = false
    ];
}
```

---

## Multi-Table Operations

### ✅ DO: Use transactions for multi-table operations
```php
DB::transaction(function () use ($data) {
    $domain = Domain::create([...]);
    $domain->dispatchers()->create([...]);
});
```

### ✅ DO: Use relationships for related data
```php
// ✅ Good: Use relationship
$domain->dispatchers()->create([...]);

// ❌ Bad: Manual setid management
Dispatcher::create(['setid' => $domain->setid, ...]);
```

### ✅ DO: Handle race conditions
```php
// For auto-incrementing IDs
$data['setid'] = DB::transaction(function () {
    $maxSetid = Domain::lockForUpdate()->max('setid') ?? 0;
    return $maxSetid + 1;
});
```

---

## Error Handling

### ✅ DO: Use try-catch with graceful degradation
```php
try {
    $miService->domainReload();
    Log::info('OpenSIPS domain module reloaded successfully');
} catch (\Exception $e) {
    Log::warning('OpenSIPS domain reload failed', ['error' => $e->getMessage()]);
    // Don't throw - graceful degradation
}
```

### ✅ DO: Notify users of failures
```php
try {
    $miService->domainReload();
} catch (\Exception $e) {
    Log::warning('OpenSIPS MI reload failed', ['error' => $e->getMessage()]);
    Notification::make()
        ->warning()
        ->title('OpenSIPS Module Reload Failed')
        ->body('The operation succeeded, but OpenSIPS modules could not be reloaded.')
        ->send();
}
```

### ✅ DO: Use Log facade consistently
```php
// ✅ Good: Import and use
use Illuminate\Support\Facades\Log;

Log::warning('Message', ['context' => $data]);

// ❌ Bad: Fully qualified
\Log::warning('Message');
```

---

## Service Classes

### ✅ DO: Use dependency injection (preferred)
```php
// ✅ Good: Constructor injection
public function __construct(
    protected OpenSIPSMIService $miService
) {
    parent::__construct();
}

// Then use:
$this->miService->domainReload();
```

### ✅ DO: Accept `app()` helper as alternative
```php
// ✅ Acceptable: app() helper
$miService = app(OpenSIPSMIService::class);
$miService->domainReload();
```

**Note:** Dependency injection is preferred for testability, but `app()` is acceptable for Filament page classes.

### ✅ DO: Use type hints and return types
```php
public function domainReload(): void
{
    // ...
}

public function call(string $command, array $params = []): array
{
    // ...
}
```

---

## Form Validation

### ✅ DO: Use proper validation rules
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

### ✅ DO: Provide helpful error messages
```php
->validationMessages([
    'regex' => 'The destination must start with "sip:" followed by an IP address or domain name.',
])
```

---

## Notifications

### ✅ DO: Import Notification class
```php
// ✅ Good
use Filament\Notifications\Notification;

Notification::make()
    ->success()
    ->title('Operation succeeded')
    ->send();

// ❌ Bad: Fully qualified
\Filament\Notifications\Notification::make()
```

---

## Read-Only Resources

### ✅ DO: Properly configure read-only resources
```php
// Form: All fields disabled
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\TextInput::make('field')
            ->disabled(),
    ]);
}

// Table: Only view action, no bulk actions
public static function table(Table $table): Table
{
    return $table
        ->actions([
            Tables\Actions\ViewAction::make(),
        ])
        ->bulkActions([
            // No bulk actions for read-only data
        ]);
}

// Pages: Only index and view
public static function getPages(): array
{
    return [
        'index' => Pages\ListExamples::route('/'),
        'view' => Pages\ViewExample::route('/{record}'),
        // No create/edit pages
    ];
}
```

---

## Code Organization

### ✅ DO: Separate business logic into service classes
```php
// ✅ Good: Service class
app/Services/OpenSIPSMIService.php

// Usage
$miService = app(OpenSIPSMIService::class);
$miService->domainReload();
```

### ✅ DO: Use model accessors for computed values
```php
// In Model
public function getFormattedDurationAttribute(): string
{
    $minutes = floor($this->duration / 60);
    $seconds = $this->duration % 60;
    return sprintf('%d:%02d', $minutes, $seconds);
}

// In Resource
Tables\Columns\TextColumn::make('formatted_duration')
```

### ✅ DO: Keep page classes focused
- Use lifecycle hooks for data transformation
- Keep business logic in service classes or models
- Use transactions for multi-table operations

---

## File Naming & Structure

### ✅ DO: Follow Laravel conventions
```
app/
├── Filament/
│   ├── Resources/
│   │   └── ExampleResource.php
│   ├── Pages/
│   └── Widgets/
├── Models/
│   └── Example.php
├── Services/
│   └── ExampleService.php
└── Http/
    └── Requests/
```

### ✅ DO: Use descriptive resource/page names
```php
// ✅ Good
CallRouteResource
CreateCallRoute
EditCallRoute

// ❌ Bad
RouteResource
CreateRoute
EditRoute
```

---

## Common Patterns

### Pattern: Auto-managed fields
```php
// Hide from users, auto-generate
protected function mutateFormDataBeforeCreate(array $data): array
{
    if (!isset($data['setid'])) {
        $data['setid'] = DB::transaction(function () {
            $maxSetid = Domain::lockForUpdate()->max('setid') ?? 0;
            return $maxSetid + 1;
        });
    }
    return $data;
}
```

### Pattern: Conditional field visibility
```php
Forms\Components\Select::make('domain_select')
    ->visible(fn (callable $get, $livewire) => 
        !($livewire instanceof EditRecord)
        && !($livewire instanceof ViewRecord)
        && $get('domain_type') === 'existing'
    )
```

### Pattern: Filter preservation on redirect
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

### Pattern: Redirect after delete
```php
Tables\Actions\DeleteAction::make()
    ->successRedirectUrl(function ($record, $livewire) {
        // Preserve filter or redirect to list
        $setid = $record->setid ?? null;
        if ($setid !== null) {
            return DispatcherResource::getUrl('index', [
                'tableFilters' => ['setid' => ['value' => $setid]],
            ]);
        }
        return DispatcherResource::getUrl('index');
    })
```

---

## Things to Avoid

### ❌ DON'T: Use `env()` in application code
```php
// ❌ Bad
$url = env('OPENSIPS_MI_URL');

// ✅ Good
$url = config('opensips.mi_url');
```

### ❌ DON'T: Create N+1 queries
```php
// ❌ Bad: Query per row
Tables\Columns\TextColumn::make('count')
    ->getStateUsing(fn ($record) => Dispatcher::where('setid', $record->setid)->count())

// ✅ Good: Use accessor with eager loading
->modifyQueryUsing(fn ($query) => $query->with('dispatchers'))
Tables\Columns\TextColumn::make('dispatchers_count')  // Uses accessor
```

### ❌ DON'T: Use `reactive()` in Filament 3.x
```php
// ❌ Bad: Deprecated
->reactive()

// ✅ Good: Use live()
->live()
```

### ❌ DON'T: Leave unused page files
```php
// If pages aren't registered in getPages(), delete the files
// Or document why they exist
```

### ❌ DON'T: Expose internal IDs to users
```php
// ❌ Bad: Show setid to users
Forms\Components\TextInput::make('setid')

// ✅ Good: Auto-manage, hide from users
// Handle in mutateFormDataBeforeCreate
```

### ❌ DON'T: Allow "ALL" pagination option
```php
// ❌ Bad: Performance issue
->paginated([10, 25, 50, 100, 'all'])

// ✅ Good: Reasonable limits
->paginated([10, 25, 50, 100])
```

---

## Testing Checklist

Before considering code complete, verify:

- [ ] No direct `env()` usage (only in config files)
- [ ] No N+1 queries (use eager loading + accessors)
- [ ] Relationships used instead of direct queries
- [ ] Transactions for multi-table operations
- [ ] Error handling with graceful degradation
- [ ] User notifications for failures
- [ ] Proper type hints and return types
- [ ] Read-only resources properly configured
- [ ] Unused page files removed
- [ ] Consistent facade usage (imported, not fully qualified)
- [ ] Proper validation with helpful messages
- [ ] No deprecated methods (`reactive()` → `live()`)

---

## Quick Reference

### Model Checklist
- [ ] `protected $table` set if not standard
- [ ] `public $timestamps = false` if no timestamps
- [ ] `protected $fillable` defined
- [ ] `protected $casts` for type casting
- [ ] Relationships defined
- [ ] Accessors for computed values
- [ ] Scopes for common queries
- [ ] `getRouteKeyName()` if non-standard primary key

### Resource Checklist
- [ ] Model set correctly
- [ ] Navigation icon and label set
- [ ] Form schema defined
- [ ] Table columns defined
- [ ] Eager loading in `modifyQueryUsing`
- [ ] Filters defined
- [ ] Actions defined
- [ ] Pages registered in `getPages()`
- [ ] Read-only properly configured (if applicable)

### Page Checklist
- [ ] Lifecycle hooks used appropriately
- [ ] Transactions for multi-table operations
- [ ] Error handling with notifications
- [ ] Custom redirects if needed
- [ ] `$canCreateAnother = false` if not needed

### Service Checklist
- [ ] Type hints and return types
- [ ] Uses `config()` not `env()`
- [ ] Error handling with logging
- [ ] Graceful degradation

---

## Related Documentation

For detailed examples and patterns, see:
- `IMPLEMENTATION-GUIDE.md` - Detailed implementation patterns
- `CODE-QUALITY.md` (this folder) - Best practices review
- `../ARCHITECTURE/ARCHITECTURE.md` - System architecture patterns

---

**Remember:** Consistency is key. Follow these patterns to maintain code quality and make the codebase easier to understand and maintain.
