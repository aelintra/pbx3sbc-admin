# Admin Panel Extensibility Guide

**Date:** January 2026  
**Purpose:** How the Filament admin panel scales as OpenSIPS features grow

## Overview

The current admin panel manages two core OpenSIPS tables:
- `domain` (Domain management)
- `dispatcher` (Dispatcher destinations)

As OpenSIPS features are added (accounting/CDR, statistics, trunking, etc.), the admin panel can easily grow to manage additional tables. This guide explains how.

## Core Architecture: Why It's Extensible

### Filament's Resource-Based Architecture

Filament uses a **Resource-based architecture** where each table/entity gets its own Resource class. This makes adding new features straightforward:

1. **One Table = One Resource** (typically)
2. **Each Resource is Self-Contained** (form, table, actions)
3. **Resources are Discovered Automatically** (no routing configuration needed)
4. **Standard Patterns** (same structure for all resources)

### Current Structure

```
app/Filament/Resources/
├── DomainResource.php        # Manages domain table
└── DispatcherResource.php    # Manages dispatcher table
```

### Future Structure (Example)

```
app/Filament/Resources/
├── DomainResource.php
├── DispatcherResource.php
├── CdrResource.php              # NEW: Accounting/CDR table
├── StatisticsResource.php       # NEW: Statistics table
├── TrunkResource.php            # NEW: Trunking configuration
├── DdiResource.php              # NEW: Direct Dial-In numbers
└── EndpointLocationResource.php # NEW: Endpoint locations (if needed)
```

**Key Point:** Adding a new table just means adding one new Resource file. No changes to existing code needed.

## Adding New Resources: Step-by-Step Pattern

### Example: Adding Accounting/CDR Management

When OpenSIPS accounting module is enabled and CDR tables are created, here's how to add management:

#### Step 1: Create Eloquent Model

```bash
php artisan make:model Cdr
```

```php
// app/Models/Cdr.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cdr extends Model
{
    protected $table = 'acc'; // OpenSIPS accounting table name
    
    protected $fillable = [
        'time',
        'method',
        'from_tag',
        'to_tag',
        'callid',
        'caller_ip',
        'callee_ip',
        // ... other CDR fields
    ];
    
    protected $casts = [
        'time' => 'datetime',
        // ... other casts
    ];
    
    public $timestamps = false; // OpenSIPS tables don't use Laravel timestamps
}
```

#### Step 2: Create Filament Resource

```bash
php artisan make:filament-resource Cdr
```

This automatically creates:
- `app/Filament/Resources/CdrResource.php`
- `app/Filament/Resources/CdrResource/Pages/ListCdrs.php`
- `app/Filament/Resources/CdrResource/Pages/CreateCdr.php` (optional, if CDRs are created manually)
- `app/Filament/Resources/CdrResource/Pages/EditCdr.php` (optional, if CDRs are read-only)

#### Step 3: Configure Resource

```php
// app/Filament/Resources/CdrResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\CdrResource\Pages;
use App\Models\Cdr;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CdrResource extends Resource
{
    protected static ?string $model = Cdr::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationLabel = 'Call Records';
    
    protected static ?string $navigationGroup = 'Analytics'; // Optional: Group related resources
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form fields if CDRs can be edited
                // Often CDRs are read-only, so form might be minimal
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('time')
                    ->dateTime()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('callid')
                    ->searchable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('caller_ip')
                    ->label('Caller IP'),
                    
                Tables\Columns\TextColumn::make('callee_ip')
                    ->label('Callee IP'),
                    
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'INVITE' => 'success',
                        'BYE' => 'danger',
                        default => 'gray',
                    }),
                // ... other columns
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'INVITE' => 'INVITE',
                        'BYE' => 'BYE',
                        'CANCEL' => 'CANCEL',
                    ]),
                    
                Tables\Filters\Filter::make('time')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('time', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('time', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('time', 'desc')
            ->actions([
                // Read-only, so no edit/delete actions
                Tables\Actions\ViewAction::make(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCdrs::route('/'),
            // 'create' => Pages\CreateCdr::route('/create'), // Only if creating CDRs manually
            // 'edit' => Pages\EditCdr::route('/{record}/edit'), // Only if editing CDRs
        ];
    }
}
```

#### Step 4: Resource Appears Automatically

Filament automatically discovers the new resource and adds it to navigation. No routing or configuration needed!

**Result:** The CDR management interface is now available in the admin panel.

## Navigation Organization

As resources grow, organize them with navigation groups:

```php
// In each Resource class
protected static ?string $navigationGroup = 'Routing'; // Domain, Dispatcher
protected static ?string $navigationGroup = 'Analytics'; // CDR, Statistics
protected static ?string $navigationGroup = 'Configuration'; // Trunks, DDI
```

This creates organized navigation in the sidebar:

```
Navigation:
├── Routing
│   ├── Domains
│   └── Dispatchers
├── Analytics
│   ├── Call Records (CDR)
│   └── Statistics
└── Configuration
    ├── Trunks
    └── DDI Numbers
```

## Service Layer Pattern

For complex operations that span multiple resources or require external integrations:

### Current Services

```
app/Services/
├── OpenSIPSMIService.php    # OpenSIPS MI integration
├── SystemService.php         # System service management
└── ExternalApiService.php    # External API integration
```

### Future Services (as needed)

```
app/Services/
├── OpenSIPSMIService.php
├── SystemService.php
├── ExternalApiService.php
├── AccountingService.php     # NEW: CDR processing, reporting
├── StatisticsService.php     # NEW: Statistics aggregation
└── TrunkService.php          # NEW: Trunk management logic
```

**Pattern:** Services handle business logic, Resources handle UI/CRUD.

## Database Evolution

### Current Tables (OpenSIPS Core)

- `domain` - Managed by DomainResource
- `dispatcher` - Managed by DispatcherResource
- `endpoint_locations` - Managed by OpenSIPS (may add resource later if needed)

### Future Tables (as OpenSIPS features are added)

#### Accounting Module
- `acc` - Call Detail Records (CDR) → `CdrResource`
- `missed_calls` - Missed calls → `MissedCallResource` (optional)

#### Statistics Module
- `stats` - Real-time statistics → `StatisticsResource`
- Historical statistics tables → Custom resources

#### Additional Modules (examples)
- `dr_gateways` - Dynamic routing gateways → `GatewayResource`
- `dr_rules` - Dynamic routing rules → `RoutingRuleResource`
- `dialplan` - Dialplan rules → `DialplanResource`

### Adding New Tables: No Code Changes to Existing Resources

**Key Point:** Adding a new table requires:
1. ✅ Create Eloquent Model (if needed)
2. ✅ Create Filament Resource
3. ✅ Configure Resource (form, table, actions)

**No changes needed to:**
- ❌ Existing Resources (Domain, Dispatcher)
- ❌ Service classes (unless new integration needed)
- ❌ Configuration files
- ❌ Routing (Filament auto-discovers resources)

## Example: Complete Feature Addition

### Scenario: Adding Statistics Dashboard

#### Step 1: Database Table Already Exists (from OpenSIPS)

OpenSIPS statistics module creates `stats` table with columns:
- `id`
- `name` (statistic name)
- `value` (statistic value)
- `updated` (last update timestamp)

#### Step 2: Create Model (if needed)

```php
// app/Models/Statistic.php
class Statistic extends Model
{
    protected $table = 'stats';
    // ... configuration
}
```

#### Step 3: Create Resource

```bash
php artisan make:filament-resource Statistic --view
```

**Note:** Using `--view` flag if statistics are read-only.

#### Step 4: Configure Resource

```php
class StatisticResource extends Resource
{
    protected static ?string $navigationGroup = 'Analytics';
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('value'),
                Tables\Columns\TextColumn::make('updated')->dateTime(),
            ])
            ->defaultSort('name');
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatistics::route('/'),
        ];
    }
}
```

#### Step 5: Optional: Add Custom Widget for Dashboard

```bash
php artisan make:filament-widget StatsOverview
```

```php
// app/Filament/Widgets/StatsOverview.php
class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Calls', Statistic::where('name', 'active_calls')->value('value')),
            Stat::make('Total Calls', Statistic::where('name', 'total_calls')->value('value')),
            // ...
        ];
    }
}
```

Add to dashboard:

```php
// app/Filament/Pages/Dashboard.php
protected function getWidgets(): array
{
    return [
        Widgets\StatsOverview::class,
        // ... other widgets
    ];
}
```

**Result:** Statistics management is now available with minimal code.

## Best Practices for Extensibility

### 1. Consistent Resource Structure

Follow the same pattern for all resources:
- Model in `app/Models/`
- Resource in `app/Filament/Resources/`
- Service classes in `app/Services/` (if complex logic needed)

### 2. Use Navigation Groups

Organize related resources:
- `Routing` - Domain, Dispatcher
- `Analytics` - CDR, Statistics
- `Configuration` - Trunks, DDI, Settings

### 3. Service Classes for Complex Logic

Keep Resources focused on UI/CRUD. Move complex logic to Service classes:
- OpenSIPS MI integration → `OpenSIPSMIService`
- System operations → `SystemService`
- Data processing → `AccountingService`, `StatisticsService`

### 4. Read-Only Resources

Many OpenSIPS tables are read-only (CDR, statistics). Use Filament's view-only resources:

```bash
php artisan make:filament-resource Cdr --view
```

This creates a read-only resource (no create/edit forms).

### 5. Custom Actions for Operations

For operations that don't fit standard CRUD, use custom actions:

```php
// In CdrResource
Tables\Actions\Action::make('export')
    ->label('Export CDR')
    ->icon('heroicon-o-arrow-down-tray')
    ->action(function ($record) {
        // Export logic
    }),
```

### 6. Filters and Search

Make large tables usable with filters:

```php
->filters([
    Tables\Filters\SelectFilter::make('status'),
    Tables\Filters\Filter::make('date_range')
        ->form([...]),
])
->searchable(['field1', 'field2'])
```

## Scalability Considerations

### Performance

- **Pagination:** Filament tables paginate by default (good for large datasets)
- **Eager Loading:** Use Eloquent relationships efficiently
- **Caching:** Cache expensive queries (statistics, aggregations)
- **Indexes:** Ensure database indexes on frequently queried columns

### Code Organization

- **Resources:** One per table (simple, clear structure)
- **Services:** One per domain (OpenSIPS MI, Accounting, Statistics)
- **Models:** Standard Eloquent models
- **Widgets:** Reusable dashboard components

### Database Growth

As tables grow:
- CDR tables can get very large → Consider read-only resources, archiving
- Statistics tables → Consider aggregation, time-based partitioning
- Use Laravel's query optimization features

## Migration Path Example

### Current State (2 Resources)

```
Resources:
1. DomainResource
2. DispatcherResource
```

### After Adding Accounting (3 Resources)

```
Resources:
1. DomainResource (unchanged)
2. DispatcherResource (unchanged)
3. CdrResource (new)
```

### After Adding Statistics (4 Resources)

```
Resources:
1. DomainResource (unchanged)
2. DispatcherResource (unchanged)
3. CdrResource (unchanged)
4. StatisticsResource (new)
```

### After Adding Trunking (5 Resources)

```
Resources:
1. DomainResource (unchanged)
2. DispatcherResource (unchanged)
3. CdrResource (unchanged)
4. StatisticsResource (unchanged)
5. TrunkResource (new)
```

**Key Point:** Each addition is independent. No changes to existing code.

## Summary

### Why Filament is Extensible

1. **Resource-Based:** One table = one resource (simple pattern)
2. **Auto-Discovery:** New resources appear automatically (no routing/config)
3. **Self-Contained:** Each resource is independent
4. **Standard Patterns:** Same structure for all resources
5. **No Coupling:** Resources don't depend on each other

### Adding a New Table/Feature

**Steps:**
1. Create Eloquent Model (if needed)
2. Create Filament Resource (`php artisan make:filament-resource`)
3. Configure Resource (form, table, actions)
4. Done! Resource appears in navigation automatically

**Code Changes:** Only new files. No changes to existing code.

### Architecture Benefits

- **Scalable:** Add resources as needed
- **Maintainable:** Clear separation (Resources, Services, Models)
- **Flexible:** Custom actions, widgets, pages as needed
- **Organized:** Navigation groups keep things tidy

**Conclusion:** The Filament architecture is designed for extensibility. Adding new OpenSIPS features (accounting, statistics, trunking, etc.) follows the same simple pattern: create a new Resource for each new table.
