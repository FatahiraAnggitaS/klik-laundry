<?php

namespace App\Services\MilestoneZero;

use App\DTOs\Payments\MilestoneZeroPreflightResult;
use DateTimeImmutable;
use InvalidArgumentException;

final class ValidateEvidenceManifestService
{
    /** @var array<string, list<string>> */
    private const REQUIRED_APPROVER_ROLES = [
        'DEC-001' => ['Business', 'Duitku'],
        'DEC-002' => ['Business', 'Legal', 'Duitku'],
        'DEC-003' => ['Business', 'Finance'],
        'DEC-004' => ['Business', 'Finance'],
        'DEC-005' => ['Business', 'Finance', 'Legal'],
        'DEC-006' => ['Business', 'Finance'],
        'DEC-007' => ['Business', 'Legal', 'Finance'],
        'DEC-008' => ['Product', 'Operations'],
        'DEC-009' => ['Product', 'Legal/Privacy'],
        'DEC-010' => ['Legal/Privacy', 'Operations'],
        'DEC-011' => ['Legal', 'Finance'],
        'DEC-012' => ['Product', 'Engineering'],
        'DEC-013' => ['Security', 'Duitku'],
        'DEC-014' => ['Engineering', 'Security'],
    ];

    private const REQUIRED_RETENTION_FIELDS = [
        'financialRecordsPeriod',
        'piiPeriod',
        'lawfulBasisReference',
        'deletionRule',
        'anonymizationRule',
    ];

    private const FORBIDDEN_KEYS = [
        'api_key',
        'apikey',
        'credential',
        'password',
        'payment_url',
        'raw_callback',
        'secret',
        'signature',
        'token',
    ];

    /** @param array<string, mixed> $manifest */
    public function handle(array $manifest, string $callbackUrl, string $returnUrl): MilestoneZeroPreflightResult
    {
        $this->assertNoForbiddenKeys($manifest);

        $decisions = $manifest['decisions'] ?? null;
        if (! is_array($decisions)) {
            throw new InvalidArgumentException('Evidence manifest harus memuat DEC-001 sampai DEC-014 secara berurutan.');
        }

        $decisionIds = array_keys($decisions);
        $requiredDecisionIds = array_keys(self::REQUIRED_APPROVER_ROLES);
        sort($decisionIds);
        sort($requiredDecisionIds);
        if ($decisionIds !== $requiredDecisionIds) {
            throw new InvalidArgumentException('Evidence manifest harus memuat DEC-001 sampai DEC-014.');
        }

        $reviewedDates = [];
        foreach (self::REQUIRED_APPROVER_ROLES as $decisionId => $requiredRoles) {
            $decision = $decisions[$decisionId] ?? null;
            if (! is_array($decision)) {
                throw new InvalidArgumentException("Evidence manifest tidak valid pada {$decisionId}.");
            }

            $status = $decision['status'] ?? null;
            $reviewedAt = $decision['reviewedAt'] ?? null;
            $roles = $decision['approverRoles'] ?? null;
            $reference = $decision['evidenceReference'] ?? null;

            if (! in_array($status, ['approved', 'verified'], true)
                || ! is_string($reviewedAt)
                || ! $this->isDate($reviewedAt)
                || ! is_array($roles)
                || array_filter($roles, static fn (mixed $role): bool => ! is_string($role)) !== []
                || array_diff($requiredRoles, $roles) !== []
                || ! $this->isSafeReference($reference)) {
                throw new InvalidArgumentException("Evidence manifest tidak lengkap pada {$decisionId}.");
            }

            $reviewedDates[] = $reviewedAt;
        }

        $retention = $manifest['retention'] ?? null;
        if (! is_array($retention)) {
            throw new InvalidArgumentException('Evidence manifest belum memuat retention contract.');
        }

        foreach (self::REQUIRED_RETENTION_FIELDS as $field) {
            if (! $this->isResolvedValue($retention[$field] ?? null)) {
                throw new InvalidArgumentException("Evidence manifest belum melengkapi retention.{$field}.");
            }
        }

        if (! $this->isSafeReference($retention['lawfulBasisReference'])) {
            throw new InvalidArgumentException('Evidence manifest memuat retention.lawfulBasisReference yang tidak aman.');
        }

        $staging = $manifest['staging'] ?? null;
        if (! is_array($staging)
            || ! $this->isSafeReference($staging['reference'] ?? null)
            || ! $this->isResolvedValue($staging['operatorRole'] ?? null)
            || ! is_string($staging['callbackUrl'] ?? null)
            || ! is_string($staging['returnUrl'] ?? null)
            || ! hash_equals($callbackUrl, $staging['callbackUrl'])
            || ! hash_equals($returnUrl, $staging['returnUrl'])) {
            throw new InvalidArgumentException('Evidence manifest staging tidak lengkap atau berbeda dari konfigurasi runtime.');
        }

        sort($reviewedDates);

        return new MilestoneZeroPreflightResult(
            decisionCount: count($decisions),
            reviewedAt: end($reviewedDates) ?: '',
            stagingReference: (string) $staging['reference'],
        );
    }

    /** @param array<string, mixed> $values */
    private function assertNoForbiddenKeys(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                throw new InvalidArgumentException('Evidence manifest memuat field sensitif yang dilarang.');
            }

            if (is_array($value)) {
                $this->assertNoForbiddenKeys($value);
            }
        }
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function isResolvedValue(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && ! str_contains(strtolower($value), 'replace-with')
            && ! str_contains(strtolower($value), 'yyyy-mm-dd');
    }

    private function isSafeReference(mixed $value): bool
    {
        if (! $this->isResolvedValue($value)) {
            return false;
        }

        /** @var string $value */
        return strlen($value) <= 200
            && ! str_contains($value, '?')
            && ! str_contains($value, '#')
            && ! str_contains($value, '@')
            && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:\/\-]*\z/', $value) === 1;
    }
}
