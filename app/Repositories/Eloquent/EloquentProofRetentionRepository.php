<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Privacy\ProofCleanupCandidateData;
use App\Enums\RefundStatus;
use App\Repositories\Contracts\ProofRetentionRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentProofRetentionRepository implements ProofRetentionRepositoryInterface
{
    public function eligibleForDeletion(string $now, int $limit): array
    {
        $tasks = $this->eligibleQuery('delivery_tasks', $now)->limit($limit)->get()
            ->map(fn (object $row): ProofCleanupCandidateData => new ProofCleanupCandidateData('delivery_task', (int) $row->id, $row->proof_disk, $row->proof_key));
        $remaining = max(0, $limit - $tasks->count());
        $weights = $remaining === 0 ? collect() : $this->eligibleQuery('weight_confirmations', $now)->limit($remaining)->get()
            ->map(fn (object $row): ProofCleanupCandidateData => new ProofCleanupCandidateData('weight_confirmation', (int) $row->id, $row->proof_disk, $row->proof_key));

        return $tasks->concat($weights)->values()->all();
    }

    public function markDeleted(ProofCleanupCandidateData $candidate, string $deletedAt): bool
    {
        return DB::table($this->table($candidate))->where('id', $candidate->id)->where('proof_key', $candidate->key)
            ->whereNull('proof_deleted_at')->update([
                'proof_disk' => null,
                'proof_key' => null,
                'proof_access_revoked_at' => DB::raw('COALESCE(proof_access_revoked_at, CURRENT_TIMESTAMP)'),
                'proof_deleted_at' => $deletedAt,
                'proof_cleanup_error' => null,
                'proof_cleanup_failed_at' => null,
                'updated_at' => now(),
            ]) === 1;
    }

    public function markFailure(ProofCleanupCandidateData $candidate, string $failedAt, string $errorClass): void
    {
        DB::table($this->table($candidate))->where('id', $candidate->id)->where('proof_key', $candidate->key)
            ->whereNull('proof_deleted_at')->update([
                'proof_cleanup_attempts' => DB::raw('proof_cleanup_attempts + 1'),
                'proof_cleanup_failed_at' => $failedAt,
                'proof_cleanup_error' => mb_substr($errorClass, 0, 120),
                'updated_at' => now(),
            ]);
    }

    public function summary(string $now): array
    {
        $eligible = $this->eligibleQuery('delivery_tasks', $now)->count() + $this->eligibleQuery('weight_confirmations', $now)->count();
        $failed = DB::table('delivery_tasks')->whereNotNull('proof_cleanup_failed_at')->whereNull('proof_deleted_at')->count()
            + DB::table('weight_confirmations')->whereNotNull('proof_cleanup_failed_at')->whereNull('proof_deleted_at')->count();
        $deleted = DB::table('delivery_tasks')->whereNotNull('proof_deleted_at')->count()
            + DB::table('weight_confirmations')->whereNotNull('proof_deleted_at')->count();

        return ['eligible' => $eligible, 'failed' => $failed, 'deleted' => $deleted];
    }

    private function eligibleQuery(string $table, string $now): Builder
    {
        return DB::table($table)->select(['id', 'proof_disk', 'proof_key'])
            ->whereNotNull('proof_disk')->whereNotNull('proof_key')
            ->whereNull('proof_deleted_at')->where('proof_expires_at', '<=', $now)
            ->whereNotExists(function (Builder $query) use ($table): void {
                $query->selectRaw('1')->from('refund_requests')
                    ->join('payments', 'payments.id', '=', 'refund_requests.payment_id')
                    ->whereColumn('payments.order_id', $table.'.order_id')
                    ->whereIn('refund_requests.status', [RefundStatus::Submitted->value, RefundStatus::Approved->value]);
            })->orderBy('id');
    }

    private function table(ProofCleanupCandidateData $candidate): string
    {
        return $candidate->source === 'delivery_task' ? 'delivery_tasks' : 'weight_confirmations';
    }
}
