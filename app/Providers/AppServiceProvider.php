<?php

namespace App\Providers;

use App\Contracts\TransactionManagerInterface;
use App\Gateways\Identity\FortifySensitiveAuthenticationVerifier;
use App\Gateways\Identity\SensitiveAuthenticationVerifierInterface;
use App\Infrastructure\LaravelTransactionManager;
use App\Listeners\StampAuthenticationSession;
use App\Policies\IdentityPolicy;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentActivityLogRepository;
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
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(TenantRepositoryInterface::class, EloquentTenantRepository::class);
        $this->app->bind(PayoutAccountRepositoryInterface::class, EloquentPayoutAccountRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, EloquentActivityLogRepository::class);
        $this->app->bind(TransactionManagerInterface::class, LaravelTransactionManager::class);
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
    }
}
