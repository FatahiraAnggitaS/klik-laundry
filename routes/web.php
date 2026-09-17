<?php

use App\Enums\UserRole;
use App\Http\Controllers\Catalog\ChangePackageStatusController;
use App\Http\Controllers\Catalog\PackageController;
use App\Http\Controllers\Customers\CustomerAddressController;
use App\Http\Controllers\Dashboard\ShowDashboardPreviewController;
use App\Http\Controllers\Foundation\ManagePlatformSettingsController;
use App\Http\Controllers\Foundation\ShowPlatformSettingsController;
use App\Http\Controllers\Identity\ConfirmSensitiveAuthenticationController;
use App\Http\Controllers\Identity\ShowSecuritySettingsController;
use App\Http\Controllers\Identity\ShowSensitiveAuthenticationController;
use App\Http\Controllers\Identity\ShowWorkspaceController;
use App\Http\Controllers\Identity\UpdateProfileController;
use App\Http\Controllers\MilestoneZero\ShowWireflowPreviewController;
use App\Http\Controllers\Outlets\ChangeOutletStatusController;
use App\Http\Controllers\Outlets\OperatingHoursController;
use App\Http\Controllers\Outlets\OutletBlackoutController;
use App\Http\Controllers\Outlets\OutletController;
use App\Http\Controllers\Outlets\OutletSlotController;
use App\Http\Controllers\Outlets\SearchOutletsController;
use App\Http\Controllers\Outlets\ShowOutletController;
use App\Http\Controllers\Outlets\TenantOperationsController;
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

Route::get('/outlets', SearchOutletsController::class)->name('outlets.index');
Route::get('/outlets/{outlet}', ShowOutletController::class)->name('outlets.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/tenant/register', ShowTenantRegistrationController::class)->name('tenant.register');
    Route::post('/tenant/register', StoreTenantRegistrationController::class)->name('tenant.register.store');
});

Route::middleware(['auth', 'identity.active'])->group(function (): void {
    Route::get('/identity/security', ShowSecuritySettingsController::class)->name('identity.security');
    Route::patch('/identity/profile', UpdateProfileController::class)->name('identity.profile.update');

    Route::get('/customer/addresses', [CustomerAddressController::class, 'index'])->name('customer.addresses.index');
    Route::post('/customer/addresses', [CustomerAddressController::class, 'store'])->name('customer.addresses.store');
    Route::put('/customer/addresses/{address}', [CustomerAddressController::class, 'update'])->name('customer.addresses.update');
    Route::delete('/customer/addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('customer.addresses.destroy');
    Route::post('/customer/addresses/{address}/default', [CustomerAddressController::class, 'makeDefault'])->name('customer.addresses.default');

    Route::middleware('verified')->group(function (): void {
        Route::get('/workspace', ShowWorkspaceController::class)
            ->middleware('two-factor.required')
            ->name('workspace');

        Route::middleware('two-factor.required')->prefix('tenant')->name('tenant.')->group(function (): void {
            Route::get('/operations', TenantOperationsController::class)->name('operations');
            Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store');
            Route::put('/outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');
            Route::delete('/outlets/{outlet}', [OutletController::class, 'destroy'])->name('outlets.destroy');
            Route::post('/outlets/{outlet}/activate', ChangeOutletStatusController::class)->defaults('target', 'active')->name('outlets.activate');
            Route::post('/outlets/{outlet}/deactivate', ChangeOutletStatusController::class)->defaults('target', 'draft')->name('outlets.deactivate');
            Route::post('/outlets/{outlet}/archive', ChangeOutletStatusController::class)->defaults('target', 'archived')->name('outlets.archive');
            Route::put('/outlets/{outlet}/hours', OperatingHoursController::class)->name('outlets.hours.update');
            Route::post('/outlets/{outlet}/slots', [OutletSlotController::class, 'store'])->name('outlets.slots.store');
            Route::put('/outlets/{outlet}/slots/{slot}', [OutletSlotController::class, 'update'])->name('outlets.slots.update');
            Route::delete('/outlets/{outlet}/slots/{slot}', [OutletSlotController::class, 'destroy'])->name('outlets.slots.destroy');
            Route::post('/outlets/{outlet}/blackouts', [OutletBlackoutController::class, 'store'])->name('outlets.blackouts.store');
            Route::delete('/outlets/{outlet}/blackouts/{blackout}', [OutletBlackoutController::class, 'destroy'])->name('outlets.blackouts.destroy');
            Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
            Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
            Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
            Route::post('/packages/{package}/activate', ChangePackageStatusController::class)->defaults('target', 'active')->name('packages.activate');
            Route::post('/packages/{package}/deactivate', ChangePackageStatusController::class)->defaults('target', 'draft')->name('packages.deactivate');
            Route::post('/packages/{package}/archive', ChangePackageStatusController::class)->defaults('target', 'archived')->name('packages.archive');
        });

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
                Route::patch('/platform-settings/service-radius', [ManagePlatformSettingsController::class, 'update'])->name('platform-settings.radius.update');
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
        Route::get('/super-user/platform-settings', [ManagePlatformSettingsController::class, 'show'])
            ->middleware('two-factor.required')
            ->name('super-user.platform-settings.show');
    });
});
