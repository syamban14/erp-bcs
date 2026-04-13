<?php

namespace App\Filament\Resources\SalarySlips\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalarySlipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('employee_division')
                    ->label('Divisi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Periode Gajian')
                    ->date('F Y')
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Diunggah Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('has_pdf')
                    ->label('Memiliki File PDF')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->whereNotNull('pdf_path')),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('download_pdf')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (\App\Models\SalarySlip $record): ?string => $record->pdf_path ? asset('storage/' . $record->pdf_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (\App\Models\SalarySlip $record): bool => (bool)$record->pdf_path),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
