<?php

namespace App\Providers;

use App\Contracts\IdentityUser;
use App\Contracts\PrivateProofStorageInterface;
use App\Contracts\TransactionManagerInterface;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\DispatchLifecycleEvent;
use App\Gateways\Identity\FortifySensitiveAuthenticationVerifier;
use App\Gateways\Identity\SensitiveAuthenticationVerifierInterface;
use App\Gateways\Payments\DuitkuGateway;
use App\Gateways\Payments\PaymentGatewayInterface;
use App\Infrastructure\LaravelPrivateProofStorage;
use App\Infrastructure\LaravelTransactionManager;
use App\Infrastructure\PulseIdentityResolver;
use App\Listeners\PersistAndBroadcastLifecycleNotification;
use App\Listeners\StampAuthenticationSession;
use App\Policies\IdentityPolicy;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\AuditReviewRepositoryInterface;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\DriverPayoutRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Repositories\Contracts\FinanceReportRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use App\Repositories\Contracts\PrivacyRepositoryInterface;
use App\Repositories\Contracts\ProofRetentionRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\TenantPayoutRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentActivityLogRepository;
use App\Repositories\Eloquent\EloquentAuditReviewRepository;
use App\Repositories\Eloquent\EloquentCustomerAddressRepository;
use App\Repositories\Eloquent\EloquentDispatchRepository;
use App\Repositories\Eloquent\EloquentDriverPayoutRepository;
use App\Repositories\Eloquent\EloquentDriverRepository;
use App\Repositories\Eloquent\EloquentFinanceReportRepository;
use App\Repositories\Eloquent\EloquentNotificationRepository;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentOutletRepository;
use App\Repositories\Eloquent\EloquentPackageRepository;
use App\Repositories\Eloquent\EloquentPaymentRepository;
use App\Repositories\Eloquent\EloquentPayoutAccountRepository;
use App\Repositories\Eloquent\EloquentPlatformSettingRepository;
use App\Repositories\Eloquent\EloquentPrivacyRepository;
use App\Repositories\Eloquent\EloquentProofRetentionRepository;
use App\Repositories\Eloquent\EloquentRefundRepository;
use App\Repositories\Eloquent\EloquentTenantPayoutRepository;
use App\Repositories\Eloquent\EloquentTenantRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Services\Operations\OperationsAlertService;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Contracts\ResolvesUsers;

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
        $this->app->bind(AuditReviewRepositoryInterface::class, EloquentAuditReviewRepository::class);
        $this->app->bind(PrivacyRepositoryInterface::class, EloquentPrivacyRepository::class);
        $this->app->bind(ProofRetentionRepositoryInterface::class, EloquentProofRetentionRepository::class);
        $this->app->bind(OutletRepositoryInterface::class, EloquentOutletRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(PackageRepositoryInterface::class, EloquentPackageRepository::class);
        $this->app->bind(CustomerAddressRepositoryInterface::class, EloquentCustomerAddressRepository::class);
        $this->app->bind(DriverRepositoryInterface::class, EloquentDriverRepository::class);
        $this->app->bind(DispatchRepositoryInterface::class, EloquentDispatchRepository::class);
        $this->app->bind(RefundRepositoryInterface::class, EloquentRefundRepository::class);
        $this->app->bind(TenantPayoutRepositoryInterface::class, EloquentTenantPayoutRepository::class);
        $this->app->bind(DriverPayoutRepositoryInterface::class, EloquentDriverPayoutRepository::class);
        $this->app->bind(FinanceReportRepositoryInterface::class, EloquentFinanceReportRepository::class);
        $this->app->bind(TransactionManagerInterface::class, LaravelTransactionManager::class);
        $this->app->bind(PrivateProofStorageInterface::class, LaravelPrivateProofStorage::class);
        $this->app->bind(SensitiveAuthenticationVerifierInterface::class, FortifySensitiveAuthenticationVerifier::class);
        $this->app->singleton(ResolvesUsers::class, PulseIdentityResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') && config('cache.limiter') !== 'redis') {
            throw new \RuntimeException('Production rate limiter store must use Redis.');
        }

        Gate::define('manage-own-tenant', [IdentityPolicy::class, 'manageOwnTenant']);
        Gate::define('manage-platform', [IdentityPolicy::class, 'managePlatform']);
        Gate::define('viewPulse', fn ($user): bool => $user instanceof IdentityUser
            && $user->role() === UserRole::SuperUser
            && $user->status() === UserStatus::Active
            && $user->hasConfirmedTwoFactorAuthentication());
        Event::listen(Login::class, StampAuthenticationSession::class);
        Event::listen(DispatchLifecycleEvent::class, PersistAndBroadcastLifecycleNotification::class);
        Event::listen(QueueBusy::class, function (QueueBusy $event): void {
            app(OperationsAlertService::class)->send(
                'queue_busy',
                'Queue backlog melewati batas',
                "Queue {$event->connectionName}:{$event->queue} memiliki {$event->size} job.",
                ['connection' => $event->connectionName, 'queue' => $event->queue, 'size' => $event->size],
            );
        });
        Event::listen(JobFailed::class, function (JobFailed $event): void {
            app(OperationsAlertService::class)->send(
                'job_failed',
                'Queued job gagal',
                'Queued job gagal setelah retry. Periksa failed jobs dan jalankan replay idempotent sesuai runbook.',
                [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                    'job' => $event->job->resolveName(),
                    'error_class' => class_basename($event->exception),
                ],
            );
        });

        RateLimiter::for('order-create', fn (Request $request): Limit => Limit::perMinute(10)->by($this->actorKey($request)));
        RateLimiter::for('payment-create', fn (Request $request): Limit => Limit::perMinute(5)->by($this->actorKey($request).':'.$request->route('order')));
        RateLimiter::for('payment-inquiry', fn (Request $request): Limit => Limit::perMinute(10)->by($this->actorKey($request)));
        RateLimiter::for('proof-upload', fn (Request $request): Limit => Limit::perMinute(10)->by($this->actorKey($request)));
        RateLimiter::for('driver-invitation', fn (Request $request): Limit => Limit::perMinute(10)->by($this->actorKey($request)));
        RateLimiter::for('sensitive-confirmation', fn (Request $request): Limit => Limit::perMinute(5)->by($this->actorKey($request)));
        RateLimiter::for('pii-reveal', fn (Request $request): Limit => Limit::perMinutes(15, 3)->by($this->actorKey($request).':'.$request->route('order')));
        RateLimiter::for('duitku-callback', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
    }

    private function actorKey(Request $request): string
    {
        return $request->user() === null ? 'ip:'.$request->ip() : 'user:'.$request->user()->getAuthIdentifier();
    }
}
