<?php

declare(strict_types=1);

use App\Support\Results\UpdateResult;

describe('UpdateResult', function () {
    describe('updated', function () {
        it('creates a successful update result', function () {
            $entity = (object) ['id' => 1, 'name' => 'Updated'];

            $result = UpdateResult::updated($entity);

            expect($result->isSuccess())->toBeTrue()
                ->and($result->getData())->toBe($entity)
                ->and($result->getMessage())->toBe('Data berhasil diperbarui.');
        });

        it('accepts custom message', function () {
            $entity = (object) ['id' => 1];

            $result = UpdateResult::updated($entity, 'Invoice updated successfully');

            expect($result->getMessage())->toBe('Invoice updated successfully');
        });
    });

    describe('notModified', function () {
        it('creates a not modified result', function () {
            $entity = (object) ['id' => 1];

            $result = UpdateResult::notModified($entity);

            expect($result->isSuccess())->toBeTrue()
                ->and($result->getData())->toBe($entity)
                ->and($result->getMessage())->toBe('Tidak ada perubahan.');
        });
    });

    describe('locked', function () {
        it('creates a locked failure result', function () {
            $result = UpdateResult::locked('Document is already posted');

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getMessage())->toBe('Data tidak dapat diubah: Document is already posted');
        });
    });
});
