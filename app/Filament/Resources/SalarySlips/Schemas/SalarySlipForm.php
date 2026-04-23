<?php

namespace App\Filament\Resources\SalarySlips\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalarySlipForm
{
    // ─────────────────────────────────────────────────────────
    // Field yang masuk ke BRUTO (pendapatan)
    // ─────────────────────────────────────────────────────────
    private static array $earningFields = [
        'basic_salary', 'professional_allowance', 'performance_allowance',
        'position_allowance', 'meal_allowance', 'transport_allowance',
        'relocation_allowance', 'skill_allowance', 'communication_allowance',
        'other_allowance', 'incentive', 'incentive_10th',
        'overtime_allowance', 'khk_allowance',
    ];

    // ─────────────────────────────────────────────────────────
    // Field yang masuk ke POTONGAN
    // ─────────────────────────────────────────────────────────
    private static array $deductionFields = [
        'zakat', 'tax', 'bpjs', 'union_fee',
        'absence_deduction', 'cooperative', 'bpr_installment', 'other_deduction',
    ];

    // ─────────────────────────────────────────────────────────
    // Fungsi rekalkukasi (dipanggil oleh setiap field yg live)
    // ─────────────────────────────────────────────────────────
    private static function recalculate($get, $set): void
    {
        $gross = 0;
        foreach (self::$earningFields as $field) {
            $gross += (float)($get($field) ?? 0);
        }

        $deductions = 0;
        foreach (self::$deductionFields as $field) {
            $deductions += (float)($get($field) ?? 0);
        }

        $net = $gross - $deductions;

        $set('gross_salary',     round($gross, 2));
        $set('total_deductions', round($deductions, 2));
        $set('net_salary',       round($net, 2));
    }

    // ─────────────────────────────────────────────────────────
    // Helper: buat TextInput numerik dengan live + recalculate
    // ─────────────────────────────────────────────────────────
    private static function moneyInput(string $name, string $label, string $prefix = 'Rp'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->prefix($prefix)
            ->default(0)
            ->live(debounce: 600)
            ->afterStateUpdated(function ($get, $set) {
                self::recalculate($get, $set);
            });
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([

                // ── Identitas ──────────────────────────────────────────
                Section::make('Identitas Karyawan')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Akun Karyawan (Mobile)')
                            ->options(\App\Models\MPresensi::query()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $user = \App\Models\MPresensi::with('karyawan.division')->find($state);
                                    if ($user && $user->karyawan) {
                                        $set('employee_name',     $user->karyawan->nama_karyawan);
                                        $set('employee_nik',      $user->karyawan->payroll_id);
                                        $set('employee_position', $user->karyawan->title ?? '-');
                                        $set('employee_division', optional($user->karyawan->division)->div_name ?? '-');
                                    }
                                }
                            }),

                        DatePicker::make('period')
                            ->label('Periode Gajian')
                            ->required()
                            ->native(false)
                            ->displayFormat('F Y')
                            ->format('Y-m-d')
                            ->default(now()),
                    ]),

                Section::make('Data Identitas (Snapshot)')
                    ->columns(2)
                    ->schema([
                        TextInput::make('employee_name')->label('Nama Karyawan')->required(),
                        TextInput::make('employee_nik')->label('NIK / Payroll ID')->required(),
                        TextInput::make('employee_position')->label('Jabatan')->nullable(),
                        TextInput::make('employee_division')->label('Cost Sales')->nullable(),
                    ]),

                // ── Tunjangan Tetap ────────────────────────────────────
                Section::make('I. Tunjangan Tetap')
                    ->columns(4)
                    ->collapsible()
                    ->schema([
                        self::moneyInput('basic_salary',            'Gaji Pokok'),
                        self::moneyInput('professional_allowance',  'Tunj. Profesi'),
                        self::moneyInput('performance_allowance',   'Tunj. Prestasi'),
                        self::moneyInput('position_allowance',      'Tunj. Jabatan'),
                    ]),

                // ── Tunjangan Variabel ────────────────────────────────
                Section::make('II. Tunjangan Variabel')
                    ->columns(4)
                    ->collapsible()
                    ->schema([
                        self::moneyInput('meal_allowance',          'Uang Makan'),
                        self::moneyInput('transport_allowance',     'Transport'),
                        self::moneyInput('relocation_allowance',    'Tunj. Relokasi'),
                        self::moneyInput('skill_allowance',         'Tunj. Skill'),
                        self::moneyInput('communication_allowance', 'Tunj. Komunikasi'),
                        self::moneyInput('other_allowance',         'Tunjangan Lain-lain'),
                        self::moneyInput('incentive',               'Insentif'),
                        self::moneyInput('incentive_10th',          'Incentive 10%'),
                    ]),

                // ── Lembur & KHK ─────────────────────────────────────
                Section::make('Lembur & KHK')
                    ->columns(4)
                    ->collapsible()
                    ->schema([
                        TextInput::make('overtime_hours')
                            ->label('Jam Lembur')->numeric()->suffix('jam')->default(0),
                        self::moneyInput('overtime_allowance', 'Lembur (IDR)'),
                        TextInput::make('khk_count')
                            ->label('Hari KHK')->numeric()->suffix('hari')->default(0),
                        self::moneyInput('khk_allowance', 'KHK (IDR)'),
                    ]),

                // ── Potongan ─────────────────────────────────────────
                Section::make('III. Potongan')
                    ->columns(4)
                    ->collapsible()
                    ->schema([
                        self::moneyInput('zakat',             'ZIS'),
                        self::moneyInput('tax',               'Pajak PPh 21'),
                        self::moneyInput('bpjs',              'BPJS'),
                        self::moneyInput('union_fee',         'Iuran SP-BCS'),
                        self::moneyInput('absence_deduction', 'Potongan Absensi'),
                        TextInput::make('absence_days')
                            ->label('Hari Alpa')->numeric()->suffix('hari')->default(0),
                        self::moneyInput('cooperative',       'Koperasi'),
                        self::moneyInput('bpr_installment',   'Angsuran BPR'),
                        self::moneyInput('other_deduction',   'Potongan Lain-lain'),
                    ]),

                // ── Ringkasan (auto-calculated, read-only display) ────
                Section::make('Ringkasan Akhir (Dihitung Otomatis)')
                    ->columns(3)
                    ->schema([
                        TextInput::make('gross_salary')
                            ->label('Total Bruto')
                            ->prefix('Rp')
                            ->numeric()
                            ->readOnly()
                            ->default(0)
                            ->extraInputAttributes(['style' => 'background:#f0f9f0;font-weight:bold;color:#16a34a']),

                        TextInput::make('total_deductions')
                            ->label('Total Potongan')
                            ->prefix('Rp')
                            ->numeric()
                            ->readOnly()
                            ->default(0)
                            ->extraInputAttributes(['style' => 'background:#fff5f5;font-weight:bold;color:#dc2626']),

                        TextInput::make('net_salary')
                            ->label('Gaji Bersih (Take Home Pay)')
                            ->prefix('Rp')
                            ->numeric()
                            ->readOnly()
                            ->default(0)
                            ->extraInputAttributes(['style' => 'background:#eff6ff;font-weight:bold;font-size:1.1em;color:#1d4ed8']),
                    ]),

                // ── Dokumen & Catatan ────────────────────────────────
                Section::make('Dokumen & Catatan')
                    ->schema([
                        FileUpload::make('pdf_path')
                            ->label('Upload PDF Manual (Opsional)')
                            ->helperText('Kosongkan bila slip diimport dari Excel — PDF akan di-generate otomatis.')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('public')
                            ->directory('payslips')
                            ->visibility('public')
                            ->nullable()
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Catatan Tambahan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
