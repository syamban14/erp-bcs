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
                        $path = storage_path('app/' . $data['file']);
                        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\SalarySlipsImport, $path);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Import Slip Gaji Selesai!')
                            ->body('Seluruh data berhasil ditangkap dan didistribusikan ke database.')
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
