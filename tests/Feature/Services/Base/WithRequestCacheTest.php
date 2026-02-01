<?php

declare(strict_types=1);

describe('WithRequestCache trait', function () {
    it('caches values for the request lifetime', function () {
        $service = new class
        {
            use \App\Services\Base\Traits\WithRequestCache;

            public int $callCount = 0;

            public function getExpensiveValue(): string
            {
                return $this->cached('expensive', function () {
                    $this->callCount++;

                    return 'computed_value';
                });
            }
        };

        $result1 = $service->getExpensiveValue();
        $result2 = $service->getExpensiveValue();
        $result3 = $service->getExpensiveValue();

        expect($result1)->toBe('computed_value')
            ->and($result2)->toBe('computed_value')
            ->and($result3)->toBe('computed_value')
            ->and($service->callCount)->toBe(1);
    });

    it('caches different keys independently', function () {
        $service = new class
        {
            use \App\Services\Base\Traits\WithRequestCache;

            public function getValue(string $key): string
            {
                return $this->cached($key, fn () => "value_for_{$key}");
            }
        };

        expect($service->getValue('a'))->toBe('value_for_a')
            ->and($service->getValue('b'))->toBe('value_for_b')
            ->and($service->getValue('a'))->toBe('value_for_a');
    });

    it('can invalidate a specific key', function () {
        $service = new class
        {
            use \App\Services\Base\Traits\WithRequestCache;

            public int $callCount = 0;

            public function getValue(): int
            {
                return $this->cached('counter', function () {
                    return ++$this->callCount;
                });
            }

            public function invalidate(): void
            {
                $this->forgetCached('counter');
            }
        };

        expect($service->getValue())->toBe(1);
        expect($service->getValue())->toBe(1);

        $service->invalidate();

        expect($service->getValue())->toBe(2);
    });

    it('can clear all cached values', function () {
        $service = new class
        {
            use \App\Services\Base\Traits\WithRequestCache;

            public int $aCount = 0;

            public int $bCount = 0;

            public function getA(): int
            {
                return $this->cached('a', fn () => ++$this->aCount);
            }

            public function getB(): int
            {
                return $this->cached('b', fn () => ++$this->bCount);
            }

            public function resetAll(): void
            {
                $this->clearRequestCache();
            }
        };

        expect($service->getA())->toBe(1)
            ->and($service->getB())->toBe(1);

        $service->resetAll();

        expect($service->getA())->toBe(2)
            ->and($service->getB())->toBe(2);
    });

    it('can cache null and false values', function () {
        $service = new class
        {
            use \App\Services\Base\Traits\WithRequestCache;

            public int $nullCalls = 0;

            public int $falseCalls = 0;

            public function getNullValue(): mixed
            {
                return $this->cached('null_key', function () {
                    $this->nullCalls++;

                    return null;
                });
            }

            public function getFalseValue(): mixed
            {
                return $this->cached('false_key', function () {
                    $this->falseCalls++;

                    return false;
                });
            }
        };

        $service->getNullValue();
        $service->getNullValue();
        $service->getFalseValue();
        $service->getFalseValue();

        expect($service->nullCalls)->toBe(1)
            ->and($service->falseCalls)->toBe(1);
    });
});
