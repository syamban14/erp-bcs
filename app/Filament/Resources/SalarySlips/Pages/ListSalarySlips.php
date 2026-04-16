<?php

namespace App\Filament\Resources\SalarySlips\Pages;

use App\Filament\Resources\SalarySlips\SalarySlipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalarySlips extends ListRecords
{
    protected static string $resource = SalarySlipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('import_excel')
                ->label('📥 Import Massal (Excel)')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('File Epayslip (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                        ->required()
                        ->disk('local')
                        ->directory('imports/salary_slips')
                        ->helperText('Unggah Excel mentah payroll perusahaan di sini. Sistem akan otomatis menyortir dan mendistribusikannya ke seluruh riwayat slip gaji karyawan terkait.'),
                ])
                ->action(function (array $data) {
                    try {
                        // Ambil path absolut file dari disk 'local'
                        $relativePath = is_array($data['file']) ? reset($data['file']) : $data['file'];
                        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($relativePath);
                        
                        if (!file_exists($path)) {
                            throw new \RuntimeException("File tidak ditemukan di disk: {$path}");
                        }
                        
                        $count = (new \App\Imports\SalarySlipsImport)->import($path);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Import Slip Gaji Selesai!')
                            ->body("{$count} data slip gaji berhasil diimpor dan didistribusikan ke seluruh karyawan.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Import Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
