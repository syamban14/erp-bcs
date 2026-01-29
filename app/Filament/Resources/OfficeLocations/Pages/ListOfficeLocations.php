<?php

namespace App\Filament\Resources\OfficeLocations\Pages;

use App\Filament\Resources\OfficeLocations\OfficeLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOfficeLocations extends ListRecords
{
    protected static string $resource = OfficeLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
