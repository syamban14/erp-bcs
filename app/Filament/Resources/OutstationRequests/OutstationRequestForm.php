<?php
namespace App\Filament\Resources\OutstationRequests\Schemas;
use Filament\Forms;
use Filament\Schemas\Schema;
class OutstationRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Informasi Karyawan')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Karyawan')
                            ->relationship('user', 'name')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                    
                Forms\Components\Section::make('Detail Tugas')
                    ->schema([
                        Forms\Components\Select::make('task_type')
                            ->label('Jenis Tugas')
                            ->options([
                                'Perjalanan Dinas' => 'Perjalanan Dinas',
                                'Pelatihan' => 'Pelatihan',
                            ])
                            ->disabled(),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Tanggal Mulai')
                                    ->disabled(),
                                Forms\Components\TimePicker::make('start_time')
                                    ->label('Jam Mulai')
                                    ->disabled(),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Tanggal Selesai')
                                    ->disabled(),
                                Forms\Components\TimePicker::make('end_time')
                                    ->label('Jam Selesai')
                                    ->disabled(),
                            ]),
                        Forms\Components\TextInput::make('location')
                            ->label('Lokasi')
                            ->disabled(),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('latitude')
                                    ->label('Latitude')
                                    ->disabled(),
                                Forms\Components\TextInput::make('longitude')
                                    ->label('Longitude')
                                    ->disabled(),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Lampiran')
                    ->schema([
                        Forms\Components\FileUpload::make('attachment_path')
                            ->label('File Lampiran')
                            ->disk('public')
                            ->directory('outstation-attachments')
                            ->disabled()
                            ->downloadable(),
                    ]),
                    
                Forms\Components\Section::make('Status Approval')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Menunggu Manager',
                                'approved_manager' => 'Menunggu Admin',
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                            ])
                            ->disabled(),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->rows(2)
                            ->visible(fn ($get) => $get('status') === 'rejected')
                            ->disabled(),
                    ]),
            ]);
    }
}