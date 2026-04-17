<?php

namespace App\Filament\Widgets;

use App\Models\Presence;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AttendanceTrendChart extends ChartWidget
{
    use \App\Filament\Concerns\ResolvesDashboardDates;

    protected ?string $heading = 'Tren Kehadiran';
    protected static ?int $sort = 2;
    protected ?string $pollingInterval = '60s'; // Auto-refresh setiap 60 detik
    
    // Disable caching to ensure real-time updates
    protected static bool $isLazy = false;
    
    protected function getData(): array
    {
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        $user = auth()->user();
        $isGlobalAdmin = $user ? $user->isGlobalAdmin() : false;
        $subordinateIds = $isGlobalAdmin ? [] : ($user ? $user->getSubordinateUserIds() : []);
        $scopeIds = empty($subordinateIds) ? [-1] : $subordinateIds;

        // BUGFIX: dulu pakai 'clock_in' (tipe TIME) — salah!
        // Sekarang pakai 'date' (tipe DATE) dan satu query GROUP BY
        $hadir = Presence::query()
            ->selectRaw("date::text as pdate, COUNT(DISTINCT user_id) as total")
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy('pdate')
            ->when(!$isGlobalAdmin, fn($q) => $q->whereIn('user_id', $scopeIds))
            ->pluck('total', 'pdate');

        $terlambat = Presence::query()
            ->selectRaw("date::text as pdate, COUNT(DISTINCT user_id) as total")
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('late_minutes', '>', 0)
            ->groupBy('pdate')
            ->when(!$isGlobalAdmin, fn($q) => $q->whereIn('user_id', $scopeIds))
            ->pluck('total', 'pdate');

        $labels  = [];
        $hadir_d = [];
        $late_d  = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $key       = $current->format('Y-m-d');
            $labels[]  = $current->format('d M');
            $hadir_d[] = (int)($hadir[$key] ?? 0);
            $late_d[]  = (int)($terlambat[$key] ?? 0);
            $current->addDay();
        }

        return [
            'datasets' => [
                [
                    'label'                => 'Hadir',
                    'data'                 => $hadir_d,
                    'borderColor'          => '#10b981',
                    'backgroundColor'      => 'rgba(16, 185, 129, 0.08)',
                    'fill'                 => true,
                    'tension'              => 0.4,
                    'pointBackgroundColor' => '#10b981',
                    'pointRadius'          => 3,
                ],
                [
                    'label'                => 'Terlambat',
                    'data'                 => $late_d,
                    'borderColor'          => '#f59e0b',
                    'backgroundColor'      => 'rgba(245, 158, 11, 0.0)',
                    'fill'                 => false,
                    'tension'              => 0.4,
                    'pointBackgroundColor' => '#f59e0b',
                    'pointRadius'          => 3,
                    'borderDash'           => [4, 4],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
