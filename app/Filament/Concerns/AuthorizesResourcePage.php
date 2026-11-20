<?php

namespace App\Filament\Concerns;

trait AuthorizesResourcePage
{
    public function bootAuthorizesResourcePage(): void
    {
        abort_unless(static::getResource()::canAccess(), 403);
    }
}
