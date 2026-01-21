<?php

declare(strict_types=1);

use App\Support\Results\ServiceResult;

describe('ServiceResult', function () {
    describe('success', function () {
        it('creates a successful result without data', function () {
            $result = ServiceResult::success();

            expect($result->isSuccess())->toBeTrue()
                ->and($result->getData())->toBeNull()
                ->and($result->getMessage())->toBe('Operasi berhasil.');
        });

        it('creates a successful result with data', function () {
            $data = (object) ['id' => 1, 'name' => 'Test'];

            $result = ServiceResult::success($data, 'Custom message');

            expect($result->isSuccess())->toBeTrue()
                ->and($result->getData())->toBe($data)
                ->and($result->getMessage())->toBe('Custom message');
        });
    });

    describe('failure', function () {
        it('creates a failed result with message', function () {
            $result = ServiceResult::failure('Something went wrong');

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getMessage())->toBe('Something went wrong')
                ->and($result->getErrors())->toBeEmpty();
        });

        it('creates a failed result with errors', function () {
            $errors = ['field' => ['Error message']];

            $result = ServiceResult::failure('Validation failed', $errors);

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getErrors())->toBe($errors);
        });
    });

    describe('validationFailed', function () {
        it('creates a validation failure result', function () {
            $errors = [
                'name' => ['Name is required'],
                'email' => ['Invalid email format'],
            ];

            $result = ServiceResult::validationFailed($errors);

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getMessage())->toBe('Validasi gagal.')
                ->and($result->getErrors())->toBe($errors);
        });
    });

    describe('notFound', function () {
        it('creates a not found result', function () {
            $result = ServiceResult::notFound('Invoice', 123);

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getMessage())->toBe('Invoice dengan ID 123 tidak ditemukan.');
        });
    });

    describe('getDataOrFail', function () {
        it('returns data when successful', function () {
            $data = (object) ['id' => 1];
            $result = ServiceResult::success($data);

            expect($result->getDataOrFail())->toBe($data);
        });

        it('throws exception when failed', function () {
            $result = ServiceResult::failure('Error');

            expect(fn () => $result->getDataOrFail())
                ->toThrow(RuntimeException::class, 'Error');
        });

        it('throws exception when no data', function () {
            $result = ServiceResult::success();

            expect(fn () => $result->getDataOrFail())
                ->toThrow(RuntimeException::class, 'No data available');
        });
    });

    describe('toArray', function () {
        it('converts successful result to array', function () {
            $data = (object) ['id' => 1, 'name' => 'Test'];
            $result = ServiceResult::success($data, 'Created');

            $array = $result->toArray();

            expect($array['success'])->toBeTrue()
                ->and($array['message'])->toBe('Created')
                ->and($array['data'])->toBe($data)
                ->and($array)->not->toHaveKey('errors');
        });

        it('converts failed result to array', function () {
            $errors = ['field' => ['Error']];
            $result = ServiceResult::failure('Failed', $errors);

            $array = $result->toArray();

            expect($array['success'])->toBeFalse()
                ->and($array['message'])->toBe('Failed')
                ->and($array['errors'])->toBe($errors)
                ->and($array)->not->toHaveKey('data');
        });
    });
});
