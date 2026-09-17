<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Tenancy\PayoutAccountData;
use App\Enums\PayoutAccountStatus;
use App\Models\TenantPayoutAccount;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use Illuminate\Support\Str;

final class EloquentPayoutAccountRepository implements PayoutAccountRepositoryInterface
{
    public function findCurrentForTenant(int $tenantId): ?PayoutAccountData
    {
        $account = TenantPayoutAccount::query()->where('current_tenant_id', $tenantId)->first();

        return $account === null ? null : $this->map($account);
    }

    public function lockCurrentForTenant(int $tenantId): ?PayoutAccountData
    {
        $account = TenantPayoutAccount::query()
            ->where('current_tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        return $account === null ? null : $this->map($account);
    }

    public function lockByPublicIdForReview(string $publicId): ?PayoutAccountData
    {
        $account = TenantPayoutAccount::query()
            ->where('public_id', $publicId)
            ->lockForUpdate()
            ->first();

        return $account === null ? null : $this->map($account);
    }

    public function pendingForReview(int $limit = 50): array
    {
        return TenantPayoutAccount::query()
            ->with('tenant:id,public_id,name')
            ->whereNotNull('current_tenant_id')
            ->where('verification_status', PayoutAccountStatus::Pending)
            ->oldest()
            ->limit($limit)
            ->get()
            ->map(fn (TenantPayoutAccount $account): array => $this->map($account)->toArray())
            ->all();
    }

    public function supersedeCurrent(int $tenantId): void
    {
        TenantPayoutAccount::query()
            ->where('current_tenant_id', $tenantId)
            ->update([
                'current_tenant_id' => null,
                'verification_status' => PayoutAccountStatus::Superseded,
                'superseded_at' => now(),
            ]);
    }

    public function create(
        int $tenantId,
        int $submitterId,
        string $bankName,
        string $accountHolderName,
        string $accountNumber,
        string $maskedAccountNumber,
    ): PayoutAccountData {
        $account = TenantPayoutAccount::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'current_tenant_id' => $tenantId,
            'bank_name' => $bankName,
            'account_holder_name' => $accountHolderName,
            'account_number' => $accountNumber,
            'masked_account_number' => $maskedAccountNumber,
            'verification_status' => PayoutAccountStatus::Pending,
            'submitted_by' => $submitterId,
        ]);

        return $this->map($account);
    }

    public function review(
        string $publicId,
        PayoutAccountStatus $status,
        int $reviewerId,
        string $reason,
    ): PayoutAccountData {
        $account = TenantPayoutAccount::query()->where('public_id', $publicId)->firstOrFail();
        $account->update([
            'verification_status' => $status,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_reason' => $reason,
        ]);

        return $this->map($account->refresh());
    }

    private function map(TenantPayoutAccount $account): PayoutAccountData
    {
        return new PayoutAccountData(
            publicId: $account->public_id,
            tenantId: $account->tenant_id,
            bankName: $account->bank_name,
            maskedHolderName: $this->maskHolder($account->account_holder_name),
            maskedAccountNumber: $account->masked_account_number,
            verificationStatus: $account->verification_status->value,
            reviewReason: $account->review_reason,
            submittedAt: $account->created_at->toIso8601String(),
            reviewedAt: $account->reviewed_at?->toIso8601String(),
            tenantPublicId: $account->relationLoaded('tenant') ? $account->tenant->public_id : null,
            tenantName: $account->relationLoaded('tenant') ? $account->tenant->name : null,
        );
    }

    private function maskHolder(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->map(fn (string $part): string => mb_substr($part, 0, 1).str_repeat('*', max(mb_strlen($part) - 1, 2)))
            ->implode(' ');
    }
}
