<?php

namespace App\Filament\Resources\Branches\Schemas;

use App\Support\Services\CodeGeneratorService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Branch Name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, callable $get, $record) {
                        if (! $record && filled($state)) {
                            $set('code', CodeGeneratorService::generateBranchCode($state));
                        }
                    }),

                TextInput::make('code')
                    ->label('Branch Code (Auto-Generated)')
                    ->required()
                    ->maxLength(10)
                    ->default(fn () => CodeGeneratorService::generateBranchCode())
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('code', Str::upper($state ?? '')))
                    ->helperText('Automatically generated from branch name or sequence.'),

                TextInput::make('address')
                    ->label('Store Address')
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('phone')
                    ->label('Contact Phone')
                    ->tel()
                    ->maxLength(20),

                Toggle::make('is_active')
                    ->label('Active Status')
                    ->default(true)
                    ->helperText('Deactivating blocks floor staff assigned to this branch from logging in.'),
            ]);
    }
}
