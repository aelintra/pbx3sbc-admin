<?php

namespace App\Filament\Pages;

use App\Services\ProvisionAccessService;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Restrict who may reach provision HTTPS (:41363) via host UFW.
 * Spec: SBC_PROVISION_ACCESS_REQUIREMENTS.md (C10)
 */
class ProvisionAccess extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'lucide-smartphone';

    protected static string $view = 'filament.pages.provision-access';

    protected static ?string $navigationLabel = 'Provision access';

    protected static ?string $title = 'Provision access';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 26;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public string $clientIp = '';

    public string $ufwStatus = '';

    public string $statePath = '';

    /** @var array<string, mixed>|null */
    public ?array $lastApply = null;

    public bool $scriptFound = false;

    public function mount(): void
    {
        $this->clientIp = (string) (request()->ip() ?? '');
        $this->refreshFromService();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Provision HTTPS lockdown')
                    ->description('Limits who can open phone provisioning HTTPS on port 41363. SIP, RTP, WSS, and admin HTTPS (:443) are unaffected. Vendor mTLS and MAC map still apply when configured. Leave off for mixed WFH fleets.')
                    ->schema([
                        Toggle::make('lockdown')
                            ->label('Restrict provision HTTPS to listed sources')
                            ->helperText('Off (default): any phone egress can reach provision.{apex}:41363 (subject to mTLS/MAC). On + Apply: only listed site egress CIDRs. Your current IP is auto-kept for curl tests from this session.'),
                        KeyValue::make('allows')
                            ->label('Allow list')
                            ->keyLabel('IP or CIDR')
                            ->valueLabel('Comment')
                            ->keyPlaceholder('203.0.113.10/32')
                            ->valuePlaceholder('Office / VPN / desk')
                            ->addActionLabel('Add source')
                            ->reorderable(false)
                            ->helperText('Trash removes that row from the form; Apply updates UFW.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function addMyIp(): void
    {
        $svc = app(ProvisionAccessService::class);
        $ip = $this->clientIp;
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            Notification::make()
                ->title('Could not detect client IP')
                ->danger()
                ->send();

            return;
        }
        $cidr = $svc->hostCidr($ip);
        $allows = $this->data['allows'] ?? [];
        if (isset($allows[$cidr]) || isset($allows[$ip])) {
            Notification::make()
                ->title('Already listed')
                ->body($cidr)
                ->info()
                ->send();

            return;
        }
        $allows[$cidr] = 'My IP';
        $this->data['allows'] = $allows;
        $this->form->fill($this->data);
        Notification::make()
            ->title('Added '.$cidr)
            ->success()
            ->send();
    }

    public function apply(): void
    {
        $state = $this->form->getState();
        $svc = app(ProvisionAccessService::class);

        try {
            $result = $svc->apply(
                [
                    'lockdown' => (bool) ($state['lockdown'] ?? false),
                    'allows' => $this->allowsMapToList($state['allows'] ?? []),
                ],
                $this->clientIp,
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Apply failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->refreshFromService();
        $this->ufwStatus = $result['ufw_output'] ?? $svc->statusOutput();

        if ($result['ok']) {
            Notification::make()
                ->title('Applied')
                ->body($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Not applied')
                ->body($result['message'])
                ->warning()
                ->send();
        }
    }

    public function refreshStatus(): void
    {
        $svc = app(ProvisionAccessService::class);
        $this->ufwStatus = $svc->statusOutput();
        $this->scriptFound = $svc->resolveApplyScript() !== null;
        Notification::make()
            ->title('Status refreshed')
            ->success()
            ->send();
    }

    private function refreshFromService(): void
    {
        $svc = app(ProvisionAccessService::class);
        $info = $svc->get();
        $this->data = [
            'lockdown' => $info['lockdown'],
            'allows' => $this->allowsListToMap($info['allows']),
        ];
        $this->form->fill($this->data);
        $this->statePath = $info['state_path'];
        $this->lastApply = $info['last_apply'];
        $this->scriptFound = $svc->resolveApplyScript() !== null;
        $this->ufwStatus = $svc->statusOutput();
    }

    /**
     * @param  list<array{cidr: string, comment: string}>  $list
     * @return array<string, string>
     */
    private function allowsListToMap(array $list): array
    {
        $map = [];
        foreach ($list as $row) {
            $cidr = trim((string) ($row['cidr'] ?? ''));
            if ($cidr === '') {
                continue;
            }
            $map[$cidr] = (string) ($row['comment'] ?? '');
        }

        return $map;
    }

    /**
     * @param  array<string, string|null>  $map
     * @return list<array{cidr: string, comment: string}>
     */
    private function allowsMapToList(array $map): array
    {
        $list = [];
        foreach ($map as $cidr => $comment) {
            $cidr = trim((string) $cidr);
            if ($cidr === '') {
                continue;
            }
            $list[] = [
                'cidr' => $cidr,
                'comment' => trim((string) ($comment ?? '')),
            ];
        }

        return $list;
    }
}
