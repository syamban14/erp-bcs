<?php

namespace App\Filament\Resources\ShiftCodeResource\Pages;

use App\Filament\Resources\ShiftCodeResource;
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
