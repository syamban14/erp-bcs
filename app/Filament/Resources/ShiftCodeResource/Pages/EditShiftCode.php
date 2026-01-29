<?php

namespace App\Filament\Resources\ShiftCodes\Pages;

use App\Filament\Resources\ShiftCodes\ShiftCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShiftCode extends EditRecord
{
    protected static string $resource = ShiftCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
