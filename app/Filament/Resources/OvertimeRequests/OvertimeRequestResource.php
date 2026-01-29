<?php

namespace App\Filament\Resources\OvertimeRequests;

use App\Filament\Resources\OvertimeRequests\Pages\ManageOvertimeRequests;
use App\Models\OvertimeRequest;
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

class OvertimeRequestResource extends Resource
{
    protected static ?string $model = OvertimeRequest::class;
    
    protected static ?string $navigationLabel = 'Approval Lembur';
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    
    protected static ?int $navigationSort = 8;

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
                    
                Forms\Components\DatePicker::make('start_date')
                    ->label('Tanggal Mulai')
                    ->disabled(),
                    
                Forms\Components\DatePicker::make('end_date')
                    ->label('Tanggal Selesai')
                    ->disabled(),
                    
                Forms\Components\TextInput::make('start_time')
                    ->label('Jam Mulai')
                    ->disabled(),
                    
                Forms\Components\TextInput::make('end_time')
                    ->label('Jam Selesai')
                    ->disabled(),
                    
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->disabled()
                    ->columnSpanFull(),
                    
                Forms\Components\TextInput::make('attachment_path')
                    ->label('File SPL')
                    ->disabled()
                    ->formatStateUsing(fn ($state) => $state ? basename($state) : '-')
                    ->helperText(fn ($record) => $record && $record->attachment_path 
                        ? new \Illuminate\Support\HtmlString('<a href="' . asset('storage/' . $record->attachment_path) . '" target="_blank" class="text-primary-600 hover:underline">Klik untuk download file</a>')
                        : 'File tidak tersedia'
                    )
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
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->date('d M Y'),
                    
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Tanggal Selesai')
                    ->date('d M Y'),
                    
                Tables\Columns\TextColumn::make('time')
                    ->label('Jam')
                    ->getStateUsing(fn ($record) => $record->start_time . ' - ' . $record->end_time),
                    
                Tables\Columns\TextColumn::make('total_hours')
                    ->label('Total Jam')
                    ->getStateUsing(fn ($record) => number_format($record->calculateOvertimeHours(), 1) . ' jam'),
                    
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
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        if ($data['status'] === 'approved' && $record->status !== 'approved') {
                            $data['approved_by'] = auth()->id();
                            $data['approved_at'] = now();
                        }
                        return $data;
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
            'index' => ManageOvertimeRequests::route('/'),
        ];
    }
}
