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

class ShiftSwapRequestResource extends Resource
{
    protected static ?string $model = ShiftSwapRequest::class;
    
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';
    
    protected static ?string $navigationLabel = 'Shift Swap Requests';
    
    public static function getNavigationGroup(): ?string
    {
        return 'Shift Management';
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
                    ->label('Shift')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('target.name')
                    ->label('Target')
                    ->searchable(),
                Tables\Columns\TextColumn::make('target_date')
                    ->label('Date')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('target_shift_code')
                    ->label('Shift')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i'),
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
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Shift Swap')
                    ->modalDescription('Shift akan otomatis ditukar. Lanjutkan?')
                    ->action(function ($record) {
                        try {
                            app(ShiftSwapService::class)->approveSwap($record->id, auth()->id());
                            Notification::make()
                                ->success()
                                ->title('Shift swap approved')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error: ' . $e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Reject')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(ShiftSwapService::class)->rejectSwap(
                                $record->id,
                                auth()->id(),
                                $data['rejection_reason']
                            );
                            Notification::make()
                                ->success()
                                ->title('Shift swap rejected')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error: ' . $e->getMessage())
                                ->send();
                        }
                    }),
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
