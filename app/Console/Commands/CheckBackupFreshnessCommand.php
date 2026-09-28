<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationsAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CheckBackupFreshnessCommand extends Command
{
    protected $signature = 'operations:check-backup';

    protected $description = 'Fail and alert when the redacted external backup status is missing or stale';

    public function handle(OperationsAlertService $alerts): int
    {
        $maximumAge = config('operations.backup_max_age_hours');
        if (! is_numeric($maximumAge) || (int) $maximumAge <= 0) {
            return $this->failCheck($alerts, 'Backup maximum age is not configured.');
        }

        $path = (string) config('operations.backup_status_path', 'operations/backup-status.json');
        $disk = Storage::disk((string) config('filesystems.private_proof_disk', 'local'));
        if (! $disk->exists($path)) {
            return $this->failCheck($alerts, 'Backup status evidence is missing.');
        }

        try {
            $status = json_decode($disk->get($path), true, 32, JSON_THROW_ON_ERROR);
            $lastSuccess = CarbonImmutable::parse((string) data_get($status, 'last_success_at_utc'), 'UTC');
        } catch (Throwable) {
            return $this->failCheck($alerts, 'Backup status evidence is invalid.');
        }

        if ($lastSuccess->addHours((int) $maximumAge)->isPast()) {
            return $this->failCheck($alerts, 'Latest encrypted backup is stale.');
        }

        $this->info('Backup freshness check passed.');

        return self::SUCCESS;
    }

    private function failCheck(OperationsAlertService $alerts, string $message): int
    {
        $alerts->send('backup_stale', 'Backup staging/production perlu diperiksa', $message);
        $this->error($message);

        return self::FAILURE;
    }
}
