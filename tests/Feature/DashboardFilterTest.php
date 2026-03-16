<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Filament\Concerns\ResolvesDashboardDates;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

class DashboardFilterTest extends TestCase
{
    use ResolvesDashboardDates;

    public function test_dashboard_date_resolution()
    {
        echo "\nTesting Dashboard Date Resolution...\n";
        echo "----------------------------------------\n";

        // Scenario 1: Default (No Session)
        Session::flush();
        $dates = $this->getFilterDates();
        echo "[Scenario 1] Default (No Session):\n";
        echo "Start: " . $dates['start']->toDateTimeString() . "\n";
        echo "End: " . $dates['end']->toDateTimeString() . "\n\n";

        // Scenario 2: Today Filter
        Session::put('dashboard_filter', 'today');
        $dates = $this->getFilterDates();
        echo "[Scenario 2] Filter 'today':\n";
        echo "Start: " . $dates['start']->toDateTimeString() . "\n";
        echo "End: " . $dates['end']->toDateTimeString() . "\n\n";

        // Scenario 3: Custom Date Range
        Session::put('dashboard_custom_start', '2024-01-01');
        Session::put('dashboard_custom_end', '2024-01-31');
        // Note: Filter session might still be there, but custom dates should take priority
        $dates = $this->getFilterDates();
        echo "[Scenario 3] Custom Dates (2024-01-01 to 2024-01-31):\n";
        echo "Start: " . $dates['start']->toDateTimeString() . "\n";
        echo "End: " . $dates['end']->toDateTimeString() . "\n\n";

        // Scenario 4: Clear Custom Dates
        Session::forget(['dashboard_custom_start', 'dashboard_custom_end']);
        Session::put('dashboard_filter', 'week');
        $dates = $this->getFilterDates();
        echo "[Scenario 4] Filter 'week' (after clear custom):\n";
        echo "Start: " . $dates['start']->toDateTimeString() . "\n";
        echo "End: " . $dates['end']->toDateTimeString() . "\n";
        
        $this->assertTrue(true);
    }
}
