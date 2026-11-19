<?php

namespace App\Livewire\Forms;

use App\Support\Constants\SystemConstants;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CurrencyInput extends Component
{
    public float $amount = 0.00;

    public string $label = '';

    #[Locked]
    public string $currencySymbol = SystemConstants::CURRENCY_SYMBOL;

    #[Locked]
    public float $min = 0.00;

    #[Locked]
    public ?float $max = null;

    public float $step = 1.00;

    #[Locked]
    public string $eventName = 'amount-changed';

    #[Locked]
    public bool $disabled = false;

    private ?float $previousAmount = null;

    public function mount(
        float $amount = 0.00,
        string $label = '',
        string $currencySymbol = SystemConstants::CURRENCY_SYMBOL,
        float $min = 0.00,
        ?float $max = null,
        float $step = 1.00,
        string $eventName = 'amount-changed',
        bool $disabled = false
    ): void {
        $this->label = $label;
        $this->currencySymbol = $currencySymbol;
        $this->min = $min;
        $this->max = $max;
        $this->step = $step;
        $this->eventName = $eventName;
        $this->disabled = $disabled;
        $this->amount = $this->clampAmount($amount);
    }

    public function addAmount(float $value): void
    {
        if ($this->disabled) {
            return;
        }

        $this->applyAmount($this->amount + $value);
    }

    public function setAmount(float $value): void
    {
        if ($this->disabled) {
            return;
        }

        $this->applyAmount($value);
    }

    public function updatingAmount(mixed $value): void
    {
        if ($this->disabled) {
            $this->previousAmount = $this->amount;
        }
    }

    public function updatedAmount(mixed $value): void
    {
        if ($this->disabled) {
            if ($this->previousAmount !== null) {
                $this->amount = $this->previousAmount;
                $this->previousAmount = null;
            }

            return;
        }

        $this->applyAmount((float) $value);
    }

    private function applyAmount(float $value): void
    {
        $this->amount = $this->clampAmount($value);
        $this->dispatch($this->eventName, amount: $this->amount);
    }

    private function clampAmount(float $value): float
    {
        if ($this->max !== null && $value > $this->max) {
            $value = $this->max;
        }
        if ($value < $this->min) {
            $value = $this->min;
        }

        return round($value, 2);
    }

    public function render()
    {
        return view('livewire.forms.currency-input');
    }
}
