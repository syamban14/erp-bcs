<?php

namespace App\Filament\Concerns;

use Carbon\Carbon;

trait ResolvesDashboardDates
{
    /**
     * Resolve start and end dates based on dashboard filters from session.
     * Priority: Custom dates > Preset filter > Default (cutoff)
     */
    protected function getFilterDates(): array
    {
        // PRIORITY 1: Check for custom date range
        $customStart = session('dashboard_custom_start');
        $customEnd = session('dashboard_custom_end');
        
        if ($customStart && $customEnd) {
            return [
                'start' => Carbon::parse($customStart)->startOfDay(),
                'end' => Carbon::parse($customEnd)->endOfDay(),
            ];
        }
        
        // PRIORITY 2: Use preset filter from session
        $filter = session('dashboard_filter', 'cutoff');
        
        $now = now();
        
        // Default (fallback)
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();
        
        switch ($filter) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
                
            case 'week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                break;
                
            case 'month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
                
            case 'cutoff':
            default:
                // Logic: Periode Gaji (16 Prev Month - 15 Current Month)
                $currentMonth = $now->month;
                $currentYear = $now->year;
                
                $end = Carbon::create($currentYear, $currentMonth, 15)->endOfDay();
                $start = $end->copy()->subMonth()->addDay()->startOfDay();
                break;
        }
        
        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}
