<?php

declare(strict_types=1);

/**
 * Browser auth tests.
 *
 * Note: The login test uses the seeded test user (admin@example.com) because
 * browser tests hit the real API server, which uses a different database than
 * the test's in-memory SQLite. Factory-created users only exist in the test DB.
 */
it('can login with valid credentials', function () {
    $page = visit(spaUrl('/login'));

    $page->fill('[data-testid="login-email"]', 'admin@example.com')
        ->fill('[data-testid="login-password"]', 'password')
        ->click('[data-testid="login-submit"]')
        ->assertPathIs('/')
        ->assertSee('Dashboard');
});

it('shows error on invalid credentials', function () {
    $page = visit(spaUrl('/login'));

    $page->fill('[data-testid="login-email"]', 'wrong@email.com')
        ->fill('[data-testid="login-password"]', 'wrongpassword')
        ->click('[data-testid="login-submit"]')
        ->assertVisible('[data-testid="login-error"]');
});

it('redirects to login when accessing protected route unauthenticated', function () {
    $page = visit(spaUrl('/'));

    $page->assertPathIs('/login');
});

it('can logout and token is cleared', function () {
    // Login first
    $page = visit(spaUrl('/login'));

    $page->fill('[data-testid="login-email"]', 'admin@example.com')
        ->fill('[data-testid="login-password"]', 'password')
        ->click('[data-testid="login-submit"]')
        ->assertPathIs('/')
        ->assertSee('Dashboard');

    // Open user dropdown in header and click Logout
    $page->click('[data-testid="user-menu-btn"]')
        ->click('[data-testid="logout-btn"]')
        ->assertPathIs('/login');
});
