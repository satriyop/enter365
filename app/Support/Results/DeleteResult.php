<?php

declare(strict_types=1);

namespace App\Support\Results;

/**
 * Result for delete operations.
 *
 * @extends ServiceResult<null>
 */
class DeleteResult extends ServiceResult
{
    /**
     * Create a successful deletion result.
     */
    public static function deleted(?string $message = null): static
    {
        return static::success(null, $message ?? 'Data berhasil dihapus.');
    }

    /**
     * Create a restricted deletion failure (has dependencies).
     *
     * @param  array<string>  $dependencies
     */
    public static function restricted(array $dependencies): static
    {
        $depList = implode(', ', $dependencies);

        return static::failure("Tidak dapat menghapus karena masih memiliki: {$depList}");
    }

    /**
     * Create a locked failure (cannot delete).
     */
    public static function locked(string $reason): static
    {
        return static::failure("Data tidak dapat dihapus: {$reason}");
    }
}
