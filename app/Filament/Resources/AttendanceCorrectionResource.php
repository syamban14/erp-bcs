<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceCorrectionResource\Pages;
use App\Models\AttendanceCorrection;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AttendanceCorrectionResource extends Resource
{
    protected static ?string $model = AttendanceCorrection::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    public static function getNavigationGroup(): ?string
    {
        return 'Absensi Management';
    }

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled(),
                Forms\Components\DatePicker::make('date')
                    ->disabled(),
                Forms\Components\TextInput::make('type')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->disabled(),
                Forms\Components\TextInput::make('time')
                    ->disabled(),
                Forms\Components\Textarea::make('reason')
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\FileUpload::make('evidence')
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
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                Tables\Columns\TextColumn::make('time'),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(20),
                Tables\Columns\ImageColumn::make('evidence')
                    ->disk('public')
                    ->visibility('public'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (AttendanceCorrection $record) => $record->status === 'pending')
                        ->action(function (AttendanceCorrection $record) {
                            $record->update([
                                'status' => 'approved',
                            ]);
                        }),

                    \Filament\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (AttendanceCorrection $record) => $record->status === 'pending')
                        ->action(function (AttendanceCorrection $record) {
                            $record->update([
                                'status' => 'rejected',
                            ]);
                        }),
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
