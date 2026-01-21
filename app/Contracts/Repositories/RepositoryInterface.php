<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Base repository interface for all repositories.
 *
 * Provides a consistent API for data access across the application,
 * enabling testability and decoupling from specific data sources.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
interface RepositoryInterface
{
    /**
     * Find entity by ID.
     *
     * @return TModel|null
     */
    public function find(int $id): ?object;

    /**
     * Find entity by ID or throw exception.
     *
     * @return TModel
     *
     * @throws \App\Exceptions\Domain\EntityNotFoundException
     */
    public function findOrFail(int $id): object;

    /**
     * Get all entities.
     *
     * @return Collection<int, TModel>
     */
    public function all(): Collection;

    /**
     * Get paginated entities.
     *
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator;

    /**
     * Create new entity.
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): object;

    /**
     * Update entity.
     *
     * @param  TModel|int  $entity
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(object|int $entity, array $data): object;

    /**
     * Delete entity.
     *
     * @param  TModel|int  $entity
     */
    public function delete(object|int $entity): bool;

    /**
     * Find entities matching criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return Collection<int, TModel>
     */
    public function findBy(array $criteria): Collection;

    /**
     * Find single entity matching criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return TModel|null
     */
    public function findOneBy(array $criteria): ?object;

    /**
     * Count entities matching criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function count(array $criteria = []): int;

    /**
     * Check if entity exists.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function exists(array $criteria): bool;

    /**
     * Apply specification to query.
     *
     * @return Collection<int, TModel>
     */
    public function match(SpecificationInterface $specification): Collection;
}
