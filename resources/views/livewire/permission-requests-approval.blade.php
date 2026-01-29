<div>
    @if($requests->count() > 0)
        <div class="space-y-4">
            @foreach($requests as $request)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ $request->user->name }}
                                </h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    {{ ucfirst($request->type) }}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Date:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $request->start_date }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Created:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $request->created_at->format('d M Y, H:i') }}</span>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Reason:</span>
                                <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $request->reason }}</p>
                            </div>
                        </div>
                        
                        <div class="flex gap-2 ml-4">
                            <button wire:click="openApproveModal({{ $request->id }})" 
                                class="inline-flex items-center px-4 py-2 bg-success-600 hover:bg-success-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Approve
                            </button>
                            <button wire:click="openRejectModal({{ $request->id }})" 
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No pending requests</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All permission requests have been processed.</p>
        </div>
    @endif

    <!-- Approve Modal -->
    <x-filament::modal id="approve-modal" wire:model="showApproveModal">
        <x-slot name="heading">
            Approve Permission Request
        </x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Are you sure you want to approve this permission request?
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
            Reject Permission Request
        </x-slot>

        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Please provide a reason for rejecting this request:
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
