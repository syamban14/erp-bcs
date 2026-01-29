<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftCodeResource\Pages;
use App\Models\ShiftCode;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftCodeResource extends Resource
{
    protected static ?string $model = ShiftCode::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';
    
    protected static ?string $navigationLabel = 'Kode Shift';

    public static function getNavigationGroup(): ?string
    {
        return 'Shift Management';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Textarea::make('description')
                    ->label('Keterangan')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TimePicker::make('time_in')
                    ->label('Jam Masuk'),
                Forms\Components\TimePicker::make('time_out')
                    ->label('Jam Pulang'),
                Forms\Components\Toggle::make('is_off')
                    ->label('Libur/Cuti')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('time_in')
                    ->label('Jam Masuk')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('time_out')
                    ->label('Jam Pulang')
                    ->time('H:i'),
                Tables\Columns\IconColumn::make('is_off')
                    ->label('Libur')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_off')
                    ->label('Libur/Cuti')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya Libur/Cuti')
                    ->falseLabel('Hanya Shift Kerja'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageShiftCodes::route('/'),
        ];
    }
}
