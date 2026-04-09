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
        
        $data = collect();
        
        // Loop through each day in the range
        // Note: For long ranges, this might be heavy. 
        // Ideally we would group by date in SQL, but for simplicity and consistency with previous logic:
        $current = $startDate->copy();
        
        $user = auth()->user();
        $isGlobalAdmin = $user ? $user->isGlobalAdmin() : false;
        $subordinateIds = $isGlobalAdmin ? [] : ($user ? $user->getSubordinateUserIds() : []);
        $scopeIds = empty($subordinateIds) ? [-1] : $subordinateIds;

        while ($current <= $endDate) {
            $query = Presence::query()
                ->whereBetween('clock_in', [
                    $current->copy()->startOfDay(),
                    $current->copy()->endOfDay()
                ])
                ->distinct('user_id');
                
            if (!$isGlobalAdmin) {
                $query->whereIn('user_id', $scopeIds);
            }
                
            $count = $query->count('user_id');
            
            $data->push([
                'date' => $current->format('d M'),
                'count' => $count,
            ]);
            
            $current->addDay();
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Kehadiran',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
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
