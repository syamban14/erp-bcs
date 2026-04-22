<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; line-height: 1.4; }

    /* DOMPDF uses default page margins. We remove width 100% to avoid overflow to the right */
    .page { padding: 5px 10px; }

    /* Fix table layout for DOMPDF compatibility */
    .header-table { border-bottom: 3px solid #1a3a6b; padding-bottom: 12px; margin-bottom: 15px; width: 100%; }
    .header-table h1 { font-size: 14pt; font-weight: bold; color: #1a3a6b; letter-spacing: 0.5px; margin: 0; }
    .header-table p { font-size: 8pt; color: #555; margin-top: 2px; margin: 0; }
    .header-table h2 { font-size: 13pt; font-weight: bold; color: #1a3a6b; text-transform: uppercase; margin: 0; }
    .period-badge { background: #1a3a6b; color: white; padding: 3px 10px; border-radius: 12px; font-size: 8pt; display: inline-block; margin-top: 6px; }

    .employee-table { background: #f0f4fb; border-collapse: separate; border-spacing: 0; border-left: 4px solid #1a3a6b; margin-bottom: 15px; width: 100%; }
    .employee-table td { padding: 10px; vertical-align: top; }
    .employee-table label { font-size: 7.5pt; color: #666; display: block; margin-bottom: 3px; }
    .employee-table span { font-size: 9pt; font-weight: bold; color: #1a1a1a; }

    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .data-table th { background: #1a3a6b; color: white; padding: 6px 8px; font-size: 8.5pt; text-align: left; border: 1px solid #1a3a6b; }
    .data-table td { padding: 6px 8px; font-size: 8.5pt; border-bottom: 1px dashed #ddd; }
    .data-table tr:nth-child(even) td { background: #fbfbfb; }
    .data-table td.amount { text-align: right; }
    .data-table td.label { width: 60%; }
    .data-table tfoot td { font-weight: bold; font-size: 9pt; border-top: 2px solid #1a3a6b; padding-top: 8px; background: #f0f4fb; }

    .summary-table { background: #1a3a6b; color: white; margin-top: 15px; width: 100%; border-collapse: collapse; }
    .summary-table td { padding: 8px 15px; font-size: 9pt; }
    .summary-table .net-row td { font-size: 11pt; font-weight: bold; padding-top: 12px; padding-bottom: 12px; }
    .summary-hr { border: 0; border-top: 1px solid rgba(255,255,255,0.3); margin: 0; }

    .terbilang-box { border: 1px dashed #1a3a6b; padding: 8px 12px; margin-top: 12px; font-size: 8.5pt; color: #333; background: #fafafa; }
    .terbilang-box span { font-style: italic; font-weight: bold; }

    .footer-table { border-top: 1px solid #ddd; margin-top: 20px; padding-top: 10px; font-size: 7.5pt; color: #888; width: 100%; }
    .confidential { font-weight: bold; color: #cc3333; }
    .section-title { font-size: 8.5pt; font-weight: bold; color: #555; text-transform: uppercase; margin-bottom: 6px; padding-left: 6px; border-left: 3px solid #1a3a6b; }
</style>
</head>
<body>
<div class="page">

    {{-- HEADER MENGGUNAKAN TABLE UNTUK DOMPDF --}}
    <table class="header-table" width="100%">
        <tr>
            @if($logo)
            <td width="100" valign="middle">
                <img src="{{ $logo }}" style="max-width: 120px; max-height: 50px; object-fit: contain;">
            </td>
            @endif
            <td valign="middle" class="company-info" style="padding-left: 10px;">
                <h1>PT. BUANA CENTRA SWAKARSA</h1>
                <p>Slip Gaji Karyawan — <span style="color:#cc3333;font-weight:bold;">Confidential</span></p>
            </td>
            <td valign="middle" align="right" width="160" class="slip-title">
                <h2>Slip Gaji</h2>
                <div class="period-badge">{{ $slip->period->format('F Y') }}</div>
            </td>
        </tr>
    </table>

    {{-- INFO KARYAWAN --}}
    <table class="employee-table">
        <tr>
            <td width="30%">
                <label>Nama Karyawan</label>
                <span>{{ strtoupper($slip->employee_name) }}</span>
            </td>
            <td width="20%">
                <label>NIK / Payroll ID</label>
                <span>{{ $slip->employee_nik }}</span>
            </td>
            <td width="25%">
                <label>Jabatan</label>
                <span>{{ strtoupper($slip->employee_position ?: '-') }}</span>
            </td>
            <td width="25%">
                <label>Divisi / Departemen</label>
                <span>{{ strtoupper($slip->employee_division ?: '-') }}</span>
            </td>
        </tr>
    </table>

    {{-- PENDAPATAN & POTONGAN --}}
    <table width="100%">
        <tr>
            {{-- KOLOM PENDAPATAN --}}
            <td width="48%" valign="top">
                <div class="section-title">Pendapatan</div>
                <table class="data-table">
                    <thead><tr><th class="label">Komponen</th><th style="text-align:right">Jumlah</th></tr></thead>
                    <tbody>
                        <tr><td>Gaji Pokok</td><td class="amount">{{ 'Rp '.number_format($slip->basic_salary,0,',','.') }}</td></tr>
                        @if($slip->professional_allowance > 0)
                        <tr><td>Tunj. Profesi / Kont.</td><td class="amount">{{ 'Rp '.number_format($slip->professional_allowance,0,',','.') }}</td></tr>
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
                        <tr><td>Tunj. Lain-lain</td><td class="amount">{{ 'Rp '.number_format($slip->other_allowance,0,',','.') }}</td></tr>
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
                            <td>Total Kotor</td>
                            <td class="amount">{{ 'Rp '.number_format($slip->gross_salary ?: $totalEarnings, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>

            <td width="4%"></td> {{-- SPACER GAP --}}

            {{-- KOLOM POTONGAN --}}
            <td width="48%" valign="top">
                <div class="section-title">Potongan</div>
                <table class="data-table">
                    <thead><tr><th class="label">Komponen</th><th style="text-align:right">Jumlah</th></tr></thead>
                    <tbody>
                        @if($slip->zakat > 0)
                        <tr><td>ZIS (Zakat/Infak)</td><td class="amount">{{ 'Rp '.number_format($slip->zakat,0,',','.') }}</td></tr>
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
                        <tr><td>Absensi ({{ $slip->absence_days ?? 0 }} hari)</td><td class="amount">{{ 'Rp '.number_format($slip->absence_deduction,0,',','.') }}</td></tr>
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
                        @foreach($slip->deductions as $deduction)
                        <tr>
                            <td>{{ $deduction->description ?? $deduction->type }}</td>
                            <td class="amount">{{ 'Rp '.number_format($deduction->amount,0,',','.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total Potongan</td>
                            <td class="amount">{{ 'Rp '.number_format($slip->total_deductions, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>
    </table>

    {{-- RINGKASAN --}}
    <table class="summary-table">
        <tr>
            <td width="70%">Total Pendapatan Kotor</td>
            <td width="30%" align="right">Rp {{ number_format($slip->gross_salary ?: $totalEarnings, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Total Potongan</td>
            <td align="right">– Rp {{ number_format($slip->total_deductions, 0, ',', '.') }}</td>
        </tr>
        <tr><td colspan="2" style="padding:0;"><hr class="summary-hr"></td></tr>
        <tr class="net-row">
            <td>Gaji Bersih (Take Home Pay)</td>
            <td align="right">Rp {{ number_format($slip->net_salary, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="terbilang-box">
        Terbilang: <span>{{ ucwords(strtolower($terbilang)) }}</span>
    </div>

    {{-- FOOTER --}}
    <table class="footer-table">
        <tr>
            <td width="50%" valign="top">
                <strong>PT. Buana Centra Swakarsa</strong><br>
                <span class="confidential">⚠ CONFIDENTIAL — Dokumen ini bersifat rahasia.</span>
            </td>
            <td width="50%" align="right" valign="top">
                Dicetak pada: {{ now()->format('d M Y H:i') }}<br>
                Generated by myBCS Payroll System
            </td>
        </tr>
    </table>

</div>
</body>
</html>
