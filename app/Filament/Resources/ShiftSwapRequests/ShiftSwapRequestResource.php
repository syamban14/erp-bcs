<?php

namespace App\Filament\Resources\ShiftSwapRequests;

use App\Filament\Resources\ShiftSwapRequests\Pages\CreateShiftSwapRequest;
use App\Filament\Resources\ShiftSwapRequests\Pages\EditShiftSwapRequest;
use App\Filament\Resources\ShiftSwapRequests\Pages\ListShiftSwapRequests;
use App\Filament\Resources\ShiftSwapRequests\Schemas\ShiftSwapRequestForm;
use App\Filament\Resources\ShiftSwapRequests\Tables\ShiftSwapRequestsTable;
use App\Models\ShiftSwapRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShiftSwapRequestResource extends Resource
{
    protected static ?string $model = ShiftSwapRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    
    // Hide from navigation (duplicate menu)
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return ShiftSwapRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftSwapRequestsTable::configure($table);
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
            'index' => ListShiftSwapRequests::route('/'),
            'create' => CreateShiftSwapRequest::route('/create'),
            'edit' => EditShiftSwapRequest::route('/{record}/edit'),
        ];
    }
}
