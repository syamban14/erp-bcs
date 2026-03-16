<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\SuperAdminOnly;
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
    use SuperAdminOnly;

    protected static ?string $model = MPresensi::class;

    protected static ?string $navigationLabel = 'Mobile Accounts';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

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

                Forms\Components\Hidden::make('role')
                    ->default('user'),

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

                // Role / Hierarki Jabatan
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'supervisor'      => 'warning',
                        'manager'         => 'info',
                        'hr'              => 'success',
                        'general_manager' => 'primary',
                        'direktur'        => 'danger',
                        default           => 'gray', // user
                    })
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'supervisor'      => '👔 Supervisor',
                        'manager'         => '🏢 Manager',
                        'hr'              => '📋 HR',
                        'general_manager' => '🌟 GM',
                        'direktur'        => '⭐ Direktur',
                        default           => '👤 User',
                    }),

                // Jumlah device terdaftar
                Tables\Columns\TextColumn::make('devices_count')
                    ->label('Device')
                    ->counts('devices')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->tooltip(fn ($record) =>
                        $record->devices()->latest('last_active_at')->first()?->device_name
                        ?? 'Belum ada device terdaftar'
                    ),

                // Last active device
                Tables\Columns\TextColumn::make('device_last_active')
                    ->label('Last Active')
                    ->getStateUsing(fn ($record) =>
                        $record->devices()->latest('last_active_at')->first()?->last_active_at?->diffForHumans()
                        ?? '-'
                    )
                    ->color('gray'),

                // Status PIN (ada/tidak)
                Tables\Columns\IconColumn::make('pin_registered')
                    ->label('PIN')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->pin))
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),

                // ── Ubah Role ─────────────────────────────────────────────
                \Filament\Actions\Action::make('change_role')
                    ->label('Ubah Role')
                    ->icon('heroicon-o-user-circle')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label('Hierarki Jabatan')
                            ->options([
                                'user'            => '🤖 Kalkulasi Dinamis (Dari M_Karyawan)',
                                'supervisor'      => '👔 Paksa jadi Supervisor',
                                'manager'         => '🏢 Paksa jadi Manager',
                                'hr'              => '📋 Paksa jadi HR / Personalia',
                                'general_manager' => '🌟 Paksa jadi General Manager',
                                'direktur'        => '⭐ Paksa jadi Direktur',
                            ])
                            ->default(fn ($record) => $record->getRawOriginal('role') ?? 'user')
                            ->required()
                            ->helperText('Pilih "Kalkulasi Dinamis" agar sistem membaca jabatan karyawan secara otomatis.'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['role' => $data['role']]);

                        \Filament\Notifications\Notification::make()
                            ->title('Role Berhasil Diubah')
                            ->body('Role ' . ($record->karyawan?->nama_karyawan ?? $record->email) . ' diubah ke: ' . ucfirst($data['role']))
                            ->success()
                            ->send();
                    }),

                // ── Reset Device ──────────────────────────────────────────
                \Filament\Actions\Action::make('reset_device')
                    ->label('Reset Device')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Device Terdaftar')
                    ->modalDescription(fn ($record) => implode(' ', [
                        'Hapus device terdaftar milik "' . ($record->karyawan?->nama_karyawan ?? $record->email) . '"?',
                        'Device: ' . ($record->devices()->latest('last_active_at')->first()?->device_name ?? 'Unknown') . '.',
                        "\n\n⚠️ Karyawan akan di-LOGOUT dari semua sesi aktif.",
                        'Login berikutnya dari HP baru akan otomatis terdaftar sebagai device baru.',
                    ]))
                    ->modalSubmitActionLabel('Ya, Reset Device & Logout')
                    ->visible(fn ($record) => $record->devices()->exists())
                    ->action(function ($record) {
                        $deviceName = $record->devices()->latest('last_active_at')->first()?->device_name ?? 'Unknown';

                        // 1. Hapus semua device terdaftar
                        $record->devices()->delete();

                        // 2. Force-logout: revoke semua token Sanctum aktif
                        $tokenCount = $record->tokens()->count();
                        $record->tokens()->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Device & Sesi Berhasil Direset')
                            ->body("Device \"{$deviceName}\" dihapus. {$tokenCount} sesi aktif direvoke. Login berikutnya dari HP baru akan otomatis terdaftar.")
                            ->success()
                            ->send();
                    }),

                // ── Reset PIN ─────────────────────────────────────────────
                \Filament\Actions\Action::make('reset_pin')
                    ->label('Reset PIN')
                    ->icon('heroicon-o-key')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reset PIN Karyawan')
                    ->modalDescription(fn ($record) =>
                        'Hapus PIN milik "' . ($record->karyawan?->nama_karyawan ?? $record->email) . '"? ' .
                        'Karyawan harus membuat PIN baru dari aplikasi mobile.'
                    )
                    ->modalSubmitActionLabel('Ya, Hapus PIN')
                    ->visible(fn ($record) => !empty($record->pin))
                    ->action(function ($record) {
                        $record->update(['pin' => null]);

                        \Filament\Notifications\Notification::make()
                            ->title('PIN Berhasil Direset')
                            ->body('Karyawan harus membuat PIN baru dari aplikasi mobile.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMPresensis::route('/'),
        ];
    }
}
