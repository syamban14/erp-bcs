<?php

namespace App\Filament\Resources\ShiftSwapRequests\Pages;

use App\Filament\Resources\ShiftSwapRequests\ShiftSwapRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShiftSwapRequest extends EditRecord
{
    protected static string $resource = ShiftSwapRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
