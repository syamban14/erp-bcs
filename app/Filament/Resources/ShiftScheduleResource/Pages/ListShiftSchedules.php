<?php

namespace App\Filament\Resources\ShiftScheduleResource\Pages;

use App\Filament\Resources\ShiftScheduleResource;
use App\Services\RosterImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListShiftSchedules extends ListRecords
{
    protected static string $resource = ShiftScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('export_roster')
                ->label('Export Roster CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $schedules = \App\Models\ShiftSchedule::with(['user', 'shiftCode'])
                            ->orderBy('user_id')
                            ->orderBy('date')
                            ->get();
                        
                        $handle = fopen('php://output', 'w');
                        
                        // Header CSV
                        fputcsv($handle, ['Karyawan', 'Tanggal', 'Kode Shift', 'Nama Shift', 'Jam Masuk', 'Jam Pulang']);
                        
                        // Data rows
                        foreach ($schedules as $schedule) {
                            fputcsv($handle, [
                                $schedule->user?->name ?? '',
                                $schedule->date?->format('Y-m-d') ?? '',
                                $schedule->shift_code,
                                $schedule->shiftCode?->name ?? '',
                                $schedule->shiftCode?->time_in ?? '',
                                $schedule->shiftCode?->time_out ?? '',
                            ]);
                        }
                        
                        fclose($handle);
                    }, 'roster-export-' . now()->format('Y-m-d') . '.csv');
                }),
            Actions\Action::make('import_roster')
                ->label('Import Roster')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label('Upload File Roster (.xlsx atau .csv)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                        ])
                        ->disk('public')
                        ->directory('roster-imports')
                        ->storeFileNamesIn('original_filename')
                        ->required()
                        ->helperText('Upload file roster langsung (.xlsx) atau CSV jika sudah dikonversi.'),
                    Select::make('month')
                        ->label('Bulan')
                        ->options([
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
                            4 => 'April', 5 => 'Mei', 6 => 'Juni',
                            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
                            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                        ])
                        ->default(now()->month)
                        ->required(),
                    TextInput::make('year')
                        ->label('Tahun')
                        ->numeric()
                        ->default(now()->year)
                        ->minValue(2020)
                        ->maxValue(2030)
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $fileName = $data['file'];
                        
                        // File disimpan di storage/app/public/roster-imports/
                        $filePath = \Storage::disk('public')->path($fileName);
                        
                        \Log::info("Import Roster - File: {$fileName}, Path: {$filePath}");
                        
                        if (!file_exists($filePath)) {
                            \Log::error("File not found at: {$filePath}");
                            
                            Notification::make()
                                ->title('File tidak ditemukan')
                                ->body("Path: {$filePath}")
                                ->danger()
                                ->send();
                            return;
                        }

                        // Import using service (auto-detect xlsx or csv)
                        $service = new RosterImportService();
                        $results = $service->importFromFile(
                            $filePath,
                            $data['month'],
                            $data['year']
                        );

                        // Show success notification
                        if ($results['success'] > 0) {
                            Notification::make()
                                ->title("Berhasil import {$results['success']} jadwal shift")
                                ->success()
                                ->send();
                        }

                        // Show warnings if any
                        if (!empty($results['warnings'])) {
                            $warningText = implode("\n", array_slice($results['warnings'], 0, 5));
                            if (count($results['warnings']) > 5) {
                                $warningText .= "\n... dan " . (count($results['warnings']) - 5) . " warning lainnya";
                            }
                            
                            Notification::make()
                                ->title(count($results['warnings']) . ' warning')
                                ->body($warningText)
                                ->warning()
                                ->send();
                        }

                        // Show errors if any
                        if (!empty($results['errors'])) {
                            $errorText = implode("\n", array_slice($results['errors'], 0, 5));
                            if (count($results['errors']) > 5) {
                                $errorText .= "\n... dan " . (count($results['errors']) - 5) . " error lainnya";
                            }
                            
                            Notification::make()
                                ->title(count($results['errors']) . ' error')
                                ->body($errorText)
                                ->danger()
                                ->send();
                        }

                        // If no success at all
                        if ($results['success'] === 0) {
                            Notification::make()
                                ->title('Import gagal')
                                ->body('Tidak ada data yang berhasil diimport. Periksa format CSV dan data karyawan.')
                                ->danger()
                                ->send();
                        }

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error saat import')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
