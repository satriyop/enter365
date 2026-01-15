<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Illuminate\Database\Eloquent\Model;

/**
 * Exception when a document cannot be modified due to its state.
 *
 * Use when:
 * - Attempting to edit a posted/approved document
 * - Attempting to delete a document with dependencies
 * - Document is in a finalized state
 */
class DocumentLockedException extends DomainException
{
    protected string $errorCode = 'DOCUMENT_LOCKED';

    protected int $statusCode = 422;

    /**
     * Create exception for non-editable document.
     */
    public static function cannotEdit(Model $document, ?string $reason = null): self
    {
        $type = class_basename($document);
        $number = $document->document_number ?? $document->getKey();
        $defaultReason = 'Dokumen tidak dapat diubah karena sudah diproses.';

        return new self(
            $reason ?? $defaultReason,
            [
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'document_number' => $number,
                'status' => $document->status?->value ?? $document->status ?? null,
            ]
        );
    }

    /**
     * Create exception for non-deletable document.
     */
    public static function cannotDelete(Model $document, ?string $reason = null): self
    {
        $type = class_basename($document);
        $number = $document->document_number ?? $document->getKey();
        $defaultReason = 'Dokumen tidak dapat dihapus.';

        return new self(
            $reason ?? $defaultReason,
            [
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'document_number' => $number,
                'status' => $document->status?->value ?? $document->status ?? null,
            ]
        );
    }

    /**
     * Create exception for document with dependencies.
     */
    public static function hasDependencies(Model $document, string $dependencyType): self
    {
        $type = class_basename($document);

        return new self(
            "{$type} tidak dapat dihapus karena memiliki {$dependencyType} terkait.",
            [
                'document_type' => $type,
                'document_id' => $document->getKey(),
                'dependency_type' => $dependencyType,
            ]
        );
    }

    /**
     * Create exception for posted document.
     */
    public static function alreadyPosted(Model $document): self
    {
        return self::cannotEdit($document, 'Dokumen sudah diposting dan tidak dapat diubah.');
    }
}
