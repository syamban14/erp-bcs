<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;

class Dashboard extends BaseDashboard
{
    public function getHeaderActions(): array
    {
        $currentFilter = session('dashboard_filter', 'cutoff');
        $hasCustomDates = session('dashboard_custom_start') && session('dashboard_custom_end');
        
        return [
            // Custom Date Range Action (Primary Filter)
            Action::make('apply_custom_dates')
                ->label(fn () => $hasCustomDates 
                    ? '📅 Periode: ' . \Carbon\Carbon::parse(session('dashboard_custom_start'))->format('d M') . ' - ' . \Carbon\Carbon::parse(session('dashboard_custom_end'))->format('d M')
                    : '📅 Filter Tanggal'
                )
                ->icon('heroicon-o-calendar')
                ->color($hasCustomDates ? 'success' : 'primary')
                ->form([
                    DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(session('dashboard_custom_start', now()->startOfMonth()->toDateString()))
                        ->maxDate(now())
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('Tanggal Selesai')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(session('dashboard_custom_end', now()->endOfMonth()->toDateString()))
                        ->maxDate(now())
                        ->afterOrEqual('start_date')
                        ->required(),
                ])
                ->action(function (array $data) {
                    \Illuminate\Support\Facades\Log::info('Dashboard Action: Custom Filter Applied');
                    session([
                        'dashboard_custom_start' => $data['start_date'],
                        'dashboard_custom_end' => $data['end_date'],
                    ]);
                    // Clear preset filter to ensure custom dates take precedence
                    session()->forget('dashboard_filter'); 
                    session()->save();
                    return redirect()->to('/admin');
                }),
                
            // Reset Filter (Clear Custom Dates)
            Action::make('clear_custom_dates')
                ->label('Reset Filter')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $hasCustomDates)
                ->action(function () {
                    \Illuminate\Support\Facades\Log::info('Dashboard Action: Filter Reset');
                    session()->forget(['dashboard_custom_start', 'dashboard_custom_end']);
                    session(['dashboard_filter' => 'cutoff']); // Revert to default
                    session()->save();
                    return redirect()->to('/admin');
                }),
        ];
    }
}
