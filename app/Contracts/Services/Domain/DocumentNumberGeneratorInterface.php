<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domain;

interface DocumentNumberGeneratorInterface
{
    /**
     * Generate a unique document number.
     *
     * @param  string  $prefix  The number prefix (e.g., 'INV-202601-')
     * @param  string  $table  The database table name
     * @param  string  $column  The column to check for last number
     * @return string The generated number (e.g., 'INV-202601-0001')
     */
    public function generate(string $prefix, string $table, string $column): string;
}
