<?php

namespace App\Filament\Resources\LeaveBalances\Pages;

use App\Filament\Resources\LeaveBalances\LeaveBalanceResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;
use App\Models\MKaryawan;
use App\Models\LeaveBalance;
use ZipArchive;

class ManageLeaveBalances extends ManageRecords
{
    protected static string $resource = LeaveBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_cuti')
                ->label('Upload Saldo Cuti')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('attachment')
                        ->label('File Excel Saldo Cuti (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required()
                        ->storeFiles(true),
                ])
                ->action(function (array $data) {
                    $attachment = is_array($data['attachment']) ? array_values($data['attachment'])[0] : $data['attachment'];
                    
                    // Coba cari di disk public
                    $path = \Illuminate\Support\Facades\Storage::disk('public')->path($attachment);
                    
                    if (!file_exists($path)) {
                        // Coba cari di disk local
                        $pathLocal = \Illuminate\Support\Facades\Storage::disk('local')->path($attachment);
                        if (file_exists($pathLocal)) {
                            $path = $pathLocal;
                        } else {
                            Notification::make()->title('Gagal: File tidak ditemukan.')->body("Path: {$path}")->danger()->send();
                            return;
                        }
                    }

                    $zip = new ZipArchive();
                    if ($zip->open($path) !== true) {
                        Notification::make()->title('Gagal membaca format file XLSX.')->danger()->send();
                        return;
                    }

                    // Extract Shared Strings
                    $sharedStrings = [];
                    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                    if ($ssXml) {
                        $ssXml = preg_replace('/<\?[a-zA-Z].*?\?>/s', '', $ssXml);
                        $ssXml = preg_replace('/\s+xmlns(?::[a-zA-Z0-9_]+)?="[^"]*"/', '', $ssXml);
                        $ss = simplexml_load_string($ssXml, 'SimpleXMLElement', LIBXML_NOERROR);
                        if ($ss) {
                            foreach ($ss->si as $si) {
                                $t = '';
                                foreach ($si->xpath('.//t') as $node) $t .= (string)$node;
                                $sharedStrings[] = $t;
                            }
                        }
                    }

                    // Extract first worksheet
                    $sheetXml = null;
                    for ($i = 1; $i <= 5; $i++) {
                        $sheetXml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
                        if ($sheetXml) break;
                    }
                    $zip->close();
                    
                    if (!$sheetXml) {
                        Notification::make()->title('Gagal: Worksheet tidak ditemukan.')->danger()->send();
                        return;
                    }

                    $sheetXml = preg_replace('/<\?[a-zA-Z].*?\?>/s', '', $sheetXml);
                    $sheetXml = preg_replace('/\s+xmlns(?::[a-zA-Z0-9_]+)?="[^"]*"/', '', $sheetXml);
                    $sheet = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NOERROR);

                    $fnColIndex = function($ref) {
                        preg_match('/^([A-Za-z]+)/', $ref, $m);
                        $col = strtoupper($m[1] ?? 'A');
                        $idx = 0;
                        for ($i = 0; $i < strlen($col); $i++) $idx = $idx * 26 + (ord($col[$i]) - 64);
                        return $idx - 1;
                    };

                    $updated = 0;
                    $failed = 0;
                    $rowNum = 0;

                    foreach ($sheet->xpath('//row') as $row) {
                        $rowNum++;
                        // Baris data mulai dari baris 3 (karena baris 1-2 header)
                        if ($rowNum < 3) continue;

                        $r = [];
                        foreach ($row->xpath('c') as $c) {
                            $ci = $fnColIndex((string)($c['r'] ?? ''));
                            while (count($r) < $ci) $r[] = null;
                            $type = (string)($c['t'] ?? '');
                            $v = (string)($c->v ?? '');
                            $r[] = $type === 's' ? ($sharedStrings[(int)$v] ?? '') : ($v !== '' ? $v : null);
                        }

                        // Pastikan ada Payroll ID (Kolom index 1)
                        $payrollId = trim((string)($r[1] ?? ''));
                        if (!$payrollId) continue;

                        $jatahCutiStr = trim((string)($r[8] ?? ''));
                        $jatahCuti = is_numeric($jatahCutiStr) ? (int)$jatahCutiStr : null;

                        // Konversi Hire Date dari serial excel (Kolom index 5)
                        $hireDateSerial = trim((string)($r[5] ?? ''));
                        $hireDate = null;
                        if (is_numeric($hireDateSerial)) {
                            // Convert Excel serial date to PHP date
                            $unixDate = ($hireDateSerial - 25569) * 86400;
                            $hireDate = gmdate('Y-m-d', $unixDate);
                        }

                        // Cocokkan melalui pgsql_master m_karyawan
                        $karyawan = MKaryawan::where('payroll_id', $payrollId)->first();
                        if (!$karyawan) {
                            $failed++;
                            continue;
                        }

                        DB::beginTransaction();
                        try {
                            // 1. Update tgl_masuk agar command leave:allocate-anniversary berjalan mulus
                            if ($hireDate) {
                                $karyawan->tgl_masuk = $hireDate;
                                $karyawan->save();
                            }

                            // 2. Timpa quota = $jatahCuti & reset used = 0
                            if ($jatahCuti !== null && $karyawan->presensiAccount) {
                                LeaveBalance::updateOrCreate(
                                    [
                                        'user_id' => $karyawan->presensiAccount->id,
                                        'year' => date('Y')
                                    ],
                                    [
                                        'quota' => $jatahCuti,
                                        'used' => 0
                                    ]
                                );
                            }
                            DB::commit();
                            $updated++;
                        } catch (\Exception $e) {
                            DB::rollBack();
                            $failed++;
                        }
                    }

                    Notification::make()
                        ->title('Sinkronisasi Cuti Selesai')
                        ->body("Data Karyawan diupdate: {$updated}<br>Gagal / Karyawan tdk ditemukan: {$failed}")
                        ->success()
                        ->send();
                }),
                
            CreateAction::make()
                ->label('Tambah Quota Cuti'),
        ];
    }
}
