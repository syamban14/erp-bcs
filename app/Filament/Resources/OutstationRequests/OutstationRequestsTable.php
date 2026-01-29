<?php
namespace App\Filament\Resources\OutstationRequests\Tables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
class OutstationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable(),
                BadgeColumn::make('task_type')
                    ->label('Jenis Tugas')
                    ->colors([
                        'info' => 'Perjalanan Dinas',
                        'success' => 'Pelatihan',
                    ]),
                TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->date('d M Y'),
                TextColumn::make('end_date')
                    ->label('Tanggal Selesai')
                    ->date('d M Y'),
                TextColumn::make('location')
                    ->label('Lokasi')
                    ->limit(30)
                    ->searchable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'approved_manager',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'pending' => 'Menunggu Manager',
                        'approved_manager' => 'Menunggu Admin',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    }),
                TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime('d M Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Menunggu Manager',
                        'approved_manager' => 'Menunggu Admin',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
                SelectFilter::make('task_type')
                    ->label('Jenis Tugas')
                    ->options([
                        'Perjalanan Dinas' => 'Perjalanan Dinas',
                        'Pelatihan' => 'Pelatihan',
                    ]),
            ])
            ->recordActions([
                Action::make('view_details')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detail Pengajuan Tugas Luar')
                    ->modalContent(fn ($record) => view('filament.outstation-detail', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                    
                Action::make('approve_manager')
                    ->label('Approve (Manager)')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Pengajuan (Manager)')
                    ->modalDescription('Anda yakin ingin menyetujui pengajuan ini sebagai Manager?')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved_manager',
                            'manager_approved_by' => auth()->id(),
                            'manager_approved_at' => now(),
                        ]);
                    })
                    ->successNotificationTitle('Pengajuan berhasil disetujui oleh Manager'),
                    
                Action::make('approve_admin')
                    ->label('Approve (Admin/HRD)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'approved_manager')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Final (Admin/HRD)')
                    ->modalDescription('Anda yakin ingin menyetujui pengajuan ini sebagai Admin/HRD?')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'admin_approved_by' => auth()->id(),
                            'admin_approved_at' => now(),
                        ]);
                    })
                    ->successNotificationTitle('Pengajuan berhasil disetujui (Final)'),
                    
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, ['pending', 'approved_manager']))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                    })
                    ->successNotificationTitle('Pengajuan ditolak'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}