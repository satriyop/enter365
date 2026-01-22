<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;

/**
 * Trait for testing application services.
 *
 * Provides common setup methods for testing service layer operations.
 *
 * @deprecated ServiceResult removed. Remove this trait if no longer needed.
 *
 * Usage in Pest:
 * ```php
 * uses(TestsServices::class);
 *
 * beforeEach(function () {
 *     $this->setUpServiceTests();
 * });
 *
 * it('creates invoice successfully', function () {
 *     $invoice = $this->service->create([...]);
 *     expect($invoice)->toBeInstanceOf(Invoice::class);
 * });
 * ```
 */
trait TestsServices
{
    protected User $testUser;

    /**
     * Set up service tests with authenticated user.
     * Call this in beforeEach().
     */
    protected function setUpServiceTests(): void
    {
        $this->testUser = User::factory()->create();
        $this->actingAs($this->testUser);
    }

    /**
     * Get the test user.
     */
    protected function getTestUser(): User
    {
        return $this->testUser;
    }

    /**
     * Create a different authenticated user for testing.
     */
    protected function actingAsDifferentUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }
}
