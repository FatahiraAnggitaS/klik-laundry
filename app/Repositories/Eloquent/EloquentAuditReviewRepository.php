<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\AuditReviewRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentAuditReviewRepository implements AuditReviewRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 30): array
    {
        $activity = DB::table('activity_logs')
            ->leftJoin('users as activity_actor', 'activity_actor.id', '=', 'activity_logs.actor_id')
            ->leftJoin('tenants as activity_tenant', 'activity_tenant.id', '=', 'activity_logs.tenant_id')
            ->selectRaw("activity_logs.id as source_id, 'activity' as source, activity_logs.action, activity_logs.subject_type, activity_logs.subject_id, CASE WHEN activity_logs.reason IS NULL OR activity_logs.reason = '' THEN 0 ELSE 1 END as reason_recorded, activity_actor.public_id as actor_public_id, activity_tenant.public_id as tenant_public_id, activity_logs.created_at as occurred_at");

        $privacy = DB::table('pii_access_logs')
            ->join('orders as privacy_order', 'privacy_order.id', '=', 'pii_access_logs.order_id')
            ->join('users as privacy_actor', 'privacy_actor.id', '=', 'pii_access_logs.actor_id')
            ->join('tenants as privacy_tenant', 'privacy_tenant.id', '=', 'privacy_order.tenant_id')
            ->leftJoin('pii_access_grants', 'pii_access_grants.id', '=', 'pii_access_logs.grant_id')
            ->selectRaw("pii_access_logs.id as source_id, 'pii_access' as source, ('pii.' || pii_access_logs.event) as action, 'order' as subject_type, privacy_order.public_id as subject_id, CASE WHEN pii_access_grants.reason IS NULL OR pii_access_grants.reason = '' THEN 0 ELSE 1 END as reason_recorded, privacy_actor.public_id as actor_public_id, privacy_tenant.public_id as tenant_public_id, pii_access_logs.occurred_at as occurred_at");

        if (DB::connection()->getDriverName() === 'pgsql') {
            $privacy = DB::table('pii_access_logs')
                ->join('orders as privacy_order', 'privacy_order.id', '=', 'pii_access_logs.order_id')
                ->join('users as privacy_actor', 'privacy_actor.id', '=', 'pii_access_logs.actor_id')
                ->join('tenants as privacy_tenant', 'privacy_tenant.id', '=', 'privacy_order.tenant_id')
                ->leftJoin('pii_access_grants', 'pii_access_grants.id', '=', 'pii_access_logs.grant_id')
                ->selectRaw("pii_access_logs.id as source_id, 'pii_access' as source, CONCAT('pii.', pii_access_logs.event) as action, 'order' as subject_type, privacy_order.public_id as subject_id, CASE WHEN pii_access_grants.reason IS NULL OR pii_access_grants.reason = '' THEN 0 ELSE 1 END as reason_recorded, privacy_actor.public_id as actor_public_id, privacy_tenant.public_id as tenant_public_id, pii_access_logs.occurred_at as occurred_at");
        }

        $query = DB::query()->fromSub($activity->unionAll($privacy), 'audit');
        $this->applyFilters($query, $filters);

        /** @var LengthAwarePaginator<int, object> $page */
        $page = $query->orderByDesc('occurred_at')->orderByDesc('source_id')->paginate($perPage)->withQueryString();

        return [
            'items' => $page->getCollection()->map(fn (object $row): array => [
                'source' => (string) $row->source,
                'action' => (string) $row->action,
                'subjectType' => (string) $row->subject_type,
                'subjectId' => (string) $row->subject_id,
                'reasonRecorded' => (bool) $row->reason_recorded,
                'actorPublicId' => $row->actor_public_id === null ? null : (string) $row->actor_public_id,
                'tenantPublicId' => $row->tenant_public_id === null ? null : (string) $row->tenant_public_id,
                'occurredAt' => (string) $row->occurred_at,
            ])->all(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /** @param array<string, string|null> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query->when($filters['action'] ?? null, fn (Builder $builder, string $value): Builder => $builder->where('action', 'like', '%'.$value.'%'))
            ->when($filters['actor'] ?? null, fn (Builder $builder, string $value): Builder => $builder->where('actor_public_id', $value))
            ->when($filters['tenant'] ?? null, fn (Builder $builder, string $value): Builder => $builder->where('tenant_public_id', $value))
            ->when($filters['subject'] ?? null, fn (Builder $builder, string $value): Builder => $builder->where('subject_id', $value))
            ->when($filters['from'] ?? null, fn (Builder $builder, string $value): Builder => $builder->where(
                'occurred_at',
                '>=',
                CarbonImmutable::parse($value, 'Asia/Jakarta')->startOfDay()->utc()->format('Y-m-d H:i:s'),
            ))
            ->when($filters['to'] ?? null, fn (Builder $builder, string $value): Builder => $builder->where(
                'occurred_at',
                '<=',
                CarbonImmutable::parse($value, 'Asia/Jakarta')->endOfDay()->utc()->format('Y-m-d H:i:s'),
            ));
    }
}
