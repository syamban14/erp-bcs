<?php

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Konten Pengumuman')
                    ->description('Tuliskan detail informasi / pengumuman yang akan disebarkan')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('title')
                            ->label('Judul Pengumuman')
                            ->placeholder('Contoh: Libur Nasional / Perbaikan Sistem')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        \Filament\Forms\Components\RichEditor::make('content')
                            ->label('Isi Pesan')
                            ->required()
                            ->columnSpanFull(),
                            
                        \Filament\Forms\Components\FileUpload::make('image_url')
                            ->label('Gambar Lampiran (Opsional)')
                            ->image()
                            ->directory('announcements')
                            ->columnSpanFull(),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Pengaturan Tayang')
                    ->description('Atur tipe, target penerima, dan jadwal tayang')
                    ->schema([
                        \Filament\Forms\Components\Select::make('type')
                            ->label('Jenis Pengumuman')
                            ->options([
                                'info' => 'Informasi Umum',
                                'warning' => 'Peringatan / Penting',
                                'event' => 'Acara / Kegiatan',
                            ])
                            ->required()
                            ->default('info'),
                            
                        \Filament\Forms\Components\DateTimePicker::make('date')
                            ->label('Tanggal Publikasi')
                            ->required()
                            ->default(now()),
                            
                        \Filament\Forms\Components\Select::make('target_user_id')
                            ->label('Target Penerima')
                            ->options(\App\Models\MPresensi::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Semua Karyawan (Global)')
                            ->helperText('Kosongkan kolom ini jika pengumuman ditujukan untuk SEMUA karyawan.'),

                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->helperText('Matikan jika pengumuman ini sudah tidak berlaku.')
                            ->default(true)
                            ->required(),
                    ])->columns(2),
            ]);
    }
}
