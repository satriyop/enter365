<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Exceptions\Domain\ValidationException;

describe('BusinessRuleException', function () {

    it('creates insufficient stock exception', function () {
        $exception = BusinessRuleException::insufficientStock('MCB-16A', 10, 5);

        expect($exception->getMessage())->toContain('MCB-16A');
        expect($exception->getMessage())->toContain('10');
        expect($exception->getMessage())->toContain('5');
        expect($exception->getErrorCode())->toBe('BUSINESS_RULE_VIOLATION');
        expect($exception->getStatusCode())->toBe(409);
        expect($exception->getContext())->toHaveKey('product');
        expect($exception->getContext())->toHaveKey('required');
        expect($exception->getContext())->toHaveKey('available');
    });

    it('creates credit limit exceeded exception', function () {
        $exception = BusinessRuleException::creditLimitExceeded('PT ABC', 10000000, 15000000);

        expect($exception->getMessage())->toContain('PT ABC');
        expect($exception->getErrorCode())->toBe('BUSINESS_RULE_VIOLATION');
        expect($exception->getStatusCode())->toBe(409);
        expect($exception->getContext()['contact'])->toBe('PT ABC');
        expect($exception->getContext()['limit'])->toBe(10000000);
        expect($exception->getContext()['outstanding'])->toBe(15000000);
    });

    it('creates fiscal period closed exception', function () {
        $exception = BusinessRuleException::fiscalPeriodClosed('2024-01');

        expect($exception->getMessage())->toContain('2024-01');
        expect($exception->getMessage())->toContain('sudah ditutup');
        expect($exception->getContext()['period'])->toBe('2024-01');
    });

    it('creates duplicate entry exception', function () {
        $exception = BusinessRuleException::duplicateEntry('Product', 'sku', 'MCB-001');

        expect($exception->getMessage())->toContain('Product');
        expect($exception->getMessage())->toContain('sku');
        expect($exception->getMessage())->toContain('MCB-001');
        expect($exception->getContext()['entity'])->toBe('Product');
        expect($exception->getContext()['field'])->toBe('sku');
        expect($exception->getContext()['value'])->toBe('MCB-001');
    });

    it('creates operation not allowed exception', function () {
        $exception = BusinessRuleException::operationNotAllowed('delete', 'Dokumen sudah diposting.');

        expect($exception->getMessage())->toContain('delete');
        expect($exception->getMessage())->toContain('Dokumen sudah diposting');
    });

    it('converts to array correctly', function () {
        $exception = BusinessRuleException::insufficientStock('MCB-16A', 10, 5);
        $array = $exception->toArray();

        expect($array)->toHaveKey('code');
        expect($array)->toHaveKey('message');
        expect($array)->toHaveKey('context');
        expect($array['code'])->toBe('BUSINESS_RULE_VIOLATION');
    });
});

describe('EntityNotFoundException', function () {

    it('creates entity not found by ID', function () {
        $exception = new EntityNotFoundException('Invoice', 123);

        expect($exception->getMessage())->toContain('Invoice');
        expect($exception->getMessage())->toContain('123');
        expect($exception->getMessage())->toContain('tidak ditemukan');
        expect($exception->getErrorCode())->toBe('ENTITY_NOT_FOUND');
        expect($exception->getStatusCode())->toBe(404);
        expect($exception->getContext()['entity'])->toBe('Invoice');
        expect($exception->getContext()['id'])->toBe(123);
    });

    it('creates entity not found by field', function () {
        $exception = EntityNotFoundException::byField('User', 'email', 'test@example.com');

        expect($exception->getMessage())->toContain('User');
        expect($exception->getMessage())->toContain('email');
        expect($exception->getMessage())->toContain('test@example.com');
        expect($exception->getContext()['field'])->toBe('email');
    });

    it('creates related entity not found', function () {
        $exception = EntityNotFoundException::relatedNotFound('Invoice', 123, 'JournalEntry');

        expect($exception->getMessage())->toContain('JournalEntry');
        expect($exception->getMessage())->toContain('Invoice');
        expect($exception->getMessage())->toContain('123');
        expect($exception->getContext()['parent_entity'])->toBe('Invoice');
        expect($exception->getContext()['parent_id'])->toBe(123);
        expect($exception->getContext()['related_entity'])->toBe('JournalEntry');
    });
});

describe('ValidationException', function () {

    it('creates validation exception with field', function () {
        $exception = ValidationException::withField('email', 'Email tidak valid.');

        expect($exception->getMessage())->toBe('Email tidak valid.');
        expect($exception->getErrorCode())->toBe('VALIDATION_ERROR');
        expect($exception->getStatusCode())->toBe(422);
        expect($exception->getContext()['field'])->toBe('email');
    });

    it('creates required field exception', function () {
        $exception = ValidationException::required('name', 'Nama');

        expect($exception->getMessage())->toContain('Nama');
        expect($exception->getMessage())->toContain('wajib diisi');
        expect($exception->getContext()['field'])->toBe('name');
        expect($exception->getContext()['rule'])->toBe('required');
    });

    it('creates invalid value exception', function () {
        $exception = ValidationException::invalid('quantity', 'Jumlah harus positif.', ['min' => 1]);

        expect($exception->getMessage())->toBe('Jumlah harus positif.');
        expect($exception->getContext()['rule'])->toBe('invalid');
        expect($exception->getContext()['min'])->toBe(1);
    });
});

describe('DocumentLockedException', function () {

    it('creates cannot edit exception with custom reason', function () {
        // Create a mock model with shouldIgnoreMissing
        $document = Mockery::mock(\Illuminate\Database\Eloquent\Model::class)->shouldIgnoreMissing();
        $document->shouldReceive('getKey')->andReturn(123);
        $document->status = (object) ['value' => 'posted'];
        $document->document_number = 'INV-001';

        $exception = DocumentLockedException::cannotEdit($document, 'Dokumen sudah diposting.');

        // When reason is provided, it becomes the message
        expect($exception->getMessage())->toBe('Dokumen sudah diposting.');
        expect($exception->getErrorCode())->toBe('DOCUMENT_LOCKED');
        expect($exception->getStatusCode())->toBe(422);
        expect($exception->getContext()['document_id'])->toBe(123);
    });

    it('creates cannot edit exception with default message', function () {
        $document = Mockery::mock(\Illuminate\Database\Eloquent\Model::class)->shouldIgnoreMissing();
        $document->shouldReceive('getKey')->andReturn(123);
        $document->status = (object) ['value' => 'posted'];
        $document->document_number = 'INV-001';

        $exception = DocumentLockedException::cannotEdit($document);

        // Default message when no reason provided
        expect($exception->getMessage())->toContain('tidak dapat diubah');
    });

    it('creates cannot delete exception', function () {
        $document = Mockery::mock(\Illuminate\Database\Eloquent\Model::class)->shouldIgnoreMissing();
        $document->shouldReceive('getKey')->andReturn(456);
        $document->status = (object) ['value' => 'posted'];
        $document->document_number = 'INV-002';

        $exception = DocumentLockedException::cannotDelete($document, 'Masih memiliki pembayaran.');

        // When reason is provided, it becomes the message
        expect($exception->getMessage())->toBe('Masih memiliki pembayaran.');
    });

    it('creates cannot delete exception with default message', function () {
        $document = Mockery::mock(\Illuminate\Database\Eloquent\Model::class)->shouldIgnoreMissing();
        $document->shouldReceive('getKey')->andReturn(456);
        $document->status = (object) ['value' => 'posted'];
        $document->document_number = 'INV-002';

        $exception = DocumentLockedException::cannotDelete($document);

        // Default message when no reason provided
        expect($exception->getMessage())->toContain('tidak dapat dihapus');
    });

    it('creates has dependencies exception', function () {
        $document = Mockery::mock(\Illuminate\Database\Eloquent\Model::class)->shouldIgnoreMissing();
        $document->shouldReceive('getKey')->andReturn(789);

        $exception = DocumentLockedException::hasDependencies($document, 'pembayaran');

        expect($exception->getMessage())->toContain('tidak dapat dihapus');
        expect($exception->getMessage())->toContain('pembayaran');
        expect($exception->getContext()['dependency_type'])->toBe('pembayaran');
    });

    it('creates already posted exception', function () {
        $document = Mockery::mock(\Illuminate\Database\Eloquent\Model::class)->shouldIgnoreMissing();
        $document->shouldReceive('getKey')->andReturn(100);
        $document->status = (object) ['value' => 'posted'];
        $document->document_number = 'INV-003';

        $exception = DocumentLockedException::alreadyPosted($document);

        expect($exception->getMessage())->toContain('sudah diposting');
    });
});
