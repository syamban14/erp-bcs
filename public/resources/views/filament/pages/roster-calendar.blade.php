<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Controls --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex flex-col gap-4">
                {{-- Month Navigation --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <x-filament::button 
                            wire:click="previousMonth" 
                            size="sm" 
                            color="gray"
                            icon="heroicon-m-chevron-double-left">
                            Prev Month
                        </x-filament::button>
                        
                        <div class="text-xl font-bold text-gray-900 dark:text-white px-4 min-w-[200px] text-center">
                            {{ $this->getMonthName() }} {{ $selectedYear }}
                        </div>
                        
                        <x-filament::button 
                            wire:click="nextMonth" 
                            size="sm" 
                            color="gray"
                            icon-position="after"
                            icon="heroicon-m-chevron-double-right">
                            Next Month
                        </x-filament::button>
                    </div>
                    
                    {{-- Search --}}
                    <div class="w-full lg:w-auto">
                        <x-filament::input.wrapper class="min-w-[300px]">
                            <x-filament::input
                                type="search"
                                wire:model.live.debounce.300ms="searchTerm"
                                placeholder="🔍 Cari nama karyawan..."
                                class="w-full"
                            />
                        </x-filament::input.wrapper>
                    </div>
                </div>
                
                {{-- Week Navigation --}}
                <div class="flex items-center justify-center gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button 
                        wire:click="previousWeek" 
                        size="sm" 
                        color="primary"
                        :disabled="$currentWeek <= 1"
                        icon="heroicon-m-chevron-left">
                        Prev Week
                    </x-filament::button>
                    
                    <div class="flex items-center gap-2 px-6">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Minggu {{ $currentWeek }} dari {{ $totalWeeks }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            ({{ $weekInfo }})
                        </span>
                    </div>
                    
                    <x-filament::button 
                        wire:click="nextWeek" 
                        size="sm" 
                        color="primary"
                        :disabled="$currentWeek >= $totalWeeks"
                        icon-position="after"
                        icon="heroicon-m-chevron-right">
                        Next Week
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Color Legend --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex flex-wrap items-center gap-6">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Keterangan:</span>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-yellow-100 border-2 border-yellow-400 rounded-md"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Pagi (P)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-100 border-2 border-blue-400 rounded-md"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Siang (S)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-purple-100 border-2 border-purple-400 rounded-md"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Malam (M)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gray-200 border-2 border-gray-400 rounded-md"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Off/Libur</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-green-100 border-2 border-green-400 rounded-md"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Lainnya</span>
                </div>
            </div>
        </div>

        {{-- Calendar Grid --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse" style="table-layout: fixed;">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-600">
                            <th class="sticky left-0 z-20 bg-gray-100 dark:bg-gray-700 border-r-2 border-gray-300 dark:border-gray-600 p-5 text-left" style="width: 250px;">
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Karyawan</span>
                            </th>
                            @foreach($datesData as $dateInfo)
                                <th class="border-l border-gray-200 dark:border-gray-600 p-4 text-center bg-gray-50 dark:bg-gray-700/50" style="width: 140px;">
                                    <div class="font-bold text-gray-900 dark:text-white" style="font-size: 1.5rem;">{{ $dateInfo['day'] }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase mt-1">{{ $dateInfo['dayName'] }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employeesData as $employee)
                            <tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="sticky left-0 z-10 bg-white dark:bg-gray-800 border-r-2 border-gray-300 dark:border-gray-600 p-5">
                                    <div class="font-semibold text-gray-900 dark:text-white text-base" title="{{ $employee['name'] }}">
                                        {{ \Illuminate\Support\Str::limit($employee['name'], 28) }}
                                    </div>
                                </td>
                                @foreach($datesData as $dateInfo)
                                    @php
                                        $shift = $employee['shifts'][$dateInfo['full']] ?? null;
                                    @endphp
                                    <td class="border-l border-gray-200 dark:border-gray-600 p-2.5">
                                        @if($shift)
                                            <div class="rounded-lg border-2 p-4 text-center transition-all hover:scale-105 cursor-pointer shadow-sm {{ $shift['color'] }}" style="min-height: 90px;">
                                                <div class="font-bold text-gray-900 mb-2" style="font-size: 1.125rem;">{{ $shift['code'] }}</div>
                                                @if(!$shift['is_off'])
                                                    <div class="text-sm text-gray-600 font-medium" style="line-height: 1.6;">
                                                        {{ $shift['time_in'] }}<br>{{ $shift['time_out'] }}
                                                    </div>
                                                @else
                                                    <div class="text-sm text-gray-500 mt-1">Off</div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-gray-300 dark:text-gray-600 text-center" style="padding: 35px 0; font-size: 1.25rem;">-</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($datesData) + 1 }}" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <x-heroicon-o-user-group class="w-16 h-16 text-gray-300 dark:text-gray-600" />
                                        <p class="text-gray-500 dark:text-gray-400 text-lg">Tidak ada data karyawan</p>
                                        <p class="text-gray-400 dark:text-gray-500 text-sm">Silakan import data roster terlebih dahulu</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Info Footer --}}
        <div class="text-xs text-gray-500 dark:text-gray-400 text-center py-2">
            {{ count($employeesData) }} karyawan • Minggu {{ $currentWeek }} dari {{ $totalWeeks }}
        </div>
    </div>
</x-filament-panels::page>
