<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;
use App\Services\Base\ServiceResult;

/**
 * Trait for testing application services.
 *
 * Provides common setup and assertion methods for
 * testing service layer operations.
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
 *     $result = $this->service->create([...]);
 *     $this->assertServiceSuccess($result);
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
     * Assert that a service result is successful.
     */
    protected function assertServiceSuccess(ServiceResult $result): void
    {
        expect($result->isSuccess())->toBeTrue(
            'Expected service success but got failure: '.($result->getMessage() ?? 'No message')
        );
    }

    /**
     * Assert that a service result is a failure.
     */
    protected function assertServiceFailure(ServiceResult $result, ?string $expectedMessage = null): void
    {
        expect($result->isFailure())->toBeTrue('Expected service failure but got success');

        if ($expectedMessage !== null) {
            expect($result->getMessage())->toContain($expectedMessage);
        }
    }

    /**
     * Assert service result and get data or fail.
     *
     * @template T
     *
     * @param  ServiceResult<T>  $result
     * @return T
     */
    protected function assertAndGetData(ServiceResult $result): mixed
    {
        $this->assertServiceSuccess($result);

        return $result->getDataOrFail();
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
