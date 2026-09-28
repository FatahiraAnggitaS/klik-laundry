<?php

it('renders each role wireflow without browser or serious accessibility errors', function (string $role): void {
    visit("/milestone-0/wireflows/{$role}")
        ->assertSee('Fixture')
        ->assertNoSmoke()
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['customer', 'tenant_owner', 'driver', 'super_user'])->group('browser', 'browser-smoke');

it('keeps role navigation usable on a mobile viewport', function (): void {
    visit('/milestone-0/wireflows/customer')
        ->on()->mobile()
        ->assertSee('Customer')
        ->assertNoSmoke()
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->group('browser');

it('provides keyboard focus on the authentication entry point', function (): void {
    visit('/login')
        ->keys('Email', 'Tab')
        ->assertScript("document.activeElement?.getAttribute('type') === 'password'", true)
        ->assertNoSmoke()
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->group('browser', 'browser-smoke');
