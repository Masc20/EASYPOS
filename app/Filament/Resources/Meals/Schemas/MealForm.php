<?php

namespace App\Filament\Resources\Meals\Schemas;

use App\Domains\Menu\Models\MealCategory;
use App\Support\Constants\SystemConstants;
use App\Support\Services\CodeGeneratorService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class MealForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();
        $isBranchScoped = (bool) ($user && $user->isBranchScoped());
        $effectiveBranchId = $isBranchScoped ? $user->effectiveBranchId() : null;

        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Menu Category')
                    ->relationship(
                        'category',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $isBranchScoped
                            ? $query->forBranch($effectiveBranchId)
                            : $query,
                    )
                    ->rule(function () use ($isBranchScoped, $effectiveBranchId): \Closure {
                        return function (string $attribute, mixed $value, \Closure $fail) use ($isBranchScoped, $effectiveBranchId): void {
                            if (! $isBranchScoped || blank($value)) {
                                return;
                            }

                            $allowed = MealCategory::query()
                                ->where('id', (int) $value)
                                ->forBranch($effectiveBranchId)
                                ->exists();

                            if (! $allowed) {
                                $fail('The selected category does not belong to your branch.');
                            }
                        };
                    })
                    ->required()
                    ->preload()
                    ->live(debounce: 300)
                    ->afterStateUpdated(function ($state, callable $set, $record) {
                        if (! $state) {
                            return;
                        }
                        if ($record && (int) $record->category_id === (int) $state && filled($record->menu_code)) {
                            $set('menu_code', $record->menu_code);
                            return;
                        }
                        $set('menu_code', CodeGeneratorService::generateMenuCode((int) $state));
                    }),

                $isBranchScoped
                    ? Hidden::make('branch_id')
                        ->default($effectiveBranchId)
                        ->dehydrateStateUsing(fn () => $effectiveBranchId)
                    : Select::make('branch_id')
                        ->label('Assigned Branch')
                        ->relationship('branch', 'name')
                        ->placeholder('All Branches (Global Menu)')
                        ->preload()
                        ->default(null),

                TextInput::make('name')
                    ->label('Dish Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('menu_code')
                    ->label('Menu Code (Auto-Generated)')
                    ->placeholder('Auto-generated from category')
                    ->maxLength(20)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                    ->unique(
                        table: 'meals',
                        column: 'menu_code',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'),
                    ),

                TextInput::make('price')
                    ->label('Selling Price')
                    ->required()
                    ->numeric()
                    ->prefix(SystemConstants::CURRENCY_SYMBOL)
                    ->minValue(0),

                Textarea::make('description')
                    ->label('Description & Prep Notes')
                    ->placeholder('Prep instructions, allergens, or ingredients')
                    ->rows(2)
                    ->columnSpanFull(),

                Toggle::make('is_available')
                    ->label('Available / In Stock')
                    ->default(true),

                Toggle::make('track_stock')
                    ->label('Track Limited Portions')
                    ->live(),

                TextInput::make('stock_quantity')
                    ->label('Available Portions')
                    ->required(fn ($get): bool => (bool) $get('track_stock'))
                    ->integer()
                    ->minValue(0),

                Toggle::make('is_active')
                    ->label('Active in Menu')
                    ->default(true),

                TagsInput::make('kitchen_tags')
                    ->label('Kitchen Tags')
                    ->placeholder('Add tag (e.g. grill, fry, vegan)')
                    ->columnSpanFull(),

                Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }
}
