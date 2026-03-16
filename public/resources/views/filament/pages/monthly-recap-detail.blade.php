@php
    use Carbon\Carbon;
    use App\Models\Presence;
    use App\Models\ShiftSchedule;

    $currentMonth = $month ?? now()->month;
    $currentYear = $year ?? now()->year;
    
    $endDate = Carbon::create($currentYear, $currentMonth, 15);
    $startDate = $endDate->copy()->subMonth()->addDay();
    
    // Fetch logs efficiently
    $presences = Presence::where('user_id', $record->id)
        ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
        ->get()
        ->keyBy(fn($item) => $item->date->format('Y-m-d'));
        
    $schedules = ShiftSchedule::where('user_id', $record->id)
        ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]) // Assuming date col
        ->get()
        ->keyBy(fn($item) => $item->date instanceof Carbon ? $item->date->format('Y-m-d') : $item->date);
@endphp

<div class="overflow-x-auto">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h3 class="font-bold text-lg">{{ $record->name }}</h3>
            <p class="text-sm text-gray-500">{{ $record->email }}</p>
        </div>
        <div class="text-right">
            <span class="px-2 py-1 text-xs font-semibold rounded bg-gray-100">
                {{ $startDate->format('d M') }} - {{ $endDate->format('d M Y') }}
            </span>
        </div>
    </div>

    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400 border-collapse">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th class="px-3 py-2 border dark:border-gray-600">Tanggal</th>
                <th class="px-3 py-2 border dark:border-gray-600">Jadwal</th>
                <th class="px-3 py-2 border dark:border-gray-600">Masuk</th>
                <th class="px-3 py-2 border dark:border-gray-600">Pulang</th>
                <th class="px-3 py-2 border dark:border-gray-600">Telat</th>
                <th class="px-3 py-2 border dark:border-gray-600">Lembur</th>
                <th class="px-3 py-2 border dark:border-gray-600">Status</th>
            </tr>
        </thead>
        <tbody>
            @for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay())
                @php
                    $dateStr = $date->format('Y-m-d');
                    $presence = $presences[$dateStr] ?? null;
                    $schedule = $schedules[$dateStr] ?? null;
                    $isWeekend = $date->isWeekend(); // Or strictly use schedule
                    
                    // Simple logic for row color
                    $bgClass = '';
                    if (!$presence && !$isWeekend) $bgClass = 'bg-red-50 dark:bg-red-900/20';
                    if ($presence && $presence->is_late) $bgClass = 'bg-yellow-50 dark:bg-yellow-900/20';
                @endphp
                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 {{ $bgClass }}">
                    <td class="px-3 py-2 border dark:border-gray-600 font-medium">
                        {{ $date->format('d M, D') }}
                    </td>
                    <td class="px-3 py-2 border dark:border-gray-600">
                         @if($schedule)
                            <span class="text-gray-500 text-xs">{{ $schedule->shift_code ?? '-' }}</span>
                         @else
                            -
                         @endif
                    </td>
                    <td class="px-3 py-2 border dark:border-gray-600">
                        {{ $presence ? $presence->clock_in : '-' }}
                    </td>
                    <td class="px-3 py-2 border dark:border-gray-600">
                        {{ $presence ? $presence->clock_out : '-' }}
                    </td>
                    <td class="px-3 py-2 border dark:border-gray-600 {{ ($presence && $presence->late_minutes > 0) ? 'text-red-600 font-bold' : '' }}">
                        {{ $presence && $presence->late_minutes > 0 ? $presence->late_minutes . 'm' : '-' }}
                    </td>
                    <td class="px-3 py-2 border dark:border-gray-600 text-blue-600">
                        {{ $presence && $presence->overtime_minutes > 0 ? floor($presence->overtime_minutes/60) . 'j ' . ($presence->overtime_minutes%60) . 'm' : '-' }}
                    </td>
                     <td class="px-3 py-2 border dark:border-gray-600">
                        @if($presence)
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $presence->late_minutes > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                {{ $presence->status ?? 'Hadir' }}
                            </span>
                        @else
                             @if($isWeekend)
                                <span class="text-gray-400 text-xs">Libur</span>
                             @else
                                <span class="text-red-500 text-xs font-bold">Alpha</span>
                             @endif
                        @endif
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>
    <div class="mt-4 text-xs text-gray-500 text-right">
        Generated at {{ now()->format('d M Y H:i') }}
    </div>
</div>
