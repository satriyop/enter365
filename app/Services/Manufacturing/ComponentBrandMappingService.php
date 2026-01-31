<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ComponentBrandMapping;
use App\Models\Manufacturing\ComponentStandard;

class ComponentBrandMappingService
{
    /**
     * Create a new component brand mapping.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(ComponentStandard $standard, array $data): ComponentBrandMapping
    {
        $data['component_standard_id'] = $standard->id;

        $mapping = ComponentBrandMapping::create($data);

        // Set as preferred if requested
        if (isset($data['is_preferred']) && $data['is_preferred']) {
            $mapping->setAsPreferred();
        }

        return $mapping->load('product');
    }

    /**
     * Update a component brand mapping.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ComponentBrandMapping $mapping, array $data): ComponentBrandMapping
    {
        $mapping->update($data);

        if (isset($data['is_preferred']) && $data['is_preferred']) {
            $mapping->setAsPreferred();
        }

        return $mapping->fresh('product');
    }

    /**
     * Delete a component brand mapping.
     */
    public function delete(ComponentBrandMapping $mapping): void
    {
        $mapping->delete();
    }

    /**
     * Verify a brand mapping.
     */
    public function verify(ComponentBrandMapping $mapping, int $userId): ComponentBrandMapping
    {
        $mapping->verify($userId);

        return $mapping->fresh('product');
    }

    /**
     * Set mapping as preferred.
     */
    public function setAsPreferred(ComponentBrandMapping $mapping): ComponentBrandMapping
    {
        $mapping->setAsPreferred();

        return $mapping->fresh('product');
    }
}
