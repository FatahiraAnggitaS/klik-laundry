<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Tenancy\TenantApplicationData;
use App\DTOs\Tenancy\TenantRegistrationData;
use App\DTOs\Tenancy\TenantResubmissionData;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Str;

final class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function create(TenantRegistrationData $data): TenantApplicationData
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $data->businessName,
            'slug' => $this->uniqueSlug($data->businessName),
            'phone' => $data->phone,
            'onboarding_status' => TenantOnboardingStatus::Pending,
            'operational_status' => TenantOperationalStatus::Inactive,
            'initial_outlet_name' => $data->outletName,
            'initial_outlet_address' => $data->outletAddress,
            'initial_outlet_city' => $data->city,
            'initial_outlet_area' => $data->area,
            'initial_outlet_latitude' => $data->latitude,
            'initial_outlet_longitude' => $data->longitude,
        ]);

        return $this->map($tenant->refresh());
    }

    public function findOwnedByTenantId(int $tenantId): ?TenantApplicationData
    {
        $tenant = Tenant::query()->with($this->ownerRelation())->find($tenantId);

        return $tenant === null ? null : $this->map($tenant);
    }

    public function lockOwnedByTenantId(int $tenantId): ?TenantApplicationData
    {
        $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->first();

        return $tenant === null ? null : $this->map($tenant);
    }

    public function lockByPublicIdForReview(string $publicId): ?TenantApplicationData
    {
        $tenant = Tenant::query()
            ->with($this->ownerRelation())
            ->where('public_id', $publicId)
            ->lockForUpdate()
            ->first();

        return $tenant === null ? null : $this->map($tenant);
    }

    public function paginateForReview(int $perPage): array
    {
        $page = Tenant::query()
            ->with($this->ownerRelation())
            ->orderByRaw("CASE onboarding_status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'items' => $page->getCollection()
                ->map(fn (Tenant $tenant): array => $this->map($tenant)->toArray())
                ->values()
                ->all(),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    public function setReviewDecision(
        int $tenantId,
        TenantOnboardingStatus $onboardingStatus,
        TenantOperationalStatus $operationalStatus,
        int $reviewerId,
        string $reason,
    ): TenantApplicationData {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->update([
            'onboarding_status' => $onboardingStatus,
            'operational_status' => $operationalStatus,
            'review_reason' => $reason,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        return $this->map($tenant->refresh());
    }

    public function resubmit(int $tenantId, TenantResubmissionData $data): TenantApplicationData
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->update([
            'name' => $data->businessName,
            'phone' => $data->phone,
            'onboarding_status' => TenantOnboardingStatus::Pending,
            'operational_status' => TenantOperationalStatus::Inactive,
            'review_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'initial_outlet_name' => $data->outletName,
            'initial_outlet_address' => $data->outletAddress,
            'initial_outlet_city' => $data->city,
            'initial_outlet_area' => $data->area,
            'initial_outlet_latitude' => $data->latitude,
            'initial_outlet_longitude' => $data->longitude,
        ]);

        return $this->map($tenant->refresh());
    }

    public function setOperationalStatus(
        int $tenantId,
        TenantOperationalStatus $status,
        int $actorId,
        string $reason,
    ): TenantApplicationData {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->update([
            'operational_status' => $status,
            'review_reason' => $reason,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
        ]);

        return $this->map($tenant->refresh());
    }

    public function requestClosure(int $tenantId): TenantApplicationData
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->update(['closure_requested_at' => now()]);

        return $this->map($tenant->refresh());
    }

    public function close(int $tenantId, int $actorId, string $reason): TenantApplicationData
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->update([
            'operational_status' => TenantOperationalStatus::Closed,
            'review_reason' => $reason,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'closed_at' => now(),
        ]);

        return $this->map($tenant->refresh());
    }

    public function setPayoutHold(int $tenantId, bool $hold, int $actorId, string $reason): TenantApplicationData
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->update([
            'payout_hold' => $hold,
            'payout_hold_reason' => $hold ? $reason : null,
            'payout_hold_set_by' => $actorId,
        ]);

        return $this->map($tenant->refresh());
    }

    /** @return array<string, callable> */
    private function ownerRelation(): array
    {
        return [
            'users' => fn ($query) => $query
                ->where('role', UserRole::TenantOwner)
                ->select(['id', 'tenant_id', 'public_id', 'name', 'email', 'status']),
        ];
    }

    private function map(Tenant $tenant): TenantApplicationData
    {
        $owner = $tenant->relationLoaded('users') ? $tenant->users->first() : null;

        return new TenantApplicationData(
            id: (int) $tenant->getKey(),
            publicId: $tenant->public_id,
            name: $tenant->name,
            slug: $tenant->slug,
            phone: $tenant->phone,
            onboardingStatus: $tenant->onboarding_status->value,
            operationalStatus: $tenant->operational_status->value,
            reviewReason: $tenant->review_reason,
            reviewedAt: $tenant->reviewed_at?->toIso8601String(),
            closureRequested: $tenant->closure_requested_at !== null,
            payoutHold: $tenant->payout_hold,
            payoutHoldReason: $tenant->payout_hold_reason,
            outletName: $tenant->initial_outlet_name,
            outletAddress: $tenant->initial_outlet_address,
            city: $tenant->initial_outlet_city,
            area: $tenant->initial_outlet_area,
            latitude: (float) $tenant->initial_outlet_latitude,
            longitude: (float) $tenant->initial_outlet_longitude,
            ownerPublicId: $owner?->public_id,
            ownerName: $owner?->name,
            ownerEmail: $owner?->email,
            ownerStatus: $owner?->status->value,
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
