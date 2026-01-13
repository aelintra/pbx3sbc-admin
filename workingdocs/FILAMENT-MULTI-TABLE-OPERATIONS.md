# Filament Multi-Table Operations Guide

**Date:** January 2026  
**Scenario:** Creating routes that require multiple database operations

## Use Case: Route Creation Workflow

**Current Manual Process:**
1. Create domain (if doesn't exist) → `domain` table
2. Create dispatcher entries → `dispatcher` table (using setid from domain)

**Desired:** Single input panel that:
- Takes domain, setid, and route(s)/destination(s)
- Creates domain entry (if needed)
- Creates dispatcher entry/entries
- All in one operation

## Answer: YES - Multiple Approaches Available

Filament supports multi-table operations through several patterns. Here are the best approaches for this use case.

---

## Approach 1: Custom Filament Page with Multi-Step Form (Recommended)

Create a custom Filament page that handles the entire workflow.

### Step 1: Create Custom Page

```bash
php artisan make:filament-page CreateRoute
```

### Step 2: Implement Multi-Step Form

```php
// app/Filament/Pages/CreateRoute.php
namespace App\Filament\Pages;

use App\Models\Domain;
use App\Models\Dispatcher;
use App\Services\OpenSIPSMIService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class CreateRoute extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static string $view = 'filament.pages.create-route';
    protected static ?string $navigationLabel = 'Create Route';
    protected static ?string $navigationGroup = 'Routing';
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Domain Information')
                ->schema([
                    Forms\Components\TextInput::make('domain')
                        ->label('Domain Name')
                        ->required()
                        ->maxLength(64)
                        ->regex('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/')
                        ->unique('domain', 'domain')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Check if domain exists
                            $existing = Domain::where('domain', $state)->first();
                            if ($existing) {
                                $set('setid', $existing->setid);
                                $set('domain_exists', true);
                            } else {
                                $set('domain_exists', false);
                            }
                        }),
                        
                    Forms\Components\TextInput::make('setid')
                        ->label('Dispatcher Set ID')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->disabled(fn (callable $get) => $get('domain_exists'))
                        ->helperText(fn (callable $get) => 
                            $get('domain_exists') 
                                ? 'Domain exists - setid from existing domain' 
                                : 'Set ID for new domain'
                        ),
                        
                    Forms\Components\Toggle::make('domain_exists')
                        ->hidden()
                        ->disabled(),
                ]),
                
            Forms\Components\Section::make('Dispatcher Destinations')
                ->schema([
                    Forms\Components\Repeater::make('dispatchers')
                        ->label('Routes/Destinations')
                        ->schema([
                            Forms\Components\TextInput::make('destination')
                                ->label('SIP URI')
                                ->required()
                                ->placeholder('sip:192.168.1.100:5060')
                                ->regex('/^sip:.+/'),
                                
                            Forms\Components\TextInput::make('weight')
                                ->label('Weight')
                                ->default('1')
                                ->numeric(),
                                
                            Forms\Components\TextInput::make('priority')
                                ->label('Priority')
                                ->default('0')
                                ->numeric(),
                                
                            Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(2),
                        ])
                        ->defaultItems(1)
                        ->minItems(1)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => 
                            $state['destination'] ?? 'New destination'
                        ),
                ]),
        ];
    }
    
    protected function getFormStatePath(): string
    {
        return 'data';
    }
    
    public function create(): void
    {
        $data = $this->form->getState();
        
        // Start database transaction
        \DB::transaction(function () use ($data) {
            // Step 1: Create or get domain
            $domain = Domain::firstOrCreate(
                ['domain' => $data['domain']],
                [
                    'setid' => $data['setid'],
                    'accept_subdomain' => 0,
                    'last_modified' => now(),
                ]
            );
            
            // If domain already existed, use its setid
            $setid = $domain->setid;
            
            // Step 2: Create dispatcher entries
            foreach ($data['dispatchers'] as $dispatcherData) {
                Dispatcher::create([
                    'setid' => $setid,
                    'destination' => $dispatcherData['destination'],
                    'weight' => $dispatcherData['weight'] ?? '1',
                    'priority' => $dispatcherData['priority'] ?? 0,
                    'description' => $dispatcherData['description'] ?? null,
                    'state' => 0, // Active
                    'probe_mode' => 0,
                ]);
            }
            
            // Step 3: Reload OpenSIPS modules
            $miService = app(OpenSIPSMIService::class);
            $miService->domainReload();
            $miService->dispatcherReload();
        });
        
        \Filament\Notifications\Notification::make()
            ->title('Route created successfully')
            ->success()
            ->send();
            
        // Redirect to dispatcher resource
        return redirect()->route('filament.admin.resources.dispatchers.index');
    }
    
    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('create')
                ->label('Create Route')
                ->submit('create'),
        ];
    }
}

// resources/views/filament/pages/create-route.blade.php
<x-filament-panels::page>
    <form wire:submit="create">
        {{ $this->form }}
        
        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </form>
</x-filament-pages::page>
```

**Advantages:**
- ✅ Single form for entire workflow
- ✅ Handles domain creation/selection
- ✅ Multiple dispatcher entries in one operation
- ✅ Transaction ensures data consistency
- ✅ Clean user experience

---

## Approach 2: Filament Resource with Relation Manager

Use DomainResource with a RelationManager for dispatchers.

### Step 1: Create Relation Manager

```bash
php artisan make:filament-relation-manager DomainResource dispatchers setid
```

### Step 2: Configure Domain Resource Form

```php
// app/Filament/Resources/DomainResource.php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('domain')
                ->required()
                ->unique(ignoreRecord: true),
                
            Forms\Components\TextInput::make('setid')
                ->required()
                ->numeric(),
        ]);
}

public static function getRelations(): array
{
    return [
        DomainResource\RelationManagers\DispatchersRelationManager::class,
    ];
}
```

### Step 3: Configure Relation Manager

```php
// app/Filament/Resources/DomainResource/RelationManagers/DispatchersRelationManager.php
namespace App\Filament\Resources\DomainResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DispatchersRelationManager extends RelationManager
{
    protected static string $relationship = 'dispatchers';
    
    protected static ?string $recordTitleAttribute = 'destination';
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('destination')
                    ->required()
                    ->regex('/^sip:.+/'),
                    
                Forms\Components\TextInput::make('weight')
                    ->default('1'),
                    
                Forms\Components\TextInput::make('priority')
                    ->default('0'),
            ]);
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('destination'),
                Tables\Columns\TextColumn::make('weight'),
                Tables\Columns\TextColumn::make('priority'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function () {
                        app(\App\Services\OpenSIPSMIService::class)->dispatcherReload();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

**Note:** This requires a relationship between Domain and Dispatcher models (which doesn't naturally exist - they're linked by setid, not foreign key).

**Advantages:**
- ✅ Built-in Filament pattern
- ✅ Dispatchers managed within domain context

**Disadvantages:**
- ❌ Requires relationship definition (artificial, since linked by setid)
- ❌ Two-step process (create domain, then add dispatchers)

---

## Approach 3: Custom Action in Domain Resource (Simpler Alternative)

Add a "Quick Create Route" action to DomainResource that opens a modal with dispatcher form.

### Implementation

```php
// app/Filament/Resources/DomainResource.php
public static function table(Table $table): Table
{
    return $table
        ->columns([...])
        ->actions([
            Tables\Actions\Action::make('quick_create_route')
                ->label('Add Route')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\Repeater::make('dispatchers')
                        ->schema([
                            Forms\Components\TextInput::make('destination')
                                ->required()
                                ->regex('/^sip:.+/'),
                            Forms\Components\TextInput::make('weight')
                                ->default('1'),
                            Forms\Components\TextInput::make('priority')
                                ->default('0'),
                        ])
                        ->defaultItems(1)
                        ->minItems(1),
                ])
                ->action(function (Domain $record, array $data) {
                    foreach ($data['dispatchers'] as $dispatcherData) {
                        \App\Models\Dispatcher::create([
                            'setid' => $record->setid,
                            'destination' => $dispatcherData['destination'],
                            'weight' => $dispatcherData['weight'] ?? '1',
                            'priority' => $dispatcherData['priority'] ?? 0,
                            'state' => 0,
                            'probe_mode' => 0,
                        ]);
                    }
                    
                    app(\App\Services\OpenSIPSMIService::class)->dispatcherReload();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Routes added successfully')
                        ->success()
                        ->send();
                }),
                
            // ... other actions
        ]);
}
```

**Advantages:**
- ✅ Quick to implement
- ✅ Domain already exists (no domain creation logic)
- ✅ Modal form for adding dispatchers

**Disadvantages:**
- ❌ Still requires domain to exist first
- ❌ Not a single unified form

---

## Approach 4: Wizard-Style Multi-Step Form (Most User-Friendly)

Use Filament's wizard component for a guided multi-step process.

### Implementation

```php
// app/Filament/Pages/CreateRoute.php
use Filament\Forms\Components\Wizard;

public function getFormSchema(): array
{
    return [
        Wizard::make([
            Wizard\Step::make('Domain')
                ->schema([
                    Forms\Components\TextInput::make('domain')
                        ->required()
                        ->unique('domain', 'domain')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $existing = Domain::where('domain', $state)->first();
                            if ($existing) {
                                $set('setid', $existing->setid);
                                $set('existing_domain', true);
                            }
                        }),
                        
                    Forms\Components\TextInput::make('setid')
                        ->required()
                        ->numeric()
                        ->disabled(fn (callable $get) => $get('existing_domain')),
                ]),
                
            Wizard\Step::make('Destinations')
                ->schema([
                    Forms\Components\Repeater::make('dispatchers')
                        ->schema([
                            Forms\Components\TextInput::make('destination')
                                ->required()
                                ->regex('/^sip:.+/'),
                            Forms\Components\TextInput::make('weight')
                                ->default('1'),
                            Forms\Components\TextInput::make('priority')
                                ->default('0'),
                        ])
                        ->defaultItems(1)
                        ->minItems(1),
                ]),
                
            Wizard\Step::make('Review')
                ->schema([
                    Forms\Components\Placeholder::make('review')
                        ->label('Review')
                        ->content(function (callable $get) {
                            return view('filament.components.route-review', [
                                'domain' => $get('domain'),
                                'setid' => $get('setid'),
                                'dispatchers' => $get('dispatchers'),
                            ]);
                        }),
                ]),
        ])
        ->submitAction(\Filament\Forms\Components\Actions\Action::make('submit')
            ->label('Create Route')
            ->submit('create')
        ),
    ];
}
```

**Advantages:**
- ✅ Best user experience (guided steps)
- ✅ Review step before submission
- ✅ Handles complex workflows elegantly

---

## Recommended Solution: Approach 1 (Custom Page) or Approach 4 (Wizard)

For your use case, I recommend **Approach 1 (Custom Page)** or **Approach 4 (Wizard)** because:

1. ✅ Single unified interface
2. ✅ Handles domain creation/selection
3. ✅ Multiple dispatcher entries in one operation
4. ✅ Transaction ensures data consistency
5. ✅ Clean, intuitive workflow

### Key Features to Include

1. **Domain Selection/Creation:**
   - Check if domain exists
   - Auto-populate setid if domain exists
   - Allow creating new domain with setid

2. **Multiple Dispatchers:**
   - Repeater component for multiple destinations
   - Add/remove destinations dynamically
   - Validate each destination

3. **Transaction Safety:**
   - Wrap all operations in database transaction
   - Rollback if any step fails

4. **OpenSIPS Integration:**
   - Reload domain and dispatcher modules after creation
   - Show success/error notifications

## Summary

**Yes, Filament can absolutely handle this!** Multiple approaches are available:

1. **Custom Page** - Best for complex workflows (Recommended)
2. **Wizard** - Best for guided multi-step processes
3. **Relation Manager** - Good if you want to manage dispatchers within domain context
4. **Custom Action** - Quick solution if domain already exists

The custom page or wizard approach provides the best user experience for your "create route" workflow, allowing users to:
- Enter domain (or select existing)
- Enter multiple destinations
- Create everything in one operation
- All with proper validation and transaction safety
