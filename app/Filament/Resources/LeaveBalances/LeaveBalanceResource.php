<?php

namespace App\Filament\Resources\LeaveBalances;

use App\Filament\Concerns\SuperAdminOnly;
use App\Filament\Resources\LeaveBalances\Pages\ManageLeaveBalances;
use App\Models\LeaveBalance;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class LeaveBalanceResource extends Resource
{
    use SuperAdminOnly;

    protected static ?string $model = LeaveBalance::class;
    
    protected static ?string $navigationLabel = 'Leave Quotas';
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;
    
    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Karyawan')
                    ->options(function () {
                        return \App\Models\MPresensi::query()
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->helperText('Pilih karyawan untuk memberikan quota cuti')
                    ->columnSpanFull(),
                    
                Forms\Components\TextInput::make('year')
                    ->label('Tahun')
                    ->numeric()
                    ->default(date('Y'))
                    ->required()
                    ->helperText('Tahun berlaku quota cuti')
                    ->minValue(2020)
                    ->maxValue(2100),
                    
                Forms\Components\TextInput::make('quota')
                    ->label('Quota Cuti (Hari)')
                    ->numeric()
                    ->default(12)
                    ->required()
                    ->helperText('Jumlah hari cuti yang diberikan')
                    ->minValue(0)
                    ->maxValue(365),
                    
                Forms\Components\TextInput::make('used')
                    ->label('Sudah Terpakai (Hari)')
                    ->numeric()
                    ->default(0)
                    ->helperText('Jumlah hari cuti yang sudah digunakan. Bisa dikoreksi manual jika diperlukan.')
                    ->minValue(0)
                    ->maxValue(365)
                    ->disabled(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (string $operation): bool => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label('Karyawan')
                    ->formatStateUsing(function ($state) {
                        $user = \App\Models\MPresensi::find($state);
                        return $user ? $user->name : '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        // Gunakan Eloquent MPresensi (koneksi pgsql_master) untuk dapatkan ID
                        $ids = \App\Models\MPresensi::where('name', 'ilike', "%{$search}%")
                            ->pluck('id')
                            ->toArray();
                        return $query->whereIn('user_id', $ids);
                    })
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('year')
                    ->label('Tahun')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('quota')
                    ->label('Quota')
                    ->suffix(' hari')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('used')
                    ->label('Terpakai')
                    ->suffix(' hari')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('remaining')
                    ->label('Sisa')
                    ->getStateUsing(fn ($record) => $record->getRemainingQuota() . ' hari')
                    ->badge()
                    ->color(fn ($record) => match(true) {
                        $record->getRemainingQuota() >= 10 => 'success',
                        $record->getRemainingQuota() >= 5 => 'warning',
                        default => 'danger',
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(function () {
                        $currentYear = date('Y');
                        return [
                            $currentYear - 1 => $currentYear - 1,
                            $currentYear => $currentYear,
                            $currentYear + 1 => $currentYear + 1,
                        ];
                    })
                    ->default(date('Y')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaveBalances::route('/'),
        ];
    }
}
