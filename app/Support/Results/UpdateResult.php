<?php

declare(strict_types=1);

namespace App\Support\Results;

/**
 * Result for update operations.
 *
 * @template T of \Illuminate\Database\Eloquent\Model
 *
 * @extends ServiceResult<T>
 */
class UpdateResult extends ServiceResult
{
    /**
     * Create a successful update result.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $entity
     */
    public static function updated(object $entity, ?string $message = null): static
    {
        return static::success($entity, $message ?? 'Data berhasil diperbarui.');
    }

    /**
     * Create a not modified result.
     */
    public static function notModified(object $entity): static
    {
        return static::success($entity, 'Tidak ada perubahan.');
    }

    /**
     * Create a locked failure (cannot update).
     */
    public static function locked(string $reason): static
    {
        return static::failure("Data tidak dapat diubah: {$reason}");
    }

    /**
     * Create a stale data failure (concurrent modification).
     */
    public static function staleData(): static
    {
        return static::failure('Data telah diubah oleh pengguna lain. Silakan muat ulang.');
    }
}
