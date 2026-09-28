<?php

namespace App\Services\Privacy;

use App\Contracts\PrivateProofStorageInterface;
use App\Contracts\TransactionManagerInterface;
use App\Repositories\Contracts\ProofRetentionRepositoryInterface;
use App\Services\Operations\OperationsAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class CleanupExpiredProofsService
{
    public function __construct(
        private ProofRetentionRepositoryInterface $retention,
        private PrivateProofStorageInterface $storage,
        private TransactionManagerInterface $transactions,
        private OperationsAlertService $alerts,
    ) {}

    /** @return array{eligible: int, deleted: int, failed: int, dryRun: bool} */
    public function handle(bool $dryRun = false, int $limit = 200, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $timestamp = $now->utc()->toIso8601String();
        $candidates = $this->retention->eligibleForDeletion($timestamp, max(1, min($limit, 1000)));
        if ($dryRun) {
            return ['eligible' => count($candidates), 'deleted' => 0, 'failed' => 0, 'dryRun' => true];
        }

        $deleted = 0;
        $failed = 0;
        foreach ($candidates as $candidate) {
            try {
                $this->storage->delete($candidate->disk, $candidate->key);
                $changed = $this->transactions->run(fn (): bool => $this->retention->markDeleted($candidate, $timestamp));
                $deleted += $changed ? 1 : 0;
            } catch (Throwable $exception) {
                $failed++;
                $errorClass = class_basename($exception);
                $this->transactions->run(function () use ($candidate, $timestamp, $errorClass): void {
                    $this->retention->markFailure($candidate, $timestamp, $errorClass);
                });
                Log::warning('privacy.proof_cleanup_failed', [
                    'source' => $candidate->source,
                    'record_id' => $candidate->id,
                    'error_class' => $errorClass,
                ]);
                $this->alerts->send(
                    'proof_cleanup_failed',
                    'Cleanup proof gagal',
                    'Satu object proof gagal dihapus. Periksa private storage dan jalankan retry sesuai runbook.',
                    ['source' => $candidate->source, 'record_id' => $candidate->id, 'error_class' => $errorClass],
                );
            }
        }

        return ['eligible' => count($candidates), 'deleted' => $deleted, 'failed' => $failed, 'dryRun' => false];
    }

    /** @return array{eligible: int, failed: int, deleted: int} */
    public function summary(?CarbonImmutable $now = null): array
    {
        return $this->retention->summary(($now ?? CarbonImmutable::now())->utc()->toIso8601String());
    }
}
