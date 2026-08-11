<?php

declare(strict_types=1);

namespace App\Services\ElectricalPanel;

use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Manufacturing\BomItem;

class ComponentStandardService
{
    /**
     * Create a new component standard.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ComponentStandard
    {
        return ComponentStandard::create($data);
    }

    /**
     * Update a component standard.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ComponentStandard $standard, array $data): ComponentStandard
    {
        $standard->update($data);

        return $standard->fresh(['brandMappings.product']);
    }

    /**
     * Delete a component standard.
     *
     * Validates that no BOM items reference this standard before deletion.
     *
     * @throws \App\Exceptions\Domain\ValidationException
     */
    public function delete(ComponentStandard $standard): void
    {
        // Check if any BOM items reference this standard via panel meta
        $bomItemCount = BomItem::query()
            ->whereHas('panelMeta', fn ($q) => $q->where('component_standard_id', $standard->id))
            ->count();

        if ($bomItemCount > 0) {
            throw \App\Exceptions\Domain\ValidationException::invalid(
                'component_standard',
                "Tidak dapat dihapus: {$bomItemCount} item BOM menggunakan komponen ini."
            );
        }

        $standard->delete();
    }
}
