<?php

use App\Contracts\PrivateProofStorageInterface;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Enums\RefundStatus;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainActionConflict;
use App\Models\DeliveryTask;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Outlet;
use App\Models\OutletSlot;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Privacy\CleanupExpiredProofsService;
use App\Services\Privacy\CloseCustomerAccountService;
use App\Services\Privacy\ExpirePiiGrantsService;
use App\Services\Privacy\ManagePiiRevealService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pulse\Recorders\SlowOutgoingRequests;
use Laravel\Pulse\Recorders\UserRequests;
use Laravel\Reverb\Pulse\Recorders\ReverbConnections;

uses(RefreshDatabase::class);

afterEach(fn () => CarbonImmutable::setTestNow());

/** @return array{customer: User, super: User, tenant: Tenant, order: Order, outlet: Outlet} */
function m9Context(FulfillmentStatus $status = FulfillmentStatus::Completed): array
{
    $customer = User::factory()->create(['role' => UserRole::Customer]);
    $super = User::factory()->create([
        'role' => UserRole::SuperUser,
        'role_slot' => 'super-user:m9',
        'two_factor_confirmed_at' => now(),
    ]);
    $tenant = Tenant::query()->create([
        'public_id' => (string) Str::ulid(), 'name' => 'Laundry M9', 'slug' => 'laundry-m9', 'phone' => '081200000001',
        'onboarding_status' => TenantOnboardingStatus::Approved, 'operational_status' => TenantOperationalStatus::Active,
        'reviewed_by' => $super->id, 'reviewed_at' => now(), 'initial_outlet_name' => 'Outlet M9',
        'initial_outlet_address' => 'Jl. M9', 'initial_outlet_city' => 'Bandung', 'initial_outlet_area' => 'Coblong',
        'initial_outlet_latitude' => '-6.8915000', 'initial_outlet_longitude' => '107.6107000',
    ]);
    $outlet = Outlet::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'name' => 'Outlet M9', 'contact_phone' => '081200000002',
        'address' => 'Jl. M9', 'city' => 'Bandung', 'area' => 'Coblong', 'latitude' => '-6.8915000', 'longitude' => '107.6107000',
        'service_radius_m' => 5000, 'pickup_fee' => 5000, 'delivery_fee' => 5000, 'status' => ResourceStatus::Active,
    ]);
    $slot = OutletSlot::query()->create([
        'public_id' => (string) Str::ulid(), 'outlet_id' => $outlet->id, 'type' => SlotType::Pickup,
        'day_of_week' => 0, 'starts_at' => '10:00', 'ends_at' => '12:00', 'is_active' => true,
    ]);
    $order = Order::query()->create([
        'public_id' => (string) Str::ulid(), 'order_number' => 'KL-20260926-M9', 'tenant_id' => $tenant->id,
        'outlet_id' => $outlet->id, 'customer_id' => $customer->id, 'tenant_name' => $tenant->name, 'outlet_name' => $outlet->name,
        'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'm9'), 'pricing_type' => PricingType::Fixed,
        'fulfillment_status' => $status, 'payment_status' => PaymentStatus::Paid, 'pickup_slot_id' => $slot->id,
        'pickup_starts_at' => now()->subDays(5), 'pickup_ends_at' => now()->subDays(5)->addHours(2),
        'items_subtotal' => 50_000, 'pickup_fee' => 5000, 'delivery_fee' => 5000, 'grand_total' => 60_000,
        'completed_at' => $status === FulfillmentStatus::Completed ? now()->subDays(100) : null,
    ]);
    OrderAddress::query()->create([
        'order_id' => $order->id, 'type' => 'pickup', 'label' => 'Rumah', 'contact_name' => 'Customer Rahasia',
        'contact_phone' => '081234567890', 'address' => 'Jl. Sangat Rahasia 1', 'city' => 'Bandung', 'area' => 'Coblong',
        'latitude' => '-6.8915000', 'longitude' => '107.6107000',
    ]);

    return compact('customer', 'super', 'tenant', 'order', 'outlet');
}

it('anonymizes a closable customer while preserving transaction snapshots', function () {
    $context = m9Context();
    DB::table('customer_addresses')->insert([
        'public_id' => (string) Str::ulid(), 'customer_id' => $context['customer']->id, 'default_customer_id' => $context['customer']->id, 'label' => 'Rumah',
        'contact_name' => 'Customer Rahasia', 'contact_phone' => '081234567890', 'address' => 'Jl. Pribadi',
        'city' => 'Bandung', 'area' => 'Coblong', 'latitude' => '-6.8915000', 'longitude' => '107.6107000',
        'location_consented_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $originalEmail = $context['customer']->email;
    DB::table('sessions')->insert([
        'id' => 'm9-customer-session', 'user_id' => $context['customer']->id, 'payload' => 'fixture', 'last_activity' => now()->timestamp,
    ]);

    app(CloseCustomerAccountService::class)->handle($context['customer']);
    app(CloseCustomerAccountService::class)->handle($context['customer']);

    $closed = $context['customer']->refresh();
    expect($closed->status->value)->toBe('closed')
        ->and($closed->anonymized_at)->not->toBeNull()
        ->and($closed->email)->not->toBe($originalEmail)
        ->and($closed->phone)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $closed->id)->exists())->toBeFalse()
        ->and(DB::table('customer_addresses')->where('customer_id', $closed->id)->exists())->toBeFalse()
        ->and(OrderAddress::query()->where('order_id', $context['order']->id)->value('address'))->toBe('Jl. Sangat Rahasia 1')
        ->and(DB::table('activity_logs')->where('action', 'identity.customer_anonymized')->where('subject_id', $closed->public_id)->exists())->toBeTrue();

    expect(User::factory()->create(['email' => $originalEmail]))->toBeInstanceOf(User::class);
});

it('blocks customer closure while an order remains active', function () {
    $context = m9Context(FulfillmentStatus::Processing);

    expect(fn () => app(CloseCustomerAccountService::class)->handle($context['customer']))
        ->toThrow(DomainActionConflict::class);
});

it('blocks customer closure for pending payments and active refunds', function () {
    $context = m9Context();
    $payment = Payment::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $context['tenant']->id, 'order_id' => $context['order']->id,
        'merchant_order_id' => 'M9-CLOSURE', 'channel_code' => 'SP', 'amount' => 60_000,
        'status' => PaymentStatus::Pending, 'reconciliation' => PaymentReconciliation::NotRequired,
        'expires_at' => now()->addHour(),
    ]);

    expect(fn () => app(CloseCustomerAccountService::class)->handle($context['customer']))
        ->toThrow(DomainActionConflict::class);

    $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now(), 'terminal_at' => now()]);
    DB::table('refund_requests')->insert([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $context['tenant']->id, 'order_id' => $context['order']->id,
        'payment_id' => $payment->id, 'status' => RefundStatus::Approved->value, 'amount' => 60_000, 'reason' => 'Refund aktif',
        'active_payment_key' => 'payment:'.$payment->id, 'submitted_by' => $context['customer']->id,
        'reviewed_by' => $context['super']->id, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => app(CloseCustomerAccountService::class)->handle($context['customer']))
        ->toThrow(DomainActionConflict::class);
});

it('keeps PII masked until a session-bound grant and expires it at the exact boundary', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-26 10:00:00', 'Asia/Jakarta'));
    $context = m9Context();
    $service = app(ManagePiiRevealService::class);

    $masked = $service->view($context['super'], $context['order']->public_id, 'session-a');
    expect($masked['privacy']['revealed'])->toBeFalse()
        ->and($masked['privacy']['addresses'][0]['address'])->toBe('[Alamat disamarkan]');

    $grant = $service->grant($context['super'], $context['order']->public_id, 'session-a', 'Investigasi keluhan Customer untuk order ini.');
    $revealed = $service->view($context['super'], $context['order']->public_id, 'session-a');
    $otherSession = $service->view($context['super'], $context['order']->public_id, 'session-b');
    expect($revealed['privacy']['revealed'])->toBeTrue()
        ->and($revealed['privacy']['addresses'][0]['address'])->toBe('Jl. Sangat Rahasia 1')
        ->and($otherSession['privacy']['revealed'])->toBeFalse()
        ->and(DB::table('pii_access_logs')->where('grant_id', $grant->id)->count())->toBe(2);

    CarbonImmutable::setTestNow(CarbonImmutable::parse($grant->expiresAt));
    expect($service->view($context['super'], $context['order']->public_id, 'session-a')['privacy']['revealed'])->toBeFalse();
    expect(app(ExpirePiiGrantsService::class)->handle())->toBe(1)
        ->and(DB::table('pii_access_logs')->where('grant_id', $grant->id)->where('event', 'expired')->exists())->toBeTrue();
});

it('renders privacy through encrypted no-store Inertia history', function () {
    $context = m9Context();

    $response = $this->actingAs($context['super'])->withSession(['auth.version' => $context['super']->auth_version])
        ->get('/super-user/orders/'.$context['order']->public_id.'/privacy');

    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page->component('super-user/privacy/show')
            ->where('privacy.revealed', false)->where('grant', null));
    expect($response->viewData('page')['encryptHistory'] ?? false)->toBeTrue();
});

it('limits PII reveal grants to three per actor and order in fifteen minutes', function () {
    $context = m9Context();
    $session = [
        'auth.version' => $context['super']->auth_version,
        'auth.sensitive_confirmed_at' => time(),
    ];

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $this->actingAs($context['super'])->withSession($session)
            ->post('/super-user/orders/'.$context['order']->public_id.'/pii-reveals', [
                'reason' => "Investigasi terkontrol nomor {$attempt} untuk order ini.",
            ])->assertRedirect();
    }

    $this->actingAs($context['super'])->withSession($session)
        ->post('/super-user/orders/'.$context['order']->public_id.'/pii-reveals', [
            'reason' => 'Investigasi keempat harus ditolak limiter.',
        ])->assertTooManyRequests();
});

it('physically deletes expired proofs only after active refunds clear', function () {
    Storage::fake('local');
    $context = m9Context();
    Storage::disk('local')->put('proofs/delete.jpg', 'image');
    $task = DeliveryTask::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $context['tenant']->id, 'order_id' => $context['order']->id,
        'outlet_id' => $context['outlet']->id, 'type' => DriverTaskType::Delivery, 'status' => DriverTaskStatus::Completed,
        'commission_amount' => 0, 'scheduled_starts_at' => now()->subDays(100), 'scheduled_ends_at' => now()->subDays(100)->addHour(),
        'proof_disk' => 'local', 'proof_key' => 'proofs/delete.jpg', 'proof_mime' => 'image/jpeg', 'proof_size' => 5,
        'proof_expires_at' => now()->subSecond(), 'completed_at' => now()->subDays(100),
    ]);

    $result = app(CleanupExpiredProofsService::class)->handle();
    expect($result['deleted'])->toBe(1)
        ->and(Storage::disk('local')->exists('proofs/delete.jpg'))->toBeFalse()
        ->and($task->refresh()->proof_key)->toBeNull()
        ->and($task->proof_deleted_at)->not->toBeNull();

    Storage::disk('local')->put('proofs/blocked.jpg', 'image');
    $blocked = DeliveryTask::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $context['tenant']->id, 'order_id' => $context['order']->id,
        'outlet_id' => $context['outlet']->id, 'type' => DriverTaskType::Pickup, 'status' => DriverTaskStatus::Completed,
        'commission_amount' => 0, 'scheduled_starts_at' => now()->subDays(100), 'scheduled_ends_at' => now()->subDays(100)->addHour(),
        'proof_disk' => 'local', 'proof_key' => 'proofs/blocked.jpg', 'proof_mime' => 'image/jpeg', 'proof_size' => 5,
        'proof_expires_at' => now()->subSecond(), 'completed_at' => now()->subDays(100),
    ]);
    $payment = Payment::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $context['tenant']->id, 'order_id' => $context['order']->id,
        'merchant_order_id' => 'M9-REFUND', 'provider_reference' => 'M9-REF', 'channel_code' => 'SP', 'amount' => 60_000,
        'status' => PaymentStatus::Paid, 'reconciliation' => PaymentReconciliation::Matched, 'fee_amount' => 1000,
        'expires_at' => now()->subDays(101), 'paid_at' => now()->subDays(100), 'terminal_at' => now()->subDays(100),
    ]);
    DB::table('refund_requests')->insert([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $context['tenant']->id, 'order_id' => $context['order']->id,
        'payment_id' => $payment->id, 'status' => RefundStatus::Submitted->value, 'amount' => 60_000, 'reason' => 'Refund aktif',
        'active_payment_key' => 'payment:'.$payment->id, 'submitted_by' => $context['customer']->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(app(CleanupExpiredProofsService::class)->handle()['eligible'])->toBe(0)
        ->and($blocked->refresh()->proof_key)->toBe('proofs/blocked.jpg')
        ->and(Storage::disk('local')->exists('proofs/blocked.jpg'))->toBeTrue();
});

it('retains the proof locator and records safe retry metadata when storage deletion fails', function () {
    $context = m9Context();
    $task = DeliveryTask::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $context['tenant']->id, 'order_id' => $context['order']->id,
        'outlet_id' => $context['outlet']->id, 'type' => DriverTaskType::Delivery, 'status' => DriverTaskStatus::Completed,
        'commission_amount' => 0, 'scheduled_starts_at' => now()->subDays(100), 'scheduled_ends_at' => now()->subDays(100)->addHour(),
        'proof_disk' => 'local', 'proof_key' => 'proofs/retry.jpg', 'proof_mime' => 'image/jpeg', 'proof_size' => 5,
        'proof_expires_at' => now()->subSecond(), 'completed_at' => now()->subDays(100),
    ]);
    $storage = Mockery::mock(PrivateProofStorageInterface::class);
    $storage->shouldReceive('delete')->once()->andThrow(new RuntimeException('Sensitive provider detail'));
    $this->app->instance(PrivateProofStorageInterface::class, $storage);

    expect(app(CleanupExpiredProofsService::class)->handle()['failed'])->toBe(1)
        ->and($task->refresh()->proof_key)->toBe('proofs/retry.jpg')
        ->and($task->proof_cleanup_attempts)->toBe(1)
        ->and($task->proof_cleanup_error)->toBe('RuntimeException')
        ->and($task->proof_cleanup_failed_at)->not->toBeNull();
});

it('returns generic readiness without exposing dependency details', function () {
    $this->get('/ready')->assertOk()->assertExactJson(['status' => 'ready']);
});

it('uses the named password reset limiter per normalized email and IP', function () {
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertRedirect();
    }

    $this->post('/forgot-password', ['email' => 'MISSING@example.test'])->assertTooManyRequests();
});

it('keeps Pulse privacy-safe and authorizes only active two-factor super users', function () {
    $context = m9Context();
    $customer = $context['customer'];

    expect(config('pulse.enabled'))->toBeFalsy()
        ->and(config('pulse.recorders.'.SlowOutgoingRequests::class.'.enabled'))->toBeFalsy()
        ->and(config('pulse.recorders.'.UserRequests::class.'.enabled'))->toBeFalsy()
        ->and(config('pulse.recorders.'.ReverbConnections::class.'.enabled'))->toBeTrue()
        ->and(Gate::forUser($context['super'])->allows('viewPulse'))->toBeTrue()
        ->and(Gate::forUser($customer)->allows('viewPulse'))->toBeFalse();
});

it('fails closed when M9 staging evidence is absent and refuses load fixtures outside staging', function () {
    Storage::fake('local');

    $this->artisan('m9:readiness-check')->assertFailed();
    $this->artisan('m9:prepare-load-fixtures', ['--run-id' => 'm9-ci-run'])->assertFailed();
});

it('creates the fixed staging load profile idempotently', function () {
    $this->app->detectEnvironment(fn (): string => 'staging');

    $arguments = ['--run-id' => 'm9-test-profile', '--date' => '2026-09-27'];
    $this->artisan('m9:prepare-load-fixtures', $arguments)->assertSuccessful();
    $this->artisan('m9:prepare-load-fixtures', $arguments)->assertSuccessful();

    expect(DB::table('tenants')->where('slug', 'like', 'm9-m9-test-profile-%')->count())->toBe(10)
        ->and(DB::table('outlets')->where('name', 'like', 'M9 Outlet %')->count())->toBe(20)
        ->and(DB::table('users')->where('email', 'like', 'm9.m9-test-profile.driver.%')->count())->toBe(50)
        ->and(DB::table('orders')->where('order_number', 'like', 'KL-M9-%')->count())->toBe(500);
});
