<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\SuperAdminOnly;
use App\Filament\Resources\MKaryawanResource\Pages;
use App\Models\MKaryawan;
use App\Models\MPresensi;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MKaryawanResource extends Resource
{   
    use SuperAdminOnly;

    protected static ?string $navigationLabel = 'Employee Management';
    protected static ?string $modelLabel = 'Employee';           // judul singular: New Employee
    protected static ?string $pluralModelLabel = 'Employee Management'; // judul plural di halaman list

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    protected static ?string $model = MKaryawan::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('nama_karyawan')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_karyawan')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('posisi')->label('Posisi'),
                Tables\Columns\IconColumn::make('has_account')
                    ->label('Mobile User')
                    ->boolean()
                    ->state(function (MKaryawan $record): bool {
                        return (bool) $record->presensiAccount;
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('create_account')
                    ->label('Buat Akun')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->rules(['unique:pgsql_master.m_presensi,email'])
                            ->default(fn (MKaryawan $record) => $record->email),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->default('password123'),
                        Forms\Components\Select::make('office_location_id')
                            ->label('Lokasi Kantor (Geofencing)')
                            ->options(\App\Models\OfficeLocation::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->helperText('Wajib ditautkan ke area absen karyawan tersebut.'),
                    ])
                    ->action(function (MKaryawan $record, array $data) {
                        // Check local
                        if (MPresensi::where('karyawan_id', $record->id)->exists()) {
                            Notification::make()->title('Akun sudah ada')->danger()->send();
                            return;
                        }

                        MPresensi::create([
                            'karyawan_id' => $record->id,
                            'name' => $record->nama_karyawan,
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                            'role' => 'user', 
                            'office_location_id' => $data['office_location_id'],
                            'is_active' => true,
                        ]);

                        Notification::make()->title('Akun berhasil dibuat')->success()->send();
                    })
                    ->visible(fn (MKaryawan $record) => ! $record->presensiAccount),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMKaryawans::route('/'),
        ];
    }
}
