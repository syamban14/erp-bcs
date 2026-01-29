<?php

namespace App\Filament\Resources\AttendanceCorrectionResource\Pages;

use App\Filament\Resources\AttendanceCorrectionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceCorrections extends ManageRecords
{
    protected static string $resource = AttendanceCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since corrections come from mobile API
        ];
    }
}
