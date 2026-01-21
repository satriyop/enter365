<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Exception when a requested entity cannot be found.
 *
 * Use when:
 * - A model/record with the given ID doesn't exist
 * - A lookup returns no results when one is expected
 * - A related entity referenced doesn't exist
 */
class EntityNotFoundException extends DomainException
{
    protected string $errorCode = 'ENTITY_NOT_FOUND';

    protected int $statusCode = 404;

    /**
     * Create exception for entity not found by ID.
     */
    public function __construct(string $entity, int|string $id)
    {
        parent::__construct(
            "{$entity} dengan ID {$id} tidak ditemukan.",
            [
                'entity' => $entity,
                'id' => $id,
            ]
        );
    }

    /**
     * Create exception for entity not found by field.
     */
    public static function byField(string $entity, string $field, mixed $value): self
    {
        $instance = new self($entity, (string) $value);
        $instance->context = [
            'entity' => $entity,
            'field' => $field,
            'value' => $value,
        ];
        $instance->message = "{$entity} dengan {$field} '{$value}' tidak ditemukan.";

        return $instance;
    }

    /**
     * Create exception for related entity not found.
     */
    public static function relatedNotFound(string $parentEntity, int|string $parentId, string $relatedEntity): self
    {
        $instance = new self($relatedEntity, 0);
        $instance->context = [
            'parent_entity' => $parentEntity,
            'parent_id' => $parentId,
            'related_entity' => $relatedEntity,
        ];
        $instance->message = "{$relatedEntity} untuk {$parentEntity} #{$parentId} tidak ditemukan.";

        return $instance;
    }
}
