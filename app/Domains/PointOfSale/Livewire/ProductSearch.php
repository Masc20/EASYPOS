<?php

namespace App\Domains\PointOfSale\Livewire;

use App\Domains\Menu\Models\Meal;
use Livewire\Component;

class ProductSearch extends Component
{
    public string $query = '';

    /**
     * Handle scanner Enter key or form submit:
     * If query matches an exact menu code (e.g. "M-01"), immediately adds to cart and resets.
     */
    public function scanOrSubmit(): void
    {
        $code = trim($this->query);
        if (empty($code)) {
            return;
        }

        $meal = Meal::where('menu_code', strtoupper($code))
            ->where('is_active', true)
            ->first();

        if ($meal && $meal->is_available && (!$meal->track_stock || $meal->stock_quantity > 0)) {
            $this->dispatch('add-to-cart-client', [
                'id' => $meal->id,
                'name' => $meal->name,
                'code' => $meal->menu_code,
                'price' => (float) $meal->price,
                'maxQty' => $meal->track_stock ? $meal->stock_quantity : null,
            ]);
            $this->dispatch('add-to-cart', mealId: $meal->id);
            $this->query = '';
            $this->dispatch('catalog-search', query: '');
        } else {
            // Otherwise just keep filtering catalog
            $this->dispatch('catalog-search', query: $code);
        }
    }

    public function updatedQuery(): void
    {
        $this->dispatch('catalog-search', query: $this->query);
    }

    public function clearSearch(): void
    {
        $this->query = '';
        $this->dispatch('catalog-search', query: '');
    }

    public function render()
    {
        return view('livewire.point-of-sale.product-search');
    }
}
