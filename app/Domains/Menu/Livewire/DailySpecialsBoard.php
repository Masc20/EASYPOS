<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Models\Meal;
use Livewire\Attributes\On;
use Livewire\Component;

class DailySpecialsBoard extends Component
{
    #[On('meal-saved')]
    public function refreshBoard(): void
    {
        // Re-renders automatically
    }

    public function render()
    {
        $limitedSpecials = Meal::where('track_stock', true)
            ->where('is_available', true)
            ->orderBy('stock_quantity')
            ->get();

        $soldOutCount = Meal::where('is_available', false)->count();

        return view('livewire.menu.daily-specials-board', [
            'limitedSpecials' => $limitedSpecials,
            'soldOutCount' => $soldOutCount,
            'currencySymbol' => config('pos.currency.symbol', '₱'),
        ]);
    }
}
