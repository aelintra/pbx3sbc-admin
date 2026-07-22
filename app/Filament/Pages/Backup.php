<?php

namespace App\Filament\Pages;

use App\Services\SbcBackupService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Cold DR backup — create/list local zips (+ optional S3 upload).
 * Restore is CLI-only. Warm standby sync is Fleet → Edge HA.
 */
class Backup extends Page
{
    protected static ?string $navigationIcon = 'lucide-hard-drive';

    protected static string $view = 'filament.pages.backup';

    protected static ?string $navigationLabel = 'Backup';

    protected static ?string $title = 'Backup';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 40;

    public bool $vipHolder = true;

    public string $advertisedAddress = '';

    public string $roleNote = '';

    public string $loadError = '';

    /** @var list<array{name: string, path: string, backup_stamp: string, created_at: string, epoch: int, bytes: int}> */
    public array $backups = [];

    public bool $creating = false;

    public bool $uploadToS3 = true;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->loadError = '';
        try {
            $svc = app(SbcBackupService::class);
            $role = $svc->vipRole();
            $this->vipHolder = $role['vip_holder'];
            $this->advertisedAddress = $role['advertised_address'];
            if ($this->vipHolder) {
                $this->roleNote = $this->advertisedAddress !== ''
                    ? "In-service member (holds advertised_address {$this->advertisedAddress})."
                    : 'In-service / solo member.';
            } else {
                $this->roleNote = 'Standby — DR backup runs on the in-service member (VIP holder). '
                    .'Warm sync is Fleet → Edge HA, not this page.';
            }
            $this->backups = $svc->listLocal();
        } catch (\Throwable $e) {
            $this->loadError = $e->getMessage();
            $this->backups = [];
        }
    }

    public function createBackup(): void
    {
        if (! $this->vipHolder) {
            Notification::make()
                ->title('Backup disabled on standby')
                ->body($this->roleNote)
                ->warning()
                ->send();

            return;
        }
        $this->creating = true;
        try {
            $result = app(SbcBackupService::class)->create($this->uploadToS3);
            $body = 'Archive '.$result['backup_stamp'];
            if ($this->uploadToS3) {
                $body .= $result['uploaded'] ? ' uploaded to S3.' : ' saved locally (S3 upload skipped or unavailable).';
            } else {
                $body .= ' saved locally.';
            }
            Notification::make()
                ->title('Backup created')
                ->body($body)
                ->success()
                ->send();
            $this->refresh();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Backup failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->creating = false;
        }
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KiB';
        }

        return round($bytes / (1024 * 1024), 1).' MiB';
    }
}
