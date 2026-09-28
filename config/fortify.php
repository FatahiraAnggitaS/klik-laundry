<?php

use App\Http\Middleware\ThrottleFortifyPasswordResetRequests;
use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web', ThrottleFortifyPasswordResetRequests::class],
    'auth_middleware' => 'identity.active',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'views' => true,
    'home' => '/workspace',
    'prefix' => '',
    'domain' => null,
    'lowercase_usernames' => true,
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'verification' => 'verification',
        'passkeys' => null,
    ],
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];
