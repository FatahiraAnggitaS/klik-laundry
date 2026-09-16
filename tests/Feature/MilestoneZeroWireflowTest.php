<?php

use App\Enums\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the first wireflow step for every role without a database', function (UserRole $role) {
    $this->get(route('milestone-zero.wireflow', ['role' => $role->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('milestone-zero/wireflow')
            ->where('activeRole.value', $role->value)
            ->where('activeStepIndex', 0)
            ->has('roles', 4)
            ->has('wireflow.steps', 6)
            ->has('activeStep.branches')
            ->has('riskNotices', 2)
        );
})->with(UserRole::cases());

it('supports a direct link to a role-specific wireflow step', function () {
    $this->get(route('milestone-zero.wireflow', [
        'role' => UserRole::Customer->value,
        'step' => 'payment-branch',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('milestone-zero/wireflow')
            ->where('activeStep.id', 'payment-branch')
            ->where('activeStepIndex', 2)
            ->where('previousStepId', 'configure-order')
            ->where('nextStepId', 'track-processing')
            ->has('activeStep.privacy')
        );
});

it('returns not found for unknown wireflow roles and steps', function () {
    $this->get('/milestone-0/wireflows/unknown-role')->assertNotFound();
    $this->get('/milestone-0/wireflows/customer?step=unknown-step')->assertNotFound();
});
