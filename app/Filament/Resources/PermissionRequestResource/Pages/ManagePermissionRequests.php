<?php

namespace App\Filament\Resources\PermissionRequestResource\Pages;

use App\Filament\Resources\PermissionRequestResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePermissionRequests extends ManageRecords
{
    protected static string $resource = PermissionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since permission requests come from mobile API
        ];
    }
}
