<?php

namespace App\Support;

use Livewire\Mechanisms\HandleRequests\HandleRequests;

class LivewireRequests extends HandleRequests
{
    public function getUpdateUri(): string
    {
        return request()->getBaseUrl().parent::getUpdateUri();
    }
}
