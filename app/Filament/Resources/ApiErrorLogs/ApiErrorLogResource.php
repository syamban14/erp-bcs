<?php

namespace App\Filament\Resources\ApiErrorLogs;

use App\Filament\Resources\ApiErrorLogs\Pages\ManageApiErrorLogs;
use App\Models\ApiErrorLog;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Forms\Components\DatePicker;
use Carbon\Carbon;

class ApiErrorLogResource extends Resource
{
    protected static ?string $model = ApiErrorLog::class;

    public static function getNavigationGroup(): ?string
    {
        return 'System Monitor';
    }
    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedServerStack;
    }

    public static function getNavigationLabel(): string
    {
        return 'API Tracker';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ApiErrorLog::where('status_code', '>=', 400)
            ->whereDate('created_at', today())->count();
        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function canViewAny(): bool
    {
        return strtolower(auth()->user()->role) === 'superhyperadmin';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('status_code')
                            ->badge()
                            ->color(fn (string $state): string => match (true) {
                                $state >= 500 => 'danger',
                                $state >= 400 => 'warning',
                                default => 'success',
                            })
                            ->size('lg')
                            ->weight('bold'),
                        TextColumn::make('method')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'GET' => 'success',
                                'POST' => 'warning',
                                'PUT', 'PATCH' => 'info',
                                'DELETE' => 'danger',
                                default => 'gray',
                            }),
                    ])->space(2)->grow(false),

                    Stack::make([
                        TextColumn::make('url')
                            ->weight('bold')
                            ->searchable()
                            ->copyable()
                            ->limit(60)
                            ->tooltip(fn ($state) => $state),
                        TextColumn::make('error_message')
                            ->color('danger')
                            ->limit(80)
                            ->searchable()
                            ->size('sm'),
                    ])->space(1),

                    Stack::make([
                        TextColumn::make('user.name')
                            ->icon('heroicon-m-user')
                            ->placeholder('Guest')
                            ->color('gray')
                            ->weight('medium'),
                        TextColumn::make('ip')
                            ->icon('heroicon-m-globe-alt')
                            ->color('gray')
                            ->size('xs'),
                    ])->space(1)->grow(false),
                    
                    Stack::make([
                        TextColumn::make('created_at')
                            ->dateTime('d M Y, H:i')
                            ->description(fn (ApiErrorLog $record): string => $record->created_at->diffForHumans())
                            ->alignEnd(),
                    ])->grow(false),
                ])->from('md'),
                
                Panel::make([
                    Stack::make([
                        TextColumn::make('error_message')
                            ->weight('bold')
                            ->color('danger'),
                        TextColumn::make('payload')
                            ->getStateUsing(fn (?ApiErrorLog $record) => $record ? json_encode($record->payload, JSON_PRETTY_PRINT) : null)
                            ->html()
                            ->formatStateUsing(fn ($state) => "<pre style='background: #111827; color: #10b981; padding: 10px; border-radius: 6px; font-size: 11px; max-height: 200px; overflow-y: auto;'>{$state}</pre>")
                            ->visible(fn (?ApiErrorLog $record) => $record && !empty($record->payload)),
                    ]),
                ])->collapsible(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('method')
                    ->label('HTTP Method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'DELETE' => 'DELETE',
                    ]),
                SelectFilter::make('status_code')
                    ->label('Status Code')
                    ->options([
                        '200' => '200 (OK)',
                        '201' => '201 (Created)',
                        '400' => '400 (Bad Request)',
                        '401' => '401 (Unauthorized)',
                        '403' => '403 (Forbidden)',
                        '404' => '404 (Not Found)',
                        '422' => '422 (Unprocessable Entity)',
                        '500' => '500 (Internal Server Error)',
                    ]),
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
                Filter::make('errors_only')
                    ->label('Hanya Error (4xx / 5xx)')
                    ->query(fn ($query) => $query->where('status_code', '>=', 400))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Hapus Terpilih'),
                ]),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('purge_old')
                    ->label('Bersihkan Log > 30 Hari')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Bersihkan API Log Lama?')
                    ->modalDescription('Semua API log yang berumur lebih dari 30 hari akan dihapus permanen.')
                    ->action(function () {
                        $deleted = ApiErrorLog::where('created_at', '<', now()->subDays(30))->delete();
                        \Filament\Notifications\Notification::make()
                            ->title("{$deleted} API log lama berhasil dibersihkan.")
                            ->success()
                            ->send();
                    }),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return clone $schema->components([
            Section::make('Request Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('created_at')->dateTime('d M Y H:i:s'),
                        TextEntry::make('ip')->label('IP Address'),
                        TextEntry::make('method')->badge(),
                    ]),
                    TextEntry::make('url')->label('Endpoint URL'),
                    TextEntry::make('user_agent')->label('User Agent'),
                ]),
                
            Section::make('Error Payload & Message')
                ->schema([
                    TextEntry::make('status_code')
                        ->badge()
                        ->color(fn ($state) => $state >= 500 ? 'danger' : 'warning'),
                    TextEntry::make('error_message')
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state) => "<pre style='white-space: pre-wrap; word-break: break-all; font-family: monospace; font-size: 12px'>{$state}</pre>")
                        ->html(),
                    KeyValueEntry::make('payload')
                        ->label('Request Payload (JSON)')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApiErrorLogs::route('/'),
        ];
    }
}
