<?php

namespace App\Filament\Resources\OfficeLocations\Pages;

use App\Filament\Resources\OfficeLocations\OfficeLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOfficeLocation extends EditRecord
{
    protected static string $resource = OfficeLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
