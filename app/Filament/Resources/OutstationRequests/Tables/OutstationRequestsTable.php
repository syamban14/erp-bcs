<?php

namespace App\Filament\Resources\OutstationRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class OutstationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('task_type')
                    ->label('Jenis Tugas')
                    ->badge()
                    ->colors([
                        'primary' => 'Perjalanan Dinas',
                        'info' => 'Pelatihan',
                    ]),
                \Filament\Tables\Columns\TextColumn::make('start_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->description(fn ($record) => $record->start_time . ' - ' . $record->end_time)
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('location')
                    ->label('Lokasi')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->location),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'approved_manager',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'approved_manager' => 'Mgr Approved',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => $state,
                    }),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved_manager' => 'Manager Approved',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('task_type')
                    ->options([
                        'Perjalanan Dinas' => 'Perjalanan Dinas',
                        'Pelatihan' => 'Pelatihan',
                    ]),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\ViewAction::make(),
                    \Filament\Actions\EditAction::make(),
                    
                    // Approve as Manager
                    \Filament\Actions\Action::make('approve_manager')
                        ->label('Approve (Manager)')
                        ->icon('heroicon-o-check')
                        ->color('info')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'approved_manager',
                                'manager_approved_by' => auth()->id(),
                                'manager_approved_at' => now(),
                            ]);
                        }),

                    // Final Approve
                    \Filament\Actions\Action::make('approve_final')
                        ->label('Approve (Final)')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => in_array($record->status, ['pending', 'approved_manager']))
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'approved',
                                'admin_approved_by' => auth()->id(),
                                'admin_approved_at' => now(),
                                // If jumping from pending, set manager approval too? 
                                // Maybe simpler to just set admin approval and status=approved.
                            ]);
                        }),

                    // Reject
                    \Filament\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            \Filament\Forms\Components\Textarea::make('rejection_reason')
                                ->label('Alasan Penolakan')
                                ->required(),
                        ])
                        ->visible(fn ($record) => !in_array($record->status, ['approved', 'rejected']))
                        ->action(function ($record, array $data) {
                            $record->update([
                                'status' => 'rejected',
                                'rejection_reason' => $data['rejection_reason'],
                                'admin_approved_by' => auth()->id(), 
                                'admin_approved_at' => now(),
                            ]);
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
