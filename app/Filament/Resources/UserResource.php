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
        return auth()->check() && (auth()->user()->roles->pluck('name')->contains('superhyperadmin') || auth()->user()->id == 1);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('m_presensi_id')
                    ->label('Tarik Data Karyawan (Mobile Account)')
                    ->options(function () {
                        return \App\Models\MPresensi::query()
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $mobileAccount = \App\Models\MPresensi::find($state);
                            if ($mobileAccount) {
                                $set('name', $mobileAccount->name);
                                $set('email', $mobileAccount->email);
                                $set('password', $mobileAccount->password);
                                $set('is_copied_password', true);
                            }
                        }
                    })
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->hint('Pilih salah satu untuk mengisi form ke bawah secara otomatis'),

                Forms\Components\Hidden::make('is_copied_password')->default(false)->dehydrated(false),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('roles')
                    ->label('Spatie Roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(function ($state, $get) {
                        // Jika ini adalah password hasil copy otomatis dari Mobile Account yang mana SUDAH terbentuk Hash, 
                        // kita JANGAN melakukan re-hash.
                        if ($get('is_copied_password')) {
                            return $state;
                        }
                        
                        // Deteksi aman bawaan Laravel jika string tersebut memang sudah ter-hash
                        if (\Illuminate\Support\Facades\Hash::info($state)['algoName'] !== 'unknown') {
                            return $state;
                        }
                        
                        return Hash::make($state);
                    })
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

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Spatie Roles')
                    ->badge()
                    ->color('primary')
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
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
