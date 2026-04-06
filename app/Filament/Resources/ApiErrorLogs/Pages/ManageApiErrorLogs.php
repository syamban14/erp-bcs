<?php

namespace App\Filament\Resources\ApiErrorLogs\Pages;

use App\Filament\Resources\ApiErrorLogs\ApiErrorLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageApiErrorLogs extends ManageRecords
{
    protected static string $resource = ApiErrorLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
