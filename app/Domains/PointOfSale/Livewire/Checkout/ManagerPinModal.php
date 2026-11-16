<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use App\Domains\PointOfSale\Actions\VoidOrderAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class ManagerPinModal extends Component
{
    public ?int $orderId = null;
    public ?string $errorMessage = null;

    #[On('open-manager-pin')]
    public function openModal(int $orderId): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->orderId = $orderId;
        $this->dispatch('open-modal', 'manager-pin-modal');
    }

    public function submitVoidRequest(array $data): bool
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if (! $this->orderId) {
            $this->errorMessage = 'No order selected for voiding.';
            return false;
        }

        if (blank($data['manager_id'] ?? null) || blank($data['manager_pin'] ?? null) || blank($data['reason'] ?? null)) {
            $this->errorMessage = 'All fields (Manager ID, PIN, Reason) are required.';
            return false;
        }

        $throttleKey = 'void-pin-approval:' . request()->ip() . ':' . trim((string) ($data['manager_id'] ?? '')) . ':' . $this->orderId;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->errorMessage = "Too many failed approval attempts. Terminal locked for void approvals. Please try again in {$seconds} seconds.";

            $lockoutContext = [
                'event' => 'MANAGER_PIN_VOID_LOCKED_OUT',
                'action' => 'void_order_pin_lockout',
                'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                'actor_id' => Auth::id(),
                'ip' => request()->ip(),
                'client_ip' => request()->ip(),
                'order_id' => $this->orderId,
                'manager_id' => $data['manager_id'] ?? null,
                'branch' => Auth::user()?->branch?->name ?? (string) Auth::user()?->branch_id ?? 'unknown',
                'branch_id' => Auth::user()?->branch_id,
                'retry_after' => $seconds,
                'timestamp' => now()->toIso8601String(),
            ];

            Log::warning('MANAGER_PIN_VOID_LOCKED_OUT', $lockoutContext);
            Log::channel('security')?->warning('MANAGER_PIN_VOID_LOCKED_OUT', $lockoutContext);

            return false;
        }

        try {
            $action = new VoidOrderAction();
            // Execute synchronously so invalid credentials/PINs are caught immediately and shown to the user!
            $action->execute([
                'order_id'       => $this->orderId,
                'manager_id'     => $data['manager_id'],
                'manager_pin'    => $data['manager_pin'],
                'reason'         => $data['reason'],
                'cashier_id'     => Auth::id(),
                'skip_pin_check' => false,
            ]);

            RateLimiter::clear($throttleKey);

            $approvalContext = [
                'event' => 'MANAGER_PIN_VOID_APPROVED',
                'action' => 'void_order_pin_approval',
                'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                'actor_id' => Auth::id(),
                'ip' => request()->ip(),
                'client_ip' => request()->ip(),
                'order_id' => $this->orderId,
                'manager_id' => $data['manager_id'],
                'reason' => $data['reason'],
                'branch' => Auth::user()?->branch?->name ?? (string) Auth::user()?->branch_id ?? 'unknown',
                'branch_id' => Auth::user()?->branch_id,
                'timestamp' => now()->toIso8601String(),
            ];

            Log::info('MANAGER_PIN_VOID_APPROVED', $approvalContext);
            Log::channel('security')?->warning('MANAGER_PIN_VOID_APPROVED', $approvalContext);

            $this->dispatch('close-modal', 'manager-pin-modal');
            $this->dispatch('order-voided', orderId: $this->orderId);
            $this->dispatch('pos-alert', [
                'message' => 'Order #' . $this->orderId . ' has been successfully voided and refunded.',
                'type' => 'success'
            ]);

            $this->orderId = null;
            return true;
        } catch (InvalidArgumentException $e) {
            RateLimiter::hit($throttleKey, 600);

            $failureContext = [
                'event' => 'MANAGER_PIN_VOID_ATTEMPT_FAILED',
                'action' => 'void_order_pin_failure',
                'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                'actor_id' => Auth::id(),
                'ip' => request()->ip(),
                'client_ip' => request()->ip(),
                'order_id' => $this->orderId,
                'manager_id' => $data['manager_id'] ?? null,
                'attempts' => RateLimiter::attempts($throttleKey),
                'branch' => Auth::user()?->branch?->name ?? (string) Auth::user()?->branch_id ?? 'unknown',
                'branch_id' => Auth::user()?->branch_id,
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ];

            Log::warning('MANAGER_PIN_VOID_ATTEMPT_FAILED', $failureContext);
            Log::channel('security')?->warning('MANAGER_PIN_VOID_ATTEMPT_FAILED', $failureContext);

            $this->errorMessage = $e->getMessage();
            return false;
        } catch (Throwable $e) {
            report($e);
            $this->errorMessage = 'An unexpected error occurred while processing the void request.';
            return false;
        }
    }

    public function render()
    {
        return view('livewire.point-of-sale.checkout.manager-pin-modal');
    }
}
