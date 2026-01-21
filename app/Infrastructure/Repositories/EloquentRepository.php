<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Repositories\SpecificationInterface;
use App\Exceptions\Domain\EntityNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Base Eloquent repository implementation.
 *
 * Provides common CRUD operations using Eloquent ORM.
 * Subclasses should define the model class and any default eager loads.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class EloquentRepository implements RepositoryInterface
{
    /** @var class-string<TModel> */
    protected string $modelClass;

    /** @var array<string> Relations to eager load by default */
    protected array $with = [];

    public function find(int $id): ?object
    {
        return $this->newQuery()->find($id);
    }

    public function findOrFail(int $id): object
    {
        $entity = $this->find($id);

        if ($entity === null) {
            throw new EntityNotFoundException($this->getEntityName(), $id);
        }

        return $entity;
    }

    public function all(): Collection
    {
        return $this->newQuery()->get();
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage);
    }

    public function create(array $data): object
    {
        $model = new $this->modelClass($data);
        $model->save();

        if (! empty($this->with)) {
            return $model->fresh($this->with);
        }

        return $model;
    }

    public function update(object|int $entity, array $data): object
    {
        $model = is_int($entity) ? $this->findOrFail($entity) : $entity;
        $model->update($data);

        if (! empty($this->with)) {
            return $model->fresh($this->with);
        }

        return $model;
    }

    public function delete(object|int $entity): bool
    {
        $model = is_int($entity) ? $this->findOrFail($entity) : $entity;

        return (bool) $model->delete();
    }

    public function findBy(array $criteria): Collection
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->get();
    }

    public function findOneBy(array $criteria): ?object
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->first();
    }

    public function count(array $criteria = []): int
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->count();
    }

    public function exists(array $criteria): bool
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->exists();
    }

    public function match(SpecificationInterface $specification): Collection
    {
        return $specification->apply($this->newQuery())->get();
    }

    /**
     * Create new query builder with default eager loads.
     *
     * @return Builder<TModel>
     */
    protected function newQuery(): Builder
    {
        $query = $this->modelClass::query();

        if (! empty($this->with)) {
            $query->with($this->with);
        }

        return $query;
    }

    /**
     * Get entity name for error messages.
     */
    protected function getEntityName(): string
    {
        return class_basename($this->modelClass);
    }
}
