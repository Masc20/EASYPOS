<?php

namespace App\Livewire\Ui;

use Livewire\Component;

class Modal extends Component
{
    public bool $open = false;

    public function render()
    {
        return view('livewire.ui.modal');
    }
}
