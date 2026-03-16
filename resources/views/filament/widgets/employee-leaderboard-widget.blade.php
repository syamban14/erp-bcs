<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-x-3 mb-4">
            <h2 class="text-lg font-bold">Leaderboard Karyawan</h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Paling Rajin -->
            <div>
                <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2 whitespace-nowrap overflow-hidden text-ellipsis">🏆 Top 5 Rajin (Tepat Waktu)</h3>
                <ul class="space-y-2">
                    @foreach($this->getMostDiligentEmployees() as $employee)
                    <li class="flex flex-col text-sm bg-gray-50 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ $employee->name }}</span>
                            <span class="font-bold text-success-600 dark:text-success-400 bg-success-50 dark:bg-success-900/30 px-2 py-0.5 rounded text-xs">{{ $employee->on_time_count }} x Tepat</span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $employee->dept }}</span>
                    </li>
                    @endforeach
                    @if($this->getMostDiligentEmployees()->isEmpty())
                    <li class="p-2 text-sm text-gray-500 text-center italic border border-dashed rounded dark:border-gray-700">Belum ada data presensi.</li>
                    @endif
                </ul>
            </div>
            
            <!-- Tukang Telat -->
            <div>
                <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2 whitespace-nowrap overflow-hidden text-ellipsis">⏰ Top 5 Sering Terlambat</h3>
                <ul class="space-y-2">
                    @foreach($this->getMostLateEmployees() as $employee)
                    <li class="flex flex-col text-sm bg-gray-50 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ $employee->name }}</span>
                            <span class="font-bold text-danger-600 dark:text-danger-400 bg-danger-50 dark:bg-danger-900/30 px-2 py-0.5 rounded text-xs">{{ $employee->total_late_minutes }} Menit</span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $employee->dept }}</span>
                    </li>
                    @endforeach
                    @if($this->getMostLateEmployees()->isEmpty())
                    <li class="p-2 text-sm text-gray-500 text-center italic border border-dashed rounded dark:border-gray-700">Belum ada data keterlambatan. Hebat!</li>
                    @endif
                </ul>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
