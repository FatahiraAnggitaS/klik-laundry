<?php

namespace App\Console\Commands;

use App\Services\Privacy\CleanupExpiredProofsService;
use Illuminate\Console\Command;

final class CleanupExpiredProofsCommand extends Command
{
    protected $signature = 'privacy:cleanup-proofs {--dry-run : Count eligible proofs without deleting objects} {--limit=200 : Maximum records per run}';

    protected $description = 'Delete expired private proofs after refund guards have cleared';

    public function handle(CleanupExpiredProofsService $service): int
    {
        $result = $service->handle((bool) $this->option('dry-run'), (int) $this->option('limit'));
        $this->components->info(sprintf(
            'eligible=%d deleted=%d failed=%d dry_run=%s',
            $result['eligible'],
            $result['deleted'],
            $result['failed'],
            $result['dryRun'] ? 'yes' : 'no',
        ));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
