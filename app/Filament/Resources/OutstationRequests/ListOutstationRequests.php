<?php
namespace App\Filament\Resources\OutstationRequests\Pages;
use App\Filament\Resources\OutstationRequests\OutstationRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListOutstationRequests extends ListRecords
{
    protected static string $resource = OutstationRequestResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}