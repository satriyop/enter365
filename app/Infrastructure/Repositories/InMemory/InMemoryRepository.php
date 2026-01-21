<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\InMemory;

use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Repositories\SpecificationInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * In-memory repository implementation for testing.
 *
 * @template T of object
 *
 * @implements RepositoryInterface<T>
 */
abstract class InMemoryRepository implements RepositoryInterface
{
    /**
     * @var Collection<int, T>
     */
    protected Collection $items;

    protected int $nextId = 1;

    public function __construct()
    {
        $this->items = collect();
    }

    public function find(int $id): ?object
    {
        return $this->items->get($id);
    }

    public function findOrFail(int $id): object
    {
        $item = $this->find($id);

        if ($item === null) {
            throw new ModelNotFoundException("Item with ID {$id} not found.");
        }

        return $item;
    }

    public function findOneBy(array $criteria): ?object
    {
        return $this->items->first(function ($item) use ($criteria) {
            return $this->matchesCriteria($item, $criteria);
        });
    }

    public function findBy(array $criteria): Collection
    {
        return $this->items->filter(function ($item) use ($criteria) {
            return $this->matchesCriteria($item, $criteria);
        })->values();
    }

    public function all(): Collection
    {
        return $this->items->values();
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        $items = $this->items->values();
        $page = 1;

        return new Paginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page
        );
    }

    public function create(array $data): object
    {
        $id = $this->nextId++;
        $item = $this->createEntity($id, $data);
        $this->items->put($id, $item);

        return $item;
    }

    public function update(object|int $entity, array $data): object
    {
        $id = is_int($entity) ? $entity : $this->getEntityId($entity);
        $item = $this->findOrFail($id);
        $item = $this->updateEntity($item, $data);
        $this->items->put($id, $item);

        return $item;
    }

    public function delete(object|int $entity): bool
    {
        $id = is_int($entity) ? $entity : $this->getEntityId($entity);

        if (! $this->items->has($id)) {
            return false;
        }

        $this->items->forget($id);

        return true;
    }

    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            return $this->items->count();
        }

        return $this->findBy($criteria)->count();
    }

    public function exists(array $criteria): bool
    {
        return $this->findOneBy($criteria) !== null;
    }

    public function match(SpecificationInterface $specification): Collection
    {
        return $this->items->filter(function ($item) use ($specification) {
            return $specification->isSatisfiedBy($item);
        })->values();
    }

    /**
     * Clear all items from the repository.
     */
    public function clear(): void
    {
        $this->items = collect();
        $this->nextId = 1;
    }

    /**
     * Add an existing entity to the repository (useful for seeding tests).
     *
     * @param  T  $entity
     */
    public function add(object $entity): void
    {
        $id = $this->getEntityId($entity);
        $this->items->put($id, $entity);

        if ($id >= $this->nextId) {
            $this->nextId = $id + 1;
        }
    }

    /**
     * Check if an item matches the given criteria.
     *
     * @param  T  $item
     */
    protected function matchesCriteria(object $item, array $criteria): bool
    {
        foreach ($criteria as $field => $value) {
            $itemValue = $this->getFieldValue($item, $field);

            if ($itemValue !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a field value from an entity.
     *
     * @param  T  $entity
     */
    protected function getFieldValue(object $entity, string $field): mixed
    {
        if (method_exists($entity, 'getAttribute')) {
            return $entity->getAttribute($field);
        }

        if (property_exists($entity, $field)) {
            return $entity->{$field};
        }

        return null;
    }

    /**
     * Get the ID from an entity.
     *
     * @param  T  $entity
     */
    protected function getEntityId(object $entity): int
    {
        return (int) $this->getFieldValue($entity, 'id');
    }

    /**
     * Create an entity from data.
     *
     * @return T
     */
    abstract protected function createEntity(int $id, array $data): object;

    /**
     * Update an entity with new data.
     *
     * @param  T  $entity
     * @return T
     */
    abstract protected function updateEntity(object $entity, array $data): object;
}
