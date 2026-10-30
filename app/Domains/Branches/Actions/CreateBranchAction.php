<?php

namespace App\Domains\Branches\Actions;

use App\Domains\Branches\Models\Branch;
use Illuminate\Validation\ValidationException;

class CreateBranchAction
{
    public function execute(array $data): Branch
    {
        $data['code'] = strtoupper(trim($data['code']));
        return Branch::create($data);
    }
}
