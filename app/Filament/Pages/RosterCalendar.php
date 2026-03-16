<?php

namespace App\Filament\Pages;

use App\Models\MPresensi;
use App\Models\ShiftSchedule;
use App\Models\ShiftCode;
use Filament\Pages\Page;
use Carbon\Carbon;

class RosterCalendar extends Page
{
    protected static \BackedEnum | string | null $navigationIcon = 'heroicon-o-calendar';
    
    protected static ?string $navigationLabel = 'Roster Calendar';
    
    protected string $view = 'filament.pages.roster-calendar';
    
    public static function getNavigationGroup(): ?string
    {
        return 'Shift Management';
    }
    
    public $selectedMonth;
    public $selectedYear;
    public $currentWeek = 1;
    public $searchTerm = '';
    
    // Data yang akan di-pass ke view
    public $employeesData = [];
    public $datesData = [];
    public $weekInfo = '';
    public $totalWeeks = 1;
    
    public function mount()
    {
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
        $this->currentWeek = 1;
        $this->loadData();
    }
    
    public function loadData()
    {
        // Generate all dates (16 this month - 15 next month)
        $allDates = $this->generateAllDates();
        
        // Get 7 days for current week
        $startIndex = ($this->currentWeek - 1) * 7;
        $weekDates = array_slice($allDates, $startIndex, 7);
        
        // Convert to simple array for view
        $this->datesData = array_map(function($date) {
            return [
                'full' => $date->format('Y-m-d'),
                'day' => $date->format('d'),
                'dayName' => $date->format('D'),
            ];
        }, $weekDates);
        
        // Get date strings for query
        $dateStrings = array_map(fn($d) => $d->format('Y-m-d'), $allDates);
        
        // Load schedules
        $schedules = ShiftSchedule::whereIn('date', $dateStrings)->get();
        
        // Get user IDs with schedules
        $userIds = $schedules->pluck('user_id')->unique()->toArray();
        
        // Load employees
        $query = MPresensi::whereIn('id', $userIds)->orderBy('name');
        
        if ($this->searchTerm) {
            $query->where('name', 'LIKE', "%{$this->searchTerm}%");
        }
        
        $employees = $query->get();
        
        // Build employee data with shifts
        $this->employeesData = [];
        foreach ($employees as $employee) {
            $shifts = [];
            foreach ($this->datesData as $dateInfo) {
                // Find schedule - handle both Carbon and string dates
                $schedule = $schedules->first(function($s) use ($employee, $dateInfo) {
                    if ($s->user_id != $employee->id) {
                        return false;
                    }
                    
                    $scheduleDate = $s->date instanceof Carbon ? $s->date->format('Y-m-d') : $s->date;
                    return $scheduleDate == $dateInfo['full'];
                });
                
                if ($schedule) {
                    $shiftCode = ShiftCode::where('code', $schedule->shift_code)->first();
                    if ($shiftCode) {
                        $shifts[$dateInfo['full']] = [
                            'code' => $schedule->shift_code,
                            'name' => $shiftCode->name,
                            'time_in' => substr($shiftCode->time_in, 0, 5),
                            'time_out' => substr($shiftCode->time_out, 0, 5),
                            'is_off' => $shiftCode->is_off,
                            'color' => $this->getShiftColor($schedule->shift_code),
                        ];
                    } else {
                        $shifts[$dateInfo['full']] = null;
                    }
                } else {
                    $shifts[$dateInfo['full']] = null;
                }
            }
            
            $this->employeesData[] = [
                'name' => $employee->name,
                'shifts' => $shifts,
            ];
        }
        
        // Calculate week info
        $this->totalWeeks = ceil(count($allDates) / 7);
        if (!empty($weekDates)) {
            $start = $weekDates[0]->format('d M');
            $end = $weekDates[count($weekDates) - 1]->format('d M');
            $this->weekInfo = "{$start} - {$end}";
        }
    }
    
    private function generateAllDates()
    {
        $dates = [];
        
        // 16-31 current month
        $start = Carbon::create($this->selectedYear, $this->selectedMonth, 16);
        $endOfMonth = $start->copy()->endOfMonth();
        
        for ($date = $start->copy(); $date->lte($endOfMonth); $date->addDay()) {
            $dates[] = $date->copy();
        }
        
        // 01-15 next month
        $nextMonth = $start->copy()->addMonth()->startOfMonth();
        for ($i = 1; $i <= 15; $i++) {
            $dates[] = $nextMonth->copy()->day($i);
        }
        
        return $dates;
    }
    
    private function getShiftColor($code)
    {
        if (!$code) return ['bg' => 'transparent', 'border' => 'transparent', 'text' => 'inherit'];
        
        $firstChar = substr($code, 0, 1);
        
        return match($firstChar) {
            'P' => ['bg' => '#fef3c7', 'border' => '#fde047', 'text' => '#854d0e'],
            'S' => ['bg' => '#dbeafe', 'border' => '#93c5fd', 'text' => '#1e40af'],
            'M' => ['bg' => '#f3e8ff', 'border' => '#d8b4fe', 'text' => '#6b21a8'],
            'X', 'O' => ['bg' => '#f3f4f6', 'border' => '#d1d5db', 'text' => '#374151'],
            default => ['bg' => '#dcfce7', 'border' => '#86efac', 'text' => '#166534'],
        };
    }
    
    public function previousWeek()
    {
        if ($this->currentWeek > 1) {
            $this->currentWeek--;
            $this->loadData();
        }
    }
    
    public function nextWeek()
    {
        if ($this->currentWeek < $this->totalWeeks) {
            $this->currentWeek++;
            $this->loadData();
        }
    }
    
    public function previousMonth()
    {
        if ($this->selectedMonth == 1) {
            $this->selectedMonth = 12;
            $this->selectedYear--;
        } else {
            $this->selectedMonth--;
        }
        $this->currentWeek = 1;
        $this->loadData();
    }
    
    public function nextMonth()
    {
        if ($this->selectedMonth == 12) {
            $this->selectedMonth = 1;
            $this->selectedYear++;
        } else {
            $this->selectedMonth++;
        }
        $this->currentWeek = 1;
        $this->loadData();
    }
    
    public function getMonthName()
    {
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $monthNames[$this->selectedMonth] ?? '';
    }
}
