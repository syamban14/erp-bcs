<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemActivities\Pages\ManageSystemActivities;
use App\Models\MPresensi;
use Spatie\Activitylog\Models\Activity;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use Carbon\Carbon;

class SystemActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    public static function getNavigationGroup(): ?string
    {
        return 'System Monitor';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedClipboardDocumentList;
    }

    public static function getNavigationLabel(): string
    {
        return 'User Activity Log';
    }

    // Badge counter di sidebar: tampilkan jumlah log hari ini
    public static function getNavigationBadge(): ?string
    {
        $count = Activity::whereDate('created_at', today())->count();
        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && (
            strtolower(auth()->user()->role ?? '') === 'superhyperadmin' ||
            auth()->user()->id == 1
        );
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        // Map singkat nama model ke label yang lebih ramah manusia
        $modelLabel = [
            'Leave'                => '🌴 Cuti / Izin',
            'PermissionRequest'    => '📋 Izin Khusus',
            'AttendanceCorrection' => '✏️ Koreksi Absen',
            'Presence'             => '🕐 Kehadiran',
            'MPresensi'            => '👤 Akun Mobile',
            'MKaryawan'            => '🧑 Data Karyawan',
            'SalarySlip'           => '💰 Slip Gaji',
            'OvertimeRequest'      => '⏰ Lembur',
            'OutstationRequest'    => '🗺️ Tugas Luar',
            'ShiftSwapRequest'     => '🔄 Tukar Shift',
            'ShiftSchedule'        => '📅 Jadwal Shift',
            'Announcement'         => '📢 Pengumuman',
            'Loan'                 => '💳 Pinjaman',
        ];

        return $table
            ->columns([
                Split::make([
                    // Kolom 1: Event & Waktu
                    Stack::make([
                        TextColumn::make('event')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'created' => '✅ Dibuat',
                                'updated' => '✏️ Diubah',
                                'deleted' => '🗑️ Dihapus',
                                default   => ucfirst($state),
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default   => 'gray',
                            }),
                        TextColumn::make('created_at')
                            ->dateTime('d M Y, H:i:s')
                            ->description(fn (?Activity $record): string => $record ? $record->created_at->diffForHumans() : '')
                            ->size('xs')
                            ->color('gray'),
                    ])->space(1)->grow(false),

                    // Kolom 2: Pelaku & Modul
                    Stack::make([
                        TextColumn::make('causer.name')
                            ->icon('heroicon-m-user-circle')
                            ->placeholder('⚙️ System / Auto')
                            ->weight('bold')
                            ->searchable()
                            ->color('primary'),
                        TextColumn::make('subject_type')
                            ->label('Modul')
                            ->formatStateUsing(function ($state) use ($modelLabel) {
                                if (!$state) return '-';
                                $basename = basename(str_replace('\\', '/', $state));
                                return $modelLabel[$basename] ?? "📦 {$basename}";
                            })
                            ->badge()
                            ->color('gray')
                            ->size('sm'),
                    ])->space(1),

                    // Kolom 3: Deskripsi & Log Name
                    Stack::make([
                        TextColumn::make('description')
                            ->weight('medium')
                            ->color('gray')
                            ->size('sm')
                            ->searchable(),
                        TextColumn::make('log_name')
                            ->badge()
                            ->color('info')
                            ->size('xs')
                            ->formatStateUsing(fn ($state) => strtoupper($state)),
                    ])->space(1),

                    // Kolom 4: Subject ID
                    Stack::make([
                        TextColumn::make('subject_id')
                            ->label('ID Record')
                            ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-')
                            ->badge()
                            ->color('gray')
                            ->size('sm'),
                    ])->grow(false),
                ])->from('md'),

                // Panel Collapsible: Perbandingan Data Lama vs Baru
                Panel::make([
                    Stack::make([
                        // Ringkasan perubahan field (human-readable)
                        TextColumn::make('id')
                            ->label('Perubahan Field')
                            ->getStateUsing(function (?Activity $record) {
                                if (!$record || !isset($record->properties['attributes'])) return null;
                                $attrs = $record->properties['attributes'];
                                $old   = $record->properties['old'] ?? [];
                                $summary = [];
                                foreach ($attrs as $key => $newVal) {
                                    $oldVal = $old[$key] ?? null;
                                    if ($oldVal !== null && $oldVal != $newVal) {
                                        $newDisplay = is_array($newVal) ? json_encode($newVal) : $newVal;
                                        $oldDisplay = is_array($oldVal) ? json_encode($oldVal) : $oldVal;
                                        $summary[] = "• <strong>{$key}</strong>: <span style='color:#ef4444'>{$oldDisplay}</span> → <span style='color:#10b981'>{$newDisplay}</span>";
                                    } elseif ($oldVal === null) {
                                        $newDisplay = is_array($newVal) ? json_encode($newVal) : $newVal;
                                        $summary[] = "• <strong>{$key}</strong>: <span style='color:#10b981'>{$newDisplay}</span> (baru)";
                                    }
                                }
                                return empty($summary) ? null : implode('<br>', $summary);
                            })
                            ->html()
                            ->formatStateUsing(fn ($state) => $state
                                ? "<div style='background:#1f2937;padding:10px;border-radius:6px;font-size:11px;line-height:1.8;'>{$state}</div>"
                                : null
                            )
                            ->visible(fn (?Activity $record) => $record && isset($record->properties['attributes'])),

                        // Data Lama (JSON lengkap)
                        TextColumn::make('properties.old')
                            ->label('Data Lama (Old)')
                            ->getStateUsing(fn (?Activity $record) => $record && isset($record->properties['old'])
                                ? json_encode($record->properties['old'], JSON_PRETTY_PRINT)
                                : null
                            )
                            ->html()
                            ->formatStateUsing(fn ($state) =>
                                "<strong style='color:#ef4444'>❌ Data Lama:</strong>" .
                                "<pre style='background:#111827;color:#fca5a5;padding:10px;border-radius:6px;font-size:10px;max-height:150px;overflow-y:auto;'>{$state}</pre>"
                            )
                            ->visible(fn (?Activity $record) => $record && isset($record->properties['old'])),

                        // Data Baru (JSON lengkap)
                        TextColumn::make('properties.attributes')
                            ->label('Data Baru (New)')
                            ->getStateUsing(fn (?Activity $record) => $record && isset($record->properties['attributes'])
                                ? json_encode($record->properties['attributes'], JSON_PRETTY_PRINT)
                                : null
                            )
                            ->html()
                            ->formatStateUsing(fn ($state) =>
                                "<strong style='color:#10b981'>✅ Data Baru:</strong>" .
                                "<pre style='background:#111827;color:#6ee7b7;padding:10px;border-radius:6px;font-size:10px;max-height:150px;overflow-y:auto;'>{$state}</pre>"
                            )
                            ->visible(fn (?Activity $record) => $record && isset($record->properties['attributes'])),
                    ]),
                ])->collapsible(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Filter Event Type
                SelectFilter::make('event')
                    ->label('Tipe Event')
                    ->options([
                        'created' => '✅ Created (Dibuat)',
                        'updated' => '✏️ Updated (Diubah)',
                        'deleted' => '🗑️ Deleted (Dihapus)',
                    ]),

                // Filter Rentang Tanggal
                Filter::make('created_at')
                    ->label('Rentang Tanggal')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'],  fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    })
                    ->indicateUsing(function (array $data) {
                        $parts = [];
                        if ($data['from'])  $parts[] = 'Dari: ' . Carbon::parse($data['from'])->format('d M Y');
                        if ($data['until']) $parts[] = 'Sampai: ' . Carbon::parse($data['until'])->format('d M Y');
                        return implode(' — ', $parts) ?: null;
                    }),

                // Filter Pelaku/User
                SelectFilter::make('causer_id')
                    ->label('Pelaku (User)')
                    ->options(fn () => MPresensi::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->placeholder('Semua User'),

                // Filter Modul / Model
                SelectFilter::make('subject_type')
                    ->label('Modul / Model')
                    ->options([
                        'App\Models\Leave'                => '🌴 Cuti / Izin',
                        'App\Models\PermissionRequest'    => '📋 Izin Khusus',
                        'App\Models\AttendanceCorrection' => '✏️ Koreksi Absen',
                        'App\Models\Presence'             => '🕐 Kehadiran',
                        'App\Models\MPresensi'            => '👤 Akun Mobile',
                        'App\Models\MKaryawan'            => '🧑 Data Karyawan',
                        'App\Models\SalarySlip'           => '💰 Slip Gaji',
                        'App\Models\OvertimeRequest'      => '⏰ Lembur',
                        'App\Models\OutstationRequest'    => '🗺️ Tugas Luar',
                        'App\Models\ShiftSwapRequest'     => '🔄 Tukar Shift',
                        'App\Models\ShiftSchedule'        => '📅 Jadwal Shift',
                        'App\Models\Announcement'         => '📢 Pengumuman',
                    ])
                    ->searchable()
                    ->placeholder('Semua Modul'),
            ])
            ->recordActions([
                DeleteAction::make()->label('Hapus')->icon('heroicon-m-trash'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Hapus Terpilih'),
                ]),
            ])
            ->headerActions([
                // Tombol Purge log lama
                \Filament\Actions\Action::make('purge_old')
                    ->label('Bersihkan Log > 90 Hari')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Bersihkan Log Lama?')
                    ->modalDescription('Semua log yang berumur lebih dari 90 hari akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.')
                    ->action(function () {
                        $deleted = Activity::where('created_at', '<', now()->subDays(90))->delete();
                        \Filament\Notifications\Notification::make()
                            ->title("✅ {$deleted} log lama berhasil dibersihkan.")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Belum Ada Aktivitas')
            ->emptyStateDescription('Sistem belum merekam perubahan data apapun.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->striped()
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSystemActivities::route('/'),
        ];
    }
}
