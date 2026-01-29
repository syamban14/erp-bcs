<?php

namespace App\Filament\Resources\MPresensiResource\Pages;

use App\Filament\Resources\MPresensiResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMPresensis extends ManageRecords
{
    protected static string $resource = MPresensiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
