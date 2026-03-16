<?php

namespace App\Filament\Resources\OutstationRequests;

use App\Filament\Resources\OutstationRequests\Pages\CreateOutstationRequest;
use App\Filament\Resources\OutstationRequests\Pages\EditOutstationRequest;
use App\Filament\Resources\OutstationRequests\Pages\ListOutstationRequests;
use App\Filament\Resources\OutstationRequests\Schemas\OutstationRequestForm;
use App\Filament\Resources\OutstationRequests\Tables\OutstationRequestsTable;
use App\Models\OutstationRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

use App\Filament\Concerns\FiltersBySubordinates;

class OutstationRequestResource extends Resource
{
    use FiltersBySubordinates;

    protected static ?string $model = OutstationRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Outstation Requests';

    public static function getNavigationGroup(): ?string
    {
        return 'Approvals';
    }

    public static function form(Schema $schema): Schema
    {
        return OutstationRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutstationRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutstationRequests::route('/'),
            'create' => CreateOutstationRequest::route('/create'),
            'edit' => EditOutstationRequest::route('/{record}/edit'),
        ];
    }
}
