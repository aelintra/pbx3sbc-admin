<?php

namespace App\Filament\Resources\CdrResource\Pages;

use App\Filament\Resources\CdrResource;
use Filament\Resources\Pages\ListRecords;

class ListCdrs extends ListRecords
{
    protected static string $resource = CdrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CDR records are created by OpenSIPS, not manually
        ];
    }

    public function mount(): void
    {
        parent::mount();
        $this->emptyDateRangeFilters();
    }

    public function resetTableFiltersForm(): void
    {
        parent::resetTableFiltersForm();
        $this->emptyDateRangeFilters();
    }

    /**
     * Blank date range = all records. Clear applied + deferred state only
     * (do not touch getTableFiltersForm() here — $table may be uninitialized).
     */
    protected function emptyDateRangeFilters(): void
    {
        $emptyCreated = [
            'created_from_date' => null,
            'created_from_time' => null,
            'created_until_date' => null,
            'created_until_time' => null,
        ];

        foreach (['tableFilters', 'tableDeferredFilters'] as $property) {
            $filters = $this->{$property};
            if (! is_array($filters)) {
                $filters = [];
            }
            $filters['created'] = $emptyCreated;
            $this->{$property} = $filters;
        }
    }
}
