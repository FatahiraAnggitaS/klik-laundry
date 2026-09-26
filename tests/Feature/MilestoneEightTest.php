<?php

use App\Enums\DriverCommissionStatus;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\PayoutAccountStatus;
use App\Enums\PricingType;
use App\Enums\RefundStatus;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\TransferMethod;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainActionConflict;
use App\Models\DeliveryTask;
use App\Models\DriverCommission;
use App\Models\FinancialAdjustment;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\OutletSlot;
use App\Models\Payment;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\TenantPayout;
use App\Models\TenantPayoutAccount;
use App\Models\User;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\FinanceReportRepositoryInterface;
use App\Services\Finance\FinancePeriodService;
use App\Services\Finance\GetFinanceDashboardService;
use App\Services\Finance\ManageDriverPayoutService;
use App\Services\Finance\ManageTenantPayoutService;
use App\Services\Finance\PrepareFinanceExportService;
use App\Services\Finance\ReviewRefundService;
use App\Services\Finance\SubmitRefundRequestService;
use App\Services\Tenancy\SubmitPayoutAccountService;
use App\Services\Tenancy\TenantOperationsGuard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function m8Context(): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-24 12:00:00', 'Asia/Jakarta'));
    $super = User::factory()->create(['role' => UserRole::SuperUser, 'role_slot' => 'super-user:m8', 'two_factor_confirmed_at' => now()]);
    $tenant = Tenant::query()->create([
        'public_id' => (string) Str::ulid(), 'name' => 'Laundry Finance', 'slug' => 'laundry-finance', 'phone' => '081200000001',
        'onboarding_status' => TenantOnboardingStatus::Approved, 'operational_status' => TenantOperationalStatus::Active,
        'reviewed_by' => $super->id, 'reviewed_at' => now(), 'initial_outlet_name' => 'Outlet Finance',
        'initial_outlet_address' => 'Jl. Finance 1', 'initial_outlet_city' => 'Bandung', 'initial_outlet_area' => 'Coblong',
        'initial_outlet_latitude' => '-6.8915000', 'initial_outlet_longitude' => '107.6107000',
    ]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TenantOwner, 'role_slot' => 'tenant-owner:'.$tenant->id, 'two_factor_confirmed_at' => now()]);
    $customer = User::factory()->create();
    $driver = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Driver]);
    $outlet = Outlet::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'name' => '=Outlet Finance',
        'contact_phone' => '081200000002', 'address' => 'Jl. Finance 1', 'city' => 'Bandung', 'area' => 'Coblong',
        'latitude' => '-6.8915000', 'longitude' => '107.6107000', 'service_radius_m' => 5000,
        'pickup_fee' => 5000, 'delivery_fee' => 5000, 'status' => ResourceStatus::Active,
    ]);
    $slot = OutletSlot::query()->create(['public_id' => (string) Str::ulid(), 'outlet_id' => $outlet->id, 'type' => SlotType::Pickup, 'day_of_week' => 0, 'starts_at' => '10:00', 'ends_at' => '12:00', 'is_active' => true]);
    $package = ServicePackage::query()->create(['public_id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'name' => 'Paket Finance', 'pricing_type' => PricingType::Fixed, 'unit_price' => 50_000, 'minimum_quantity' => 1, 'estimated_duration_minutes' => 120, 'status' => ResourceStatus::Active]);
    $account = TenantPayoutAccount::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'current_tenant_id' => $tenant->id,
        'bank_name' => 'Bank Uji', 'account_holder_name' => 'Pemilik Finance', 'account_number' => '1234567890',
        'masked_account_number' => '******7890', 'verification_status' => PayoutAccountStatus::Verified,
        'submitted_by' => $owner->id, 'reviewed_by' => $super->id, 'reviewed_at' => now(),
    ]);

    $makePayment = function (string $suffix, CarbonImmutable $completedAt, ?int $fee) use ($tenant, $outlet, $customer, $slot, $package): array {
        $order = Order::query()->create([
            'public_id' => (string) Str::ulid(), 'order_number' => 'KL-20260924-'.$suffix, 'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id, 'customer_id' => $customer->id, 'tenant_name' => $tenant->name, 'outlet_name' => $outlet->name,
            'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', $suffix), 'pricing_type' => PricingType::Fixed,
            'fulfillment_status' => FulfillmentStatus::Completed, 'payment_status' => PaymentStatus::Paid,
            'pickup_slot_id' => $slot->id, 'pickup_starts_at' => $completedAt->subDays(2), 'pickup_ends_at' => $completedAt->subDays(2)->addHours(2),
            'items_subtotal' => 50_000, 'pickup_fee' => 5000, 'delivery_fee' => 5000, 'grand_total' => 60_000,
            'completed_at' => $completedAt,
        ]);
        DB::table('order_items')->insert(['order_id' => $order->id, 'package_id' => $package->id, 'package_name' => $package->name, 'pricing_type' => PricingType::Fixed->value, 'unit_price' => 50_000, 'minimum_quantity' => 1, 'estimated_duration_minutes' => 120, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $payment = Payment::query()->create([
            'public_id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'order_id' => $order->id,
            'merchant_order_id' => 'M8-'.$suffix, 'provider_reference' => 'REF-'.$suffix, 'channel_code' => 'SP',
            'amount' => 60_000, 'status' => PaymentStatus::Paid, 'reconciliation' => PaymentReconciliation::Matched,
            'fee_amount' => $fee, 'expires_at' => $completedAt->subDays(3), 'paid_at' => $completedAt->subDays(3), 'terminal_at' => $completedAt->subDays(3),
        ]);

        return compact('order', 'payment');
    };

    $refundable = $makePayment('REFUND', CarbonImmutable::now()->subDays(2), 1200);
    $settlement = $makePayment('SETTLE', CarbonImmutable::now()->subDays(5), 1200);
    $unknown = $makePayment('UNKNOWN', CarbonImmutable::now()->subDays(4), null);
    $task = DeliveryTask::query()->create([
        'public_id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'order_id' => $settlement['order']->id,
        'outlet_id' => $outlet->id, 'type' => DriverTaskType::Delivery, 'status' => DriverTaskStatus::Completed,
        'assignee_id' => $driver->id, 'commission_amount' => 10_000, 'scheduled_starts_at' => now()->subDays(5),
        'scheduled_ends_at' => now()->subDays(5)->addHours(2), 'completed_at' => now()->subDays(5),
    ]);
    $commission = DriverCommission::query()->create(['public_id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'task_id' => $task->id, 'driver_id' => $driver->id, 'amount' => 10_000, 'status' => DriverCommissionStatus::Earned, 'earned_at' => now()->subDays(5)]);

    return compact('super', 'tenant', 'owner', 'customer', 'driver', 'outlet', 'account', 'refundable', 'settlement', 'unknown', 'commission');
}

it('completes a full refund as one negative adjustment without changing payment or commission', function () {
    $context = m8Context();
    $submitted = app(SubmitRefundRequestService::class)->handle($context['owner'], $context['refundable']['payment']->public_id, 'Customer mengajukan refund penuh.');
    app(ReviewRefundService::class)->review($context['super'], $submitted->publicId, RefundStatus::Approved, 'Bukti operasional sesuai.');
    $completed = app(ReviewRefundService::class)->complete($context['super'], $submitted->publicId, TransferMethod::BankTransfer->value, 'TRX-M8-001', 'Transfer manual terverifikasi.');

    expect($completed->status)->toBe(RefundStatus::Completed->value)
        ->and(Payment::query()->findOrFail($context['refundable']['payment']->id)->status)->toBe(PaymentStatus::Paid)
        ->and(FinancialAdjustment::query()->where('refund_request_id', $completed->id)->value('amount'))->toBe(-60_000)
        ->and(DriverCommission::query()->findOrFail($context['commission']->id)->status)->toBe(DriverCommissionStatus::Earned)
        ->and($completed->toArray())->not->toHaveKeys(['id', 'tenantId', 'orderId', 'paymentId']);
});

it('marks report totals incomplete when actual fee is unknown and neutralizes csv formulas', function () {
    $context = m8Context();
    $dashboard = app(GetFinanceDashboardService::class)->tenant($context['owner'], '2026-09-01', '2026-09-24', null);
    expect($dashboard['summary']['unknownFeeCount'])->toBe(1)
        ->and($dashboard['summary']['isFinal'])->toBeFalse()
        ->and($dashboard['summary']['netOperational'])->toBeNull();

    $export = app(PrepareFinanceExportService::class)->tenant($context['owner'], '2026-09-01', '2026-09-24', null);
    $rows = iterator_to_array($export['rows']);
    expect($rows)->not->toBeEmpty()
        ->and(collect($rows)->pluck(2)->contains("'=Outlet Finance"))->toBeTrue();
});

it('selects tenant payout membership server-side and finalizes immutable snapshots', function () {
    $context = m8Context();
    $service = app(ManageTenantPayoutService::class);
    $payout = $service->create($context['super'], $context['tenant']->public_id, '2026-09-24');
    expect($payout->payments)->toHaveCount(1)
        ->and($payout->grossAmount)->toBe(60_000)
        ->and($payout->feeAmount)->toBe(1200)
        ->and($payout->netAmount)->toBe(58_800)
        ->and(DB::table('tenant_payouts')->where('id', $payout->id)->value('account_number'))->not->toBe('1234567890');

    Tenant::query()->whereKey($context['tenant']->id)->update(['payout_hold' => true]);
    expect(fn () => $service->finalize($context['super'], $payout->publicId, TransferMethod::BankTransfer->value, 'TP-EXT-001', 'Transfer diperiksa.'))->toThrow(DomainActionConflict::class);
    Tenant::query()->whereKey($context['tenant']->id)->update(['payout_hold' => false]);
    Payment::query()->whereKey($context['settlement']['payment']->id)->update(['fee_amount' => 1300]);
    expect(fn () => $service->finalize($context['super'], $payout->publicId, TransferMethod::BankTransfer->value, 'TP-EXT-001', 'Transfer diperiksa.'))->toThrow(DomainActionConflict::class);
    Payment::query()->whereKey($context['settlement']['payment']->id)->update(['fee_amount' => 1200]);
    $final = $service->finalize($context['super'], $payout->publicId, TransferMethod::BankTransfer->value, 'TP-EXT-001', 'Transfer diperiksa.');
    expect($final->status)->toBe('finalized');
});

it('finalizes all earned driver commissions once and allows zero-value settlement semantics', function () {
    $context = m8Context();
    $service = app(ManageDriverPayoutService::class);
    $payout = $service->create($context['owner'], $context['driver']->public_id, '2026-09-24');
    $final = $service->finalize($context['owner'], $payout->publicId, TransferMethod::BankTransfer->value, 'DP-EXT-001', null, 'Driver sudah dibayar.');
    expect($final->totalAmount)->toBe(10_000)
        ->and(DriverCommission::query()->findOrFail($context['commission']->id)->status)->toBe(DriverCommissionStatus::Paid)
        ->and($service->finalize($context['owner'], $payout->publicId, TransferMethod::BankTransfer->value, 'DP-EXT-001', null, 'Retry aman.')->status)->toBe('finalized');
});

it('auto-voids pending tenant payout when payout account changes', function () {
    $context = m8Context();
    $payout = app(ManageTenantPayoutService::class)->create($context['super'], $context['tenant']->public_id, '2026-09-24');
    app(SubmitPayoutAccountService::class)->handle($context['owner'], 'Bank Baru', 'Pemilik Baru', '9988776655');

    expect(TenantPayout::query()->findOrFail($payout->id)->status->value)->toBe('voided')
        ->and(DB::table('tenant_payout_items')->where('tenant_payout_id', $payout->id)->whereNotNull('active_payment_key')->exists())->toBeFalse();
});

it('keeps finance pages and refund detail tenant-scoped', function () {
    $context = m8Context();
    $refund = app(SubmitRefundRequestService::class)->handle($context['owner'], $context['refundable']['payment']->public_id, 'Refund terkontrol.');
    $otherTenant = Tenant::query()->create([
        'public_id' => (string) Str::ulid(), 'name' => 'Tenant Lain', 'slug' => 'tenant-lain', 'phone' => '081299999999',
        'onboarding_status' => TenantOnboardingStatus::Approved, 'operational_status' => TenantOperationalStatus::Active,
        'initial_outlet_name' => 'Outlet Lain', 'initial_outlet_address' => 'Alamat', 'initial_outlet_city' => 'Bandung',
        'initial_outlet_area' => 'Area', 'initial_outlet_latitude' => '-6.9000000', 'initial_outlet_longitude' => '107.6000000',
    ]);
    $otherOwner = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => UserRole::TenantOwner, 'role_slot' => 'tenant-owner:'.$otherTenant->id, 'two_factor_confirmed_at' => now()]);

    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])->get('/tenant/finance')->assertOk();
    $this->actingAs($otherOwner)->withSession(['auth.version' => $otherOwner->auth_version])->get('/finance/refunds/'.$refund->publicId)->assertNotFound();
    $this->actingAs($context['driver'])->withSession(['auth.version' => $context['driver']->auth_version])->get('/driver/finance')->assertOk();
});

it('scopes payout details by actor and omits internal identifiers', function () {
    $context = m8Context();
    $tenantPayout = app(ManageTenantPayoutService::class)->create($context['super'], $context['tenant']->public_id, '2026-09-24');
    $driverPayout = app(ManageDriverPayoutService::class)->create($context['owner'], $context['driver']->public_id, '2026-09-24');

    $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->get('/finance/tenant-payouts/'.$tenantPayout->publicId)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('finance/payout-detail')
            ->where('kind', 'tenant')
            ->missing('payout.id')
            ->missing('payout.tenantId')
            ->missing('payout.payoutAccountId'));

    $this->actingAs($context['driver'])->withSession(['auth.version' => $context['driver']->auth_version])
        ->get('/finance/driver-payouts/'.$driverPayout->publicId)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('finance/payout-detail')
            ->where('kind', 'driver')
            ->missing('payout.id')
            ->missing('payout.driverId'));

    $this->actingAs($context['driver'])->withSession(['auth.version' => $context['driver']->auth_version])
        ->get('/finance/tenant-payouts/'.$tenantPayout->publicId)
        ->assertNotFound();
});

it('blocks milestone eight rollback after financial data exists', function () {
    $context = m8Context();
    app(SubmitRefundRequestService::class)->handle($context['owner'], $context['refundable']['payment']->public_id, 'Refund menjaga rollback guard.');
    $migration = require database_path('migrations/2026_09_24_000013_create_finance_refund_and_payout_tables.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Milestone 8 finance data exists');
});

it('enforces export period and row limits and streams utf-8 csv', function () {
    $context = m8Context();
    expect(fn () => app(FinancePeriodService::class)->normalize('2026-06-01', '2026-09-24'))->toThrow(ValidationException::class);

    $reports = Mockery::mock(FinanceReportRepositoryInterface::class);
    $reports->shouldReceive('countTenantExportRows')->once()->andReturn(5001);
    $service = new PrepareFinanceExportService(
        $reports,
        app(TenantOperationsGuard::class),
        app(FinancePeriodService::class),
        app(ActivityLogRepositoryInterface::class),
    );
    expect(fn () => $service->tenant($context['owner'], '2026-09-01', '2026-09-24', null))->toThrow(DomainActionConflict::class);

    $response = $this->actingAs($context['owner'])->withSession(['auth.version' => $context['owner']->auth_version])
        ->get('/tenant/finance/export?from=2026-09-01&to=2026-09-24')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toStartWith("\xEF\xBB\xBF");
});
