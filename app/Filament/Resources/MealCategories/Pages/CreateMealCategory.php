<?php

namespace App\Filament\Resources\MealCategories\Pages;

use App\Filament\Resources\MealCategories\MealCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMealCategory extends CreateRecord
{
    use \App\Filament\Concerns\AuthorizesResourcePage;

    protected static string $resource = MealCategoryResource::class;
}
