<?php

namespace App\Filament\Resources\MKaryawanResource\Pages;

use App\Filament\Resources\MKaryawanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMKaryawans extends ListRecords
{
    protected static string $resource = MKaryawanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
