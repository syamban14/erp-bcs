<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'Jadwal Cicilan';

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('installment_number')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('installment_number')
            ->columns([
                TextColumn::make('installment_number')
                    ->label('Cicilan Ke')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR'),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('paid_at')
                    ->label('Dibayar Tgl')
                    ->datetime('d M Y H:i')
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
                \Filament\Actions\Action::make('mark_as_paid')
                    ->label('Tandai Lunas')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status !== 'paid')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);
                        
                        // Check if loan is fully paid
                        $loan = $record->loan;
                        if ($loan->remaining_amount <= 0 || $loan->installments()->where('status', '!=', 'paid')->count() == 0) {
                            $loan->update(['status' => 'paid_off']);
                        } else {
                            // Update remaining amount logic if needed
                            // usually remaining amount is reduced when paid
                             $loan->decrement('remaining_amount', $record->amount);
                        }
                    }),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
