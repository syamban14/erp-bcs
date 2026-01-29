<?php

namespace App\Filament\Resources\Leaves;

use App\Filament\Resources\Leaves\Pages\ManageLeaves;
use App\Models\Leave;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class LeaveResource extends Resource
{
    protected static ?string $model = Leave::class;
    
    protected static ?string $navigationLabel = 'Approval Cuti';
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    
    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Karyawan')
                    ->options(function () {
                        return \App\Models\MPresensi::query()
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->disabled()
                    ->dehydrated(false),
                    
                Forms\Components\TextInput::make('type')
                    ->label('Jenis Cuti')
                    ->disabled(),
                    
                Forms\Components\DatePicker::make('start_date')
                    ->label('Tanggal Mulai')
                    ->disabled(),
                    
                Forms\Components\DatePicker::make('end_date')
                    ->label('Tanggal Selesai')
                    ->disabled(),
                    
                Forms\Components\Textarea::make('reason')
                    ->label('Alasan')
                    ->rows(3)
                    ->disabled()
                    ->columnSpanFull(),
                    
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->columnSpanFull(),
                    
                Forms\Components\Textarea::make('rejection_reason')
                    ->label('Alasan Penolakan')
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
                    ->label('Karyawan')
                    ->formatStateUsing(function ($state) {
                        $user = \App\Models\MPresensi::find($state);
                        return $user ? $user->name : '-';
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->whereIn('user_id', function ($q) use ($search) {
                            $q->select('id')
                                ->from('master_db.m_presensi')
                                ->where('name', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Jenis Cuti')
                    ->colors([
                        'warning' => 'Tahunan',
                        'success' => 'Spesial',
                        'info' => fn ($state) => !in_array($state, ['Tahunan', 'Spesial']),
                    ]),
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->date('d M Y'),
                    
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Tanggal Selesai')
                    ->date('d M Y'),
                    
                Tables\Columns\TextColumn::make('days')
                    ->label('Durasi')
                    ->getStateUsing(fn ($record) => $record->calculateLeaveDays() . ' hari'),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'pending' => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => $state,
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ])
                    ->default('pending'),
                    
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis Cuti')
                    ->options([
                        'Tahunan' => 'Cuti Tahunan',
                        'Spesial' => 'Cuti Spesial',
                        'Sakit' => 'Cuti Sakit',
                        'Menikah' => 'Cuti Menikah',
                        'Melahirkan' => 'Cuti Melahirkan',
                        'Kematian' => 'Cuti Kematian',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        // Auto-set approved_by and approved_at if status changed to approved
                        if ($data['status'] === 'approved' && $record->status !== 'approved') {
                            $data['approved_by'] = auth()->id();
                            $data['approved_at'] = now();
                        }
                        return $data;
                    })
                    ->after(function ($record) {
                        // Auto-deduct quota if cuti tahunan and approved
                        if ($record->status === 'approved' && strtolower($record->type) === 'tahunan') {
                            $days = $record->calculateLeaveDays();
                            $year = $record->start_date->year;
                            
                            // Find or create balance
                            $balance = \App\Models\LeaveBalance::firstOrCreate(
                                [
                                    'user_id' => $record->user_id,
                                    'year' => $year,
                                ],
                                [
                                    'quota' => 12,
                                    'used' => 0,
                                ]
                            );
                            
                            // Deduct quota
                            $balance->increment('used', $days);
                        }
                    }),
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
