<?php

namespace App\Domains\Identity\Models;

use App\Domains\Branches\Models\Branch;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'emp_id',
        'branch_id',
        'pin_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin_code',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_code' => 'hashed',
        ];
    }

    /**
     * The branch this employee belongs to (null for global Owner).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            // Security gate: A 4-digit PIN terminal session can NEVER access the admin panel.
            // Full email + password authentication is strictly required for back-office administration.
            if (session('auth_method') === 'pin') {
                return false;
            }

            return $this->hasRole(['owner', 'super-admin', 'branch-manager']) || $this->can('admin.access');
        }

        return true;
    }

    /**
     * Verify the 4-digit PIN for floor staff.
     */
    public function verifyPin(string $pin): bool
    {
        if (blank($this->pin_code)) {
            return false;
        }

        return Hash::check($pin, $this->pin_code);
    }

    /**
     * Check if user is a floor staff member (Cashier, Cook, Chef).
     */
    public function isFloorStaff(): bool
    {
        return $this->hasAnyRole(['cashier', 'cook', 'chef']);
    }

    /**
     * Check if user is strictly floor staff without management/ownership roles.
     */
    public function isStrictFloorStaff(): bool
    {
        return $this->isFloorStaff() && ! $this->hasAnyRole(['owner', 'super-admin', 'branch-manager']);
    }

    /**
     * Get the dedicated station title for floor staff.
     */
    public function stationTitle(): string
    {
        if ($this->hasRole('cashier')) {
            return 'POS Register Terminal';
        }

        if ($this->hasRole('chef')) {
            return 'Kitchen & Inventory Station';
        }

        if ($this->hasRole('cook')) {
            return 'Kitchen Display System (KDS)';
        }

        return 'Terminal Station';
    }

    /**
     * Get the dedicated station route for floor staff.
     */
    public function stationRoute(): string
    {
        if ($this->hasRole('cashier')) {
            return route('pos');
        }

        if ($this->hasRole('chef')) {
            return route('inventory');
        }

        if ($this->hasRole('cook')) {
            return route('kitchen');
        }

        if ($this->hasRole('branch-manager')) {
            return route('pos');
        }

        return route('dashboard');
    }

    /**
     * Check if user is the global owner or executive.
     */
    public function isOwner(): bool
    {
        return $this->hasAnyRole(['owner', 'super-admin']);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
