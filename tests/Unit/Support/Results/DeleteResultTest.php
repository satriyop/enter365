<?php

declare(strict_types=1);

use App\Support\Results\DeleteResult;

describe('DeleteResult', function () {
    describe('deleted', function () {
        it('creates a successful deletion result', function () {
            $result = DeleteResult::deleted();

            expect($result->isSuccess())->toBeTrue()
                ->and($result->getData())->toBeNull()
                ->and($result->getMessage())->toBe('Data berhasil dihapus.');
        });

        it('accepts custom message', function () {
            $result = DeleteResult::deleted('Invoice deleted successfully');

            expect($result->getMessage())->toBe('Invoice deleted successfully');
        });
    });

    describe('restricted', function () {
        it('creates a restricted failure with dependencies', function () {
            $dependencies = ['pembayaran', 'jurnal'];

            $result = DeleteResult::restricted($dependencies);

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getMessage())->toBe('Tidak dapat menghapus karena masih memiliki: pembayaran, jurnal');
        });
    });

    describe('locked', function () {
        it('creates a locked failure result', function () {
            $result = DeleteResult::locked('Document is already posted');

            expect($result->isSuccess())->toBeFalse()
                ->and($result->getMessage())->toBe('Data tidak dapat dihapus: Document is already posted');
        });
    });
});
