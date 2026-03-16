<?php

namespace App\Filament\Widgets;

use App\Models\Presence;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Storage;

class RecentActivitiesWidget extends BaseWidget
{
    use \App\Filament\Concerns\ResolvesDashboardDates;

    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected ?string $pollingInterval = '30s'; // Auto-refresh setiap 30 detik
    
    // Disable caching to ensure real-time updates
    protected static bool $isLazy = false;
    
    public function table(Table $table): Table
    {
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        
        return $table
            ->heading('Aktivitas Clock-In Terbaru')
            ->query(
                Presence::query()
                    ->with(['user'])
                    ->whereNotNull('clock_in')
                    ->whereBetween('clock_in', [
                        $startDate->startOfDay(),
                        $endDate->endOfDay()
                    ])
                    ->latest('created_at') // Sort by created_at for proper chronological order
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('user.photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->user->name ?? 'User') . '&background=random')
                    ->size(40),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->weight('medium'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Clock-In')
                    ->formatStateUsing(function ($record) {
                        // Combine date from created_at with time from clock_in
                        $date = $record->created_at ? $record->created_at->format('d M Y') : '-';
                        $time = $record->clock_in ?? '-';
                        return $date . ', ' . $time;
                    })
                    ->sortable()
                    ->icon('heroicon-m-clock'),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hadir' => 'success',
                        'terlambat' => 'warning',
                        'alpha' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'hadir' => 'Tepat Waktu',
                        'terlambat' => 'Terlambat',
                        'alpha' => 'Alpha',
                        default => ucfirst($state),
                    }),
                    
                Tables\Columns\TextColumn::make('location')
                    ->label('Lokasi')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    })
                    ->icon('heroicon-m-map-pin')
                    ->default('-'),
            ])
            ->paginated(false);
    }
}
