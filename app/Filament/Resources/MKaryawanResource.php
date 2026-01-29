<?php

namespace App\Filament\Resources;

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
                            ->default(fn (MKaryawan $record) => $record->email),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->default('password123'),
                        Forms\Components\Select::make('role')
                            ->options([
                                'user' => 'User Biasa',
                                'supervisor' => 'Supervisor Shift',
                            ])
                            ->default('user')
                            ->required(),
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
                            'role' => $data['role'], // Assumes role column exists (next step migration)
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
