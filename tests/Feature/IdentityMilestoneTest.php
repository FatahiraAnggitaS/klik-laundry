<?php

use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

it('registers only a customer through the public Fortify registration', function () {
    $response = $this->post('/register', [
        'name' => 'Customer Baru',
        'email' => 'customer@example.test',
        'phone' => '081234567890',
        'password' => 'StrongPassword123',
        'password_confirmation' => 'StrongPassword123',
    ]);

    $response->assertRedirect('/workspace');
    $user = User::query()->where('email', 'customer@example.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->phone)->toBe('081234567890')
        ->and(Hash::check('StrongPassword123', $user->password))->toBeTrue();
});

it('validates customer registration phone email and password', function () {
    User::factory()->create(['email' => 'used@example.test']);

    $this->post('/register', [
        'name' => 'Invalid Customer',
        'email' => 'used@example.test',
        'phone' => 'abc',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['email', 'phone', 'password']);
});

it('denies suspended and closed identities at login', function (UserStatus $status) {
    User::factory()->create([
        'email' => "{$status->value}@example.test",
        'status' => $status,
        'password' => Hash::make('StrongPassword123'),
    ]);

    $this->post('/login', [
        'email' => "{$status->value}@example.test",
        'password' => 'StrongPassword123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([UserStatus::Suspended, UserStatus::Closed]);

it('supports login logout and email verification', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'verify@example.test',
        'password' => Hash::make('StrongPassword123'),
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123'])
        ->assertRedirect('/workspace');

    $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(15), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->get($verificationUrl)->assertRedirect('/workspace?verified=1');
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

it('keeps a tenant owner remembered until explicit logout', function () {
    $tenant = Tenant::query()->create([
        'public_id' => (string) Str::ulid(),
        'name' => 'Laundry Persisten',
        'slug' => 'laundry-persisten',
        'phone' => '081234567890',
        'onboarding_status' => TenantOnboardingStatus::Approved,
        'operational_status' => TenantOperationalStatus::Active,
        'initial_outlet_name' => 'Outlet Persisten',
        'initial_outlet_address' => 'Jl. Aman 1',
        'initial_outlet_city' => 'Bandung',
        'initial_outlet_area' => 'Coblong',
        'initial_outlet_latitude' => '-6.8915000',
        'initial_outlet_longitude' => '107.6107000',
    ]);
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::TenantOwner,
        'role_slot' => 'tenant:'.$tenant->id.':owner',
        'email' => 'owner-persisten@example.test',
        'password' => Hash::make('StrongPassword123'),
        'remember_token' => null,
    ]);

    $response = $this->post('/login', [
        'email' => $owner->email,
        'password' => 'StrongPassword123',
        'remember' => false,
    ])->assertRedirect('/workspace');

    expect($owner->refresh()->remember_token)->not->toBeNull();
    $recallerName = Auth::guard('web')->getRecallerName();
    $response->assertCookie($recallerName);
    $recaller = $response->getCookie($recallerName, false);

    $this->withUnencryptedCookie($recallerName, (string) $recaller?->getValue())
        ->post('/logout')->assertRedirect('/')
        ->assertCookieExpired($recallerName);
    $this->assertGuest();
});

it('resets a password and increments the authentication version', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.test']);
    DB::table('sessions')->insert([
        'id' => 'reset-session',
        'user_id' => $user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewStrongPassword123',
        'password_confirmation' => 'NewStrongPassword123',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect(Hash::check('NewStrongPassword123', $user->password))->toBeTrue()
        ->and($user->auth_version)->toBe(2)
        ->and(DB::table('sessions')->where('id', 'reset-session')->exists())->toBeFalse();
});

it('completes a two factor challenge with totp or a recovery code', function (bool $useRecoveryCode) {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $recoveryCode = 'recovery-code-for-test';
    $user = User::factory()->create([
        'email' => $useRecoveryCode ? 'recovery@example.test' : 'totp@example.test',
        'password' => Hash::make('StrongPassword123'),
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode([$recoveryCode], JSON_THROW_ON_ERROR)),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123'])
        ->assertRedirect('/two-factor-challenge');

    $payload = $useRecoveryCode
        ? ['recovery_code' => $recoveryCode]
        : ['code' => $google2fa->getCurrentOtp($secret)];

    $this->post('/two-factor-challenge', $payload)->assertRedirect('/workspace');
    $this->assertAuthenticatedAs($user);
})->with([false, true]);

it('sets up and confirms two factor authentication with recovery codes', function () {
    $user = User::factory()->create([
        'role' => UserRole::TenantOwner,
        'two_factor_confirmed_at' => null,
    ]);
    $session = [
        'auth.version' => $user->auth_version,
        'auth.password_confirmed_at' => time(),
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->post('/user/two-factor-authentication')
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->recoveryCodes())->toHaveCount(8);

    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
    $this->withSession($session)
        ->post('/user/confirmed-two-factor-authentication', [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertSessionHasNoErrors();

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->withSession($session)
        ->getJson('/user/two-factor-recovery-codes')
        ->assertOk()
        ->assertJsonCount(8);
});

it('requires confirmed two factor authentication for tenant owners and super users', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role, 'role_slot' => $role === UserRole::SuperUser ? 'super-user:primary' : null]);

    $this->actingAs($user)
        ->withSession(['auth.version' => $user->auth_version])
        ->get('/workspace')
        ->assertRedirect('/identity/security');
})->with([UserRole::TenantOwner, UserRole::SuperUser]);

it('revokes an authenticated session when its auth version is stale', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.version' => $user->auth_version - 1])
        ->get('/workspace')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('protects Fortify account settings with account status and auth version', function () {
    $user = User::factory()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($user)
        ->withSession(['auth.version' => $user->auth_version])
        ->put('/user/password', [
            'current_password' => 'password',
            'password' => 'ReplacementPassword123',
            'password_confirmation' => 'ReplacementPassword123',
        ])->assertRedirect('/login');

    $this->assertGuest();
});

it('updates a password through the service boundary and audits only safe metadata', function () {
    $user = User::factory()->create(['password' => Hash::make('StrongPassword123')]);
    DB::table('sessions')->insert([
        'id' => 'password-update-session',
        'user_id' => $user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.version' => $user->auth_version])
        ->put('/user/password', [
            'current_password' => 'StrongPassword123',
            'password' => 'ReplacementPassword123',
            'password_confirmation' => 'ReplacementPassword123',
        ])->assertSessionHasNoErrors();

    $user->refresh();
    $audit = ActivityLog::query()->where('action', 'identity.password_updated')->firstOrFail();
    expect(Hash::check('ReplacementPassword123', $user->password))->toBeTrue()
        ->and($user->auth_version)->toBe(2)
        ->and(DB::table('sessions')->where('id', 'password-update-session')->exists())->toBeFalse()
        ->and($audit->after)->toBe(['sessionsRevoked' => true])
        ->and($audit->toJson())->not->toContain('ReplacementPassword123');
});

it('requires password and totp for sensitive authentication without auditing secrets', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $user = User::factory()->create([
        'role' => UserRole::TenantOwner,
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['unused-code'], JSON_THROW_ON_ERROR)),
        'two_factor_confirmed_at' => now(),
        'password' => Hash::make('StrongPassword123'),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.version' => $user->auth_version])
        ->post('/identity/confirm-sensitive-action', [
            'password' => 'StrongPassword123',
            'code' => $google2fa->getCurrentOtp($secret),
        ])->assertRedirect('/workspace');

    expect(session('auth.sensitive_confirmed_at'))->toBeInt();
    $audit = ActivityLog::query()->where('action', 'identity.sensitive_authentication_confirmed')->firstOrFail();
    expect($audit->after)->toBe(['verified' => true])
        ->and($audit->toJson())->not->toContain('StrongPassword123')->not->toContain($secret);

    $this->withSession(['auth.version' => $user->auth_version])
        ->post('/identity/confirm-sensitive-action', ['password' => 'wrong-password', 'code' => '000000'])
        ->assertSessionHasErrors('password');

    expect(ActivityLog::query()->where('action', 'identity.sensitive_authentication_failed')->exists())->toBeTrue();
});

it('does not expose passkey routes', function () {
    $this->get('/user/passkeys')->assertNotFound();
    $this->post('/user/passkeys')->assertNotFound();
});

it('does not expose impersonation or generic status and payment override routes', function () {
    $this->post('/impersonate/01TEST')->assertNotFound();
    $this->patch('/super-user/users/01TEST/status', ['status' => 'active'])->assertNotFound();
    $this->patch('/super-user/payments/01TEST/status', ['status' => 'paid'])->assertNotFound();
});
