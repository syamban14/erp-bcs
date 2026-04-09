<?php

namespace App\Filament\Widgets;

use App\Models\Leave;
use Filament\Widgets\ChartWidget;

class LeaveRequestsChart extends ChartWidget
{
    use \App\Filament\Concerns\ResolvesDashboardDates;

    protected ?string $heading = 'Pengajuan Cuti';
    protected static ?int $sort = 3;
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

        $leavesQuery = Leave::query()
            ->whereBetween('start_date', [
                $startDate->startOfDay(),
                $endDate->endOfDay()
            ]);
            
        if (!$isGlobalAdmin) {
            $leavesQuery->whereIn('user_id', $scopeIds);
        }
            
        $leaves = $leavesQuery->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get();
        
        $labels = [];
        $data = [];
        $colors = [
            '#3b82f6', // blue
            '#ef4444', // red
            '#f59e0b', // yellow
            '#8b5cf6', // purple
            '#10b981', // green
        ];
        
        foreach ($leaves as $index => $leave) {
            $labels[] = $this->translateLeaveType($leave->type);
            $data[] = $leave->count;
        }
        
        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
    
    private function translateLeaveType($type): string
    {
        $translations = [
            'annual' => 'Cuti Tahunan',
            'sick' => 'Sakit',
            'permission' => 'Izin',
            'unpaid' => 'Cuti Tidak Dibayar',
            'other' => 'Lainnya',
        ];
        
        return $translations[$type] ?? ucfirst($type);
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
