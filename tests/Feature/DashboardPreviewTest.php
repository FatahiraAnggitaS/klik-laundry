<?php

use App\Enums\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the default customer dashboard without a database', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/index')
            ->where('activeRole.value', UserRole::Customer->value)
            ->has('metrics', 4)
            ->has('workItems', 3)
            ->has('milestones', 3)
            ->where('milestones.1.status', 'completed')
        );
});

it('renders every role dashboard preview', function (UserRole $role) {
    $this->get(route('preview.dashboard', ['role' => $role->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/index')
            ->where('activeRole.value', $role->value)
            ->where('activeRole.label', $role->label())
            ->has('navigation', 5)
        );
})->with(UserRole::cases());

it('returns not found for an unknown role preview', function () {
    $this->get('/preview/unknown-role')->assertNotFound();
});
