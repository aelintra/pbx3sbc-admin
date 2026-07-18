<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\View\View;

/**
 * SPA kinship: ← {list label} above the page heading (PanelBackLink pattern).
 */
trait HasPanelBackLink
{
    public function getHeader(): ?View
    {
        return view('filament.components.panel-back-header', [
            'backUrl' => $this->getPanelBackUrl(),
            'backLabel' => $this->getPanelBackLabel(),
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    protected function getPanelBackUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getPanelBackLabel(): string
    {
        $resource = static::getResource();

        return $resource::getNavigationLabel()
            ?? $resource::getPluralModelLabel();
    }
}
