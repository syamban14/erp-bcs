<?php

namespace App\Filament\Resources\SystemActivities\Pages;

use App\Filament\Resources\SystemActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSystemActivities extends ManageRecords
{
    protected static string $resource = SystemActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
