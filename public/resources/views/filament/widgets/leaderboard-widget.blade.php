<x-filament-widgets::widget>
    <x-filament::section>
        {{-- Ultra Compact Header --}}
        <div class="mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
                <span class="text-base">🏆</span>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Leaderboard Absensi</h2>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400">
                        {{ now()->subMonth()->day(16)->format('d M') }} - {{ now()->day(15)->format('d M Y') }}
                    </p>
                </div>
            </div>
        </div>

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

        <div class="lb-grid">
            {{-- Divisi Minim Terlambat --}}
            <div>
                <div class="lb-header">
                    <div class="lb-icon-container bg-green-500" style="background-color: #22c55e;">
                        <svg class="lb-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="lb-title text-gray-900 dark:text-white">Minim Terlambat</h3>
                </div>
                
                @php
                    $leastLate = $this->getLeastLateDivisions();
                @endphp
                
                <div class="lb-list">
                    @foreach($leastLate->take(5) as $index => $division)
                    <div class="lb-card">
                        <div class="lb-rank"
                             style="background-color: {{ $index === 0 ? '#facc15' : ($index === 1 ? '#d1d5db' : ($index === 2 ? '#fb923c' : '#f3f4f6')) }}; color: {{ $index === 0 ? '#713f12' : ($index === 1 ? '#374151' : ($index === 2 ? '#7c2d12' : '#4b5563')) }};">
                            {{ $index + 1 }}
                        </div>
                        <div class="lb-name-container">
                            <div class="lb-name">{{ $division->div_name }}</div>
                        </div>
                        <div class="lb-count-badge" style="background-color: #dcfce7; color: #15803d;">
                            {{ $division->late_count }}
                        </div>
                    </div>
                    @endforeach
                </div>
                
                @if($leastLate->count() > 5)
                <div class="lb-footer">
                    <a href="{{ route('filament.admin.pages.leaderboard-full') }}" class="lb-link" style="color: #16a34a;">
                        <span>Lihat Semua</span>
                        <svg style="height: 0.75rem; width: 0.75rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
                @endif
            </div>
            
            {{-- Divisi Paling Sering Alpa --}}
            <div>
                <div class="lb-header">
                    <div class="lb-icon-container bg-red-500" style="background-color: #ef4444;">
                        <svg class="lb-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/>
                        </svg>
                    </div>
                    <h3 class="lb-title text-gray-900 dark:text-white">Paling Sering Alpa</h3>
                </div>
                
                @php
                    $mostAbsent = $this->getMostAbsentDivisions();
                @endphp
                
                <div class="lb-list">
                    @foreach($mostAbsent->take(5) as $index => $division)
                    <div class="lb-card">
                        <div class="lb-rank"
                             style="background-color: {{ $index === 0 ? '#ef4444' : ($index === 1 ? '#f87171' : ($index === 2 ? '#fca5a5' : '#f3f4f6')) }}; color: {{ $index === 0 ? 'white' : ($index === 1 ? 'white' : ($index === 2 ? '#7f1d1d' : '#4b5563')) }};">
                            {{ $index + 1 }}
                        </div>
                        <div class="lb-name-container">
                            <div class="lb-name">{{ $division->div_name }}</div>
                        </div>
                        <div class="lb-count-badge" style="background-color: #fee2e2; color: #b91c1c;">
                            {{ $division->alpha_count }}
                        </div>
                    </div>
                    @endforeach
                </div>
                
                @if($mostAbsent->count() > 5)
                <div class="lb-footer">
                    <a href="{{ route('filament.admin.pages.leaderboard-full') }}" class="lb-link" style="color: #dc2626;">
                        <span>Lihat Semua</span>
                        <svg style="height: 0.75rem; width: 0.75rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
