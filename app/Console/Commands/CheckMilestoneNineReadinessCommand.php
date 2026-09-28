<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;

class CheckMilestoneNineReadinessCommand extends Command
{
    protected $signature = 'm9:readiness-check {--manifest=milestone-9/evidence.json : Path on the private disk}';

    protected $description = 'Validate redacted Milestone 9 release evidence without printing sensitive content';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Readiness preflight must run in staging, not production.');

            return self::FAILURE;
        }

        $path = (string) $this->option('manifest');
        $disk = Storage::disk((string) config('filesystems.private_proof_disk', 'local'));

        if (! $disk->exists($path)) {
            $this->error('Private evidence manifest is missing.');

            return self::FAILURE;
        }

        try {
            $manifest = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error('Private evidence manifest is not valid JSON.');

            return self::FAILURE;
        }

        if (! is_array($manifest)) {
            $this->error('Private evidence manifest has an invalid root value.');

            return self::FAILURE;
        }

        $requiredPasses = [
            'load_test.status',
            'restore_drill.status',
            'process_restart.status',
            'browser_uat.status',
            'availability.status',
            'rpo_rto_approval.status',
            'blockers.milestone_0',
            'blockers.milestone_6',
        ];

        $failures = [];
        foreach ($requiredPasses as $key) {
            if (data_get($manifest, $key) !== 'passed') {
                $failures[] = $key;
            }
        }

        foreach (['load_test', 'restore_drill', 'process_restart', 'browser_uat', 'availability', 'rpo_rto_approval'] as $section) {
            if (! is_string(data_get($manifest, "$section.evidence_reference")) || data_get($manifest, "$section.evidence_reference") === '') {
                $failures[] = "$section.evidence_reference";
            }
        }

        if (config('operations.rpo_approved') !== true || config('operations.rto_approved') !== true) {
            $failures[] = 'operations environment approval';
        }

        if ($failures !== []) {
            $this->error('Milestone 9 remains release blocked. Missing or failed checks:');
            foreach (array_unique($failures) as $failure) {
                $this->line("- {$failure}");
            }

            return self::FAILURE;
        }

        $this->info('Milestone 9 release evidence is complete.');

        return self::SUCCESS;
    }
}
