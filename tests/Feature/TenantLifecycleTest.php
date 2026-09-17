<?php

use App\DTOs\Tenancy\TenantRegistrationData;
use App\DTOs\Tenancy\TenantResubmissionData;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Identity\ChangeUserStatusService;
use App\Services\Tenancy\ChangeTenantOperationalStatusService;
use App\Services\Tenancy\CloseTenantService;
use App\Services\Tenancy\RegisterTenantService;
use App\Services\Tenancy\RequestTenantClosureService;
use App\Services\Tenancy\ResubmitTenantApplicationService;
use App\Services\Tenancy\ReviewTenantApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function m2TenantRegistration(string $email, string $business = 'Laundry M2'): TenantRegistrationData
{
    return new TenantRegistrationData(
        businessName: $business,
        ownerName: "Owner {$business}",
        email: $email,
        phone: '081234567890',
        password: 'StrongPassword123',
        outletName: "Outlet {$business}",
        outletAddress: 'Jl. Pengujian 10',
        city: 'Bandung',
        area: 'Coblong',
        latitude: '-6.8915000',
        longitude: '107.6107000',
    );
}

function m2SuperUser(): User
{
    return User::factory()->create([
        'role' => UserRole::SuperUser,
        'role_slot' => 'super-user:primary',
        'two_factor_confirmed_at' => now(),
    ]);
}

it('registers a pending tenant and owner atomically without auditing credentials or pii', function () {
    $owner = app(RegisterTenantService::class)->handle(m2TenantRegistration('owner@example.test'));
    $tenant = Tenant::query()->firstOrFail();
    $log = ActivityLog::query()->firstOrFail();

    expect($tenant->onboarding_status)->toBe(TenantOnboardingStatus::Pending)
        ->and($tenant->operational_status)->toBe(TenantOperationalStatus::Inactive)
        ->and($owner->role())->toBe(UserRole::TenantOwner)
        ->and($owner->tenantId())->toBe($tenant->getKey())
        ->and(Hash::check('StrongPassword123', $owner->getAuthPassword()))->toBeTrue()
        ->and(json_encode([$log->before, $log->after, $log->reason]))
        ->not->toContain('owner@example.test')
        ->not->toContain('081234567890')
        ->not->toContain('StrongPassword123');
});

it('supports approval suspension and reactivation while rejecting invalid transitions', function () {
    app(RegisterTenantService::class)->handle(m2TenantRegistration('lifecycle@example.test'));
    $tenant = Tenant::query()->firstOrFail();
    $superUser = m2SuperUser();
    $review = app(ReviewTenantApplicationService::class);
    $status = app(ChangeTenantOperationalStatusService::class);

    $review->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Approved, 'Dokumen usaha telah diverifikasi.');
    $status->handle($superUser, $tenant->public_id, TenantOperationalStatus::Suspended, 'Pemeriksaan kepatuhan sedang berlangsung.');
    $status->handle($superUser, $tenant->public_id, TenantOperationalStatus::Active, 'Pemeriksaan kepatuhan telah selesai.');

    expect($tenant->refresh()->operational_status)->toBe(TenantOperationalStatus::Active);
    expect(fn () => $review->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Rejected, 'Tidak boleh direview kembali.'))
        ->toThrow(DomainActionConflict::class);
});

it('allows only rejected tenant applications to be resubmitted', function () {
    $owner = app(RegisterTenantService::class)->handle(m2TenantRegistration('resubmit@example.test'));
    $tenant = Tenant::query()->firstOrFail();
    $superUser = m2SuperUser();
    $review = app(ReviewTenantApplicationService::class);

    $review->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Rejected, 'Alamat outlet belum dapat diverifikasi.');
    app(ResubmitTenantApplicationService::class)->handle($owner, new TenantResubmissionData(
        businessName: 'Laundry Diperbarui',
        phone: '089876543210',
        outletName: 'Outlet Baru',
        outletAddress: 'Jl. Baru 2',
        city: 'Bandung',
        area: 'Dago',
        latitude: '-6.8800000',
        longitude: '107.6200000',
    ));

    $tenant->refresh();
    expect($tenant->onboarding_status)->toBe(TenantOnboardingStatus::Pending)
        ->and($tenant->operational_status)->toBe(TenantOperationalStatus::Inactive)
        ->and($tenant->review_reason)->toBeNull();
});

it('makes tenant closure terminal and revokes every tenant identity', function () {
    $owner = app(RegisterTenantService::class)->handle(m2TenantRegistration('closure@example.test'));
    $tenant = Tenant::query()->firstOrFail();
    $superUser = m2SuperUser();

    app(ReviewTenantApplicationService::class)->handle($superUser, $tenant->public_id, TenantOnboardingStatus::Approved, 'Dokumen lengkap dan valid.');
    app(RequestTenantClosureService::class)->handle($owner, 'Pemilik menghentikan operasional bisnis.');
    app(CloseTenantService::class)->handle($superUser, $tenant->public_id, 'Tidak ada kewajiban M2 yang tersisa.');

    expect($tenant->refresh()->operational_status)->toBe(TenantOperationalStatus::Closed)
        ->and(User::query()->findOrFail($owner->databaseId())->status)->toBe(UserStatus::Closed);

    expect(fn () => app(ChangeTenantOperationalStatusService::class)->handle(
        $superUser,
        $tenant->public_id,
        TenantOperationalStatus::Active,
        'Closed tidak dapat diaktifkan kembali.',
    ))->toThrow(DomainActionConflict::class);
});

it('keeps tenant data isolated on http service and repository boundaries', function () {
    $ownerA = app(RegisterTenantService::class)->handle(m2TenantRegistration('a@example.test', 'Tenant A'));
    $tenantA = Tenant::query()->where('name', 'Tenant A')->firstOrFail();
    app(RegisterTenantService::class)->handle(m2TenantRegistration('b@example.test', 'Tenant B'));
    $tenantB = Tenant::query()->where('name', 'Tenant B')->firstOrFail();

    expect(fn () => app(ReviewTenantApplicationService::class)->handle(
        $ownerA,
        $tenantB->public_id,
        TenantOnboardingStatus::Approved,
        'Tenant owner tidak boleh melakukan review.',
    ))->toThrow(DomainRecordNotFound::class);

    $owned = app(TenantRepositoryInterface::class)->findOwnedByTenantId($ownerA->tenantId() ?? 0);
    expect($owned?->publicId)->toBe($tenantA->public_id)->not->toBe($tenantB->public_id);

    $ownerModel = User::query()->findOrFail($ownerA->databaseId());
    $ownerModel->forceFill(['email_verified_at' => now(), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($ownerModel)
        ->withSession(['auth.version' => $ownerModel->auth_version])
        ->get('/workspace')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.publicId', $tenantA->public_id)
            ->missing('tenantB'));

    $this->actingAs($ownerModel)
        ->withSession(['auth.version' => $ownerModel->auth_version, 'auth.sensitive_confirmed_at' => time()])
        ->patch("/super-user/tenants/{$tenantB->public_id}/review", [
            'decision' => 'approved',
            'reason' => 'Cross tenant harus ditolak sepenuhnya.',
        ])->assertForbidden();
});

it('suspends and reactivates non super users but protects super user accounts', function () {
    $superUser = m2SuperUser();
    $customer = User::factory()->create();
    $service = app(ChangeUserStatusService::class);

    $service->handle($superUser, $customer->public_id, UserStatus::Suspended, 'Aktivitas akun perlu diperiksa.');
    expect($customer->refresh()->status)->toBe(UserStatus::Suspended)->and($customer->auth_version)->toBe(2);

    $service->handle($superUser, $customer->public_id, UserStatus::Active, 'Pemeriksaan akun telah selesai.');
    expect($customer->refresh()->status)->toBe(UserStatus::Active);

    expect(fn () => $service->handle($superUser, $superUser->public_id, UserStatus::Suspended, 'Tidak diizinkan.'))
        ->toThrow(DomainActionConflict::class);
    expect(fn () => $service->handle($superUser, $customer->public_id, UserStatus::Closed, 'Generic close tidak diizinkan.'))
        ->toThrow(DomainActionConflict::class);
});

it('provisions one super user interactively without printing credentials', function () {
    $password = 'CommandPassword123';

    $this->artisan('super-user:provision')
        ->expectsQuestion('Nama lengkap', 'Admin Utama')
        ->expectsQuestion('Email', 'admin@example.test')
        ->expectsQuestion('Nomor telepon', '081234567890')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Konfirmasi password', $password)
        ->expectsOutputToContain('Super User dibuat')
        ->assertSuccessful();

    expect(Artisan::output())->not->toContain($password)->not->toContain('admin@example.test');
    expect(User::query()->where('role', UserRole::SuperUser)->count())->toBe(1);

    $this->artisan('super-user:provision')
        ->expectsOutputToContain('Super User sudah tersedia')
        ->assertFailed();
});

it('enforces append only activity logs', function () {
    $log = ActivityLog::query()->create([
        'action' => 'test.created',
        'subject_type' => 'test',
        'subject_id' => 'test-1',
        'created_at' => now(),
    ]);

    expect(fn () => $log->update(['action' => 'test.changed']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});
