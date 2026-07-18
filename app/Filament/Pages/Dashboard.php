<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Home';

    protected static ?string $navigationIcon = 'lucide-home';

    public function getTitle(): string | Htmlable
    {
        $fqdn = parse_url((string) config('app.url'), PHP_URL_HOST)
            ?: request()->getHost();

        return $fqdn ? "Home — {$fqdn}" : 'Home';
    }
}
