<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; }

    .page { width: 100%; padding: 18px 24px; }

    /* Header Perusahaan */
    .header { display: flex; align-items: center; border-bottom: 3px solid #1a3a6b; padding-bottom: 10px; margin-bottom: 12px; }
    .header .logo-area { width: 70px; height: 70px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; margin-right: 14px; border-radius: 4px; overflow: hidden; }
    .header .logo-area img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .header .logo-area .no-logo { font-size: 7pt; color: #999; text-align: center; line-height: 1.4; }
    .header .company-info h1 { font-size: 14pt; font-weight: bold; color: #1a3a6b; letter-spacing: 0.5px; }
    .header .company-info p { font-size: 8pt; color: #555; margin-top: 2px; }
    .header .slip-title { margin-left: auto; text-align: right; }
    .header .slip-title h2 { font-size: 13pt; font-weight: bold; color: #1a3a6b; text-transform: uppercase; }
    .header .slip-title .period-badge { background: #1a3a6b; color: white; padding: 3px 10px; border-radius: 12px; font-size: 8pt; display: inline-block; margin-top: 4px; }

    /* Info Karyawan */
    .employee-card { background: #f0f4fb; border-left: 4px solid #1a3a6b; padding: 8px 12px; margin-bottom: 12px; border-radius: 0 4px 4px 0; display: flex; gap: 30px; }
    .employee-card .info-block label { font-size: 7.5pt; color: #666; display: block; }
    .employee-card .info-block span { font-size: 9pt; font-weight: bold; color: #1a1a1a; }

    /* Tabel Slip */
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table th { background: #1a3a6b; color: white; padding: 5px 8px; font-size: 8.5pt; text-align: left; }
    table td { padding: 4px 8px; font-size: 8.5pt; border-bottom: 1px solid #e8e8e8; }
    table tr:nth-child(even) td { background: #f9f9f9; }
    table td.amount { text-align: right; font-variant-numeric: tabular-nums; }
    table td.label { width: 55%; }
    table tfoot td { font-weight: bold; font-size: 9pt; border-top: 2px solid #1a3a6b; padding-top: 6px; }

    /* Summary Total */
    .summary-box { background: #1a3a6b; color: white; border-radius: 6px; padding: 10px 14px; margin-top: 10px; }
    .summary-box .summary-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 9pt; }
    .summary-box .summary-row.net { border-top: 1px solid rgba(255,255,255,0.3); margin-top: 5px; padding-top: 7px; }
    .summary-box .summary-row.net .label { font-size: 11pt; font-weight: bold; }
    .summary-box .summary-row.net .value { font-size: 13pt; font-weight: bold; }

    .terbilang-box { border: 1px dashed #1a3a6b; border-radius: 4px; padding: 7px 12px; margin-top: 8px; font-size: 8pt; color: #333; }
    .terbilang-box span { font-style: italic; }

    /* Status dibayarkan */
    .paid-stamp { text-align: right; margin-top: 10px; }
    .paid-stamp .badge { display: inline-block; border: 2px solid #16a34a; color: #16a34a; border-radius: 4px; padding: 3px 12px; font-size: 9pt; font-weight: bold; transform: rotate(-3deg); }

    /* Footer */
    .footer { border-top: 1px solid #ddd; margin-top: 14px; padding-top: 8px; display: flex; justify-content: space-between; font-size: 7.5pt; color: #888; }
    .footer .confidential { font-weight: bold; color: #cc3333; }

    .two-col { display: flex; gap: 10px; }
    .two-col table { flex: 1; }

    /* Divider angka */
    .section-title { font-size: 8pt; font-weight: bold; color: #555; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; padding-left: 4px; border-left: 3px solid #1a3a6b; }
</style>
</head>
<body>
<div class="page">

    {{-- HEADER --}}
    <div class="header">
        <div class="logo-area">
            @if($logo)
                <img src="{{ $logo }}" alt="Logo">
            @else
                <div class="no-logo"><strong>PT. BCS</strong></div>
            @endif
        </div>
        <div class="company-info">
            <h1>PT. BUANA CENTRA SWAKARSA</h1>
            <p>Slip Gaji Karyawan — Confidential</p>
        </div>
        <div class="slip-title">
            <h2>Slip Gaji</h2>
            <span class="period-badge">{{ $slip->period->format('F Y') }}</span>
        </div>
    </div>

    {{-- INFO KARYAWAN --}}
    <div class="employee-card">
        <div class="info-block">
            <label>Nama Karyawan</label>
            <span>{{ $slip->employee_name }}</span>
        </div>
        <div class="info-block">
            <label>NIK / Payroll ID</label>
            <span>{{ $slip->employee_nik }}</span>
        </div>
        <div class="info-block">
            <label>Jabatan</label>
            <span>{{ $slip->employee_position ?: '-' }}</span>
        </div>
        <div class="info-block">
            <label>Divisi / Departemen</label>
            <span>{{ $slip->employee_division ?: '-' }}</span>
        </div>
    </div>

    {{-- PENDAPATAN & POTONGAN --}}
    <div class="two-col">

        {{-- Pendapatan --}}
        <div>
            <div class="section-title">Pendapatan</div>
            <table>
                <thead><tr><th class="label">Komponen</th><th>Jumlah</th></tr></thead>
                <tbody>
                    <tr><td>Gaji Pokok</td><td class="amount">{{ 'Rp '.number_format($slip->basic_salary,0,',','.') }}</td></tr>
                    @if($slip->professional_allowance > 0)
                    <tr><td>Tunj. Profesi / Kontribusi</td><td class="amount">{{ 'Rp '.number_format($slip->professional_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->performance_allowance > 0)
                    <tr><td>Tunj. Prestasi</td><td class="amount">{{ 'Rp '.number_format($slip->performance_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->position_allowance > 0)
                    <tr><td>Tunj. Jabatan</td><td class="amount">{{ 'Rp '.number_format($slip->position_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->meal_allowance > 0)
                    <tr><td>Uang Makan</td><td class="amount">{{ 'Rp '.number_format($slip->meal_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->transport_allowance > 0)
                    <tr><td>Transport</td><td class="amount">{{ 'Rp '.number_format($slip->transport_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->relocation_allowance > 0)
                    <tr><td>Tunj. Relokasi</td><td class="amount">{{ 'Rp '.number_format($slip->relocation_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->skill_allowance > 0)
                    <tr><td>Tunj. Skill</td><td class="amount">{{ 'Rp '.number_format($slip->skill_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if(($slip->communication_allowance ?? 0) > 0)
                    <tr><td>Tunj. Komunikasi</td><td class="amount">{{ 'Rp '.number_format($slip->communication_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if(($slip->other_allowance ?? 0) > 0)
                    <tr><td>Tunjangan Lain-lain</td><td class="amount">{{ 'Rp '.number_format($slip->other_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if(($slip->incentive ?? 0) > 0)
                    <tr><td>Insentif</td><td class="amount">{{ 'Rp '.number_format($slip->incentive,0,',','.') }}</td></tr>
                    @endif
                    @if(($slip->overtime_allowance ?? 0) > 0)
                    <tr><td>Lembur ({{ $slip->overtime_hours ?? 0 }} jam)</td><td class="amount">{{ 'Rp '.number_format($slip->overtime_allowance,0,',','.') }}</td></tr>
                    @endif
                    @if(($slip->khk_allowance ?? 0) > 0)
                    <tr><td>KHK ({{ $slip->khk_count ?? 0 }} hari)</td><td class="amount">{{ 'Rp '.number_format($slip->khk_allowance,0,',','.') }}</td></tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total Pendapatan Kotor</td>
                        <td class="amount">{{ 'Rp '.number_format($slip->gross_salary ?: $totalEarnings, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Potongan --}}
        <div>
            <div class="section-title">Potongan</div>
            <table>
                <thead><tr><th class="label">Komponen</th><th>Jumlah</th></tr></thead>
                <tbody>
                    @if($slip->zakat > 0)
                    <tr><td>ZIS (Zakat, Infak, Shodaqoh)</td><td class="amount">{{ 'Rp '.number_format($slip->zakat,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->tax > 0)
                    <tr><td>Pajak / PPh 21</td><td class="amount">{{ 'Rp '.number_format($slip->tax,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->bpjs > 0)
                    <tr><td>BPJS</td><td class="amount">{{ 'Rp '.number_format($slip->bpjs,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->union_fee > 0)
                    <tr><td>Iuran SP-BCS</td><td class="amount">{{ 'Rp '.number_format($slip->union_fee,0,',','.') }}</td></tr>
                    @endif
                    @if(($slip->absence_deduction ?? 0) > 0)
                    <tr><td>Absensi ({{ $slip->absence_days ?? 0 }} hari alpa)</td><td class="amount">{{ 'Rp '.number_format($slip->absence_deduction,0,',','.') }}</td></tr>
                    @endif
                    @if($slip->cooperative > 0)
                    <tr><td>Koperasi</td><td class="amount">{{ 'Rp '.number_format($slip->cooperative,0,',','.') }}</td></tr>
                    @endif
                    @if(($slip->bpr_installment ?? 0) > 0)
                    <tr><td>Angsuran BPR</td><td class="amount">{{ 'Rp '.number_format($slip->bpr_installment,0,',','.') }}</td></tr>
                    @endif
                    @if(($slip->other_deduction ?? 0) > 0)
                    <tr><td>Lain-lain</td><td class="amount">{{ 'Rp '.number_format($slip->other_deduction,0,',','.') }}</td></tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total Potongan</td>
                        <td class="amount">{{ 'Rp '.number_format($slip->total_deductions, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>{{-- end two-col --}}

    {{-- RINGKASAN --}}
    <div class="summary-box">
        <div class="summary-row">
            <span>Total Pendapatan Kotor</span>
            <span>Rp {{ number_format($slip->gross_salary ?: $totalEarnings, 0, ',', '.') }}</span>
        </div>
        <div class="summary-row">
            <span>Total Potongan</span>
            <span>– Rp {{ number_format($slip->total_deductions, 0, ',', '.') }}</span>
        </div>
        <div class="summary-row net">
            <span class="label">Gaji Bersih (Take Home Pay)</span>
            <span class="value">Rp {{ number_format($slip->net_salary, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="terbilang-box">
        Terbilang: <span>{{ ucwords(strtolower($terbilang)) }}</span>
    </div>

    <div class="paid-stamp">
        <div class="badge">✓ TELAH DIBAYARKAN</div>
    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <div>
            <p>PT. Buana Centra Swakarsa</p>
            <p class="confidential">⚠ CONFIDENTIAL — Dokumen ini bersifat rahasia.</p>
        </div>
        <div style="text-align:right">
            <p>Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
            <p>Generated by myBCS Payroll System</p>
        </div>
    </div>

</div>
</body>
</html>
