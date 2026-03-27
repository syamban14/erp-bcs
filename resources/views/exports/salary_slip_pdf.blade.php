<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - {{ $slip->employee_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #111; }

        /* ── HEADER ── */
        .header { display: table; width: 100%; border-bottom: 2px solid #1a3c6e; padding-bottom: 6px; margin-bottom: 6px; }
        .header-left  { display: table-cell; width: 50%; vertical-align: middle; }
        .header-right { display: table-cell; width: 50%; vertical-align: middle; text-align: right; }
        .logo-box { display: flex; align-items: center; }
        .logo-badge {
            background: linear-gradient(135deg, #f7931e, #f7931e 45%, #1a3c6e 45%);
            color: white; font-weight: bold; font-size: 13px;
            padding: 4px 10px; border-radius: 3px; letter-spacing: 1px;
            display: inline-block;
        }
        .logo-text { margin-left: 6px; font-weight: bold; font-size: 11px; color: #1a3c6e; }
        .company-name { font-size: 10px; font-weight: bold; color: #1a3c6e; }
        .company-sub  { font-size: 8px; color: #555; }
        .doc-title    { font-size: 9px; color: #333; margin-top: 2px; }
        .confidential { font-size: 8px; color: #c00; font-weight: bold; letter-spacing: 1px; margin-top: 2px; }

        /* ── INFO KARYAWAN ── */
        .info-section { margin-bottom: 6px; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 2px 4px; font-size: 9px; }
        .info-label { width: 120px; font-weight: bold; }
        .info-colon { width: 8px; }

        /* ── TABEL GAJI ── */
        .salary-wrap { display: table; width: 100%; }
        .col-left  { display: table-cell; width: 50%; vertical-align: top; padding-right: 4px; }
        .col-right { display: table-cell; width: 50%; vertical-align: top; padding-left: 4px; }

        .section-title {
            background: #1a3c6e; color: white; font-weight: bold;
            padding: 3px 6px; font-size: 8px; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 1px;
        }
        table.salary { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.salary td { padding: 2px 5px; border-bottom: 1px solid #e8eef8; font-size: 8.5px; }
        table.salary td.label { width: 60%; }
        table.salary td.meta  { color: #888; font-size: 8px; }
        table.salary td.amount { text-align: right; white-space: nowrap; }
        table.salary tr.subtotal td { font-weight: bold; background: #edf2fc; border-top: 1px solid #c8d8f0; }
        table.salary tr.total-row td { font-weight: bold; background: #1a3c6e; color: white; font-size: 9px; }

        /* ── SUMMARY BOX ── */
        .summary-box { margin-top: 6px; border: 1.5px solid #1a3c6e; border-radius: 3px; overflow: hidden; }
        .summary-box table { width: 100%; border-collapse: collapse; }
        .summary-box td { padding: 4px 8px; font-size: 9px; }
        .summary-box .s-label { font-weight: bold; width: 50%; }
        .summary-box .s-val   { text-align: right; font-weight: bold; }
        .summary-box tr.thp   { background: #1a3c6e; color: white; }
        .summary-box tr.thp td { font-size: 10px; }

        /* ── WORDS ── */
        .words-box { border: 1px solid #c8d8f0; background: #f9fbff; padding: 4px 8px; margin-top: 4px; font-size: 8.5px; }
        .words-label { font-weight: bold; color: #1a3c6e; }

        /* ── TANDA TANGAN ── */
        .signature { display: table; width: 100%; margin-top: 14px; }
        .sig-cell  { display: table-cell; width: 33%; text-align: center; font-size: 8.5px; }
        .sig-space { height: 30px; }
        .sig-line  { border-top: 1px solid #333; margin-top: 2px; font-weight: bold; }

        /* ── FOOTER ── */
        .footer { margin-top: 8px; border-top: 1px solid #c8d8f0; padding-top: 4px; font-size: 7.5px; color: #999; text-align: center; }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="header">
    <div class="header-left">
        <div class="logo-box">
            <span class="logo-badge">BCS Logistics</span>
            <span class="logo-text">PT. Buana Centra Swakarsa</span>
        </div>
    </div>
    <div class="header-right">
        <div class="company-name">PT. Buana Centra Swakarsa</div>
        <div class="company-sub">Rincian Gaji Karyawan</div>
        <div class="doc-title">Periode: {{ $periodLabel }}</div>
        <div class="confidential">⬛ CONFIDENTIAL</div>
    </div>
</div>

{{-- INFO KARYAWAN --}}
<div class="info-section">
    <table class="info-table">
        <tr>
            <td class="info-label">NIK</td>
            <td class="info-colon">:</td>
            <td>{{ $slip->employee_nik ?? '-' }}</td>
            <td class="info-label">Periode</td>
            <td class="info-colon">:</td>
            <td>{{ $slip->period->format('n - Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Nama</td>
            <td class="info-colon">:</td>
            <td>{{ $slip->employee_name }}</td>
            <td class="info-label">Hari Kerja</td>
            <td class="info-colon">:</td>
            <td>{{ $slip->work_days }}</td>
        </tr>
        <tr>
            <td class="info-label">Jabatan</td>
            <td class="info-colon">:</td>
            <td>{{ $slip->employee_position ?? '-' }}</td>
            <td class="info-label">Bank</td>
            <td class="info-colon">:</td>
            <td>{{ $slip->bank_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Divisi</td>
            <td class="info-colon">:</td>
            <td>{{ $slip->employee_division ?? '-' }}</td>
            <td class="info-label">No. Rekening</td>
            <td class="info-colon">:</td>
            <td>{{ $slip->account_number ?? '-' }}</td>
        </tr>
    </table>
</div>

{{-- TABEL PENDAPATAN & POTONGAN --}}
<div class="salary-wrap">

    {{-- KIRI: PENDAPATAN --}}
    <div class="col-left">
        <div class="section-title">I. Pendapatan Tetap</div>
        <table class="salary">
            <tr><td class="label">a &nbsp; Upah Pokok</td>                <td class="amount">{{ number_format($slip->basic_salary, 0, ',', '.') }}</td></tr>
            <tr><td class="label">b &nbsp; Tunj. Kontribusi Profesi</td>  <td class="amount">{{ number_format($slip->professional_allowance, 0, ',', '.') }}</td></tr>
            <tr><td class="label">c &nbsp; Tunj. Prestasi</td>            <td class="amount">{{ number_format($slip->performance_allowance, 0, ',', '.') }}</td></tr>
            <tr><td class="label">d &nbsp; Tunj. Jabatan</td>             <td class="amount">{{ number_format($slip->position_allowance, 0, ',', '.') }}</td></tr>
        </table>

        <div class="section-title">II. Pendapatan Variabel</div>
        <table class="salary">
            <tr><td class="label">a &nbsp; Makan</td>              <td class="amount">{{ number_format($slip->meal_allowance, 0, ',', '.') }}</td></tr>
            <tr><td class="label">b &nbsp; Transport</td>          <td class="amount">{{ number_format($slip->transport_allowance, 0, ',', '.') }}</td></tr>
            <tr><td class="label">c &nbsp; Tunj. Relokasi</td>     <td class="amount">{{ number_format($slip->relocation_allowance, 0, ',', '.') }}</td></tr>
            <tr><td class="label">d &nbsp; Tunj. Skill</td>        <td class="amount">{{ number_format($slip->skill_allowance, 0, ',', '.') }}</td></tr>
            <tr><td class="label">e &nbsp; Tunj. Lain-lain</td>    <td class="amount">{{ number_format($slip->other_allowance, 0, ',', '.') }}</td></tr>
            <tr><td class="label">f &nbsp; Incentive tgl 10</td>   <td class="amount">{{ number_format($slip->incentive_10th, 0, ',', '.') }}</td></tr>
            <tr><td class="label">g &nbsp; Tunj. Komunikasi</td>   <td class="amount">{{ number_format($slip->communication_allowance, 0, ',', '.') }}</td></tr>
            <tr><td class="label">h &nbsp; Insentif</td>           <td class="amount">{{ number_format($slip->incentive, 0, ',', '.') }}</td></tr>
            <tr>
                <td class="label">i &nbsp; Shift <span class="meta">({{ $slip->shift_count }})</span></td>
                <td class="amount">{{ number_format($slip->shift_allowance, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">j &nbsp; Lembur <span class="meta">({{ $slip->overtime_hours }})</span></td>
                <td class="amount">{{ number_format($slip->overtime_allowance, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">k &nbsp; Khk <span class="meta">({{ $slip->khk_count }})</span></td>
                <td class="amount">{{ number_format($slip->khk_allowance, 0, ',', '.') }}</td>
            </tr>
            <tr class="subtotal">
                <td class="label">PENERIMAAN BRUTO</td>
                <td class="amount">{{ number_format($slip->gross_salary, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    {{-- KANAN: POTONGAN --}}
    <div class="col-right">
        <div class="section-title">III. Potongan</div>
        <table class="salary">
            <tr><td class="label">a &nbsp; Zakat, Infak, Sodaqoh</td>  <td class="amount">{{ number_format($slip->zakat, 0, ',', '.') }}</td></tr>
            <tr><td class="label">b &nbsp; Pajak/PPH.21</td>           <td class="amount">{{ number_format($slip->tax, 0, ',', '.') }}</td></tr>
            <tr><td class="label">c &nbsp; BPJS</td>                   <td class="amount">{{ number_format($slip->bpjs, 0, ',', '.') }}</td></tr>
            <tr><td class="label">d &nbsp; Iuran SP-BCS</td>           <td class="amount">{{ number_format($slip->union_fee, 0, ',', '.') }}</td></tr>
            <tr>
                <td class="label">e &nbsp; Alpa/Absen <span class="meta">({{ $slip->absence_days }})</span></td>
                <td class="amount">{{ number_format($slip->absence_deduction, 0, ',', '.') }}</td>
            </tr>
            <tr><td class="label">f &nbsp; Koperasi</td>               <td class="amount">{{ number_format($slip->cooperative, 0, ',', '.') }}</td></tr>
            <tr><td class="label">g &nbsp; Angsuran BPR</td>           <td class="amount">{{ number_format($slip->bpr_installment, 0, ',', '.') }}</td></tr>
            <tr><td class="label">h &nbsp; Lain-lain</td>              <td class="amount">{{ number_format($slip->other_deduction, 0, ',', '.') }}</td></tr>

            {{-- Dynamic deductions (kasbon, dll) --}}
            @foreach($dynamicDeductions as $ded)
            <tr>
                <td class="label">&nbsp;&nbsp; {{ $ded['label'] }} @if(!empty($ded['meta']))<span class="meta">{{ $ded['meta'] }}</span>@endif</td>
                <td class="amount">{{ number_format($ded['amount'], 0, ',', '.') }}</td>
            </tr>
            @endforeach

            <tr class="subtotal">
                <td class="label">TOTAL POTONGAN</td>
                <td class="amount">{{ number_format($totalDeductions, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- SUMMARY THP --}}
<div class="summary-box">
    <table>
        <tr>
            <td class="s-label">Penerimaan Bruto</td>
            <td class="s-val">Rp {{ number_format($slip->gross_salary, 0, ',', '.') }}</td>
            <td style="width:20px"></td>
            <td class="s-label">Total Potongan</td>
            <td class="s-val">Rp {{ number_format($totalDeductions, 0, ',', '.') }}</td>
        </tr>
        <tr class="thp">
            <td class="s-label" colspan="2">TOTAL PENERIMAAN (Take Home Pay)</td>
            <td></td>
            <td class="s-label">Rp</td>
            <td class="s-val">{{ number_format($netSalary, 0, ',', '.') }}</td>
        </tr>
    </table>
</div>

{{-- TERBILANG --}}
@if($slip->salary_in_words)
<div class="words-box">
    <span class="words-label">Terbilang:</span> {{ $slip->salary_in_words }}
</div>
@endif

{{-- TANDA TANGAN --}}
<div class="signature">
    <div class="sig-cell">
        <div>Diketahui,</div>
        <div class="sig-space"></div>
        <div class="sig-line">PAYROLL OFFICER</div>
    </div>
    <div class="sig-cell"></div>
    <div class="sig-cell">
        <div>Penerima,</div>
        <div class="sig-space"></div>
        <div class="sig-line">{{ strtoupper($slip->employee_name) }}</div>
    </div>
</div>

{{-- FOOTER --}}
<div class="footer">
    Dokumen ini bersifat rahasia (Confidential). Dicetak oleh Sistem Penggajian BCS &bull; {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
