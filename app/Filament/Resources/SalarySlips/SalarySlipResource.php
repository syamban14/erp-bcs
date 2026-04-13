<?php

namespace App\Filament\Resources\SalarySlips;

use App\Filament\Resources\SalarySlips\Pages\CreateSalarySlip;
use App\Filament\Resources\SalarySlips\Pages\EditSalarySlip;
use App\Filament\Resources\SalarySlips\Pages\ListSalarySlips;
use App\Filament\Resources\SalarySlips\Pages\ViewSalarySlip;
use App\Filament\Resources\SalarySlips\Schemas\SalarySlipForm;
use App\Filament\Resources\SalarySlips\Schemas\SalarySlipInfolist;
use App\Filament\Resources\SalarySlips\Tables\SalarySlipsTable;
use App\Models\SalarySlip;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalarySlipResource extends Resource
{
    protected static ?string $model = SalarySlip::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function getNavigationLabel(): string
    {
        return 'Slip Gaji (Upload)';
    }

    protected static ?string $recordTitleAttribute = 'employee_name';

    public static function canViewAny(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['superadmin', 'hr']);
    }

    public static function form(Schema $schema): Schema
    {
        return SalarySlipForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalarySlipInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalarySlipsTable::configure($table);
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
            'index' => ListSalarySlips::route('/'),
            'create' => CreateSalarySlip::route('/create'),
            'view' => ViewSalarySlip::route('/{record}'),
            'edit' => EditSalarySlip::route('/{record}/edit'),
        ];
    }
}
