<?php

namespace App\Filament\Resources\LoanResource\Widgets;

use App\Models\Loan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LoanStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $activeLoans = Loan::active()->count();
        $totalOutstanding = Loan::active()->sum('remaining_amount');
        $pendingLoans = Loan::pending()->count();

        return [
            Stat::make('Pinjaman Aktif', $activeLoans)
                ->description('Total karyawan dengan pinjaman aktif')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
            
            Stat::make('Total Outstanding', 'Rp ' . number_format($totalOutstanding, 0, ',', '.'))
                ->description('Total sisa pinjaman yang belum lunas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Menunggu Persetujuan', $pendingLoans)
                ->description('Pengajuan baru yang perlu diproses')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingLoans > 0 ? 'warning' : 'gray'),
        ];
    }
}
