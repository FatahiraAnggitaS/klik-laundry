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

it('persists the selected visual theme across a reload', function (): void {
    visit('/preview/customer')
        ->click('button[title="Tema gelap"]')
        ->assertScript('document.documentElement.dataset.theme', 'dark')
        ->refresh()
        ->assertScript('document.documentElement.dataset.theme', 'dark')
        ->assertSee('Snapshot metrik role')
        ->click('@chart-legend-toggle')
        ->assertScript('document.querySelector(\'[data-testid="chart-legend-toggle"]\')?.getAttribute(\'aria-pressed\')', 'false')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->group('browser', 'browser-smoke');

it('closes mobile navigation with escape and restores trigger focus', function (): void {
    visit('/preview/customer')
        ->on()->mobile()
        ->click('button[aria-label="Buka navigasi"]')
        ->assertScript('document.querySelector(\'dialog[aria-label="Navigasi utama"]\')?.open', true)
        ->keys('dialog[aria-label="Navigasi utama"]', 'Escape')
        ->assertScript('document.querySelector(\'dialog[aria-label="Navigasi utama"]\')?.open', false)
        ->assertScript('document.activeElement?.getAttribute(\'aria-label\')', 'Buka navigasi')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->group('browser');

it('provides keyboard focus on the authentication entry point', function (): void {
    visit('/')
        ->assertSee('Balik lagi? Yuk, masuk')
        ->keys('Email', 'Tab')
        ->assertScript("document.activeElement?.getAttribute('type') === 'password'", true)
        ->assertNoSmoke()
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->group('browser', 'browser-smoke');

it('shows friendly registration copy without changing form semantics', function (): void {
    visit('/register')
        ->assertSee('Yuk, bikin akun')
        ->assertSee('Buat akun sekarang')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->group('browser', 'browser-smoke');
