<?php

namespace App\Filament\Resources\Branches\Schemas;

use App\Support\Services\CodeGeneratorService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

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
                    ->extraInputAttributes(fn ($record) => $record ? [] : [
                        'x-on:input' => '
                            const val = ($el.value || "").trim();
                            if (!val) return;
                            const words = val.split(/\s+/);
                            let code = "";
                            if (words.length >= 2) {
                                code = (words[0].substring(0, 2) + words[1].substring(0, 2)).toUpperCase();
                            } else {
                                code = val.replace(/[^a-zA-Z0-9]/g, "").substring(0, 4).toUpperCase();
                            }
                            if (code.length >= 2) {
                                const codeInput = $el.closest("form")?.querySelector("[data-branch-code-input]");
                                if (codeInput) {
                                    codeInput.value = code;
                                    codeInput.dispatchEvent(new Event("input", { bubbles: true }));
                                }
                            }
                        ',
                    ]),

                TextInput::make('code')
                    ->label('Branch Code (Auto-Generated)')
                    ->required()
                    ->maxLength(10)
                    ->default(fn ($record) => $record ? null : CodeGeneratorService::generateBranchCode())
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes([
                        'data-branch-code-input' => 'true',
                        'x-on:input' => '$el.value = $el.value.toUpperCase()',
                    ]),

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
                    ->default(true),
            ]);
    }
}
