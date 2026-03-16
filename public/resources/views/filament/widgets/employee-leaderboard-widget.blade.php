<x-filament-widgets::widget>
    <x-filament::section>
        {{-- Custom Styles for robust layout and dark mode support --}}
        <style>
            .lb-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 0.75rem;
            }
            .lb-header {
                display: flex;
                align-items: center;
                gap: 0.375rem;
                margin-bottom: 0.5rem;
            }
            .lb-icon-container {
                display: flex;
                height: 1.5rem;
                width: 1.5rem;
                flex-shrink: 0;
                align-items: center;
                justify-content: center;
                border-radius: 0.25rem;
            }
            .lb-icon {
                height: 1rem;
                width: 1rem;
                color: white;
            }
            .lb-title {
                font-size: 0.75rem;
                font-weight: 700;
            }
            .lb-list {
                display: flex;
                flex-direction: column;
                gap: 0.375rem;
            }
            .lb-card {
                display: flex;
                align-items: center;
                gap: 0.375rem;
                padding: 0.375rem;
                border-radius: 0.25rem;
                border: 1px solid #e5e7eb;
                background-color: white;
                color: #111827; /* gray-900 */
                transition: all 0.2s;
            }
            /* Dark Mode overrides */
            .dark .lb-card {
                background-color: #1f2937; /* gray-800 */
                border-color: #374151; /* gray-700 */
                color: white;
            }
            .lb-rank {
                display: flex;
                height: 1.5rem;
                width: 1.5rem;
                flex-shrink: 0;
                align-items: center;
                justify-content: center;
                border-radius: 0.25rem;
                font-size: 10px;
                font-weight: 700;
            }
            .lb-name-container {
                flex: 1 1 0%;
                min-width: 0;
            }
            .lb-name {
                font-size: 10px;
                font-weight: 600;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .lb-subtext {
                font-size: 9px;
                font-weight: 400;
                color: #6b7280; /* gray-500 */
            }
            .dark .lb-subtext {
                color: #9ca3af; /* gray-400 */
            }
            .lb-count-badge {
                display: flex;
                align-items: center;
                gap: 0.125rem;
                padding: 0.125rem 0.375rem;
                border-radius: 9999px;
                flex-shrink: 0;
                font-size: 10px;
                font-weight: 700;
            }
            .lb-footer {
                margin-top: 0.5rem;
                text-align: center;
            }
            .lb-link {
                display: inline-flex;
                align-items: center;
                gap: 0.125rem;
                font-size: 10px;
                font-weight: 500;
            }
        </style>
        
        {{-- Ultra Compact Header --}}
        <div class="mb-3 pb-2 border-b border-gray-200 dark:border-gray-700" style="margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom-width: 1px; border-color: #e5e7eb;">
            <div class="flex items-center gap-2" style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="text-base" style="font-size: 1rem;">🥇</span>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white" style="font-size: 0.875rem; font-weight: 700;">Top Employees</h2>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400" style="font-size: 10px; color: #6b7280;">
                        {{ now()->subMonth()->day(16)->format('d M') }} - {{ now()->day(15)->format('d M Y') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="lb-grid">
            {{-- Top Rajin (Minim Terlambat) --}}
            <div>
                <div class="lb-header">
                    <div class="lb-icon-container bg-blue-500" style="background-color: #3b82f6;">
                        <svg class="lb-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <h3 class="lb-title text-gray-900 dark:text-white">Most Diligent (On Time)</h3>
                </div>
                
                @php
                    $mostDiligent = $this->getMostDiligentEmployees();
                @endphp
                
                <div class="lb-list">
                    @foreach($mostDiligent as $index => $emp)
                    <div class="lb-card">
                        <div class="lb-rank"
                             style="background-color: {{ $index === 0 ? '#facc15' : ($index === 1 ? '#d1d5db' : ($index === 2 ? '#fb923c' : '#f3f4f6')) }}; color: {{ $index === 0 ? '#713f12' : ($index === 1 ? '#374151' : ($index === 2 ? '#7c2d12' : '#4b5563')) }};">
                            {{ $index + 1 }}
                        </div>
                        <div class="lb-name-container">
                            <div class="lb-name">{{ $emp->name }}</div>
                            <div class="lb-subtext">{{ $emp->dept }}</div>
                        </div>
                        <div class="lb-count-badge" style="background-color: #dcfce7; color: #15803d;">
                            {{ $emp->on_time_count }} Days
                        </div>
                    </div>
                    @endforeach
                    
                    @if($mostDiligent->isEmpty())
                        <div class="text-center text-xs text-gray-500 py-2">No data available</div>
                    @endif
                </div>
            </div>
            
            {{-- Top Telat (Paling Sering Alpa) --}}
            <div>
                <div class="lb-header">
                    <div class="lb-icon-container bg-orange-500" style="background-color: #f97316;">
                        <svg class="lb-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="lb-title text-gray-900 dark:text-white">Most Late Minutes</h3>
                </div>
                
                @php
                    $mostLate = $this->getMostLateEmployees();
                @endphp
                
                <div class="lb-list">
                    @foreach($mostLate as $index => $emp)
                    <div class="lb-card">
                        <div class="lb-rank"
                             style="background-color: {{ $index === 0 ? '#ef4444' : ($index === 1 ? '#f87171' : ($index === 2 ? '#fca5a5' : '#f3f4f6')) }}; color: {{ $index === 0 ? 'white' : ($index === 1 ? 'white' : ($index === 2 ? '#7f1d1d' : '#4b5563')) }};">
                            {{ $index + 1 }}
                        </div>
                        <div class="lb-name-container">
                            <div class="lb-name">{{ $emp->name }}</div>
                            <div class="lb-subtext">{{ $emp->dept }}</div>
                        </div>
                        <div class="lb-count-badge" style="background-color: #ffedd5; color: #c2410c;">
                            {{ $emp->total_late_minutes }}m
                        </div>
                    </div>
                    @endforeach
                    
                    @if($mostLate->isEmpty())
                        <div class="text-center text-xs text-gray-500 py-2">No data available</div>
                    @endif
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
