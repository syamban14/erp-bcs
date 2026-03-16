{{-- Approval Chain Modal Content --}}
<div class="space-y-4 py-2">
    {{-- Overall Status --}}
    <div class="flex items-center justify-between rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Overall Status</span>
        <span @class([
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $record->status === 'approved',
            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'         => $record->status === 'rejected',
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => $record->status === 'pending',
        ])>
            {{ ucfirst($record->status) }}
        </span>
    </div>

    {{-- Approval Steps --}}
    <div class="relative">
        @php
            use App\Models\ApprovalFlow;
            $levelLabels = ApprovalFlow::LEVEL_LABELS;
            $levelRoles  = ApprovalFlow::LEVEL_ROLES;
        @endphp

        @foreach ($levelLabels as $level => $label)
            @php
                $flow   = $flows->firstWhere('level', $level);
                $status = $flow?->status ?? 'waiting';
                $isCurrent = ($level === $current && $record->status === 'pending');
            @endphp

            <div class="flex items-start gap-4 {{ !$loop->last ? 'mb-4' : '' }}">
                {{-- Step Circle --}}
                <div class="mt-0.5 flex-shrink-0">
                    @if ($status === 'approved')
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                            <x-heroicon-s-check class="h-4 w-4 text-green-600 dark:text-green-400" />
                        </div>
                    @elseif ($status === 'rejected')
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                            <x-heroicon-s-x-mark class="h-4 w-4 text-red-600 dark:text-red-400" />
                        </div>
                    @elseif ($isCurrent)
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900 ring-2 ring-blue-500">
                            <x-heroicon-s-clock class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                    @else
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $level }}</span>
                        </div>
                    @endif
                </div>

                {{-- Step Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            Level {{ $level }}: {{ $label }}
                        </p>
                        <span class="text-xs text-gray-400">({{ $levelRoles[$level] ?? '' }})</span>
                        @if ($isCurrent)
                            <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                Awaiting
                            </span>
                        @endif
                    </div>

                    @if ($flow && $flow->approver)
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            By: <span class="font-medium">{{ $flow->approver->name }}</span>
                            · {{ $flow->approved_at?->format('d M Y H:i') }}
                        </p>
                    @endif

                    @if ($flow?->notes)
                        <p class="mt-1 text-xs italic text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded px-2 py-1">
                            "{{ $flow->notes }}"
                        </p>
                    @endif
                </div>
            </div>

            {{-- Connector Line --}}
            @if (!$loop->last)
                <div class="ml-4 h-4 border-l-2 border-dashed border-gray-200 dark:border-gray-700"></div>
            @endif
        @endforeach
    </div>
</div>
