<?php

use App\DTOs\Tenancy\TenantRegistrationData;
use App\Enums\PayoutAccountStatus;
use App\Enums\TenantOnboardingStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainActionConflict;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use App\Services\Tenancy\RegisterTenantService;
use App\Services\Tenancy\ReviewPayoutAccountService;
use App\Services\Tenancy\ReviewTenantApplicationService;
use App\Services\Tenancy\SetTenantPayoutHoldService;
use App\Services\Tenancy\SubmitPayoutAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** @return array{owner: User, superUser: User, tenant: Tenant} */
function m2ApprovedTenant(string $email = 'payout@example.test', string $name = 'Payout Tenant'): array
{
    $identity = app(RegisterTenantService::class)->handle(new TenantRegistrationData(
        businessName: $name,
        ownerName: "Owner {$name}",
        email: $email,
        phone: '081234567890',
        password: 'StrongPassword123',
        outletName: "Outlet {$name}",
        outletAddress: 'Jl. Aman 10',
        city: 'Bandung',
        area: 'Coblong',
        latitude: '-6.8915000',
        longitude: '107.6107000',
    ));
    $owner = User::query()->findOrFail($identity->databaseId());
    $owner->forceFill(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()])->save();
    $superUser = User::factory()->create([
        'role' => UserRole::SuperUser,
        'role_slot' => 'super-user:primary',
        'two_factor_confirmed_at' => now(),
    ]);
    $tenant = Tenant::query()->where('name', $name)->firstOrFail();

    app(ReviewTenantApplicationService::class)->handle(
        $superUser,
        $tenant->public_id,
        TenantOnboardingStatus::Approved,
        'Dokumen payout telah diverifikasi.',
    );

    return ['owner' => $owner->refresh(), 'superUser' => $superUser, 'tenant' => $tenant->refresh()];
}

it('encrypts payout pii at rest and returns only masked presentation data', function () {
    ['owner' => $owner, 'tenant' => $tenant] = m2ApprovedTenant();
    $service = app(SubmitPayoutAccountService::class);

    $service->handle($owner, 'Bank Aman', 'Nama Pemilik Rahasia', '123456789012');

    $raw = DB::table('tenant_payout_accounts')->first();
    expect($raw)->not->toBeNull()
        ->and($raw->account_number)->not->toBe('123456789012')
        ->and($raw->account_holder_name)->not->toBe('Nama Pemilik Rahasia')
        ->and($raw->masked_account_number)->toBe('********9012');

    $dto = app(PayoutAccountRepositoryInterface::class)->findCurrentForTenant($tenant->getKey());
    expect($dto?->maskedAccountNumber)->toBe('********9012')
        ->and($dto?->maskedHolderName)->not->toBe('Nama Pemilik Rahasia');

    $serializedAudit = ActivityLog::query()->get(['before', 'after', 'reason'])->toJson();
    expect($serializedAudit)->not->toContain('123456789012')->not->toContain('Nama Pemilik Rahasia');
});

it('supersedes the previous payout account and resets verification to pending', function () {
    ['owner' => $owner] = m2ApprovedTenant();
    $service = app(SubmitPayoutAccountService::class);

    $service->handle($owner, 'Bank Pertama', 'Pemilik Satu', '1234567890');
    $first = TenantPayoutAccount::query()->firstOrFail();
    $service->handle($owner, 'Bank Kedua', 'Pemilik Dua', '9876543210');

    expect($first->refresh()->verification_status)->toBe(PayoutAccountStatus::Superseded)
        ->and($first->current_tenant_id)->toBeNull()
        ->and(TenantPayoutAccount::query()->whereNotNull('current_tenant_id')->count())->toBe(1)
        ->and(TenantPayoutAccount::query()->latest('id')->firstOrFail()->verification_status)->toBe(PayoutAccountStatus::Pending);
});

it('reviews payout accounts and controls payout holds with explicit reasons', function () {
    ['owner' => $owner, 'superUser' => $superUser, 'tenant' => $tenant] = m2ApprovedTenant();
    app(SubmitPayoutAccountService::class)->handle($owner, 'Bank Review', 'Pemilik Review', '1234567890');
    $account = TenantPayoutAccount::query()->firstOrFail();

    app(ReviewPayoutAccountService::class)->handle(
        $superUser,
        $account->public_id,
        PayoutAccountStatus::Verified,
        'Nama dan nomor rekening telah dicocokkan.',
    );
    app(SetTenantPayoutHoldService::class)->handle($superUser, $tenant->public_id, true, 'Menunggu verifikasi operasional tambahan.');
    app(SetTenantPayoutHoldService::class)->handle($superUser, $tenant->public_id, false, 'Verifikasi operasional telah selesai.');

    expect($account->refresh()->verification_status)->toBe(PayoutAccountStatus::Verified)
        ->and($tenant->refresh()->payout_hold)->toBeFalse();

    expect(fn () => app(ReviewPayoutAccountService::class)->handle(
        $superUser,
        $account->public_id,
        PayoutAccountStatus::Rejected,
        'Review kedua harus ditolak.',
    ))->toThrow(DomainActionConflict::class);
});

it('denies stale sensitive authentication before payout mutation', function () {
    ['owner' => $owner] = m2ApprovedTenant();

    $this->actingAs($owner)
        ->withSession(['auth.version' => $owner->auth_version])
        ->put('/tenant/payout-account', [
            'bank_name' => 'Bank Stale',
            'account_holder_name' => 'Pemilik Stale',
            'account_number' => '1234567890',
        ])->assertRedirect('/identity/confirm-sensitive-action');

    expect(TenantPayoutAccount::query()->count())->toBe(0);
});

it('exposes only masked payout data to tenant and super user inertia pages', function () {
    ['owner' => $owner, 'superUser' => $superUser] = m2ApprovedTenant();
    app(SubmitPayoutAccountService::class)->handle($owner, 'Bank Browser', 'Pemilik Browser Rahasia', '445566778899');

    $this->actingAs($owner)
        ->withSession(['auth.version' => $owner->auth_version])
        ->get('/workspace')
        ->assertDontSee('445566778899')
        ->assertDontSee('Pemilik Browser Rahasia')
        ->assertInertia(fn (Assert $page) => $page
            ->where('payoutAccount.maskedAccountNumber', '********8899')
            ->missing('payoutAccount.accountNumber')
            ->missing('payoutAccount.accountHolderName'));

    $this->actingAs($superUser)
        ->withSession(['auth.version' => $superUser->auth_version])
        ->get('/super-user/tenants')
        ->assertDontSee('445566778899')
        ->assertDontSee('Pemilik Browser Rahasia')
        ->assertInertia(fn (Assert $page) => $page
            ->where('payoutAccounts.0.maskedAccountNumber', '********8899')
            ->missing('payoutAccounts.0.accountNumber')
            ->missing('payoutAccounts.0.accountHolderName'));
});

it('keeps payout accounts isolated between two tenants and denies owner review', function () {
    ['owner' => $ownerA, 'tenant' => $tenantA] = m2ApprovedTenant('payout-a@example.test', 'Payout A');
    app(SubmitPayoutAccountService::class)->handle($ownerA, 'Bank A', 'Pemilik A', '111122223333');

    User::query()->where('role', UserRole::SuperUser)->delete();
    ['owner' => $ownerB, 'tenant' => $tenantB] = m2ApprovedTenant('payout-b@example.test', 'Payout B');
    app(SubmitPayoutAccountService::class)->handle($ownerB, 'Bank B', 'Pemilik B', '999988887777');

    $repository = app(PayoutAccountRepositoryInterface::class);
    expect($repository->findCurrentForTenant($tenantA->getKey())?->maskedAccountNumber)->toBe('********3333')
        ->and($repository->findCurrentForTenant($tenantB->getKey())?->maskedAccountNumber)->toBe('********7777');

    $accountB = TenantPayoutAccount::query()->where('tenant_id', $tenantB->getKey())->firstOrFail();
    $this->actingAs($ownerA)
        ->withSession(['auth.version' => $ownerA->auth_version, 'auth.sensitive_confirmed_at' => time()])
        ->patch("/super-user/payout-accounts/{$accountB->public_id}/review", [
            'decision' => 'verified',
            'reason' => 'Tenant owner tidak boleh melakukan review.',
        ])->assertForbidden();
});

it('requires verified email and confirmed two factor authentication in the payout service', function () {
    ['owner' => $owner] = m2ApprovedTenant();
    $owner->forceFill(['email_verified_at' => null])->save();

    expect(fn () => app(SubmitPayoutAccountService::class)->handle(
        $owner->refresh(),
        'Bank Guard',
        'Pemilik Guard',
        '1234567890',
    ))->toThrow(DomainActionConflict::class);
});
