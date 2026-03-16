<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Filament\Resources\LoanResource\RelationManagers;
use App\Models\Loan;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Actions\Action;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-banknotes';
    }

    protected static ?string $navigationLabel = 'Employee Loans';

    public static function getNavigationGroup(): ?string
    {
        return 'Finance';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getModelLabel(): string
    {
        return 'Pinjaman Karyawan';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('Informasi Pemohon')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('user_name')
                            ->label('Nama Karyawan')
                            ->formatStateUsing(fn ($record) => $record?->user->name ?? '-')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('user_nik')
                            ->label('NIK')
                            ->formatStateUsing(fn ($record) => $record?->user->employee_nik ?? '-')
                            ->disabled(),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Detail Pinjaman')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Jumlah Pinjaman')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('tenor_months')
                            ->label('Tenor (Bulan)')
                            ->suffix('Bulan')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('reason')
                            ->label('Keperluan')
                            ->disabled(),
                        \Filament\Forms\Components\Textarea::make('reason_detail')
                            ->label('Detail Keperluan')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(3),

                \Filament\Schemas\Components\Section::make('Kalkulasi Pembayaran')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('interest_rate_percent')
                            ->label('Bunga (%)')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('monthly_installment')
                            ->label('Cicilan per Bulan')
                            ->prefix('Rp')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('total_repayment')
                            ->label('Total Pengembalian')
                            ->prefix('Rp')
                            ->disabled(),
                    ])->columns(3),

                \Filament\Schemas\Components\Section::make('Status & Persetujuan')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('status')
                            ->label('Status Saat Ini')
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('approved_by_name')
                            ->label('Disetujui Oleh')
                            ->formatStateUsing(fn ($record) => $record?->approver->name ?? '-')
                            ->visible(fn ($record) => $record?->approved_at != null)
                            ->disabled(),
                        \Filament\Forms\Components\DatePicker::make('approved_at')
                            ->label('Tanggal Disetujui')
                            ->visible(fn ($record) => $record?->approved_at != null)
                            ->disabled(),
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->visible(fn ($record) => $record?->status === 'rejected')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('tenor_months')
                    ->label('Tenor')
                    ->suffix(' Bulan')
                    ->alignCenter(),
                TextColumn::make('monthly_installment')
                    ->label('Cicilan/Bln')
                    ->money('IDR'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_approval' => 'warning',
                        'approved' => 'info',
                        'active' => 'success',
                        'paid_off' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending_approval' => 'Menunggu Approval',
                        'approved' => 'Disetujui',
                        'active' => 'Aktif (Cair)',
                        'paid_off' => 'Lunas',
                        'rejected' => 'Ditolak',
                        'cancelled' => 'Dibatalkan',
                        default => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('Tgl Pengajuan')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending_approval' => 'Menunggu Approval',
                        'approved' => 'Disetujui',
                        'active' => 'Aktif',
                        'paid_off' => 'Lunas',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                // View Action (Manual)
                Action::make('view')
                    ->label('Lihat')
                    ->icon('heroicon-s-eye')
                    ->url(fn (Loan $record): string => route('filament.admin.resources.loans.view', $record)),
                
                // Approve Action
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Pengajuan Pinjaman')
                    ->modalDescription('Apakah Anda yakin ingin menyetujui pengajuan ini? Status akan berubah menjadi Approved.')
                    ->visible(fn (Loan $record) => $record->status === 'pending_approval')
                    ->action(function (Loan $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                    }),

                // Reject Action
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->visible(fn (Loan $record) => $record->status === 'pending_approval')
                    ->action(function (Loan $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['reason'],
                            'approved_by' => auth()->id(), // Rejector ID
                            'approved_at' => now(),
                        ]);
                    }),

                // Disburse Action
                Action::make('disburse')
                    ->label('Cairkan Dana')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('disbursement_date')
                            ->label('Tanggal Pencairan')
                            ->default(now())
                            ->required(),
                    ])
                    ->visible(fn (Loan $record) => $record->status === 'approved')
                    ->action(function (Loan $record, array $data) {
                        // Generate Installments Logic Here
                        // For simplicity, we assume generic logic or call Service
                        // Ideally call a Service to robustly handle this
                        
                        $record->update([
                            'status' => 'active',
                            'disbursement_date' => $data['disbursement_date'],
                            'start_date' => \Carbon\Carbon::parse($data['disbursement_date'])->startOfMonth()->addMonth(), // Start deduction next month? Or same month? usually next month
                            // Let's assume start date is next month for deduction
                        ]);
                        
                        // Create Installments
                        $tenor = $record->tenor_months;
                        $amount = $record->monthly_installment;
                        
                        for ($i = 1; $i <= $tenor; $i++) {
                            \App\Models\LoanInstallment::create([
                                'loan_id' => $record->id,
                                'installment_number' => $i,
                                'amount' => $amount,
                                'due_date' => \Carbon\Carbon::parse($data['disbursement_date'])->addMonths($i),
                                'status' => 'pending',
                            ]);
                        }
                    }),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Resources\LoanResource\Widgets\LoanStatsOverview::class,
        ];
    }
    
    public static function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\LoanResource\Widgets\LoanStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
            'view' => Pages\ViewLoan::route('/{record}'),
        ];
    }
}
