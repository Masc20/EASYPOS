<?php

namespace App\Livewire\Forms;

use Carbon\Carbon;
use Livewire\Component;

class DatePicker extends Component
{
    public string $selectedDate = '';
    public string $label = '';
    public string $preset = 'today';
    public string $eventName = 'date-selected';

    public function mount(
        string $selectedDate = '',
        string $label = '',
        string $preset = 'today',
        string $eventName = 'date-selected'
    ): void {
        $this->label = $label;
        $this->eventName = $eventName;
        $this->preset = $preset;

        if ($selectedDate) {
            $this->selectedDate = $selectedDate;
        } else {
            $this->applyPreset($this->preset);
        }
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        $date = match ($preset) {
            'yesterday' => Carbon::yesterday()->toDateString(),
            'last_week' => Carbon::now()->subWeek()->toDateString(),
            'first_of_month' => Carbon::now()->startOfMonth()->toDateString(),
            default => Carbon::today()->toDateString(),
        };

        $this->selectedDate = $date;
        $this->dispatch($this->eventName, date: $this->selectedDate, preset: $this->preset);
    }

    public function updatedSelectedDate(string $val): void
    {
        $this->preset = 'custom';
        $this->dispatch($this->eventName, date: $val, preset: 'custom');
    }

    public function render()
    {
        return view('livewire.forms.date-picker');
    }
}
