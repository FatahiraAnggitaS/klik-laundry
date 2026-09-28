<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class CheckReadinessService
{
    public function __construct(private OperationsAlertService $alerts) {}

    public function handle(): bool
    {
        try {
            DB::select('select 1');
            $key = 'readiness:'.Str::uuid();
            Cache::put($key, 'ok', 10);
            if (Cache::pull($key) !== 'ok') {
                throw new RuntimeException('Cache readiness check failed.');
            }
            Storage::disk((string) config('filesystems.private_proof_disk', 'local'))->exists('.readiness');

            return true;
        } catch (Throwable $exception) {
            $this->alerts->send(
                'readiness_failed',
                'Readiness check gagal',
                'Satu dependency aplikasi tidak siap. Ikuti runbook readiness tanpa membocorkan detail ke client.',
                ['error_class' => class_basename($exception)],
            );

            return false;
        }
    }
}
