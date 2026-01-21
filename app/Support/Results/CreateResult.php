<?php

declare(strict_types=1);

namespace App\Support\Results;

/**
 * Result for create operations.
 *
 * @template T of \Illuminate\Database\Eloquent\Model
 *
 * @extends ServiceResult<T>
 */
class CreateResult extends ServiceResult
{
    /**
     * Create a successful creation result.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $entity
     */
    public static function created(object $entity, ?string $message = null): static
    {
        return static::success($entity, $message ?? 'Data berhasil dibuat.');
    }

    /**
     * Create a duplicate entry failure.
     */
    public static function duplicate(string $field, string $value): static
    {
        return static::failure("Duplikat {$field}: {$value}");
    }

    /**
     * Create an invalid data failure.
     *
     * @param  array<string, array<string>>  $errors
     */
    public static function invalid(array $errors): static
    {
        return static::validationFailed($errors)->withMessage('Data tidak valid.');
    }
}
