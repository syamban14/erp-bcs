<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Absensi - {{ $user->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #222; }

        /* ── KOP SURAT ── */
        .header { text-align: center; padding: 12px 0 8px; border-bottom: 2px solid #1a3c6e; margin-bottom: 10px; }
        .company-name { font-size: 16px; font-weight: bold; color: #1a3c6e; letter-spacing: 1px; }
        .company-sub  { font-size: 9px; color: #555; margin-top: 2px; }
        .doc-title    { font-size: 13px; font-weight: bold; margin-top: 8px; text-transform: uppercase; color: #1a3c6e; }
        .doc-period   { font-size: 10px; color: #333; margin-top: 2px; }

        /* ── INFO KARYAWAN ── */
        .info-box { background: #f4f7fc; border: 1px solid #c8d8f0; border-radius: 4px; padding: 8px 12px; margin-bottom: 10px; }
        .info-row { display: flex; margin-bottom: 3px; }
        .info-label { width: 130px; font-weight: bold; color: #444; }
        .info-value { flex: 1; color: #222; }

        /* ── SUMMARY ── */
        .summary-title { font-weight: bold; font-size: 10px; color: #1a3c6e; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-grid { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .summary-grid td { padding: 6px 10px; border: 1px solid #c8d8f0; text-align: center; }
        .summary-grid th { padding: 6px 10px; background: #1a3c6e; color: white; font-size: 9px; text-transform: uppercase; }
        .badge-hadir     { background: #d4edda; color: #155724; border-radius: 3px; padding: 1px 5px; }
        .badge-terlambat { background: #fff3cd; color: #856404; border-radius: 3px; padding: 1px 5px; }
        .badge-izin      { background: #d1ecf1; color: #0c5460; border-radius: 3px; padding: 1px 5px; }
        .badge-alpha     { background: #f8d7da; color: #721c24; border-radius: 3px; padding: 1px 5px; }

        /* ── TABEL HARIAN ── */
        .daily-title { font-weight: bold; font-size: 10px; color: #1a3c6e; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.daily { width: 100%; border-collapse: collapse; }
        table.daily th {
            background: #1a3c6e; color: white; padding: 5px 6px;
            text-align: center; font-size: 9px; text-transform: uppercase;
        }
        table.daily td { padding: 4px 6px; border: 1px solid #dde4f0; text-align: center; font-size: 9px; }
        table.daily tr:nth-child(even) td { background: #f4f7fc; }
        table.daily tr:hover td { background: #e8eef8; }
        .status-hadir     { color: #155724; font-weight: bold; }
        .status-terlambat { color: #856404; font-weight: bold; }
        .status-libur     { color: #6c757d; }
        .status-alpha     { color: #721c24; font-weight: bold; }
        .status-izin      { color: #0c5460; font-weight: bold; }

        /* ── FOOTER ── */
        .footer { margin-top: 14px; border-top: 1px solid #c8d8f0; padding-top: 6px; font-size: 8px; color: #888; text-align: right; }
    </style>
</head>
<body>

    {{-- KOP SURAT --}}
    <div class="header">
        <div class="company-name">PT. BCS LOGISTICS</div>
        <div class="company-sub">Sistem Manajemen Kehadiran Karyawan</div>
        <div class="doc-title">Rekap Absensi Karyawan</div>
        <div class="doc-period">Periode: {{ $periodLabel }}</div>
    </div>

    {{-- INFO KARYAWAN --}}
    <div class="info-box">
        <div class="info-row"><span class="info-label">Nama Karyawan</span><span class="info-value">: {{ $user->name }}</span></div>
        <div class="info-row"><span class="info-label">Email</span><span class="info-value">: {{ $user->email }}</span></div>
        @if($karyawan)
        <div class="info-row"><span class="info-label">Departemen</span><span class="info-value">: {{ $karyawan->dept_id ?? '-' }}</span></div>
        @endif
        <div class="info-row"><span class="info-label">Tanggal Cetak</span><span class="info-value">: {{ now()->format('d F Y, H:i') }} WIB</span></div>
    </div>

    {{-- SUMMARY --}}
    <div class="summary-title">📊 Ringkasan Kehadiran</div>
    <table class="summary-grid">
        <tr>
            <th>Total Hari Kerja</th>
            <th>Hadir</th>
            <th>Terlambat</th>
            <th>Akumulasi Terlambat</th>
            <th>Cuti</th>
            <th>Izin</th>
            <th>Alpha</th>
        </tr>
        <tr>
            <td>{{ $summary['working_days'] }} hari</td>
            <td><span class="badge-hadir">{{ $summary['total_hadir'] }} hari</span></td>
            <td><span class="badge-terlambat">{{ $summary['total_terlambat'] }} hari</span></td>
            <td><span class="badge-terlambat">{{ $summary['total_late_minutes'] }} menit</span></td>
            <td><span class="badge-izin">{{ $summary['total_cuti'] }} hari</span></td>
            <td><span class="badge-izin">{{ $summary['total_izin'] }} hari</span></td>
            <td><span class="badge-alpha">{{ $summary['total_alpha'] }} hari</span></td>
        </tr>
    </table>

    {{-- TABEL HARIAN --}}
    <div class="daily-title">📋 Detail Kehadiran Harian</div>
    <table class="daily">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Hari</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar</th>
                <th>Status</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dailyRecords as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['day'] }}</td>
                <td>{{ $row['clock_in'] }}</td>
                <td>{{ $row['clock_out'] }}</td>
                <td>
                    @if($row['status'] === 'Hadir')
                        <span class="status-hadir">Hadir</span>
                    @elseif($row['status'] === 'Terlambat')
                        <span class="status-terlambat">Terlambat</span>
                    @elseif($row['status'] === 'Alpha')
                        <span class="status-alpha">Alpha</span>
                    @elseif($row['status'] === 'Libur')
                        <span class="status-libur">Libur</span>
                    @else
                        <span class="status-izin">{{ $row['status'] }}</span>
                    @endif
                </td>
                <td>{{ $row['notes'] }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center; color:#888;">Tidak ada data kehadiran</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- FOOTER --}}
    <div class="footer">
        Dokumen ini digenerate secara otomatis oleh Sistem Presensi BCS &bull; {{ now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>
