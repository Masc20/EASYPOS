<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use App\Domains\Audit\Services\ActivityLogger;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Support\SecurityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class ManagerPinModal extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->user()?->can('pos.access'), 403);
    }

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

        $targetOrderId = isset($data['order_id']) && is_numeric($data['order_id'])
            ? (int) $data['order_id']
            : $this->orderId;

        if (! $targetOrderId) {
            $this->errorMessage = 'No order selected for voiding.';
            return false;
        }

        $this->orderId = $targetOrderId;

        if (blank($data['manager_id'] ?? null) || blank($data['manager_pin'] ?? null) || blank($data['reason'] ?? null)) {
            $this->errorMessage = 'All fields (Manager ID, PIN, Reason) are required.';
            return false;
        }

        $normalizedManagerId = strtoupper(trim((string) ($data['manager_id'] ?? '')));
        $throttleKey = 'void-pin-approval:' . request()->ip() . ':' . $normalizedManagerId;

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
                'timestamp' => now(config('app.business_timezone'))->toIso8601String(),
            ];

            Log::warning('MANAGER_PIN_VOID_LOCKED_OUT', $lockoutContext);
            SecurityLogger::log('warning', 'MANAGER_PIN_VOID_LOCKED_OUT', $lockoutContext);
            ActivityLogger::failed(
                category: 'pos',
                event: 'order_void_failed',
                description: "Void approval PIN locked out for order #{$this->orderId}.",
                metadata: ['order_id' => $this->orderId, 'manager_id' => $data['manager_id'] ?? null, 'reason' => 'lockout'],
            );

            return false;
        }

        $actor = Auth::user();
        $targetOrder = Order::find($this->orderId);
        if (! $targetOrder || ($actor && $actor->isBranchScoped() && ($actor->branch_id === null || (int) $targetOrder->branch_id !== (int) $actor->branch_id))) {
            RateLimiter::hit($throttleKey, 600);
            $errorReason = 'Unable to process void request for the selected order.';

            $failureContext = [
                'event' => 'MANAGER_PIN_VOID_ATTEMPT_FAILED',
                'action' => 'void_order_pin_failure',
                'actor' => $actor?->emp_id ?? (string) Auth::id() ?? 'guest',
                'actor_id' => Auth::id(),
                'ip' => request()->ip(),
                'client_ip' => request()->ip(),
                'order_id' => $this->orderId,
                'manager_id' => $data['manager_id'] ?? null,
                'attempts' => RateLimiter::attempts($throttleKey),
                'branch' => $actor?->branch?->name ?? (string) $actor?->branch_id ?? 'unknown',
                'branch_id' => $actor?->branch_id,
                'error' => $errorReason,
                'timestamp' => now(config('app.business_timezone'))->toIso8601String(),
            ];

            Log::warning('MANAGER_PIN_VOID_ATTEMPT_FAILED', $failureContext);
            SecurityLogger::log('warning', 'MANAGER_PIN_VOID_ATTEMPT_FAILED', $failureContext);
            ActivityLogger::failed(
                category: 'pos',
                event: 'order_void_failed',
                description: "Failed void authorization for order #{$this->orderId}: {$errorReason}",
                metadata: ['order_id' => $this->orderId, 'manager_id' => $data['manager_id'] ?? null, 'error' => $errorReason],
            );

            $this->errorMessage = $errorReason;
            return false;
        }

        try {
            $action = new VoidOrderAction();
            // Execute synchronously so invalid credentials/PINs are caught immediately and shown to the user!
            $voidedOrder = $action->execute([
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
                'timestamp' => now(config('app.business_timezone'))->toIso8601String(),
            ];

            Log::info('MANAGER_PIN_VOID_APPROVED', $approvalContext);
            SecurityLogger::log('warning', 'MANAGER_PIN_VOID_APPROVED', $approvalContext);

            $this->dispatch('close-modal', 'manager-pin-modal');
            $this->dispatch('order-voided', orderId: $this->orderId);
            $this->dispatch('pos-alert', [
                'message' => 'Order #' . ($voidedOrder->order_number ?: $this->orderId) . ' has been successfully voided and refunded.',
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
                'timestamp' => now(config('app.business_timezone'))->toIso8601String(),
            ];

            Log::warning('MANAGER_PIN_VOID_ATTEMPT_FAILED', $failureContext);
            SecurityLogger::log('warning', 'MANAGER_PIN_VOID_ATTEMPT_FAILED', $failureContext);
            ActivityLogger::failed(
                category: 'pos',
                event: 'order_void_failed',
                description: "Failed void authorization for order #{$this->orderId}: {$e->getMessage()}",
                metadata: ['order_id' => $this->orderId, 'manager_id' => $data['manager_id'] ?? null, 'error' => $e->getMessage()],
            );

            $this->errorMessage = $this->sanitizeVoidErrorMessage($e->getMessage());
            return false;
        } catch (Throwable $e) {
            report($e);
            $this->errorMessage = 'An unexpected error occurred while processing the void request.';
            return false;
        }
    }

    private function sanitizeVoidErrorMessage(string $rawMessage): string
    {
        $credentialOrBranchErrors = [
            'Manager record not found.',
            'Invalid manager PIN.',
            'Manager does not belong to this branch.',
            'User is not authorized to approve voids.',
        ];

        if (in_array($rawMessage, $credentialOrBranchErrors, true)) {
            return 'Invalid manager PIN.';
        }

        if (str_contains(strtolower($rawMessage), 'not found')) {
            return 'Unable to process void request for the selected order.';
        }

        return $rawMessage;
    }

    public function render()
    {
        return view('livewire.point-of-sale.checkout.manager-pin-modal');
    }
}
