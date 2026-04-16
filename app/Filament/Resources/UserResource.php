<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\MKaryawan;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationLabel = 'Admin / Users';
    
    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function canAccess(): bool
    {
        // Fitur ini hanya bisa diakses oleh Superhyperadmin
        return auth()->check() && strtolower(auth()->user()->role) === 'superhyperadmin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->label('Primary Role (DB Column)')
                    ->options([
                        'superhyperadmin' => 'Superhyperadmin',
                        'superadmin'      => 'Superadmin',
                        'admin'           => 'Admin',
                        'user'            => 'User',
                    ])
                    ->required(),
                Forms\Components\Select::make('roles')
                    ->label('Spatie Roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('DB Role')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'superhyperadmin' => 'danger',
                        'superadmin' => 'success',
                        'admin'      => 'warning',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Spatie Roles')
                    ->badge()
                    ->color('primary')
            ])
            ->filters([
                //
            ])
            ->actions([
                // Quick action jadikan superadmin
                Tables\Actions\Action::make('makeSuperadmin')
                    ->label('Jadikan Superadmin')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => strtolower($record->role) !== 'superadmin' && strtolower($record->role) !== 'superhyperadmin')
                    ->action(function (User $record) {
                        $record->role = 'superadmin';
                        $record->save();
                        
                        // Coba tambah role spatie jika ada
                        try {
                            if (class_exists(Role::class)) {
                                if (! Role::where('name', 'superadmin')->exists()) {
                                    Role::create(['name' => 'superadmin']);
                                }
                                $record->assignRole('superadmin');
                            }
                        } catch (\Exception $e) {}

                        Notification::make()
                            ->title('Success')
                            ->body("{$record->name} sekarang menjabat sebagai Superadmin.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
