<?php

namespace App\Filament\Resources\Leaves;

use App\Filament\Resources\Leaves\Pages\ManageLeaves;
use App\Models\ApprovalFlow;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\MPresensi;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

use App\Filament\Concerns\FiltersBySubordinates;

class LeaveResource extends Resource
{
    use FiltersBySubordinates;

    protected static ?string $model = Leave::class;

    protected static ?string $navigationLabel = 'Leave Approvals';

    public static function getNavigationGroup(): ?string
    {
        return 'Approvals';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Employee')
                    ->options(fn () => MPresensi::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('type')
                    ->label('Leave Type')
                    ->disabled(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->disabled(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date')
                    ->disabled(),

                Forms\Components\Textarea::make('reason')
                    ->label('Reason')
                    ->rows(3)
                    ->disabled()
                    ->columnSpanFull(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->rows(2)
                    ->visible(fn ($get) => $get('status') === 'rejected')
                    ->required(fn ($get) => $get('status') === 'rejected')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label('Employee')
                    ->formatStateUsing(fn ($state) => MPresensi::find($state)?->name ?? '-')
                    ->searchable(query: fn ($query, $search) => $query->whereIn(
                        'user_id',
                        MPresensi::where('name', 'ilike', "%{$search}%")->pluck('id')
                    ))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Leave Type')
                    ->colors([
                        'warning' => 'Tahunan',
                        'success' => 'Spesial',
                        'info'    => fn ($state) => !in_array($state, ['Tahunan', 'Spesial']),
                    ]),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('days')
                    ->label('Duration')
                    ->getStateUsing(fn ($record) => $record->calculateLeaveDays() . ' days'),

                // ─── Approval Progress Badge ──────────────────────────────
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
                    ->label('Submitted')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Leave Type')
                    ->options([
                        'Tahunan'    => 'Annual Leave',
                        'Spesial'    => 'Special Leave',
                        'Sakit'      => 'Sick Leave',
                        'Menikah'    => 'Marriage Leave',
                        'Melahirkan' => 'Maternity Leave',
                        'Kematian'   => 'Bereavement Leave',
                    ]),
            ])
            ->recordActions([
                // ─── APPROVE ACTION ────────────────────────────────────────
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Leave Request')
                    ->modalDescription(fn ($record) => "Approve this leave request? Current stage: " . ($record->approval_progress_label ?? ''))
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->visible(fn ($record) => $record->status === 'pending' && $record->canBeApprovedBy(auth()->user()))
                    ->action(function ($record, array $data) {
                        $wasLevel4 = ($record->current_approval_level ?? 1) >= count(ApprovalFlow::LEVEL_ROLES);

                        $record->approve(auth()->user(), $data['notes'] ?? null);

                        // Deduct leave quota if fully approved and type is Tahunan
                        if ($wasLevel4 && strtolower($record->fresh()->type) === 'tahunan') {
                            $balance = LeaveBalance::firstOrCreate(
                                ['user_id' => $record->user_id, 'year' => $record->start_date->year],
                                ['quota' => 12, 'used' => 0]
                            );
                            $balance->increment('used', $record->calculateLeaveDays());
                        }

                        Notification::make()
                            ->title($wasLevel4 ? '✅ Leave Request Fully Approved!' : '✅ Approved — Forwarded to next level')
                            ->success()
                            ->send();
                    }),

                // ─── REJECT ACTION ─────────────────────────────────────────
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
                    ->modalHeading('Reject Leave Request')
                    ->visible(fn ($record) => $record->status === 'pending' && $record->canBeRejectedBy(auth()->user()))
                    ->action(function ($record, array $data) {
                        $record->reject(auth()->user(), $data['reason']);

                        Notification::make()
                            ->title('❌ Leave Request Rejected')
                            ->danger()
                            ->send();
                    }),

                // ─── VIEW APPROVAL HISTORY ──────────────────────────────────
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

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaves::route('/'),
        ];
    }
}
