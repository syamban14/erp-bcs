<?php

namespace App\Filament\Widgets;

use App\Models\Leave;
use App\Models\PermissionRequest;
use App\Models\AttendanceCorrection;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingApprovalsWidget extends BaseWidget
{
    use \App\Filament\Concerns\ResolvesDashboardDates;

    protected static ?int $sort = 0; // Prioritas utama paling atas
    protected int | string | array $columnSpan = 'full';
    protected ?string $pollingInterval = '30s'; // Auto-refresh setiap 30 detik
    
    // Disable caching to ensure real-time updates
    protected static bool $isLazy = false;
    
    public function table(Table $table): Table
    {
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        $user = auth()->user();
        $isGlobalAdmin = $user ? $user->isGlobalAdmin() : false;
        $subordinateIds = $isGlobalAdmin ? [] : ($user ? $user->getSubordinateUserIds() : []);
        if (!$isGlobalAdmin && $user) {
            $subordinateIds[] = $user->id;
        }
        $scopeIds = empty($subordinateIds) ? [-1] : $subordinateIds;

        $query = Leave::query()
                    ->with(['user'])
                    ->where('status', 'pending')
                    ->whereBetween('created_at', [
                        $startDate->startOfDay(),
                        $endDate->endOfDay()
                    ])
                    ->latest()
                    ->limit(10);
                    
        if (!$isGlobalAdmin) {
            $query->whereIn('user_id', $scopeIds);
        }

        return $table
            ->heading('⚠️ Menunggu Persetujuan (Action Required)')
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                    
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'annual' => 'Cuti Tahunan',
                        'sick' => 'Sakit',
                        'permission' => 'Izin',
                        'unpaid' => 'Tidak Dibayar',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'annual' => 'primary',
                        'sick' => 'danger',
                        'permission' => 'warning',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->date('d M Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Tanggal Selesai')
                    ->date('d M Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('days')
                    ->label('Durasi')
                    ->suffix(' hari')
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(40)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 40) {
                            return null;
                        }
                        return $state;
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->since()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Menunggu',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => ucfirst($state),
                    }),
            ])
            ->paginated(false);
    }
}
