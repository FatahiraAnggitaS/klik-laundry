<?php

namespace App\Providers;

use App\Contracts\PrivateProofStorageInterface;
use App\Contracts\TransactionManagerInterface;
use App\Events\DispatchLifecycleEvent;
use App\Gateways\Identity\FortifySensitiveAuthenticationVerifier;
use App\Gateways\Identity\SensitiveAuthenticationVerifierInterface;
use App\Gateways\Payments\DuitkuGateway;
use App\Gateways\Payments\PaymentGatewayInterface;
use App\Infrastructure\LaravelPrivateProofStorage;
use App\Infrastructure\LaravelTransactionManager;
use App\Listeners\PersistAndBroadcastLifecycleNotification;
use App\Listeners\StampAuthenticationSession;
use App\Policies\IdentityPolicy;
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
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PlatformSettingRepositoryInterface::class,
            EloquentPlatformSettingRepository::class,
        );
        $this->app->bind(
            PaymentRepositoryInterface::class,
            EloquentPaymentRepository::class,
        );
        $this->app->bind(
            PaymentGatewayInterface::class,
            DuitkuGateway::class,
        );
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(TenantRepositoryInterface::class, EloquentTenantRepository::class);
        $this->app->bind(PayoutAccountRepositoryInterface::class, EloquentPayoutAccountRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, EloquentActivityLogRepository::class);
        $this->app->bind(OutletRepositoryInterface::class, EloquentOutletRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(PackageRepositoryInterface::class, EloquentPackageRepository::class);
        $this->app->bind(CustomerAddressRepositoryInterface::class, EloquentCustomerAddressRepository::class);
        $this->app->bind(DriverRepositoryInterface::class, EloquentDriverRepository::class);
        $this->app->bind(DispatchRepositoryInterface::class, EloquentDispatchRepository::class);
        $this->app->bind(TransactionManagerInterface::class, LaravelTransactionManager::class);
        $this->app->bind(PrivateProofStorageInterface::class, LaravelPrivateProofStorage::class);
        $this->app->bind(SensitiveAuthenticationVerifierInterface::class, FortifySensitiveAuthenticationVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-own-tenant', [IdentityPolicy::class, 'manageOwnTenant']);
        Gate::define('manage-platform', [IdentityPolicy::class, 'managePlatform']);
        Event::listen(Login::class, StampAuthenticationSession::class);
        Event::listen(DispatchLifecycleEvent::class, PersistAndBroadcastLifecycleNotification::class);
    }
}
