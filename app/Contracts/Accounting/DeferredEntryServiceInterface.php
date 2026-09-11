<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\DeferredEntry;

interface DeferredEntryServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $kind, array $data): DeferredEntry;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DeferredEntry $entry, array $data): DeferredEntry;

    public function delete(DeferredEntry $entry): void;

    public function confirm(DeferredEntry $entry): DeferredEntry;

    public function postNextRecognition(DeferredEntry $entry): DeferredEntry;
}
