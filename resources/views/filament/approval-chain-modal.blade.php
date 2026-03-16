<div class="space-y-4">
    @if($flows->count() > 0)
        <ul class="space-y-2 relative border-l border-gray-200 dark:border-gray-700 ml-3">
            @foreach($flows as $flow)
                <li class="pl-4 {{ $loop->last ? '' : 'mb-4' }}">
                    <div class="absolute w-3 h-3 bg-{{ $flow->status_color }}-500 rounded-full mt-1.5 -left-1.5 border border-white dark:border-gray-900 shadow"></div>
                    
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{ $flow->level_label }}
                        </span>
                        
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                @if($flow->status === 'approved')
                                    Disetujui oleh {{ $flow->approver?->name ?? 'Sistem' }}
                                @elseif($flow->status === 'rejected')
                                    Ditolak oleh {{ $flow->approver?->name ?? 'Sistem' }}
                                @else
                                    Menunggu Persetujuan
                                @endif
                            </span>
                            
                            <x-filament::badge :color="$flow->status_color" size="sm">
                                {{ ucfirst($flow->status) }}
                            </x-filament::badge>
                        </div>
                        
                        @if($flow->approved_at)
                            <span class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                <x-heroicon-o-clock class="w-3 h-3"/>
                                {{ $flow->approved_at->format('d M Y H:i') }}
                            </span>
                        @endif
                        
                        @if($flow->notes)
                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 p-2 rounded-md italic">
                                "{!! nl2br(e($flow->notes)) !!}"
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <div class="flex flex-col items-center justify-center p-4 text-gray-500">
            <x-heroicon-o-document-magnifying-glass class="w-8 h-8 mb-2 text-gray-400"/>
            <p>Belum ada riwayat persetujuan terisi untuk pengajuan ini.</p>
        </div>
    @endif
    
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-white/5 p-3 rounded-lg">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Progress Saat Ini: <span class="font-bold text-gray-900 dark:text-white">{{ $current }} / {{ $max }}</span> Level
        </div>
        <x-filament::badge :color="$record->status === 'approved' ? 'success' : ($record->status === 'rejected' ? 'danger' : 'warning')">
            {{ strtoupper($record->status) }}
        </x-filament::badge>
    </div>
</div>
