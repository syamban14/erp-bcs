<x-filament-widgets::widget>
    <x-filament::section>
        <p style="font-size:1rem; font-weight:700; color:inherit; margin:0 0 2px;">Peringkat Karyawan</p>
        <p style="font-size:0.75rem; color:#6b7280; margin:0 0 16px;">Top 5 berdasarkan kedisiplinan periode ini</p>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">

            {{-- KOLOM KIRI: Tepat Waktu --}}
            <div>
                <p style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#10b981; margin:0 0 10px;">
                    Top 5 Tepat Waktu
                </p>
                @php $rank = 1; @endphp
                @forelse($this->getMostDiligentEmployees() as $employee)
                <div style="display:flex; align-items:center; gap:10px; border:1px solid #374151; border-radius:8px; padding:10px 12px; margin-bottom:8px; background:rgba(255,255,255,0.02);">
                    <span style="
                        display:inline-flex; align-items:center; justify-content:center;
                        min-width:28px; height:28px; border-radius:50%; font-size:0.7rem; font-weight:700;
                        background:{{ $rank===1 ? '#fef3c7' : ($rank===2 ? '#f3f4f6' : ($rank===3 ? '#ffedd5' : '#f9fafb')) }};
                        color:{{ $rank===1 ? '#b45309' : ($rank===2 ? '#6b7280' : ($rank===3 ? '#c2410c' : '#9ca3af')) }};
                    ">{{ $rank }}</span>
                    <div style="flex:1; min-width:0;">
                        <p style="font-size:0.875rem; font-weight:600; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $employee->name }}</p>
                        <p style="font-size:0.7rem; color:#9ca3af; margin:2px 0 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $employee->dept ?: '—' }}</p>
                    </div>
                    <div style="flex-shrink:0; text-align:right;">
                        <span style="border-radius:9999px; padding:2px 10px; font-size:0.7rem; font-weight:700; background:#d1fae5; color:#065f46; white-space:nowrap;">
                            {{ $employee->on_time_count }}x
                        </span>
                        <p style="font-size:0.65rem; color:#9ca3af; margin:2px 0 0;">tepat waktu</p>
                    </div>
                </div>
                @php $rank++; @endphp
                @empty
                <p style="font-size:0.75rem; color:#9ca3af; text-align:center; padding:16px 0;">Belum ada data presensi tepat waktu.</p>
                @endforelse
            </div>

            {{-- KOLOM KANAN: Sering Terlambat --}}
            <div>
                <p style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#ef4444; margin:0 0 10px;">
                    Top 5 Sering Terlambat
                </p>
                @php $rank = 1; @endphp
                @forelse($this->getMostLateEmployees() as $employee)
                @php
                    $hours = intdiv($employee->total_late_minutes, 60);
                    $mins  = $employee->total_late_minutes % 60;
                    $label = $hours > 0 ? "{$hours}j {$mins}m" : "{$mins} mnt";
                @endphp
                <div style="display:flex; align-items:center; gap:10px; border:1px solid #374151; border-radius:8px; padding:10px 12px; margin-bottom:8px; background:rgba(255,255,255,0.02);">
                    <span style="display:inline-flex; align-items:center; justify-content:center; min-width:28px; height:28px; border-radius:50%; font-size:0.7rem; font-weight:700; background:#fef2f2; color:#ef4444;">{{ $rank }}</span>
                    <div style="flex:1; min-width:0;">
                        <p style="font-size:0.875rem; font-weight:600; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $employee->name }}</p>
                        <p style="font-size:0.7rem; color:#9ca3af; margin:2px 0 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $employee->dept ?: '—' }}</p>
                    </div>
                    <div style="flex-shrink:0; text-align:right;">
                        <span style="border-radius:9999px; padding:2px 10px; font-size:0.7rem; font-weight:700; background:#fee2e2; color:#991b1b; white-space:nowrap;">
                            {{ $label }}
                        </span>
                        <p style="font-size:0.65rem; color:#9ca3af; margin:2px 0 0;">total telat</p>
                    </div>
                </div>
                @php $rank++; @endphp
                @empty
                <p style="font-size:0.75rem; color:#10b981; text-align:center; padding:16px 0;">Tidak ada keterlambatan. Luar biasa!</p>
                @endforelse
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>
