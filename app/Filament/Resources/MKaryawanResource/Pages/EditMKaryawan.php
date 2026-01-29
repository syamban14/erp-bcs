<?php

namespace App\Filament\Resources\MKaryawanResource\Pages;

use App\Filament\Resources\MKaryawanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMKaryawan extends EditRecord
{
    protected static string $resource = MKaryawanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
