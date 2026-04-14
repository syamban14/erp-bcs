<?php

namespace App\Filament\Resources\SalarySlips\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SalarySlipInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('employee_name')
                    ->label('Nama Karyawan')
                    ->weight('bold'),
                TextEntry::make('employee_nik')
                    ->label('NIK'),
                TextEntry::make('employee_division')
                    ->label('Divisi'),
                TextEntry::make('employee_position')
                    ->label('Posisi / Jabatan'),
                TextEntry::make('period')
                    ->label('Periode Gajian')
                    ->date('F Y'),
                TextEntry::make('pdf_path')
                    ->label('Dokumen Slip Gaji (PDF)')
                    ->formatStateUsing(fn ($state) => $state ? 'Terlampir' : 'Tidak Ada File')
                    ->url(fn (\App\Models\SalarySlip $record) => $record->pdf_path ? url('/api/v1/salaries/' . $record->id . '/export') : null, true)
                    ->color('success')
                    ->icon('heroicon-o-document-text'),
                TextEntry::make('created_at')
                    ->label('Waktu Upload')
                    ->dateTime('d M Y, H:i'),
                TextEntry::make('notes')
                    ->label('Catatan Tambahan')
                    ->columnSpanFull()
                    ->placeholder('Tidak ada catatan.'),
            ]);
    }
}
