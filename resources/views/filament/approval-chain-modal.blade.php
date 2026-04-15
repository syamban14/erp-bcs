<div class="w-full">

    {{-- ─── Timeline ────────────────────────────────────────────────────── --}}
    @if($flows->count() > 0)
        <div class="flow-root">
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach($flows as $i => $flow)
                    @php
                        $colors = [
                            'approved' => ['dot' => 'bg-emerald-500', 'badge' => 'text-emerald-700 bg-emerald-50 ring-emerald-600/20 dark:text-emerald-400 dark:bg-emerald-400/10 dark:ring-emerald-400/20'],
                            'rejected' => ['dot' => 'bg-red-500',     'badge' => 'text-red-700 bg-red-50 ring-red-600/20 dark:text-red-400 dark:bg-red-400/10 dark:ring-red-400/20'],
                            'pending'  => ['dot' => 'bg-amber-400',   'badge' => 'text-amber-700 bg-amber-50 ring-amber-600/20 dark:text-amber-400 dark:bg-amber-400/10 dark:ring-amber-400/20'],
                        ];
                        $status   = $flow->status ?? 'pending';
                        $color    = $colors[$status] ?? $colors['pending'];
                        $levelMap = ['1' => 'PIC / Atasan Langsung', '2' => 'Manager / Kepala Unit', '3' => 'HR & General Affairs', '4' => 'Direktur / Manajemen'];
                        $levelLabel = $levelMap[(string)($flow->level ?? '')] ?? ($flow->level_label ?? ("Level {$flow->level}"));
                    @endphp

                    <li class="flex items-start gap-4 py-4 px-1">
                        {{-- Step indicator --}}
                        <div class="flex-shrink-0 flex flex-col items-center gap-1 w-8 mt-0.5">
                            <div class="w-8 h-8 rounded-full {{ $color['dot'] }} flex items-center justify-center shadow-sm">
                                @if($status === 'approved')
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @elseif($status === 'rejected')
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/><circle cx="12" cy="12" r="10" stroke-linecap="round"/></svg>
                                @endif
                            </div>
                            {{-- Vertical connector --}}
                            @if(!$loop->last)
                                <div class="w-px h-4 bg-gray-200 dark:bg-white/10"></div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0 pb-0.5">
                            {{-- Header row: level label + status badge --}}
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                    Level {{ $flow->level ?? ($i+1) }} — {{ $levelLabel }}
                                </p>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $color['badge'] }}">
                                    {{ strtoupper($status) }}
                                </span>
                            </div>

                            {{-- Approver name --}}
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                @if($status === 'approved')
                                    Disetujui oleh <span class="text-emerald-600 dark:text-emerald-400">{{ $flow->approver?->name ?? 'Sistem' }}</span>
                                @elseif($status === 'rejected')
                                    Ditolak oleh <span class="text-red-600 dark:text-red-400">{{ $flow->approver?->name ?? 'Sistem' }}</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400 italic">Menunggu persetujuan…</span>
                                @endif
                            </p>

                            {{-- Timestamp --}}
                            @if($flow->approved_at)
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0h18"/></svg>
                                    {{ $flow->approved_at->format('d M Y, H:i') }}
                                </p>
                            @endif

                            {{-- Notes --}}
                            @if($flow->notes)
                                <div class="mt-2 rounded-md bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 px-3 py-2">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 italic">"{{ $flow->notes }}"</p>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        {{-- Empty state: teks saja, tanpa ikon --}}
        <div class="py-8 text-center">
            <p class="text-sm text-gray-400 dark:text-gray-500">Belum ada riwayat persetujuan untuk pengajuan ini.</p>
        </div>
    @endif

    {{-- ─── Footer: progress & status keseluruhan ─────────────────────── --}}
    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/10 flex items-center justify-between gap-3">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Progress:
            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $current }} / {{ $max }}</span>
            level diproses
        </div>

        @php
            $overallStatus  = $record->status ?? 'pending';
            $overallColors  = ['approved' => 'text-emerald-700 bg-emerald-50 ring-emerald-600/20 dark:text-emerald-400 dark:bg-emerald-400/10', 'rejected' => 'text-red-700 bg-red-50 ring-red-600/20 dark:text-red-400 dark:bg-red-400/10', 'pending' => 'text-amber-700 bg-amber-50 ring-amber-600/20 dark:text-amber-400 dark:bg-amber-400/10'];
            $overallClass   = $overallColors[$overallStatus] ?? $overallColors['pending'];
        @endphp
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $overallClass }}">
            {{ strtoupper($overallStatus) }}
        </span>
    </div>
</div>
