<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Period Info --}}
        <div class="bg-primary-50 dark:bg-primary-900/20 p-4 rounded-lg">
            <div class="flex items-center gap-2">
                <x-heroicon-o-calendar class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                <span class="font-semibold text-primary-900 dark:text-primary-100">
                    Periode: {{ $this->getPeriodLabel() }}
                </span>
            </div>
        </div>
        
        {{-- Tabs --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-8">
                <button 
                    wire:click="$set('activeTab', 'late')"
                    class="@if($activeTab === 'late') border-primary-500 text-primary-600 dark:text-primary-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                >
                    🏆 Divisi Minim Terlambat
                </button>
                <button 
                    wire:click="$set('activeTab', 'absent')"
                    class="@if($activeTab === 'absent') border-danger-500 text-danger-600 dark:text-danger-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                >
                    ⚠️ Divisi Paling Sering Alpa
                </button>
            </nav>
        </div>
        
        {{-- Late Rankings Table --}}
        @if($activeTab === 'late')
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Peringkat</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Divisi</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Jumlah Karyawan</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Skor Terlambat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                        @foreach($this->getAllLateRankings() as $index => $division)
                        <tr class="@if($index < 3) bg-yellow-50 dark:bg-yellow-900/10 @endif">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm">
                                <div class="flex items-center gap-2">
                                    @if($index === 0)
                                        <span class="text-2xl">🥇</span>
                                    @elseif($index === 1)
                                        <span class="text-2xl">🥈</span>
                                    @elseif($index === 2)
                                        <span class="text-2xl">🥉</span>
                                    @else
                                        <span class="font-bold text-gray-500 dark:text-gray-400">{{ $index + 1 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $division->div_name }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $division->employee_count }} karyawan
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium @if($division->late_count > 10) bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200 @else bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200 @endif">
                                    {{ $division->late_count }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        
        {{-- Absent Rankings Table --}}
        @if($activeTab === 'absent')
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Peringkat</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Divisi</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Jumlah Karyawan</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Skor Alpha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                        @foreach($this->getAllAbsentRankings() as $index => $division)
                        <tr class="@if($index < 3) bg-red-50 dark:bg-red-900/10 @endif">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm">
                                <div class="flex items-center gap-2">
                                    @if($index === 0)
                                        <span class="text-2xl">🥇</span>
                                    @elseif($index === 1)
                                        <span class="text-2xl">🥈</span>
                                    @elseif($index === 2)
                                        <span class="text-2xl">🥉</span>
                                    @else
                                        <span class="font-bold text-gray-500 dark:text-gray-400">{{ $index + 1 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $division->div_name }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $division->employee_count }} karyawan
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">
                                    {{ $division->alpha_count }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
