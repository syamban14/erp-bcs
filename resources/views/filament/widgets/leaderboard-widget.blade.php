<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-x-3 mb-4">
            <h2 class="text-lg font-bold">Leaderboard Divisi</h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Divisi Paling Rajin (Telat Terkecil) -->
            <div>
                <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Paling Rajin (Telat Paling Sedikit)</h3>
                <ul class="space-y-2">
                    @foreach($this->getLeastLateDivisions() as $division)
                    <li class="flex items-center justify-between text-sm bg-gray-50 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="font-medium">{{ $division->div_name }}</span>
                        <div class="flex items-center gap-1">
                            <span class="font-bold text-success-600 dark:text-success-400">{{ $division->late_count }}</span>
                            <span class="text-xs text-gray-500">Telat</span>
                        </div>
                    </li>
                    @endforeach
                    @if($this->getLeastLateDivisions()->isEmpty())
                    <li class="p-2 text-sm text-gray-500">Belum ada data divisi.</li>
                    @endif
                </ul>
            </div>
            
            <!-- Divisi Paling Banyak Alpha -->
            <div>
                <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Paling Banyak Alpha</h3>
                <ul class="space-y-2">
                    @foreach($this->getMostAbsentDivisions() as $division)
                    <li class="flex items-center justify-between text-sm bg-gray-50 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="font-medium">{{ $division->div_name }}</span>
                        <div class="flex items-center gap-1">
                            <span class="font-bold text-danger-600 dark:text-danger-400">{{ $division->alpha_count }}</span>
                            <span class="text-xs text-gray-500">Alpha</span>
                        </div>
                    </li>
                    @endforeach
                    @if($this->getMostAbsentDivisions()->isEmpty())
                    <li class="p-2 text-sm text-gray-500">Tidak ada data alpha. Semua hadir.</li>
                    @endif
                </ul>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
