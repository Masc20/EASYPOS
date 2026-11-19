<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityLogger
{
    /**
     * Record a structured audit/activity log entry safely without breaking business operations.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $category,
        string $event,
        string $status,
        string $description,
        ?User $actor = null,
        ?int $branchId = null,
        ?Model $subject = null,
        array $metadata = [],
        ?string $actorName = null,
        ?string $actorEmpId = null,
    ): ?ActivityLog {
        try {
            $resolvedActor = $actor ?? Auth::guard('web')->user();
            $resolvedBranchId = $branchId ?? $resolvedActor?->branch_id;
            $request = app()->bound('request') ? request() : null;

            return ActivityLog::create([
                'user_id' => $resolvedActor?->id,
                'actor_name' => $actorName ?? $resolvedActor?->name ?? 'Guest / System',
                'actor_emp_id' => $actorEmpId ?? $resolvedActor?->emp_id,
                'branch_id' => $resolvedBranchId,
                'category' => $category,
                'event' => $event,
                'status' => $status,
                'description' => mb_substr($description, 0, 500),
                'subject_type' => $subject ? $subject->getMorphClass() : null,
                'subject_id' => $subject?->getKey(),
                'ip_address' => $request?->ip() ?? '127.0.0.1',
                'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'metadata' => $metadata !== [] ? $metadata : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('ActivityLogger::record failed.', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function success(
        string $category,
        string $event,
        string $description,
        ?User $actor = null,
        ?int $branchId = null,
        ?Model $subject = null,
        array $metadata = [],
    ): ?ActivityLog {
        return self::record($category, $event, 'success', $description, $actor, $branchId, $subject, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function failed(
        string $category,
        string $event,
        string $description,
        ?User $actor = null,
        ?int $branchId = null,
        ?Model $subject = null,
        array $metadata = [],
        ?string $actorName = null,
        ?string $actorEmpId = null,
    ): ?ActivityLog {
        return self::record($category, $event, 'failed', $description, $actor, $branchId, $subject, $metadata, $actorName, $actorEmpId);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function denied(
        string $category,
        string $event,
        string $description,
        ?User $actor = null,
        ?int $branchId = null,
        ?Model $subject = null,
        array $metadata = [],
    ): ?ActivityLog {
        return self::record($category, $event, 'denied', $description, $actor, $branchId, $subject, $metadata);
    }
}
