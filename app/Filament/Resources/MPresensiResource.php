<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MPresensiResource\Pages;
use App\Models\MKaryawan;
use App\Models\MPresensi;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MPresensiResource extends Resource
{
    protected static ?string $model = MPresensi::class;

    protected static ?string $navigationLabel = 'Mobile Users (Presensi)';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-device-phone-mobile';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('karyawan_id')
                    ->label('Karyawan')
                    ->options(MKaryawan::query()->pluck('nama_karyawan', 'id'))
                    ->searchable()
                    ->required()
                    ->unique(ignoreRecord: true),
                
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
                
                Forms\Components\Select::make('office_location_id')
                    ->label('Lokasi Kantor (Geofencing)')
                    ->options(\App\Models\OfficeLocation::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->helperText('Pilih lokasi kantor untuk validasi geofencing saat absensi'),
                
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('karyawan.nama_karyawan')
                    ->label('Karyawan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('officeLocation.name')
                    ->label('Lokasi Kantor')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMPresensis::route('/'),
        ];
    }
}
