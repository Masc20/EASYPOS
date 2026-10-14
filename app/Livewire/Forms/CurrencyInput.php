<?php

namespace App\Livewire\Forms;

use Livewire\Component;

class CurrencyInput extends Component
{
    public float $amount = 0.00;
    public string $label = '';
    public string $currencySymbol = '₱';
    public float $min = 0.00;
    public ?float $max = null;
    public float $step = 1.00;
    public string $eventName = 'amount-changed';
    public bool $disabled = false;

    public function mount(
        float $amount = 0.00,
        string $label = '',
        string $currencySymbol = '₱',
        float $min = 0.00,
        ?float $max = null,
        float $step = 1.00,
        string $eventName = 'amount-changed',
        bool $disabled = false
    ): void {
        $this->amount = $amount;
        $this->label = $label;
        $this->currencySymbol = $currencySymbol;
        $this->min = $min;
        $this->max = $max;
        $this->step = $step;
        $this->eventName = $eventName;
        $this->disabled = $disabled;
    }

    public function addAmount(float $value): void
    {
        if ($this->disabled) {
            return;
        }

        $newAmount = $this->amount + $value;
        if ($this->max !== null && $newAmount > $this->max) {
            $newAmount = $this->max;
        }

        $this->amount = round($newAmount, 2);
        $this->dispatch($this->eventName, amount: $this->amount);
    }

    public function setAmount(float $value): void
    {
        if ($this->disabled) {
            return;
        }

        if ($this->max !== null && $value > $this->max) {
            $value = $this->max;
        }
        if ($value < $this->min) {
            $value = $this->min;
        }

        $this->amount = round($value, 2);
        $this->dispatch($this->eventName, amount: $this->amount);
    }

    public function updatedAmount($value): void
    {
        $floatVal = (float) $value;
        if ($this->max !== null && $floatVal > $this->max) {
            $floatVal = $this->max;
        }
        if ($floatVal < $this->min) {
            $floatVal = $this->min;
        }

        $this->amount = round($floatVal, 2);
        $this->dispatch($this->eventName, amount: $this->amount);
    }

    public function render()
    {
        return view('livewire.forms.currency-input');
    }
}
