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
                \Filament\Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Shift Swap')
                    ->visible(fn ($record) => $record->status === 'pending' && $record->canBeApprovedBy(auth()->user()))
                    ->action(function ($record) {
                        try {
                            app(\App\Services\ShiftSwapService::class)->approveSwap($record->id, auth()->id());
                            \Filament\Notifications\Notification::make()->title('✅ Shift Swap Approved & Executed!')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal Approve')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->modalHeading('Reject Shift Swap')
                    ->visible(fn ($record) => $record->status === 'pending' && $record->canBeRejectedBy(auth()->user()))
                    ->action(function ($record, array $data) {
                        try {
                            app(\App\Services\ShiftSwapService::class)->rejectSwap($record->id, auth()->id(), $data['reason']);
                            \Filament\Notifications\Notification::make()->title('❌ Shift Swap Rejected')->danger()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal Reject')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageShiftSwapRequests::route('/'),
        ];
    }
}
