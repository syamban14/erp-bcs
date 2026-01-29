<?php

namespace App\Filament\Resources\ShiftSwapRequests\Pages;

use App\Filament\Resources\ShiftSwapRequests\ShiftSwapRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShiftSwapRequests extends ListRecords
{
    protected static string $resource = ShiftSwapRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
