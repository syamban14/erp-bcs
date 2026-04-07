{{-- Detail Modal: Monthly Recap per Karyawan --}}
@php
    use Carbon\Carbon;
    use App\Models\Presence;
    use App\Models\Leave;
    use App\Models\PermissionRequest;
    use App\Models\OutstationRequest;

    $month      = $month ?? now()->month;
    $year       = $year  ?? now()->year;
    $endDate    = Carbon::create($year, $month, 15)->endOfDay();
    $startDate  = $endDate->copy()->subMonth()->addDay()->startOfDay();

    $presences = Presence::where('user_id', $record->id)
        ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
        ->orderBy('date')
        ->get();

    $leaves = Leave::where('user_id', $record->id)
        ->where('status', 'approved')
        ->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date',   [$startDate, $endDate]);
        })
        ->get()
        ->keyBy(fn($l) => Carbon::parse($l->start_date)->format('Y-m-d'));

    $outstations = OutstationRequest::where('user_id', $record->id)
        ->where('status', 'approved')
        ->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date',   [$startDate, $endDate]);
        })
        ->get();

    $presenceMap = $presences->keyBy(fn($p) => Carbon::parse($p->date)->format('Y-m-d'));

    // Build day-by-day entries
    $days = [];
    $cur = $startDate->copy();
    while ($cur->lte($endDate)) {
        $key       = $cur->format('Y-m-d');
        $presence  = $presenceMap->get($key);
        $leave     = $leaves->get($key);
        $outstation= $outstations->first(fn($o) =>
            $cur->between(Carbon::parse($o->start_date), Carbon::parse($o->end_date))
        );

        $status = 'Tidak Hadir';
        $badge  = 'bg-red-100 text-red-700';
        if ($presence && $presence->clock_in) {
            $status = 'Hadir';
            $badge  = 'bg-green-100 text-green-700';
            if ($presence->late_minutes > 0) {
                $status = 'Hadir (Terlambat ' . $presence->late_minutes . ' mnt)';
                $badge  = 'bg-yellow-100 text-yellow-700';
            }
        } elseif ($leave) {
            $type   = ucfirst($leave->type);
            $status = "Cuti / Izin ({$type})";
            $badge  = 'bg-blue-100 text-blue-700';
        } elseif ($outstation) {
            $status = 'Tugas Luar';
            $badge  = 'bg-purple-100 text-purple-700';
        } elseif ($cur->isWeekend()) {
            $status = 'Libur';
            $badge  = 'bg-gray-100 text-gray-500';
        }

        $days[] = [
            'date'       => $cur->copy(),
            'clock_in'   => $presence?->clock_in  ? Carbon::parse($presence->clock_in)->format('H:i')  : '-',
            'clock_out'  => $presence?->clock_out ? Carbon::parse($presence->clock_out)->format('H:i') : '-',
            'late'       => $presence?->late_minutes ?? 0,
            'overtime'   => $presence?->overtime_minutes ?? 0,
            'hours'      => $presence?->working_hours ? round($presence->working_hours, 1) : 0,
            'status'     => $status,
            'badge'      => $badge,
        ];

        $cur->addDay();
    }
@endphp

<div style="font-family: system-ui, sans-serif; padding: 0.25rem;">

    {{-- Header karyawan --}}
    <div style="background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px;padding:1rem 1.25rem;margin-bottom:1rem;color:#1a1a1a;">
        <div style="font-size:1.1rem;font-weight:700;">{{ $record->name }}</div>
        <div style="font-size:0.8rem;opacity:.85;margin-top:2px;">
            Periode: {{ $startDate->format('d M Y') }} – {{ $endDate->format('d M Y') }}
        </div>
    </div>

    {{-- Legenda satuan --}}
    <div style="background:#1e293b;border-radius:8px;padding:0.6rem 1rem;margin-bottom:0.75rem;font-size:0.72rem;color:#94a3b8;display:flex;gap:1.5rem;flex-wrap:wrap;">
        <span>📅 <b>Hadir & Cuti</b> = Hari</span>
        <span>⏱ <b>Durasi Kerja</b> = Jam</span>
        <span>⏰ <b>Terlambat / Lembur</b> = Menit</span>
    </div>

    {{-- Tabel Harian --}}
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.78rem;">
            <thead>
                <tr style="background:#1e293b;color:#94a3b8;text-align:center;">
                    <th style="padding:6px 8px;text-align:left;border-radius:6px 0 0 0;">Tanggal</th>
                    <th style="padding:6px 8px;">Status</th>
                    <th style="padding:6px 8px;">Masuk</th>
                    <th style="padding:6px 8px;">Pulang</th>
                    <th style="padding:6px 8px;">Durasi (Jam)</th>
                    <th style="padding:6px 8px;">Telat (Mnt)</th>
                    <th style="padding:6px 8px;border-radius:0 6px 0 0;">Lembur (Mnt)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $i => $day)
                <tr style="background:{{ $i % 2 === 0 ? '#0f172a' : '#111827' }};border-bottom:1px solid #1e293b;">
                    <td style="padding:6px 8px;color:#e2e8f0;white-space:nowrap;">
                        {{ $day['date']->translatedFormat('D, d M') }}
                    </td>
                    <td style="padding:6px 8px;text-align:center;">
                        <span style="padding:2px 8px;border-radius:999px;font-size:0.7rem;{{ str_replace(['bg-','text-','100','700','500'],['background-color:#','color:#','e0','800','600'],$day['badge']) }}" class="{{ $day['badge'] }}">
                            {{ $day['status'] }}
                        </span>
                    </td>
                    <td style="padding:6px 8px;text-align:center;color:#6ee7b7;">{{ $day['clock_in'] }}</td>
                    <td style="padding:6px 8px;text-align:center;color:#6ee7b7;">{{ $day['clock_out'] }}</td>
                    <td style="padding:6px 8px;text-align:center;color:#e2e8f0;">{{ $day['hours'] ?: '-' }}</td>
                    <td style="padding:6px 8px;text-align:center;color:{{ $day['late'] > 0 ? '#fca5a5' : '#6b7280' }};">
                        {{ $day['late'] > 0 ? $day['late'] : '-' }}
                    </td>
                    <td style="padding:6px 8px;text-align:center;color:{{ $day['overtime'] > 0 ? '#93c5fd' : '#6b7280' }};">
                        {{ $day['overtime'] > 0 ? $day['overtime'] : '-' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#1e293b;color:#f59e0b;font-weight:600;font-size:0.8rem;">
                    <td style="padding:7px 8px;" colspan="4">TOTAL</td>
                    <td style="padding:7px 8px;text-align:center;">
                        {{ round($presences->sum('working_hours'), 1) }} Jam
                    </td>
                    <td style="padding:7px 8px;text-align:center;">
                        {{ $presences->sum('late_minutes') }} Mnt
                    </td>
                    <td style="padding:7px 8px;text-align:center;">
                        {{ $presences->sum('overtime_minutes') }} Mnt
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
