<?php

namespace App\Services\Finance;

use App\Contracts\IdentityUser;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\FinanceReportRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class PrepareFinanceExportService
{
    public function __construct(private FinanceReportRepositoryInterface $reports, private TenantOperationsGuard $guard, private FinancePeriodService $periods, private ActivityLogRepositoryInterface $activityLogs) {}

    /** @return array{filename: string, headers: list<string>, rows: iterable<int, list<int|string|null>>} */
    public function tenant(IdentityUser $actor, ?string $from, ?string $to, ?string $outlet): array
    {
        $tenant = $this->guard->forRead($actor);
        $period = $this->periods->normalize($from, $to);
        $count = $this->reports->countTenantExportRows($tenant->id, $period['from'], $period['to'], $outlet);
        $this->assertLimit($count);
        $this->audit($actor, $tenant->id, 'tenant_finance', $period, $count);

        return ['filename' => 'tenant-finance-'.$period['fromDate'].'-'.$period['toDate'].'.csv', 'headers' => ['type', 'recognized_at_wib', 'outlet', 'order_number', 'source_id', 'gross_paid', 'gateway_fee_actual', 'net_after_fee', 'driver_commission', 'adjustment', 'status'], 'rows' => $this->sanitize($this->reports->tenantExportRows($tenant->id, $period['from'], $period['to'], $outlet))];
    }

    /** @return array{filename: string, headers: list<string>, rows: iterable<int, list<int|string|null>>} */
    public function driver(IdentityUser $actor, ?string $from, ?string $to): array
    {
        if ($actor->role() !== UserRole::Driver || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
        $period = $this->periods->normalize($from, $to);
        $count = $this->reports->countDriverExportRows($actor->databaseId(), $period['from'], $period['to']);
        $this->assertLimit($count);
        $this->audit($actor, $actor->tenantId(), 'driver_commission', $period, $count);

        return ['filename' => 'driver-commission-'.$period['fromDate'].'-'.$period['toDate'].'.csv', 'headers' => ['commission_id', 'task_id', 'task_type', 'order_number', 'amount', 'status', 'earned_at_wib', 'paid_at_wib'], 'rows' => $this->sanitize($this->reports->driverExportRows($actor->databaseId(), $period['from'], $period['to']))];
    }

    /** @return array{filename: string, headers: list<string>, rows: iterable<int, list<int|string|null>>} */
    public function support(IdentityUser $actor, ?string $from, ?string $to): array
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
        $period = $this->periods->normalize($from, $to);
        $count = $this->reports->countSupportExportRows($period['from'], $period['to']);
        $this->assertLimit($count);
        $this->audit($actor, null, 'support_reconciliation', $period, $count);

        return ['filename' => 'reconciliation-'.$period['fromDate'].'-'.$period['toDate'].'.csv', 'headers' => ['type', 'recognized_at_wib', 'tenant', 'order_number', 'source_id', 'amount', 'fee', 'status', 'masked_reference'], 'rows' => $this->sanitize($this->reports->supportExportRows($period['from'], $period['to']))];
    }

    private function assertLimit(int $count): void
    {
        if ($count > 5000) {
            throw new DomainActionConflict('Export row limit exceeded.', 'Export melebihi 5.000 baris. Persempit rentang atau filter.');
        }
    }

    /** @param array{from: string, to: string, fromDate: string, toDate: string} $period */
    private function audit(IdentityUser $actor, ?int $tenantId, string $type, array $period, int $count): void
    {
        $this->activityLogs->record(new ActivityLogData($tenantId, $actor->databaseId(), 'finance.exported', 'finance_export', $type.':'.$period['fromDate'].':'.$period['toDate'], after: ['type' => $type, 'from' => $period['fromDate'], 'to' => $period['toDate'], 'rowCount' => $count]));
    }

    /** @param iterable<int, list<int|string|null>> $rows @return iterable<int, list<int|string|null>> */
    private function sanitize(iterable $rows): iterable
    {
        foreach ($rows as $row) {
            yield array_map(function (int|string|null $value): int|string|null {
                if (! is_string($value)) {
                    return $value;
                }
                $normalized = trim((string) preg_replace('/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F]+/u', ' ', $value));

                return preg_match('/^[=+\-@]/u', ltrim($normalized)) === 1 ? "'".$normalized : $normalized;
            }, $row);
        }
    }
}
