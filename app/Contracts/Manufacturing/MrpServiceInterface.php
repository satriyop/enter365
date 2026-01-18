<?php

declare(strict_types=1);

namespace App\Contracts\Manufacturing;

use App\Models\Manufacturing\MrpRun;

/**
 * Interface for MRP (Material Requirements Planning) service operations.
 */
interface MrpServiceInterface
{
    /**
     * Create a new MRP run.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MrpRun;

    /**
     * Execute an MRP run to generate demands and suggestions.
     */
    public function execute(MrpRun $run, ?int $userId = null): MrpRun;

    /**
     * Update an MRP run.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MrpRun $run, array $data): MrpRun;

    /**
     * Delete an MRP run.
     */
    public function delete(MrpRun $run): bool;

    /**
     * Get MRP statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array;
}
