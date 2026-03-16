<div>
    @if($corrections->count() > 0)
        <div class="space-y-4">
            @foreach($corrections as $correction)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ $correction->user->name }}
                                </h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                    {{ strtoupper($correction->type) }}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Date:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $correction->date }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Time:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $correction->time }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Created:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $correction->created_at->format('d M Y, H:i') }}</span>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Reason:</span>
                                <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $correction->reason }}</p>
                            </div>
                            
                            @if($correction->evidence)
                                <div class="mt-3">
                                    <a href="{{ asset('storage/' . $correction->evidence) }}" target="_blank" 
                                       class="inline-flex items-center text-sm text-primary-600 hover:text-primary-700">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                        View Evidence
                                    </a>
                                </div>
                            @endif
                        </div>
                        
                        <div class="flex gap-2 ml-4">
                            <button wire:click="openApproveModal({{ $correction->id }})" 
                                class="inline-flex items-center px-4 py-2 bg-success-600 hover:bg-success-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Approve
                            </button>
                            <button wire:click="openRejectModal({{ $correction->id }})" 
                                class="inline-flex items-center px-4 py-2 bg-danger-600 hover:bg-danger-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No pending corrections</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All attendance corrections have been processed.</p>
        </div>
    @endif

    <!-- Approve Modal -->
    <x-filament::modal id="approve-modal" wire:model="showApproveModal">
        <x-slot name="heading">
            Approve Attendance Correction
        </x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Are you sure you want to approve this attendance correction?
        </p>

        <x-slot name="footer">
            <div class="flex gap-3 justify-end">
                <x-filament::button color="gray" wire:click="$set('showApproveModal', false)">
                    Cancel
                </x-filament::button>
                <x-filament::button color="success" wire:click="approve">
                    Approve
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    <!-- Reject Modal -->
    <x-filament::modal id="reject-modal" wire:model="showRejectModal">
        <x-slot name="heading">
            Reject Attendance Correction
        </x-slot>

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Please provide a reason for rejecting this correction:
            </p>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Rejection Reason
                </label>
                <textarea 
                    wire:model="rejectionReason" 
                    rows="3" 
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    placeholder="Enter rejection reason..."></textarea>
                @error('rejectionReason') 
                    <span class="text-sm text-danger-600 mt-1">{{ $message }}</span> 
                @enderror
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex gap-3 justify-end">
                <x-filament::button color="gray" wire:click="$set('showRejectModal', false)">
                    Cancel
                </x-filament::button>
                <x-filament::button color="danger" wire:click="reject">
                    Reject
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>
</div>
