<?php

namespace App\Filament\Resources\MealCategories\Pages;

use App\Filament\Resources\MealCategories\MealCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMealCategories extends ListRecords
{
    use \App\Filament\Concerns\AuthorizesResourcePage;

    protected static string $resource = MealCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Category')
                ->modal()
                ->modalWidth('md'),
        ];
    }
}
