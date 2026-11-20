<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    use \App\Filament\Concerns\AuthorizesResourcePage;

    protected static string $resource = BranchResource::class;
}
