<?php

namespace App\Filament\Resources\SalarySlips\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SalarySlipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('Informasi Slip Gaji')
                    ->schema([
                        \Filament\Forms\Components\Select::make('user_id')
                            ->label('Nama Karyawan')
                            ->options(\App\Models\MPresensi::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live() // Update related properties when changed
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $user = \App\Models\MPresensi::with('karyawan.department')->find($state);
                                    if ($user && $user->karyawan) {
                                        $set('employee_name', $user->karyawan->nama_karyawan);
                                        $set('employee_nik', $user->karyawan->payroll_id);
                                        $set('employee_position', $user->karyawan->department ? $user->karyawan->department->dept_name : '-');
                                        $set('employee_division', $user->karyawan->department ? $user->karyawan->department->div_name : '-');
                                    }
                                }
                            }),
                            
                        \Filament\Forms\Components\DatePicker::make('period')
                            ->label('Tgl Gajian (Bulan/Tahun)')
                            ->required()
                            ->native(false)
                            ->displayFormat('F Y')
                            ->format('Y-m-d')
                            ->default(now()),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Relasi Autentik (Terisi Otomatis)')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('employee_name')
                            ->label('Nama Karyawan (Sistem)')
                            ->readOnly(),
                        \Filament\Forms\Components\TextInput::make('employee_division')
                            ->label('Divisi')
                            ->readOnly(),
                        \Filament\Forms\Components\Hidden::make('employee_nik'),
                        \Filament\Forms\Components\Hidden::make('employee_position'),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Dokumen Upload')
                    ->schema([
                        \Filament\Forms\Components\FileUpload::make('pdf_path')
                            ->label('Format PDF (Slip Gaji)')
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('payslips')
                            ->preserveFilenames()
                            ->required()
                            ->columnSpanFull(),
                            
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Catatan Tambahan (Opsional)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
