<?php

declare(strict_types=1);

it('loads the SPA login page', function () {
    $page = visit(env('SPA_URL', 'http://localhost:3000').'/login');

    $page->assertSee('Sign in to your account');
});

it('has no javascript errors on login page', function () {
    $page = visit(env('SPA_URL', 'http://localhost:3000').'/login');

    $page->assertNoJavascriptErrors();
});
