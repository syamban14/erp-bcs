<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemActivities\Pages\ManageSystemActivities;
use Spatie\Activitylog\Models\Activity;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Filters\SelectFilter;
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

    public static function canViewAny(): bool
    {
        return auth()->check() && strtolower(auth()->user()->role) === 'superhyperadmin';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('log_name')
                            ->badge()
                            ->color('info')
                            ->weight('bold'),
                        TextColumn::make('event')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),
                    ])->space(2)->grow(false),

                    Stack::make([
                        TextColumn::make('description')
                            ->weight('bold')
                            ->searchable()
                            ->color('primary'),
                        TextColumn::make('subject_type')
                            ->label('Target Model')
                            ->color('gray')
                            ->size('sm')
                            ->formatStateUsing(fn ($state, $record) => $state ? basename(str_replace('\\', '/', $state)) . ' #' . $record->subject_id : '-'),
                    ])->space(1),

                    Stack::make([
                        TextColumn::make('causer.name')
                            ->icon('heroicon-m-user')
                            ->placeholder('System / Auto')
                            ->color('gray')
                            ->weight('medium'),
                    ])->space(1)->grow(false),
                    
                    Stack::make([
                        TextColumn::make('created_at')
                            ->dateTime('d M Y, H:i:s')
                            ->description(fn (Activity $record): string => $record->created_at->diffForHumans())
                            ->alignEnd(),
                    ])->grow(false),
                ])->from('md'),
                
                Panel::make([
                    Stack::make([
                        TextColumn::make('properties.old')
                            ->label('Data Lama (Old)')
                            ->getStateUsing(fn (Activity $record) => isset($record->properties['old']) ? json_encode($record->properties['old'], JSON_PRETTY_PRINT) : null)
                            ->html()
                            ->formatStateUsing(fn ($state) => "<strong style='color:#ef4444'>Data Lama:</strong><pre style='background: #111827; color: #fca5a5; padding: 10px; border-radius: 6px; font-size: 11px; max-height: 200px; overflow-y: auto;'>{$state}</pre>")
                            ->visible(fn (Activity $record) => isset($record->properties['old'])),
                            
                        TextColumn::make('properties.attributes')
                            ->label('Data Baru (New)')
                            ->getStateUsing(fn (Activity $record) => isset($record->properties['attributes']) ? json_encode($record->properties['attributes'], JSON_PRETTY_PRINT) : null)
                            ->html()
                            ->formatStateUsing(fn ($state) => "<strong style='color:#10b981'>Data Baru / Perubahan:</strong><pre style='background: #111827; color: #6ee7b7; padding: 10px; border-radius: 6px; font-size: 11px; max-height: 200px; overflow-y: auto;'>{$state}</pre>")
                            ->visible(fn (Activity $record) => isset($record->properties['attributes'])),
                    ]),
                ])->collapsible(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created (Dibuat)',
                        'updated' => 'Updated (Diubah)',
                        'deleted' => 'Deleted (Dihapus)',
                    ]),
            ])
            ->recordActions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSystemActivities::route('/'),
        ];
    }
}
