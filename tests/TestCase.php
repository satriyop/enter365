<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @property \App\Models\User $user
 * @property \App\Models\Contacts\Contact $contact
 * @property \App\Models\Inventory\Product $product
 * @property mixed $service
 * @property \App\Models\Contacts\Contact $client
 * @property \App\Models\Contacts\Contact $customer
 * @property \App\Models\Contacts\Contact $vendor
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Assert that the number of database queries does not exceed a limit.
     *
     * Budgets for authenticated endpoints include one query for the caller's
     * role/permission set, loaded once per request by HasRolesAndPermissions.
     */
    protected function assertMaxQueries(int $max, callable $callback): void
    {
        \DB::flushQueryLog();
        \DB::enableQueryLog();

        $callback();

        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            $max,
            $queries,
            "Terlalu banyak query: {$queries} (Max: {$max}). Kemungkinan ada masalah N+1."
        );
    }
}
