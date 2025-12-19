<?php

namespace App\Filament\Admin\Resources\ActivityLogs\Pages;

use App\Filament\Admin\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Activity Log';
    }
}
