<?php

namespace App\Domains\PointOfSale\Models;

use App\Domains\Branches\Models\Branch;
use App\Domains\Kitchen\Models\KitchenTicket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BranchDailySequence extends Model
{
    use HasFactory;

    protected $table = 'branch_daily_sequences';

    protected $fillable = [
        'branch_id',
        'sequence_date',
        'sequence_type',
        'current_value',
    ];

    protected $casts = [
        'current_value' => 'integer',
    ];

    public function getSequenceDateAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function setSequenceDateAttribute($value): void
    {
        $this->attributes['sequence_date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : (is_string($value) ? substr($value, 0, 10) : $value);
    }

    /**
     * Branch this sequence belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the next sequence value atomically for a branch, sequence type, and date.
     * Uses pessimistic row-level locking on the sequence record to prevent
     * concurrent allocation collisions and gap lock deadlocks.
     *
     * @param int $branchId
     * @param string $type e.g. 'order', 'kitchen_ticket'
     * @param string|null $date Format: 'Y-m-d', defaults to today
     * @return int Next allocated sequential number (1-based)
     */
    public static function getNextSequence(int $branchId, string $type, ?string $date = null): int
    {
        $date = $date ?? today()->toDateString();

        $allocate = function () use ($branchId, $type, $date) {
            $sequence = static::where('branch_id', $branchId)
                ->where('sequence_date', $date)
                ->where('sequence_type', $type)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                // If sequence record does not exist yet for today, determine initial count
                // based on any pre-existing records to guarantee continuity across legacy seeds
                $initialCount = static::resolvePreExistingCount($branchId, $type, $date);

                try {
                    $sequence = static::create([
                        'branch_id' => $branchId,
                        'sequence_date' => $date,
                        'sequence_type' => $type,
                        'current_value' => $initialCount,
                    ]);

                    // Acquire row lock on newly created row
                    $sequence = static::where('id', $sequence->id)->lockForUpdate()->first();
                } catch (QueryException $e) {
                    // Handled race condition: another concurrent process created the row first
                    $sequence = static::where('branch_id', $branchId)
                        ->where('sequence_date', $date)
                        ->where('sequence_type', $type)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $sequence->increment('current_value');

            return (int) $sequence->current_value;
        };

        if (DB::transactionLevel() > 0) {
            return $allocate();
        }

        return DB::transaction($allocate);
    }

    /**
     * Count any pre-existing records for today to prevent sequence collision
     * with existing manually seeded orders or tickets.
     */
    protected static function resolvePreExistingCount(int $branchId, string $type, string $date): int
    {
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->addDay()->startOfDay();

        if ($type === 'order') {
            return Order::where('branch_id', $branchId)
                ->where('created_at', '>=', $startOfDay)
                ->where('created_at', '<', $endOfDay)
                ->count();
        }

        if ($type === 'kitchen_ticket') {
            return KitchenTicket::where('branch_id', $branchId)
                ->where('created_at', '>=', $startOfDay)
                ->where('created_at', '<', $endOfDay)
                ->count();
        }

        return 0;
    }
}
