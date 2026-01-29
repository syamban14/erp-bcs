<?php
namespace App\Filament\Resources\OutstationRequests\Pages;
use App\Filament\Resources\OutstationRequests\OutstationRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditOutstationRequest extends EditRecord
{
    protected static string $resource = OutstationRequestResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}