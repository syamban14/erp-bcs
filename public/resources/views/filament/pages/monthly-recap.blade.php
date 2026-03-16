<x-filament-panels::page>
    <!-- Stats Widget -->
    @livewire(\App\Filament\Pages\Widgets\RecapStatsOverview::class, [
        'month' => $month,
        'year' => $year,
        'unitId' => $unit,
    ], key('stats-overview'))

    <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
        {{ $this->form }}
    </div>

    {{ $this->table }}
</x-filament-panels::page>
