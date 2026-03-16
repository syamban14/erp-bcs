<?php

namespace App\Filament\Resources\OvertimeRequests;

use App\Filament\Resources\OvertimeRequests\Pages\ManageOvertimeRequests;
use App\Models\ApprovalFlow;
use App\Models\MPresensi;
use App\Models\OvertimeRequest;
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

class OvertimeRequestResource extends Resource
{
    use FiltersBySubordinates;

    protected static ?string $model = OvertimeRequest::class;

    protected static ?string $navigationLabel = 'Overtime Approvals';

    public static function getNavigationGroup(): ?string
    {
        return 'Approvals';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 8;

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

                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->disabled(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date')
                    ->disabled(),

                Forms\Components\TextInput::make('start_time')
                    ->label('Start Time')
                    ->disabled(),

                Forms\Components\TextInput::make('end_time')
                    ->label('End Time')
                    ->disabled(),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->disabled()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('attachment_path')
                    ->label('SPL File')
                    ->disabled()
                    ->formatStateUsing(fn ($state) => $state ? basename($state) : '-')
                    ->helperText(fn ($record) => $record && $record->attachment_path
                        ? new \Illuminate\Support\HtmlString('<a href="' . asset('storage/' . $record->attachment_path) . '" target="_blank" class="text-primary-600 hover:underline">Click to download file</a>')
                        : 'No file available'
                    )
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

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('d M Y'),

                Tables\Columns\TextColumn::make('time')
                    ->label('Hours')
                    ->getStateUsing(fn ($record) => $record->start_time . ' - ' . $record->end_time),

                Tables\Columns\TextColumn::make('total_hours')
                    ->label('Total Hours')
                    ->getStateUsing(fn ($record) => number_format($record->calculateOvertimeHours(), 1) . ' hrs'),

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

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ]),

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
            ])
            ->recordActions([
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Overtime Request')
                    ->form([
                        Forms\Components\Textarea::make('notes')->label('Notes (optional)')->rows(2),
                    ])
                    ->visible(fn ($record) => $record->status === 'pending' && $record->canBeApprovedBy(auth()->user()))
                    ->action(function ($record, array $data) {
                        $record->approve(auth()->user(), $data['notes'] ?? null);
                        Notification::make()->title('✅ Approved — Forwarded to next level')->success()->send();
                    }),

                \Filament\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Rejection Reason')->required()->rows(3),
                    ])
                    ->modalHeading('Reject Overtime Request')
                    ->visible(fn ($record) => $record->status === 'pending' && $record->canBeRejectedBy(auth()->user()))
                    ->action(function ($record, array $data) {
                        $record->reject(auth()->user(), $data['reason']);
                        Notification::make()->title('❌ Request Rejected')->danger()->send();
                    }),

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
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOvertimeRequests::route('/'),
        ];
    }
}
