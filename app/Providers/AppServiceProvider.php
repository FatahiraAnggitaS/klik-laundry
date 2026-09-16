<?php

namespace App\Providers;

use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use App\Repositories\Eloquent\EloquentPlatformSettingRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
