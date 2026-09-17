<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Audit\ActivityLogData;
use App\Models\ActivityLog;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;

final class EloquentActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function record(ActivityLogData $data): void
    {
        ActivityLog::query()->create([
            'tenant_id' => $data->tenantId,
            'actor_id' => $data->actorId,
            'action' => $data->action,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'reason' => $data->reason,
            'before' => $data->before ?: null,
            'after' => $data->after ?: null,
            'created_at' => now(),
        ]);
    }

    public function latestForTenant(int $tenantId, int $limit = 20): array
    {
        return ActivityLog::query()
            ->where('tenant_id', $tenantId)
            ->latest('created_at')
            ->limit($limit)
            ->get(['action', 'subject_type', 'subject_id', 'reason', 'created_at'])
            ->map(fn (ActivityLog $log): array => [
                'action' => $log->action,
                'subjectType' => $log->subject_type,
                'subjectId' => $log->subject_id,
                'reason' => $log->reason,
                'createdAt' => $log->created_at->toIso8601String(),
            ])
            ->all();
    }
}
