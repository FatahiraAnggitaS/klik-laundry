<?php

use App\Enums\UserRole;
use App\Http\Controllers\Dashboard\ShowDashboardPreviewController;
use App\Http\Controllers\Foundation\ShowPlatformSettingsController;
use App\Http\Controllers\Identity\ConfirmSensitiveAuthenticationController;
use App\Http\Controllers\Identity\ShowSecuritySettingsController;
use App\Http\Controllers\Identity\ShowSensitiveAuthenticationController;
use App\Http\Controllers\Identity\ShowWorkspaceController;
use App\Http\Controllers\Identity\UpdateProfileController;
use App\Http\Controllers\MilestoneZero\ShowWireflowPreviewController;
use App\Http\Controllers\SuperUser\CloseTenantController;
use App\Http\Controllers\SuperUser\ListTenantApplicationsController;
use App\Http\Controllers\SuperUser\ReactivateTenantController;
use App\Http\Controllers\SuperUser\ReactivateUserController;
use App\Http\Controllers\SuperUser\ReleasePayoutHoldController;
use App\Http\Controllers\SuperUser\ReviewPayoutAccountController;
use App\Http\Controllers\SuperUser\ReviewTenantApplicationController;
use App\Http\Controllers\SuperUser\SetPayoutHoldController;
use App\Http\Controllers\SuperUser\SuspendTenantController;
use App\Http\Controllers\SuperUser\SuspendUserController;
use App\Http\Controllers\Tenancy\RequestTenantClosureController;
use App\Http\Controllers\Tenancy\ResubmitTenantApplicationController;
use App\Http\Controllers\Tenancy\ShowTenantRegistrationController;
use App\Http\Controllers\Tenancy\StoreTenantRegistrationController;
use App\Http\Controllers\Tenancy\SubmitPayoutAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowDashboardPreviewController::class)
    ->defaults('role', UserRole::Customer->value)
    ->name('home');

Route::get('/preview/{role}', ShowDashboardPreviewController::class)
    ->whereIn('role', array_map(
        static fn (UserRole $role): string => $role->value,
        UserRole::cases(),
    ))
    ->name('preview.dashboard');

Route::get('/milestone-0/wireflows/{role}', ShowWireflowPreviewController::class)
    ->whereIn('role', array_map(
        static fn (UserRole $role): string => $role->value,
        UserRole::cases(),
    ))
    ->name('milestone-zero.wireflow');

Route::get('/foundation/platform-settings', ShowPlatformSettingsController::class)
    ->name('foundation.platform-settings');

Route::middleware('guest')->group(function (): void {
    Route::get('/tenant/register', ShowTenantRegistrationController::class)->name('tenant.register');
    Route::post('/tenant/register', StoreTenantRegistrationController::class)->name('tenant.register.store');
});

Route::middleware(['auth', 'identity.active'])->group(function (): void {
    Route::get('/identity/security', ShowSecuritySettingsController::class)->name('identity.security');
    Route::patch('/identity/profile', UpdateProfileController::class)->name('identity.profile.update');

    Route::middleware('verified')->group(function (): void {
        Route::get('/workspace', ShowWorkspaceController::class)
            ->middleware('two-factor.required')
            ->name('workspace');

        Route::get('/identity/confirm-sensitive-action', ShowSensitiveAuthenticationController::class)
            ->middleware('two-factor.required')
            ->name('identity.sensitive-authentication.create');
        Route::post('/identity/confirm-sensitive-action', ConfirmSensitiveAuthenticationController::class)
            ->middleware(['two-factor.required', 'throttle:5,1'])
            ->name('identity.sensitive-authentication.store');

        Route::post('/tenant/application/resubmission', ResubmitTenantApplicationController::class)
            ->middleware('two-factor.required')
            ->name('tenant.application.resubmit');

        Route::middleware(['two-factor.required', 'sensitive.confirmed'])->group(function (): void {
            Route::post('/tenant/closure-requests', RequestTenantClosureController::class)->name('tenant.closure-request.store');
            Route::put('/tenant/payout-account', SubmitPayoutAccountController::class)->name('tenant.payout-account.update');

            Route::prefix('super-user')->name('super-user.')->group(function (): void {
                Route::patch('/tenants/{tenant}/review', ReviewTenantApplicationController::class)->name('tenants.review');
                Route::post('/tenants/{tenant}/suspension', SuspendTenantController::class)->name('tenants.suspend');
                Route::post('/tenants/{tenant}/reactivation', ReactivateTenantController::class)->name('tenants.reactivate');
                Route::post('/tenants/{tenant}/closure', CloseTenantController::class)->name('tenants.close');
                Route::post('/tenants/{tenant}/payout-hold', SetPayoutHoldController::class)->name('tenants.payout-hold.set');
                Route::delete('/tenants/{tenant}/payout-hold', ReleasePayoutHoldController::class)->name('tenants.payout-hold.release');
                Route::patch('/payout-accounts/{account}/review', ReviewPayoutAccountController::class)->name('payout-accounts.review');
                Route::post('/users/{user}/suspension', SuspendUserController::class)->name('users.suspend');
                Route::post('/users/{user}/reactivation', ReactivateUserController::class)->name('users.reactivate');
            });
        });

        Route::get('/super-user/tenants', ListTenantApplicationsController::class)
            ->middleware('two-factor.required')
            ->name('super-user.tenants.index');
    });
});
