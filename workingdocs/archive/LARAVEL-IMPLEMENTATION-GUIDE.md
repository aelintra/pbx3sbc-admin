# Laravel + Filament Implementation Guide

**Date:** January 2026  
**Stack:** Laravel 12 + Filament (TALL Stack) - Integrated Admin Panel

## Technology Stack (Confirmed)

### Backend/Frontend (Integrated)
- **Runtime:** PHP 8.2+ (or PHP 8.1 minimum)
- **Framework:** Laravel 12 (current stable release)
- **Admin Panel:** Filament 3.x (TALL stack - Livewire + Alpine.js + Tailwind CSS)
- **Database:** MySQL (existing OpenSIPS database)
- **ORM:** Eloquent (Laravel's built-in ORM)
- **Authentication:** Laravel sessions (Filament built-in)

**Note:** Laravel 12 is the current stable release. Laravel no longer maintains LTS versions; all releases receive bug fixes for 18 months and security fixes for 2 years.

**Why Filament?** See FRONTEND-OPTIONS-DETAILED-ANALYSIS.md for detailed comparison. Filament was chosen for:
- Purpose-built for admin panels
- Rapid development
- Stays in PHP/Laravel ecosystem
- No separate frontend project needed
- Perfect for CRUD operations

## Project Structure

```
admin-panel/
├── app/
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── DomainResource.php
│   │   │   ├── DispatcherResource.php
│   │   │   ├── S3ObjectResource.php (optional)
│   │   │   └── ServiceResource.php (optional)
│   │   ├── Pages/
│   │   │   └── Dashboard.php (custom dashboard - optional)
│   │   └── Widgets/
│   │       └── ServiceStatusWidget.php (optional)
│   ├── Models/
│   │   ├── User.php (Laravel default)
│   │   ├── Domain.php
│   │   └── Dispatcher.php
│   ├── Services/
│   │   ├── OpenSIPSMIService.php
│   │   ├── SystemService.php
│   │   └── ExternalApiService.php (optional)
│   └── Http/
│       └── Requests/
│           ├── StoreDomainRequest.php
│           └── UpdateDomainRequest.php
├── database/
│   ├── migrations/
│   │   └── (Laravel/Filament creates users table)
│   └── seeders/
├── config/
│   ├── database.php
│   └── filesystems.php (for S3/Minio)
└── routes/
    └── (Filament uses its own routes - no API routes needed)
```

**Key Difference:** No separate `frontend/` folder - Filament is integrated into Laravel.

## Database Configuration

Laravel will connect to the same MySQL database as OpenSIPS:

```php
// config/database.php
'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'opensips'),
        'username' => env('DB_USERNAME', 'opensips'),
        'password' => env('DB_PASSWORD', ''),
        // ...
    ],
],
```

**Important:** Laravel will read/write to OpenSIPS tables (`domain`, `dispatcher`) while also using application tables (`users`, etc.) in the same database.

## Eloquent Models for OpenSIPS Tables

Since we're using existing OpenSIPS tables, we need to configure Eloquent models carefully:

```php
// app/Models/Domain.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $table = 'domain';
    
    protected $fillable = [
        'domain',
        'setid',
        'attrs',
        'accept_subdomain',
    ];
    
    protected $casts = [
        'accept_subdomain' => 'integer',
        'setid' => 'integer',
        'last_modified' => 'datetime',
    ];
    
    public $timestamps = false; // OpenSIPS tables don't use Laravel timestamps
}
```

```php
// app/Models/Dispatcher.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispatcher extends Model
{
    protected $table = 'dispatcher';
    
    protected $fillable = [
        'setid',
        'destination',
        'socket',
        'state',
        'weight',
        'priority',
        'attrs',
        'description',
        'probe_mode',
    ];
    
    protected $casts = [
        'setid' => 'integer',
        'state' => 'integer',
        'priority' => 'integer',
        'probe_mode' => 'integer',
    ];
    
    public $timestamps = false;
}
```

## Filament Installation & Setup

### Initial Setup

```bash
# Create Laravel project
composer create-project laravel/laravel:^12.0 admin-panel
cd admin-panel

# Install Filament
composer require filament/filament:"^3.0"

# Install Filament admin panel
php artisan filament:install --panels

# Create admin user
php artisan make:filament-user
```

### Filament Configuration

Filament configuration is in `config/filament.php`. Key settings:

```php
// config/filament.php (defaults are usually fine)
'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),
```

### Panel Configuration

Filament panels are configured in `app/Providers/Filament/AdminPanelProvider.php`:

```php
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('OpenSIPS Admin')
            ->colors([
                'primary' => '#3b82f6', // Tailwind blue-500
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets');
    }
}
```

## Authentication (Filament Built-in)

Filament uses Laravel's built-in authentication system (sessions, not JWT/API tokens).

### User Management

- Filament provides login/logout pages automatically
- Users are stored in the standard `users` table
- Create users via: `php artisan make:filament-user`
- Or manage users via a Filament UserResource (optional)

### Optional: Role-Based Access Control (RBAC)

If you need roles and permissions:

```bash
# Install Filament Shield plugin
composer require bezhansalleh/filament-shield

# Publish and run migrations
php artisan shield:install

# This creates roles, permissions tables and integrates with Filament
```

## Creating Filament Resources

### Domain Resource

```bash
php artisan make:filament-resource Domain
```

This creates:
- `app/Filament/Resources/DomainResource.php` - Main resource file
- `app/Filament/Resources/DomainResource/Pages/` - List, Create, Edit pages

Example DomainResource:

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

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    
    protected static ?string $navigationLabel = 'Domains';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('domain')
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/')
                    ->unique(ignoreRecord: true)
                    ->label('Domain Name'),
                    
                Forms\Components\TextInput::make('setid')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->label('Dispatcher Set ID'),
                    
                Forms\Components\Toggle::make('accept_subdomain')
                    ->label('Accept Subdomain')
                    ->default(false),
                    
                Forms\Components\TextInput::make('attrs')
                    ->maxLength(255)
                    ->label('Attributes'),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('setid')
                    ->sortable()
                    ->label('Set ID'),
                    
                Tables\Columns\IconColumn::make('accept_subdomain')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('last_modified')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('setid')
                    ->label('Set ID'),
            ])
            ->actions([
                Tables\Actions\Action::make('reload')
                    ->label('Reload OpenSIPS')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Domain $record) {
                        app(\App\Services\OpenSIPSMIService::class)->domainReload();
                        \Filament\Notifications\Notification::make()
                            ->title('OpenSIPS domain module reloaded')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
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

### Hooks for OpenSIPS MI Integration

To call OpenSIPS MI after create/update/delete, use Filament lifecycle hooks:

```php
// app/Filament/Resources/DomainResource/Pages/CreateDomain.php
namespace App\Filament\Resources\DomainResource\Pages;

use App\Filament\Resources\DomainResource;
use App\Services\OpenSIPSMIService;
use Filament\Resources\Pages\CreateRecord;

class CreateDomain extends CreateRecord
{
    protected static string $resource = DomainResource::class;
    
    protected function afterCreate(): void
    {
        app(OpenSIPSMIService::class)->domainReload();
    }
}
```

```php
// app/Filament/Resources/DomainResource/Pages/EditDomain.php
namespace App\Filament\Resources\DomainResource\Pages;

use App\Filament\Resources\DomainResource;
use App\Services\OpenSIPSMIService;
use Filament\Resources\Pages\EditRecord;

class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;
    
    protected function afterSave(): void
    {
        app(OpenSIPSMIService::class)->domainReload();
    }
}
```

```php
// app/Filament/Resources/DomainResource/Pages/ListDomains.php
namespace App\Filament\Resources\DomainResource\Pages;

use App\Filament\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDomains extends ListRecords
{
    protected static string $resource = DomainResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

### Dispatcher Resource

Similar structure for Dispatcher:

```bash
php artisan make:filament-resource Dispatcher
```

Key features for Dispatcher Resource:
- Table filter by `setid`
- Custom action to toggle state (active/inactive)
- Custom action to reload OpenSIPS dispatcher module
- Custom column showing state (badge/icon)

## OpenSIPS MI Integration Service

Create a service class to handle OpenSIPS MI HTTP calls:

```php
// app/Services/OpenSIPSMIService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenSIPSMIService
{
    protected $miUrl;
    
    public function __construct()
    {
        $this->miUrl = config('opensips.mi_url', 'http://127.0.0.1:8888/mi');
    }
    
    /**
     * Call OpenSIPS MI command
     */
    public function call(string $command, array $params = []): array
    {
        try {
            $response = Http::timeout(5)->post($this->miUrl, [
                'jsonrpc' => '2.0',
                'method' => $command,
                'params' => $params,
                'id' => 1,
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('OpenSIPS MI call failed', [
                'command' => $command,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            throw new \Exception("OpenSIPS MI call failed: {$response->status()}");
            
        } catch (\Exception $e) {
            Log::error('OpenSIPS MI exception', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    public function domainReload(): void
    {
        $this->call('domain_reload');
    }
    
    public function dispatcherReload(): void
    {
        $this->call('dispatcher_reload');
    }
    
    public function dispatcherSetState(int $setid, string $destination, int $state): void
    {
        $this->call('dispatcher_set_state', [
            'setid' => $setid,
            'destination' => $destination,
            'state' => $state,
        ]);
    }
    
    public function dispatcherList(): array
    {
        return $this->call('dispatcher_list');
    }
}
```

Add to config:

```php
// config/opensips.php (create this file)
return [
    'mi_url' => env('OPENSIPS_MI_URL', 'http://127.0.0.1:8888/mi'),
];
```

## Additional Requirements

### S3/Minio Object Storage

**See:** LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md for detailed assessment

Configure Minio in Laravel:

```php
// config/filesystems.php
'disks' => [
    'minio' => [
        'driver' => 's3',
        'key' => env('MINIO_KEY'),
        'secret' => env('MINIO_SECRET'),
        'region' => env('MINIO_REGION', 'us-east-1'),
        'bucket' => env('MINIO_BUCKET'),
        'endpoint' => env('MINIO_ENDPOINT'),
        'url' => env('MINIO_URL'),
        'use_path_style_endpoint' => true, // Required for Minio
    ],
],
```

Use in Filament:

```php
// In a Filament Resource or Page
use Illuminate\Support\Facades\Storage;

// List files
$files = Storage::disk('minio')->files();

// Upload file (via Filament file upload component)
Forms\Components\FileUpload::make('file')
    ->disk('minio')
    ->directory('uploads')
    ->visibility('private'),

// Download (via Filament action)
Tables\Actions\Action::make('download')
    ->action(function ($record) {
        return Storage::disk('minio')->download($record->file_path);
    }),
```

### Linux Service Management

**See:** LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md for detailed assessment

Create SystemService class:

```php
// app/Services/SystemService.php
namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class SystemService
{
    /**
     * Start a systemd service
     */
    public function startService(string $service): bool
    {
        // Validate service name to prevent command injection
        if (!preg_match('/^[a-zA-Z0-9@\-_.]+$/', $service)) {
            throw new \InvalidArgumentException("Invalid service name: {$service}");
        }
        
        $result = Process::run(['sudo', 'systemctl', 'start', $service]);
        
        Log::info("Service started: {$service}", [
            'success' => $result->successful(),
            'output' => $result->output(),
        ]);
        
        return $result->successful();
    }
    
    public function stopService(string $service): bool
    {
        if (!preg_match('/^[a-zA-Z0-9@\-_.]+$/', $service)) {
            throw new \InvalidArgumentException("Invalid service name: {$service}");
        }
        
        $result = Process::run(['sudo', 'systemctl', 'stop', $service]);
        
        Log::info("Service stopped: {$service}", [
            'success' => $result->successful(),
        ]);
        
        return $result->successful();
    }
    
    public function restartService(string $service): bool
    {
        if (!preg_match('/^[a-zA-Z0-9@\-_.]+$/', $service)) {
            throw new \InvalidArgumentException("Invalid service name: {$service}");
        }
        
        $result = Process::run(['sudo', 'systemctl', 'restart', $service]);
        
        Log::info("Service restarted: {$service}", [
            'success' => $result->successful(),
        ]);
        
        return $result->successful();
    }
    
    public function getStatus(string $service): array
    {
        if (!preg_match('/^[a-zA-Z0-9@\-_.]+$/', $service)) {
            throw new \InvalidArgumentException("Invalid service name: {$service}");
        }
        
        $result = Process::run(['sudo', 'systemctl', 'status', $service]);
        
        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'active' => str_contains($result->output(), 'active (running)'),
        ];
    }
}
```

Use in Filament Resource:

```php
// In a Service Resource
Tables\Actions\Action::make('start')
    ->label('Start')
    ->icon('heroicon-o-play')
    ->requiresConfirmation()
    ->action(function ($record) {
        $service = app(\App\Services\SystemService::class);
        $service->startService($record->name);
        
        \Filament\Notifications\Notification::make()
            ->title('Service started')
            ->success()
            ->send();
    }),
```

**Security Setup:** Configure sudoers file:

```bash
# /etc/sudoers.d/laravel
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start opensips
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop opensips
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart opensips
www-data ALL=(ALL) NOPASSWD: /bin/systemctl status opensips
```

### Remote API Integration

**See:** LARAVEL-ADDITIONAL-REQUIREMENTS-ASSESSMENT.md for detailed assessment

Create ExternalApiService:

```php
// app/Services/ExternalApiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExternalApiService
{
    protected $baseUrl;
    protected $token;
    
    public function __construct()
    {
        $this->baseUrl = config('services.external_api.url');
        $this->token = config('services.external_api.token');
    }
    
    /**
     * Fetch data from external API
     */
    public function fetchData(): array
    {
        $cacheKey = 'external_api_data';
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->get("{$this->baseUrl}/data");
            
            if (!$response->successful()) {
                Log::error('External API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception("API call failed: {$response->status()}");
            }
            
            return $response->json();
        });
    }
    
    /**
     * Update data to external API
     */
    public function updateData(int $id, array $data): bool
    {
        $response = Http::withToken($this->token)
            ->timeout(10)
            ->put("{$this->baseUrl}/data/{$id}", $data);
        
        if ($response->successful()) {
            // Clear cache
            Cache::forget('external_api_data');
            return true;
        }
        
        Log::error('External API update failed', [
            'id' => $id,
            'status' => $response->status(),
        ]);
        
        return false;
    }
}
```

Use in Filament:

```php
// In a Filament Resource or Page
Tables\Actions\Action::make('sync')
    ->label('Sync from API')
    ->icon('heroicon-o-arrow-path')
    ->action(function () {
        $service = app(\App\Services\ExternalApiService::class);
        $data = $service->fetchData();
        
        // Process and save data...
        
        \Filament\Notifications\Notification::make()
            ->title('Data synced from API')
            ->success()
            ->send();
    }),
```

## Form Request Validation

Laravel Form Requests work with Filament:

```php
// app/Http/Requests/StoreDomainRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDomainRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'],
            'setid' => ['required', 'integer', 'min:1'],
            'accept_subdomain' => ['boolean'],
            'attrs' => ['nullable', 'string', 'max:255'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'domain.regex' => 'The domain must be a valid domain name.',
            'setid.required' => 'Dispatcher set ID is required.',
        ];
    }
}
```

Use in Filament Resource:

```php
// In DomainResource
public static function form(Form $form): Form
{
    return $form
        ->schema([...])
        ->model(static::getModel())
        ->statePath('data')
        ->rules(\App\Http\Requests\StoreDomainRequest::class);
}
```

Or validate inline in the resource (simpler for Filament).

## Deployment Considerations

### Development

```bash
cd admin-panel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve  # Runs on http://localhost:8000
```

Access Filament at: `http://localhost:8000/admin`

### Production Deployment

1. **Nginx Configuration:**
   ```nginx
   server {
       listen 80;
       server_name admin.example.com;
       root /var/www/admin-panel/public;
       
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

2. **Environment Variables:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=opensips
   DB_USERNAME=opensips
   DB_PASSWORD=your-password
   
   OPENSIPS_MI_URL=http://127.0.0.1:8888/mi
   
   MINIO_KEY=your-key
   MINIO_SECRET=your-secret
   MINIO_ENDPOINT=http://localhost:9000
   MINIO_BUCKET=your-bucket
   ```

3. **Permissions:**
   ```bash
   sudo chown -R www-data:www-data /var/www/admin-panel
   sudo chmod -R 755 /var/www/admin-panel
   sudo chmod -R 775 /var/www/admin-panel/storage
   sudo chmod -R 775 /var/www/admin-panel/bootstrap/cache
   ```

4. **Systemd Service (optional):**
   ```ini
   # /etc/systemd/system/admin-panel.service
   [Unit]
   Description=OpenSIPS Admin Panel
   After=network.target mysql.service
   
   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/var/www/admin-panel
   ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8000
   Restart=always
   
   [Install]
   WantedBy=multi-user.target
   ```

## Development Workflow

1. **Create Filament Resource:**
   ```bash
   php artisan make:filament-resource Domain
   ```

2. **Edit Resource:**
   - Open `app/Filament/Resources/DomainResource.php`
   - Configure form fields, table columns, actions
   - Add lifecycle hooks for OpenSIPS MI calls

3. **Test:**
   - Access `/admin` in browser
   - Navigate to resource
   - Test create, edit, delete operations
   - Verify OpenSIPS MI calls work

4. **Hot Reload:**
   - Livewire (used by Filament) provides hot reload
   - Changes to PHP files trigger automatic page refresh
   - No separate frontend build process needed

## Key Advantages of Filament for This Project

1. **Rapid Development:** Built-in CRUD operations, forms, tables
2. **No Separate Frontend:** Everything in one Laravel application
3. **Eloquent Integration:** Works directly with Eloquent models
4. **Modern UI:** Tailwind CSS, responsive, dark mode
5. **Reactive UI:** Livewire provides reactivity without JavaScript framework
6. **Built-in Features:** Authentication, authorization, notifications, file uploads
7. **Extensible:** Easy to add custom actions, pages, widgets

## Next Steps for Implementation

1. Set up Laravel + Filament project
2. Configure database connection to OpenSIPS MySQL database
3. Create Eloquent models for `domain` and `dispatcher` tables
4. Create Filament Resources for domains and dispatchers
5. Implement OpenSIPS MI service
6. Add custom actions for OpenSIPS operations
7. Implement additional requirements (S3, services, APIs) as needed

---

**Note:** This guide is written for Laravel 12 and Filament 3.x. For Laravel 12 documentation, see: https://laravel.com/docs/12.x  
For Filament documentation, see: https://filamentphp.com/docs
