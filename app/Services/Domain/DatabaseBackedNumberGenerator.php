<?php

declare(strict_types=1);

namespace App\Services\Domain;

use App\Contracts\Services\Domain\DocumentNumberGeneratorInterface;
use Illuminate\Support\Facades\DB;

class DatabaseBackedNumberGenerator implements DocumentNumberGeneratorInterface
{
    /**
     * Generate a unique document number by querying the database.
     */
    public function generate(string $prefix, string $table, string $column): string
    {
        $lastRecord = DB::table($table)
            ->where($column, 'like', $prefix.'%')
            ->orderBy($column, 'desc')
            ->first();

        if ($lastRecord) {
            $lastNumber = (int) substr($lastRecord->$column, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
