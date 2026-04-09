<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftScheduleResource\Pages;
use App\Models\ShiftSchedule;
use App\Models\MPresensi;
use App\Models\ShiftCode;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Concerns\FiltersBySubordinates;

class ShiftScheduleResource extends Resource
{
    use FiltersBySubordinates;

    protected static ?string $model = ShiftSchedule::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = 'Shift Schedules';

    public static function getNavigationGroup(): ?string
    {
        return 'Shift Management';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Karyawan')
                    ->options(MPresensi::query()->pluck('name', 'id')) // Note: MPresensi is on different connection, but pluck should work if model configured
                    ->searchable()
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\Select::make('shift_code')
                    ->label('Kode Shift')
                    ->options(ShiftCode::query()->get()->filter(function ($shift) {
                        $name = strtolower($shift->name);
                        return str_contains($name, 'pagi') || 
                               str_contains($name, 'siang') || 
                               str_contains($name, 'malam') || 
                               str_contains($name, 'off') || 
                               str_contains($name, 'libur');
                    })->mapWithKeys(function ($shift) {
                        if ($shift->is_off) {
                            return [$shift->code => $shift->name . ' (Libur / Off)'];
                        }
                        $timeIn = $shift->time_in ? \Carbon\Carbon::parse($shift->time_in)->format('H:i') : '--:--';
                        $timeOut = $shift->time_out ? \Carbon\Carbon::parse($shift->time_out)->format('H:i') : '--:--';
                        return [$shift->code => $shift->name . ' (' . $timeIn . ' - ' . $timeOut . ')'];
                    }))
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shift_code')
                    ->label('Kode Shift')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state)
                    ->color(fn ($state) => ShiftCode::where('code', $state)->first()?->is_off ? 'gray' : 'success'),
                Tables\Columns\TextColumn::make('shiftCode.name')
                    ->label('Nama Shift')
                    ->searchable(),
                Tables\Columns\TextColumn::make('shiftCode.time_in')
                    ->label('Jam Masuk')
                    ->time('H:i')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('shiftCode.time_out')
                    ->label('Jam Pulang')
                    ->time('H:i')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Karyawan')
                    ->options(MPresensi::query()->pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
                Tables\Filters\SelectFilter::make('shift_code')
                    ->label('Kode Shift')
                    ->options(ShiftCode::query()->pluck('code', 'code'))
                    ->searchable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShiftSchedules::route('/'),
            // 'create' => Pages\CreateShiftSchedule::route('/create'),
            // 'edit' => Pages\EditShiftSchedule::route('/{record}/edit'),
        ];
    }
}
