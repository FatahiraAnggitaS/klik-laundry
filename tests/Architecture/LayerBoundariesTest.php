<?php

use App\Contracts\PrivateProofStorageInterface;
use App\Contracts\TransactionManagerInterface;
use App\Gateways\Identity\FortifySensitiveAuthenticationVerifier;
use App\Gateways\Identity\SensitiveAuthenticationVerifierInterface;
use App\Infrastructure\LaravelPrivateProofStorage;
use App\Infrastructure\LaravelTransactionManager;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentActivityLogRepository;
use App\Repositories\Eloquent\EloquentCustomerAddressRepository;
use App\Repositories\Eloquent\EloquentDispatchRepository;
use App\Repositories\Eloquent\EloquentDriverRepository;
use App\Repositories\Eloquent\EloquentNotificationRepository;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentOutletRepository;
use App\Repositories\Eloquent\EloquentPackageRepository;
use App\Repositories\Eloquent\EloquentPaymentRepository;
use App\Repositories\Eloquent\EloquentPayoutAccountRepository;
use App\Repositories\Eloquent\EloquentPlatformSettingRepository;
use App\Repositories\Eloquent\EloquentTenantRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\Facades\File;

function phpSourcesIn(string $directory): array
{
    if (! File::isDirectory($directory)) {
        return [];
    }

    return collect(File::allFiles($directory))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getPathname() => File::get($file->getPathname())])
        ->all();
}

it('keeps controllers and form requests away from persistence details', function () {
    $sources = [
        ...phpSourcesIn(app_path('Http/Controllers')),
        ...phpSourcesIn(app_path('Http/Requests')),
    ];

    foreach ($sources as $source) {
        expect($source)
            ->not->toContain('use App\\Models\\')
            ->not->toContain('use App\\Repositories\\')
            ->not->toContain('use Illuminate\\Support\\Facades\\DB;')
            ->not->toContain('Illuminate\\Database\\Eloquent')
            ->not->toContain('Illuminate\\Database\\Query');
    }
});

it('keeps services away from Eloquent and query builder details', function () {
    foreach (phpSourcesIn(app_path('Services')) as $source) {
        expect($source)
            ->not->toContain('use App\\Models\\')
            ->not->toContain('Illuminate\\Database\\Eloquent')
            ->not->toContain('Illuminate\\Database\\Query');
    }
});

it('keeps repositories away from orchestration and external side effects', function () {
    foreach (phpSourcesIn(app_path('Repositories')) as $source) {
        expect($source)
            ->not->toContain('use App\\Services\\')
            ->not->toContain('use App\\Gateways\\')
            ->not->toContain('use App\\Events\\')
            ->not->toContain('use Illuminate\\Support\\Facades\\Http;')
            ->not->toContain('use Illuminate\\Support\\Facades\\Event;')
            ->not->toContain('use Illuminate\\Support\\Facades\\Notification;');
    }
});

it('resolves repository contracts to their infrastructure implementations', function () {
    expect(app(PlatformSettingRepositoryInterface::class))
        ->toBeInstanceOf(EloquentPlatformSettingRepository::class)
        ->and(app(UserRepositoryInterface::class))->toBeInstanceOf(EloquentUserRepository::class)
        ->and(app(TenantRepositoryInterface::class))->toBeInstanceOf(EloquentTenantRepository::class)
        ->and(app(PayoutAccountRepositoryInterface::class))->toBeInstanceOf(EloquentPayoutAccountRepository::class)
        ->and(app(ActivityLogRepositoryInterface::class))->toBeInstanceOf(EloquentActivityLogRepository::class)
        ->and(app(OutletRepositoryInterface::class))->toBeInstanceOf(EloquentOutletRepository::class)
        ->and(app(OrderRepositoryInterface::class))->toBeInstanceOf(EloquentOrderRepository::class)
        ->and(app(PackageRepositoryInterface::class))->toBeInstanceOf(EloquentPackageRepository::class)
        ->and(app(CustomerAddressRepositoryInterface::class))->toBeInstanceOf(EloquentCustomerAddressRepository::class)
        ->and(app(DriverRepositoryInterface::class))->toBeInstanceOf(EloquentDriverRepository::class)
        ->and(app(NotificationRepositoryInterface::class))->toBeInstanceOf(EloquentNotificationRepository::class)
        ->and(app(DispatchRepositoryInterface::class))->toBeInstanceOf(EloquentDispatchRepository::class)
        ->and(app(PaymentRepositoryInterface::class))->toBeInstanceOf(EloquentPaymentRepository::class)
        ->and(app(TransactionManagerInterface::class))->toBeInstanceOf(LaravelTransactionManager::class)
        ->and(app(PrivateProofStorageInterface::class))->toBeInstanceOf(LaravelPrivateProofStorage::class)
        ->and(app(SensitiveAuthenticationVerifierInterface::class))->toBeInstanceOf(FortifySensitiveAuthenticationVerifier::class);
});
