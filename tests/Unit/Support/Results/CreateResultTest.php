<?php

declare(strict_types=1);

use App\Support\Results\CreateResult;

describe('CreateResult', function () {
    describe('created', function () {
        it('creates a successful creation result', function () {
            $entity = (object) ['id' => 1, 'name' => 'Test'];

            $result = CreateResult::created($entity);

            expect($result->isSuccess())->toBeTrue()
                ->and($result->getData())->toBe($entity)
                ->and($result->getMessage())->toBe('Data berhasil dibuat.');
        });

        it('accepts custom message', function () {
            $entity = (object) ['id' => 1];

            $result = CreateResult::created($entity, 'Invoice created');

            expect($result->getMessage())->toBe('Invoice created');
        });
    });

    describe('duplicate', function () {
        it('creates a duplicate failure result', function () {
            $result = CreateResult::duplicate('invoice_number', 'INV-001');

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getMessage())->toBe('Duplikat invoice_number: INV-001');
        });
    });

    describe('invalid', function () {
        it('creates an invalid data failure result', function () {
            $errors = [
                'name' => ['Name is required'],
                'amount' => ['Amount must be positive'],
            ];

            $result = CreateResult::invalid($errors);

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getMessage())->toBe('Data tidak valid.')
                ->and($result->getErrors())->toBe($errors);
        });
    });
});
