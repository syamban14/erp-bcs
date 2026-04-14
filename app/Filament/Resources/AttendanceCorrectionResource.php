<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceCorrectionResource\Pages;
use App\Models\ApprovalFlow;
use App\Models\AttendanceCorrection;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

use App\Filament\Concerns\FiltersBySubordinates;

class AttendanceCorrectionResource extends Resource
{
    use FiltersBySubordinates;

    protected static ?string $model = AttendanceCorrection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Attendance Corrections';

    public static function getNavigationGroup(): ?string
    {
        return 'Approvals';
    }

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Employee')
                    ->disabled(),
                Forms\Components\DatePicker::make('date')
                    ->label('Date')
                    ->disabled(),
                Forms\Components\TextInput::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->disabled(),
                Forms\Components\TextInput::make('time')
                    ->label('Time')
                    ->disabled(),
                Forms\Components\Textarea::make('reason')
                    ->label('Reason')
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\FileUpload::make('evidence')
                    ->label('Evidence')
                    ->image()
                    ->disk('public')
                    ->directory('corrections')
                    ->visibility('public')
                    ->downloadable()
                    ->openable()
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

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in'    => 'success',
                        'out'   => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                Tables\Columns\TextColumn::make('time')
                    ->label('Time'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(20),

                Tables\Columns\TextColumn::make('evidence')
                    ->label('Evidence')
                    ->formatStateUsing(fn ($state) => $state ? 'View Evidence' : '-')
                    ->icon(fn ($state) => $state ? 'heroicon-o-document-magnifying-glass' : null)
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->url(fn (AttendanceCorrection $record) => $record->evidence_url)
                    ->openUrlInNewTab(),

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
                        ->visible(fn (AttendanceCorrection $record) => $record->status === 'pending' && $record->canBeApprovedBy(auth()->user()))
                        ->action(function (AttendanceCorrection $record, array $data) {
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
                        ->visible(fn (AttendanceCorrection $record) => $record->status === 'pending' && $record->canBeRejectedBy(auth()->user()))
                        ->action(function (AttendanceCorrection $record, array $data) {
                            $record->reject(auth()->user(), $data['reason']);
                            Notification::make()->title('❌ Rejected')->danger()->send();
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
                ]),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('approveAll')
                        ->label('Approve All Selected')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve All Selected Correction Requests?')
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
                            Notification::make()
                                ->title("✅ {$approved} koreksi disetujui" . ($skipped ? ", {$skipped} dilewati." : '.'))
                                ->success()->send();
                        }),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAttendanceCorrections::route('/'),
        ];
    }
}
