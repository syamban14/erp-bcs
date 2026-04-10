<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftSwapRequestResource\Pages;
use App\Models\ShiftSwapRequest;
use App\Services\ShiftSwapService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Forms;

use App\Filament\Concerns\FiltersBySubordinates;

class ShiftSwapRequestResource extends Resource
{
    use FiltersBySubordinates;
    
    // Spesifik untuk shift swap, filternya berdasarkan requester_id
    protected static string $subordinateFilterColumn = 'requester_id';

    protected static ?string $model = ShiftSwapRequest::class;
    
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';
    
    protected static ?string $navigationLabel = 'Shift Swap Requests';
    
    public static function getNavigationGroup(): ?string
    {
        return 'Approvals';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requester')
                    ->searchable(),
                Tables\Columns\TextColumn::make('requester_date')
                    ->label('Date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('requester_shift_code')
                    ->label('Shift (Req)')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('target.name')
                    ->label('Target Employee')
                    ->searchable(),
                Tables\Columns\TextColumn::make('target_date')
                    ->label('Date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('target_shift_code')
                    ->label('Shift (Tar)')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('approval_progress')
                    ->label('Approval Stage')
                    ->getStateUsing(function ($record) {
                        return $record->approval_progress_label;
                    })
                    ->badge()
                    ->color(fn ($record) => match ($record->status) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default    => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Shift Swap')
                    ->modalDescription(fn ($record) => "Approve this shift swap request? Current stage: " . ($record->approval_progress_label ?? ''))
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->visible(fn ($record) => $record->status === 'pending' && $record->canBeApprovedBy(auth()->user()))
                    ->action(function ($record, array $data) {
                        $wasLevel4 = ($record->current_approval_level ?? 1) >= count(\App\Models\ApprovalFlow::LEVEL_ROLES);
                        $record->approve(auth()->user(), $data['notes'] ?? null);

                        Notification::make()
                            ->title($wasLevel4 ? '✅ Shift Swap Fully Approved & Executed!' : '✅ Approved — Forwarded to next level')
                            ->success()
                            ->send();
                    }),
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
                    ->modalHeading('Reject Shift Swap')
                    ->visible(fn ($record) => $record->status === 'pending' && $record->canBeRejectedBy(auth()->user()))
                    ->action(function ($record, array $data) {
                        $record->reject(auth()->user(), $data['reason']);

                        Notification::make()
                            ->title('❌ Shift Swap Rejected')
                            ->danger()
                            ->send();
                    }),
                \Filament\Actions\Action::make('history')
                    ->label('Approval Chain')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading('Approval Chain History')
                    ->modalContent(function ($record) {
                        $flows   = $record->approvalFlows()->with('approver')->get();
                        $current = $record->current_approval_level ?? 1;
                        $max     = count(\App\Models\ApprovalFlow::LEVEL_ROLES);

                        return view('filament.approval-chain-modal', compact('flows', 'current', 'max', 'record'));
                    })
                    ->modalSubmitAction(false),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageShiftSwapRequests::route('/'),
        ];
    }
}
