<?php

namespace App\Filament\Resources\OutstationRequests\Schemas;

use Filament\Schemas\Schema;

class OutstationRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Forms\Components\Select::make('user_id')
                    ->label('Karyawan')
                    ->options(function () {
                        return \App\Models\MPresensi::query()
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->disabled()
                    ->dehydrated(false),

                \Filament\Forms\Components\TextInput::make('task_type')
                    ->label('Jenis Tugas')
                    ->disabled(),

                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->disabled()
                            ->columnSpan(['sm' => 2, 'xl' => 1]),
                        \Filament\Forms\Components\TextInput::make('start_time')
                            ->label('Jam Mulai')
                            ->disabled()
                            ->columnSpan(['sm' => 2, 'xl' => 1]),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->disabled()
                            ->columnSpan(['sm' => 2, 'xl' => 1]),
                        \Filament\Forms\Components\TextInput::make('end_time')
                            ->label('Jam Selesai')
                            ->disabled()
                            ->columnSpan(['sm' => 2, 'xl' => 1]),

                \Filament\Forms\Components\TextInput::make('location')
                    ->label('Lokasi')
                    ->columnSpanFull()
                    ->disabled(),

                \Filament\Forms\Components\Textarea::make('description')
                    ->label('Keterangan')
                    ->rows(3)
                    ->columnSpanFull()
                    ->disabled(),

                \Filament\Forms\Components\TextInput::make('attachment_path')
                    ->label('Lampiran')
                    ->disabled()
                    ->formatStateUsing(fn ($state) => $state ? basename($state) : '-')
                    ->helperText(fn ($record) => $record && $record->attachment_path 
                        ? new \Illuminate\Support\HtmlString('<a href="' . asset('storage/' . $record->attachment_path) . '" target="_blank" class="text-primary-600 hover:underline">Download Lampiran</a>')
                        : 'Tidak ada lampiran'
                    )
                    ->columnSpanFull(),

                        \Filament\Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->disabled(),
                        \Filament\Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->disabled(),

                \Filament\Forms\Components\Select::make('status')
                    ->label('Status Approval')
                    ->options([
                        'pending' => 'Pending',
                        'approved_manager' => 'Approved by Manager',
                        'approved' => 'Approved (Final)',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->columnSpanFull(),

                \Filament\Forms\Components\Textarea::make('rejection_reason')
                    ->label('Alasan Penolakan')
                    ->rows(2)
                    ->visible(fn ($get) => $get('status') === 'rejected')
                    ->required(fn ($get) => $get('status') === 'rejected')
                    ->columnSpanFull(),
            ]);
    }
}
