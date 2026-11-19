<?php

namespace App\Domains\Menu\Events;

use App\Domains\Menu\Models\Meal;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MealAvailabilityToggled implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Meal $meal,
        public ?int $branchId = null,
        public ?bool $effectiveAvailability = null,
    ) {}

    public function broadcastWhen(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        return ! in_array($driver, ['', 'null', 'log'], true);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $target = $this->branchId ?? $this->meal->branch_id ?? 'global';

        return [
            new PrivateChannel('branch.'.$target),
        ];
    }

    /**
     * @return array{meal_id: int, is_available: bool, branch_id: ?int, name: string}
     */
    public function broadcastWith(): array
    {
        return [
            'meal_id' => $this->meal->id,
            'is_available' => $this->effectiveAvailability ?? (bool) $this->meal->is_available,
            'branch_id' => $this->branchId ?? $this->meal->branch_id,
            'name' => $this->meal->name,
        ];
    }
}
