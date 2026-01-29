<?php

namespace App\Filament\Resources\ShiftSwapRequestResource\Pages;

use App\Filament\Resources\ShiftSwapRequestResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions;

class ManageShiftSwapRequests extends ManageRecords
{
    protected static string $resource = ShiftSwapRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tidak ada create action karena swap dilakukan manual via edit shift schedules
        ];
    }
}
