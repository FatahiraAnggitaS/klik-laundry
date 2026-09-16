<?php

use App\Enums\UserRole;
use App\Http\Controllers\Dashboard\ShowDashboardPreviewController;
use App\Http\Controllers\Foundation\ShowPlatformSettingsController;
use App\Http\Controllers\MilestoneZero\ShowWireflowPreviewController;
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
