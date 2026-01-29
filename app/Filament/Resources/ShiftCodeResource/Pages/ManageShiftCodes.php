<?php

namespace App\Filament\Resources\ShiftCodeResource\Pages;

use App\Filament\Resources\ShiftCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageShiftCodes extends ManageRecords
{
    protected static string $resource = ShiftCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
