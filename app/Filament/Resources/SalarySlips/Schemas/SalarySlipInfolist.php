<?php

namespace App\Filament\Resources\SalarySlips\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class SalarySlipInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn ($state) => $state
            ? 'Rp ' . number_format((float)$state, 0, ',', '.')
            : 'Rp 0';

        return $schema
            ->components([
                // ── Info Karyawan ──────────────────────────────────────
                Section::make('Identitas Karyawan')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('employee_name')->label('Nama Karyawan')->weight('bold'),
                        TextEntry::make('employee_nik')->label('NIK / Payroll ID'),
                        TextEntry::make('employee_division')->label('Cost Sales'),
                        TextEntry::make('employee_position')->label('Jabatan'),
                        TextEntry::make('period')->label('Periode Gajian')->date('F Y'),
                        TextEntry::make('created_at')->label('Waktu Upload')->dateTime('d M Y, H:i'),
                        TextEntry::make('notes')->label('Catatan')->columnSpanFull()->placeholder('-'),
                    ]),

                // ── Gaji Pokok & Tunjangan Tetap ──────────────────────
                Section::make('I. Tunjangan Tetap')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('basic_salary')->label('Gaji Pokok')->formatStateUsing($money)->color('success'),
                        TextEntry::make('professional_allowance')->label('Tunj. Profesi')->formatStateUsing($money),
                        TextEntry::make('performance_allowance')->label('Tunj. Prestasi')->formatStateUsing($money),
                        TextEntry::make('position_allowance')->label('Tunj. Jabatan')->formatStateUsing($money),
                    ]),

                // ── Tunjangan Variabel ─────────────────────────────────
                Section::make('II. Tunjangan Variabel')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('meal_allowance')->label('Uang Makan')->formatStateUsing($money),
                        TextEntry::make('transport_allowance')->label('Transport')->formatStateUsing($money),
                        TextEntry::make('relocation_allowance')->label('Tunj. Relokasi')->formatStateUsing($money),
                        TextEntry::make('skill_allowance')->label('Tunj. Skill')->formatStateUsing($money),
                        TextEntry::make('communication_allowance')->label('Tunj. Komunikasi')->formatStateUsing($money),
                        TextEntry::make('other_allowance')->label('Lain-lain')->formatStateUsing($money),
                        TextEntry::make('incentive')->label('Insentif')->formatStateUsing($money),
                        TextEntry::make('incentive_10th')->label('Incentive 10%')->formatStateUsing($money),
                        TextEntry::make('overtime_allowance')->label('Lembur (IDR)')->formatStateUsing($money),
                        TextEntry::make('overtime_hours')->label('Jam Lembur')->suffix(' jam'),
                        TextEntry::make('khk_allowance')->label('KHK (IDR)')->formatStateUsing($money),
                        TextEntry::make('khk_count')->label('Hari KHK')->suffix(' hari'),
                    ]),

                // ── Potongan ───────────────────────────────────────────
                Section::make('III. Potongan')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('zakat')->label('ZIS')->formatStateUsing($money)->color('danger'),
                        TextEntry::make('tax')->label('Pajak PPh 21')->formatStateUsing($money)->color('danger'),
                        TextEntry::make('bpjs')->label('BPJS')->formatStateUsing($money)->color('danger'),
                        TextEntry::make('union_fee')->label('Iuran SP-BCS')->formatStateUsing($money)->color('danger'),
                        TextEntry::make('absence_deduction')->label('Potongan Absensi')->formatStateUsing($money)->color('danger'),
                        TextEntry::make('absence_days')->label('Hari Alpa')->suffix(' hari'),
                        TextEntry::make('cooperative')->label('Koperasi')->formatStateUsing($money)->color('danger'),
                        TextEntry::make('bpr_installment')->label('Angsuran BPR')->formatStateUsing($money)->color('danger'),
                        TextEntry::make('other_deduction')->label('Potongan Lain')->formatStateUsing($money)->color('danger'),
                    ]),

                // ── Ringkasan ──────────────────────────────────────────
                Section::make('Ringkasan Akhir')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('gross_salary')->label('Total Bruto')->formatStateUsing($money)->size('lg'),
                        TextEntry::make('total_deductions')->label('Total Potongan')->formatStateUsing($money)->color('danger')->size('lg'),
                        TextEntry::make('net_salary')->label('Gaji Bersih (Take Home Pay)')->formatStateUsing($money)->color('success')->weight('bold')->size('lg'),
                    ]),
            ]);
    }
}
