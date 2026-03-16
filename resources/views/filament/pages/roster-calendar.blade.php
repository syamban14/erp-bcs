<x-filament-panels::page>
    <x-filament::section>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <x-filament::button wire:click="previousMonth" color="gray" size="sm">
                    &Lt;
                </x-filament::button>
                <div style="font-weight: bold; font-size: 1.125rem; min-width: 150px; text-align: center;">
                    {{ $this->getMonthName() }} {{ $selectedYear }}
                </div>
                <x-filament::button wire:click="nextMonth" color="gray" size="sm">
                    &Gt;
                </x-filament::button>
            </div>
            
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <x-filament::button wire:click="previousWeek" color="gray" size="sm" :disabled="$currentWeek <= 1">
                    &lt; Prev Week
                </x-filament::button>
                <div style="font-weight: 500; padding: 0 1rem; text-align: center; min-width: 120px;">
                    Week {{ $currentWeek }} of {{ $totalWeeks }}
                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">{{ $weekInfo }}</div>
                </div>
                <x-filament::button wire:click="nextWeek" color="gray" size="sm" :disabled="$currentWeek >= $totalWeeks">
                    Next Week &gt;
                </x-filament::button>
            </div>
            
            <div>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.500ms="searchTerm"
                        placeholder="Search employee..."
                    />
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <div style="overflow-x: auto; padding-bottom: 0.5rem;">
            <table style="width: 100%; font-size: 0.875rem; text-align: left; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr>
                        <th style="padding: 0.75rem 1rem; font-weight: 600; min-width: 200px; border-bottom: 1px solid rgba(107, 114, 128, 0.2); border-right: 1px solid rgba(107, 114, 128, 0.2); position: sticky; left: 0; background-color: rgba(107, 114, 128, 0.05); z-index: 10;">
                            Employee
                        </th>
                        @foreach($datesData as $date)
                            <th style="padding: 0.75rem 0.5rem; text-align: center; border-bottom: 1px solid rgba(107, 114, 128, 0.2); border-right: 1px solid rgba(107, 114, 128, 0.2); min-width: 80px; background-color: rgba(107, 114, 128, 0.05);">
                                <div style="font-weight: bold; font-size: 1.125rem;">{{ $date['day'] }}</div>
                                <div style="font-size: 0.75rem; opacity: 0.7; text-transform: uppercase;">{{ $date['dayName'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($employeesData as $employee)
                        <tr>
                            <td style="padding: 0.75rem 1rem; font-weight: 500; border-bottom: 1px solid rgba(107, 114, 128, 0.1); border-right: 1px solid rgba(107, 114, 128, 0.2); position: sticky; left: 0; background-color: var(--fi-bg-base, #fff); z-index: 10; white-space: nowrap;">
                                {{ $employee['name'] }}
                            </td>
                            
                            @foreach($datesData as $date)
                                @php
                                    $shift = $employee['shifts'][$date['full']] ?? null;
                                    $isWeekend = in_array(strtolower($date['dayName']), ['sat', 'sun']);
                                    $tdStyle = "padding: 0.25rem; border-bottom: 1px solid rgba(107, 114, 128, 0.1); border-right: 1px solid rgba(107, 114, 128, 0.2); text-align: center;";
                                    if ($isWeekend) $tdStyle .= " background-color: rgba(156, 163, 175, 0.05);";
                                @endphp
                                <td style="{{ $tdStyle }}">
                                    @if($shift)
                                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.35rem; border-radius: 0.375rem; border: 1px solid {{ $shift['color']['border'] }}; background-color: {{ $shift['color']['bg'] }}; color: {{ $shift['color']['text'] }};">
                                            <span style="font-weight: 700; font-size: 0.75rem; line-height: 1;">{{ $shift['code'] }}</span>
                                            @if(!$shift['is_off'])
                                                <span style="font-size: 0.65rem; white-space: nowrap; margin-top: 0.15rem; font-weight: 500; opacity: 0.9;">
                                                    {{ $shift['time_in'] }}-{{ $shift['time_out'] }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div style="height: 2.25rem; width: 100%;"></div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($datesData) + 1 }}" style="padding: 2rem 1rem; text-align: center; opacity: 0.6;">
                                No employees found or no schedules assigned for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
    
    <x-filament::section>
        <div style="font-size: 0.85rem;">
            <div style="font-weight: 600; margin-bottom: 0.75rem;">Shift Legend:</div>
            <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center;"><span style="width: 1.25rem; height: 1.25rem; border-radius: 0.25rem; border: 1px solid #fde047; margin-right: 0.4rem; background-color: #fef3c7;"></span> Pagi / Shift 1</div>
                <div style="display: flex; align-items: center;"><span style="width: 1.25rem; height: 1.25rem; border-radius: 0.25rem; border: 1px solid #93c5fd; margin-right: 0.4rem; background-color: #dbeafe;"></span> Siang / Shift 2</div>
                <div style="display: flex; align-items: center;"><span style="width: 1.25rem; height: 1.25rem; border-radius: 0.25rem; border: 1px solid #d8b4fe; margin-right: 0.4rem; background-color: #f3e8ff;"></span> Malam / Shift 3</div>
                <div style="display: flex; align-items: center;"><span style="width: 1.25rem; height: 1.25rem; border-radius: 0.25rem; border: 1px solid #86efac; margin-right: 0.4rem; background-color: #dcfce7;"></span> Non-Shift / Normal</div>
                <div style="display: flex; align-items: center;"><span style="width: 1.25rem; height: 1.25rem; border-radius: 0.25rem; border: 1px solid #d1d5db; margin-right: 0.4rem; background-color: #f3f4f6;"></span> Off / Libur</div>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
