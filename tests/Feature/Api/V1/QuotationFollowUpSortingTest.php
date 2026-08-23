<?php

declare(strict_types=1);

use App\Models\Sales\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    Sanctum::actingAs(User::factory()->admin()->create());
    Quotation::factory()->count(3)->create();
});

describe('quotation follow-up sorting is injection-safe', function () {
    it('ignores a malicious sort_dir instead of interpolating it', function () {
        $payload = 'asc, (CASE WHEN (SELECT COUNT(*) FROM users) > 0 THEN 1 ELSE 1/0 END)';

        $this->getJson('/api/v1/quotation-follow-up?sort_dir='.urlencode($payload))
            ->assertOk();

        // The dangerous fragment must never reach SQL.
        DB::enableQueryLog();
        $this->getJson('/api/v1/quotation-follow-up?sort_dir='.urlencode($payload))->assertOk();
        $sql = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        expect($sql)->not->toContain('CASE WHEN')
            ->and($sql)->not->toContain('1/0');
    });

    it('ignores a malicious sort_by instead of interpolating it', function () {
        $this->getJson('/api/v1/quotation-follow-up?sort_by='.urlencode('id; DROP TABLE users'))
            ->assertOk();

        expect(DB::table('users')->count())->toBeGreaterThan(0);
    });

    it('still honours a legitimate descending sort', function () {
        $this->getJson('/api/v1/quotation-follow-up?sort_dir=desc')->assertOk();
        $this->getJson('/api/v1/quotation-follow-up?sort_by=total_amount&sort_dir=desc')->assertOk();
    });

    it('defaults an unknown sort field to next_follow_up_at', function () {
        $this->getJson('/api/v1/quotation-follow-up?sort_by=password')->assertOk();
    });
});
