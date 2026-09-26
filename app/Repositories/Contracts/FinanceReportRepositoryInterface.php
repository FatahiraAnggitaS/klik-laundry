<?php

namespace App\Repositories\Contracts;

interface FinanceReportRepositoryInterface
{
    /** @return array<string, mixed> */
    public function tenantReport(int $tenantId, string $from, string $to, ?string $outletPublicId): array;

    /** @return array<string, mixed> */
    public function driverReport(int $driverId, string $from, string $to): array;

    /** @return array<string, int> */
    public function supportMetrics(string $from, string $to): array;

    /** @return list<array{publicId: string, name: string}> */
    public function driversForTenant(int $tenantId): array;

    /** @return list<array{publicId: string, name: string}> */
    public function outletsForTenant(int $tenantId): array;

    /** @return list<array{publicId: string, name: string}> */
    public function tenantsForSupport(): array;

    public function countTenantExportRows(int $tenantId, string $from, string $to, ?string $outletPublicId): int;

    /** @return iterable<int, list<int|string|null>> */
    public function tenantExportRows(int $tenantId, string $from, string $to, ?string $outletPublicId): iterable;

    public function countDriverExportRows(int $driverId, string $from, string $to): int;

    /** @return iterable<int, list<int|string|null>> */
    public function driverExportRows(int $driverId, string $from, string $to): iterable;

    public function countSupportExportRows(string $from, string $to): int;

    /** @return iterable<int, list<int|string|null>> */
    public function supportExportRows(string $from, string $to): iterable;
}
