<?php

namespace App\Filament\Resources\ShiftCodes\Pages;

use App\Filament\Resources\ShiftCodes\ShiftCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShiftCodes extends ListRecords
{
    protected static string $resource = ShiftCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
