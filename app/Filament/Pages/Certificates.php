<?php

namespace App\Filament\Pages;

use App\Services\LetsEncryptService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * SPA Certificates panel kinship — Let's Encrypt + purchased cert.
 * Edge differences: single FQDN (APP_URL); no multi-tenant SAN sync.
 */
class Certificates extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'lucide-lock';

    protected static string $view = 'filament.pages.certificates';

    protected static ?string $navigationLabel = 'Certificates';

    protected static ?string $title = 'Certificates';

    protected static ?int $navigationSort = 90;

    public string $activeLabel = '';

    public string $loadError = '';

    /** @var array<string, mixed>|null */
    public ?array $leStatus = null;

    public bool $leLoading = true;

    public string $leError = '';

    public string $leSetupFqdn = '';

    public string $leSetupEmail = '';

    public bool $settingUp = false;

    public string $setupErrorMessage = '';

    public string $setupErrorDetail = '';

    public string $setupSuccess = '';

    public bool $renewing = false;

    public string $renewMessage = '';

    public string $renewErrorMessage = '';

    public string $renewErrorDetail = '';

    public bool $customInstalled = false;

    public bool $customLoading = true;

    public string $customError = '';

    public bool $installing = false;

    public string $installError = '';

    public string $installSuccess = '';

    public bool $removing = false;

    public bool $showRemoveConfirm = false;

    public $certFile = null;

    public $keyFile = null;

    public function mount(): void
    {
        $this->refetchAll();
    }

    public function refetchAll(): void
    {
        $this->fetchActive();
        $this->fetchLetsEncrypt();
        $this->fetchCustom();
    }

    public function fetchActive(): void
    {
        $this->loadError = '';
        try {
            $source = app(LetsEncryptService::class)->activeSource();
            $this->activeLabel = match ($source) {
                'custom' => 'Purchased certificate',
                'letsencrypt' => "Let's Encrypt",
                default => '',
            };
        } catch (\Throwable $e) {
            $this->loadError = $e->getMessage();
            $this->activeLabel = '';
        }
    }

    public function fetchLetsEncrypt(): void
    {
        $this->leLoading = true;
        $this->leError = '';
        $this->renewMessage = '';
        $this->renewErrorMessage = '';
        $this->renewErrorDetail = '';
        try {
            $svc = app(LetsEncryptService::class);
            $this->leSetupFqdn = $svc->fqdn();
            $this->leStatus = $svc->status();
        } catch (\Throwable $e) {
            $this->leError = $e->getMessage();
            $this->leStatus = null;
        } finally {
            $this->leLoading = false;
        }
    }

    public function fetchCustom(): void
    {
        $this->customLoading = true;
        $this->customError = '';
        try {
            $this->customInstalled = app(LetsEncryptService::class)->customInstalled();
        } catch (\Throwable $e) {
            $this->customError = $e->getMessage();
            $this->customInstalled = false;
        } finally {
            $this->customLoading = false;
        }
    }

    public function setupLetsEncrypt(): void
    {
        if ($this->leSetupEmail === '') {
            return;
        }
        $this->settingUp = true;
        $this->setupErrorMessage = '';
        $this->setupErrorDetail = '';
        $this->setupSuccess = '';
        try {
            app(LetsEncryptService::class)->setup($this->leSetupEmail);
            $this->setupSuccess = 'Certificate obtained.';
            Notification::make()->title($this->setupSuccess)->success()->send();
            $this->refetchAll();
        } catch (\Throwable $e) {
            $this->setupErrorMessage = 'Setup failed';
            $this->setupErrorDetail = $e->getMessage();
            Notification::make()->title($this->setupErrorMessage)->body($this->setupErrorDetail)->danger()->send();
        } finally {
            $this->settingUp = false;
        }
    }

    public function renewNow(): void
    {
        $this->renewing = true;
        $this->renewMessage = '';
        $this->renewErrorMessage = '';
        $this->renewErrorDetail = '';
        try {
            app(LetsEncryptService::class)->renew();
            $this->renewMessage = 'Renewal completed.';
            Notification::make()->title($this->renewMessage)->success()->send();
            $this->refetchAll();
        } catch (\Throwable $e) {
            $this->renewErrorMessage = 'Renewal failed';
            $this->renewErrorDetail = $e->getMessage();
            Notification::make()->title($this->renewErrorMessage)->body($this->renewErrorDetail)->danger()->send();
        } finally {
            $this->renewing = false;
        }
    }

    public function installCustom(): void
    {
        if (! $this->certFile instanceof TemporaryUploadedFile || ! $this->keyFile instanceof TemporaryUploadedFile) {
            $this->installError = 'Please select both certificate and key files.';

            return;
        }
        $this->installing = true;
        $this->installError = '';
        $this->installSuccess = '';
        try {
            $certPath = $this->certFile->getRealPath();
            $keyPath = $this->keyFile->getRealPath();
            app(LetsEncryptService::class)->installCustom($certPath, $keyPath);
            $this->installSuccess = 'Purchased certificate installed.';
            Notification::make()->title($this->installSuccess)->success()->send();
            $this->certFile = null;
            $this->keyFile = null;
            $this->refetchAll();
        } catch (\Throwable $e) {
            $this->installError = $e->getMessage();
            Notification::make()->title('Install failed')->body($this->installError)->danger()->send();
        } finally {
            $this->installing = false;
        }
    }

    public function confirmRemoveCustom(): void
    {
        $this->showRemoveConfirm = true;
    }

    public function doRemoveCustom(): void
    {
        $this->removing = true;
        try {
            app(LetsEncryptService::class)->removeCustom();
            Notification::make()->title('Purchased certificate removed.')->success()->send();
            $this->showRemoveConfirm = false;
            $this->refetchAll();
        } catch (\Throwable $e) {
            Notification::make()->title('Remove failed')->body($e->getMessage())->danger()->send();
        } finally {
            $this->removing = false;
        }
    }
}
