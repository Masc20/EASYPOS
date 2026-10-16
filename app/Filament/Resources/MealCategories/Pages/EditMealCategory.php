<?php

namespace App\Filament\Resources\MealCategories\Pages;

use App\Filament\Resources\MealCategories\MealCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMealCategory extends EditRecord
{
    protected static string $resource = MealCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
