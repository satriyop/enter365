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
    $page = visit(env('SPA_URL', 'http://localhost:3000').'/login');

    $page->fill('input[type="email"]', 'admin@example.com')
        ->fill('input[type="password"]', 'password')
        ->click('button[type="submit"]')
        ->assertPathIs('/')
        ->assertSee('Dashboard');
});

it('shows error on invalid credentials', function () {
    $page = visit(env('SPA_URL', 'http://localhost:3000').'/login');

    $page->fill('input[type="email"]', 'wrong@email.com')
        ->fill('input[type="password"]', 'wrongpassword')
        ->click('button[type="submit"]')
        ->assertVisible('.text-destructive');
});

it('redirects to login when accessing protected route unauthenticated', function () {
    $page = visit(env('SPA_URL', 'http://localhost:3000').'/');

    $page->assertPathIs('/login');
});
