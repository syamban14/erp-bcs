<?php

namespace App\Filament\Resources\ShiftSwapRequests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ShiftSwapRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('requester_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('requester_date')
                    ->required(),
                TextInput::make('requester_shift_code'),
                TextInput::make('target_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('target_date')
                    ->required(),
                TextInput::make('target_shift_code'),
                Textarea::make('reason')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('approved_by')
                    ->numeric(),
                DateTimePicker::make('approved_at'),
                Textarea::make('rejection_reason')
                    ->columnSpanFull(),
            ]);
    }
}
