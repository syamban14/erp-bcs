<?php

namespace App\Filament\Pages;

use App\Models\MPresensi;
use App\Services\RecapService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class MonthlyRecap extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string | \UnitEnum | null $navigationGroup = 'Reports';
    protected static ?string $title = 'Monthly Recap (16-15)';
    protected string $view = 'filament.pages.monthly-recap';

    public $month;
    public $year;
    
    // Cache for recap data to avoid repeated queries per cell
    protected array $recapCache = [];
    protected RecapService $recapService;

    public function mount(): void
    {
        // Default to current period
        // If today is Jan 20, 2026. Period is Jan 2026 (Dec 16 - Jan 15). 
        // Wait, if today is Jan 20, the Jan period (ending Jan 15) is past.
        // Let's default to CURRENT month index.
        $this->month = now()->month;
        $this->year = now()->year;
        
        $this->form->fill([
            'month' => $this->month,
            'year' => $this->year,
        ]);
    }
    
    public function boot(RecapService $recapService) {
        $this->recapService = $recapService;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('month')
                    ->options([
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                    ])
                    ->required()
                    ->live(), // Auto reload table?
                Select::make('year')
                    ->options(array_combine(range(now()->year - 1, now()->year + 1), range(now()->year - 1, now()->year + 1)))
                    ->required()
                    ->live(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MPresensi::query()->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('total_hari_kerja')
                    ->label('Hari Kerja')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['total_hari_kerja']),

                TextColumn::make('total_kehadiran')
                    ->label('Hadir')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['total_kehadiran']),

                TextColumn::make('durasi_kehadiran')
                    ->label('Durasi (Jam)')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['durasi_kehadiran']),

                TextColumn::make('cuti_tahunan')
                    ->label('Cuti Thn')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['cuti_tahunan'])
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                    
                TextColumn::make('cuti_special')
                    ->label('Cuti Spesial')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['cuti_special'])
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray'),
                    
                TextColumn::make('cuti_sakit')
                    ->label('Sakit')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['cuti_sakit'])
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('izin_jam')
                    ->label('Izin (Kali)')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['izin_jam']),
                    
                TextColumn::make('tugas_luar')
                    ->label('Tugas Luar')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['tugas_luar']),

                TextColumn::make('alpa')
                    ->label('Alpa')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['alpa'])
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->weight('bold'),

                TextColumn::make('lembur_jam')
                    ->label('Lembur (Jam)')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['lembur_jam']),
                    
                TextColumn::make('terlambat_jam')
                    ->label('Telat (Jam)')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['terlambat_jam'])
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                    
                TextColumn::make('pulang_awal_jam')
                    ->label('Plg Awal (Jam)')
                    ->alignCenter()
                    ->state(fn ($record) => $this->getRecap($record)['pulang_awal_jam'])
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
            ])
            ->striped();
    }
    
    protected function getRecap($record)
    {
        if (isset($this->recapCache[$record->id])) {
            return $this->recapCache[$record->id];
        }
        
        // Calculate period: 16th Prev Month to 15th Current Month
        // If year=2026, month=2 (Feb). 
        // Start: Jan 16, 2026
        // End: Feb 15, 2026
        
        $currentMonth = $this->month ?? now()->month;
        $currentYear = $this->year ?? now()->year;
        
        $endDate = \Carbon\Carbon::create($currentYear, $currentMonth, 15);
        $startDate = $endDate->copy()->subMonth()->addDay(); // e.g. Jan 16
        
        // Initialize service manually if injection failed (boot not always called in same lifecycle?)
        // Safer to resolve it here
        $service = app(RecapService::class);
        
        $data = $service->getRecapData($record, $startDate, $endDate);
        
        $this->recapCache[$record->id] = $data;
        
        return $data;
    }
}
