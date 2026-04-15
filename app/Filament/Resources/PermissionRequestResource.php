<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionRequestResource\Pages;
use App\Models\ApprovalFlow;
use App\Models\PermissionRequest;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

use App\Filament\Concerns\FiltersBySubordinates;

class PermissionRequestResource extends Resource
{
    use FiltersBySubordinates;

    protected static ?string $model = PermissionRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Permission Requests';

    public static function getNavigationGroup(): ?string
    {
        return 'Approvals';
    }

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Employee')
                    ->disabled(),
                Forms\Components\TextInput::make('type')
                    ->label('Permission Type')
                    ->disabled(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Date')
                    ->disabled(),
                Forms\Components\Textarea::make('reason')
                    ->label('Reason')
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label('Employee')
                    ->formatStateUsing(fn ($state) => \App\Models\MPresensi::find($state)?->name ?? '-')
                    ->searchable(query: fn ($query, $search) => $query->whereIn(
                        'user_id',
                        \App\Models\MPresensi::where('name', 'ilike', "%{$search}%")->pluck('id')
                    ))
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('time')
                    ->label('Time')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap(),

                // ─── Approval Progress ─────────────────────────────────────
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

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'  => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('notes')->label('Notes (optional)')->rows(2),
                        ])
                        ->visible(fn (PermissionRequest $record) => $record->status === 'pending' && $record->canBeApprovedBy(auth()->user()))
                        ->action(function (PermissionRequest $record, array $data) {
                            $record->approve(auth()->user(), $data['notes'] ?? null);
                            Notification::make()->title('✅ Approved!')->success()->send();
                        }),

                    \Filament\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('reason')->label('Rejection Reason')->required()->rows(3),
                        ])
                        ->visible(fn (PermissionRequest $record) => $record->status === 'pending' && $record->canBeRejectedBy(auth()->user()))
                        ->action(function (PermissionRequest $record, array $data) {
                            $record->reject(auth()->user(), $data['reason']);
                            Notification::make()->title('❌ Rejected')->danger()->send();
                        }),

                    \Filament\Actions\Action::make('history')
                        ->label('Approval Chain')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->modalIcon(null)
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
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('approveAll')
                        ->label('Approve All Selected')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve All Selected Permission Requests?')
                        ->modalDescription('Hanya pengajuan yang statusnya Pending dan bisa Anda approve yang akan diproses.')
                        ->modalSubmitActionLabel('Ya, Approve Semua')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $approved = 0; $skipped = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'pending' || !$record->canBeApprovedBy(auth()->user())) {
                                    $skipped++; continue;
                                }
                                $record->approve(auth()->user(), 'Bulk approved by admin');
                                $approved++;
                            }
                            \Filament\Notifications\Notification::make()
                                ->title("✅ {$approved} izin disetujui" . ($skipped ? ", {$skipped} dilewati." : '.'))
                                ->success()->send();
                        }),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePermissionRequests::route('/'),
        ];
    }
}
