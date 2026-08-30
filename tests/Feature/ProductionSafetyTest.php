<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks DatabaseSeeder in production', function () {
    $this->app['env'] = 'production';

    expect(fn () => (new DatabaseSeeder)->run())
        ->toThrow(RuntimeException::class, 'blocked in production');
});

it('blocks seed:demo --fresh in production', function () {
    $this->app['env'] = 'production';

    $this->artisan('seed:demo', [
        '--fresh' => true,
        '--demo' => 'pos',
    ])->assertFailed();
});
