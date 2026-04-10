<?php

namespace App\Filament\Resources\OutstationRequests\Tables;

use App\Models\ApprovalFlow;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Table;

class OutstationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('user_id')
                    ->label('Employee')
                    ->formatStateUsing(fn ($state) => \App\Models\MPresensi::find($state)?->name ?? '-')
                    ->searchable(query: fn ($query, $search) => $query->whereIn(
                        'user_id',
                        \App\Models\MPresensi::where('name', 'ilike', "%{$search}%")->pluck('id')
                    ))
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('task_type')
                    ->label('Task Type')
                    ->badge()
                    ->colors([
                        'primary' => 'Perjalanan Dinas',
                        'info'    => 'Pelatihan',
                    ]),

                \Filament\Tables\Columns\TextColumn::make('start_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->description(fn ($record) => $record->start_time . ' - ' . $record->end_time)
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->location),

                // ─── Approval Progress (4-level) ───────────────────────────
                \Filament\Tables\Columns\TextColumn::make('approval_progress')
                    ->label('Approval Stage')
                    ->getStateUsing(function ($record) {
                        if ($record->status === 'approved') return 'Fully Approved';
                        if ($record->status === 'rejected') {
                            $level = $record->current_approval_level ?? 1;
                            $label = ApprovalFlow::LEVEL_LABELS[$level] ?? "Level {$level}";
                            return "Rejected @ {$label}";
                        }
                        $level = $record->current_approval_level ?? 1;
                        $max   = count(ApprovalFlow::LEVEL_ROLES);
                        $label = ApprovalFlow::LEVEL_LABELS[$level] ?? "Level {$level}";
                        return "Awaiting {$label} ({$level}/{$max})";
                    })
                    ->badge()
                    ->color(fn ($record) => match ($record->status) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    }),

                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ]),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),

                \Filament\Tables\Filters\SelectFilter::make('task_type')
                    ->label('Task Type')
                    ->options([
                        'Perjalanan Dinas' => 'Perjalanan Dinas',
                        'Pelatihan'        => 'Pelatihan',
                    ]),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\ViewAction::make(),

                    // ─── APPROVE ──────────────────────────────────────────
                    \Filament\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Outstation Request')
                        ->form([
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes (optional)')
                                ->rows(2),
                        ])
                        ->visible(fn ($record) => $record->status === 'pending' && $record->canBeApprovedBy(auth()->user()))
                        ->action(function ($record, array $data) {
                            $record->approve(auth()->user(), $data['notes'] ?? null);
                            $fresh = $record->fresh();
                            $msg   = $fresh->status === 'approved'
                                ? '✅ Outstation Request Fully Approved!'
                                : '✅ Approved — Forwarded to next level';
                            Notification::make()->title($msg)->success()->send();
                        }),

                    // ─── REJECT ───────────────────────────────────────────
                    \Filament\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Rejection Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->modalHeading('Reject Outstation Request')
                        ->visible(fn ($record) => $record->status === 'pending' && $record->canBeRejectedBy(auth()->user()))
                        ->action(function ($record, array $data) {
                            $record->reject(auth()->user(), $data['reason']);
                            Notification::make()->title('❌ Request Rejected')->danger()->send();
                        }),

                    // ─── HISTORY ──────────────────────────────────────────
                    \Filament\Actions\Action::make('history')
                        ->label('Approval Chain')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->modalHeading('Approval Chain History')
                        ->modalContent(function ($record) {
                            $flows   = $record->approvalFlows()->with('approver')->get();
                            $current = $record->current_approval_level ?? 1;
                            $max     = count(ApprovalFlow::LEVEL_ROLES);
                            return view('filament.approval-chain-modal', compact('flows', 'current', 'max', 'record'));
                        })
                        ->modalSubmitAction(false),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
