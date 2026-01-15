<?php

declare(strict_types=1);

use App\Filters\QueryFilter;
use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasRelationFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

// Create a concrete test filter class
class TestProductFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasRelationFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    protected function getSearchableFields(): array
    {
        return ['name', 'sku', 'description'];
    }

    protected function getAllowedSortFields(): array
    {
        return ['id', 'name', 'created_at', 'price'];
    }

    public function type(string $value): void
    {
        $this->builder->where('type', $value);
    }

    public function minPrice(float $value): void
    {
        $this->builder->where('price', '>=', $value);
    }

    public function maxPrice(float $value): void
    {
        $this->builder->where('price', '<=', $value);
    }
}

describe('QueryFilter Base Class', function () {
    it('applies method-based filters from request parameters', function () {
        $request = Request::create('/', 'GET', [
            'status' => 'active',
            'type' => 'product',
        ]);

        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('status', 'active')
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('where')
            ->with('type', 'product')
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')
            ->with('created_at', 'desc')
            ->once()
            ->andReturnSelf();

        $result = $filter->apply($builder);

        expect($result)->toBe($builder);
    });

    it('ignores null and empty values', function () {
        $request = Request::create('/', 'GET', [
            'status' => '',
            'type' => null,
            'category_id' => 0, // 0 is valid
        ]);

        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        // Should NOT call where for empty/null values
        $builder->shouldReceive('where')
            ->with('category_id', 0)
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')
            ->andReturnSelf();

        $filter->apply($builder);
    });

    it('excludes pagination parameters from filtering', function () {
        $request = Request::create('/', 'GET', [
            'page' => 2,
            'per_page' => 15,
            'status' => 'active',
        ]);

        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        // Should only apply status filter, not page/per_page
        $builder->shouldReceive('where')
            ->with('status', 'active')
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')
            ->andReturnSelf();

        $filter->apply($builder);
    });

    it('applies custom sorting when provided', function () {
        $request = Request::create('/', 'GET', [
            'sort' => 'name',
            'direction' => 'asc',
        ]);

        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('orderBy')
            ->with('name', 'asc')
            ->once()
            ->andReturnSelf();

        $filter->apply($builder);
    });

    it('ignores invalid sort fields', function () {
        $request = Request::create('/', 'GET', [
            'sort' => 'invalid_field',
        ]);

        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        // Should not call orderBy for invalid field
        $builder->shouldNotReceive('orderBy')
            ->with('invalid_field', \Mockery::any());
        $builder->shouldReceive('orderBy')
            ->never();

        $filter->apply($builder);
    });
});

describe('HasStatusFilter Trait', function () {
    it('filters by single status', function () {
        $request = Request::create('/', 'GET', ['status' => 'draft']);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('status', 'draft')
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });

    it('filters by multiple statuses', function () {
        $request = Request::create('/', 'GET', ['statuses' => 'draft,approved,sent']);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('whereIn')
            ->with('status', ['draft', 'approved', 'sent'])
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });
});

describe('HasDateRangeFilter Trait', function () {
    it('filters by start date', function () {
        $request = Request::create('/', 'GET', ['start_date' => '2024-01-01']);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('created_at', '>=', '2024-01-01')
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });

    it('filters by end date', function () {
        $request = Request::create('/', 'GET', ['end_date' => '2024-12-31']);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('created_at', '<=', '2024-12-31 23:59:59')
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });

    it('filters by date range', function () {
        $request = Request::create('/', 'GET', ['date_range' => '2024-01-01,2024-12-31']);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('whereBetween')
            ->with('created_at', ['2024-01-01', '2024-12-31 23:59:59'])
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });
});

describe('HasSearchFilter Trait', function () {
    it('searches across multiple fields', function () {
        $request = Request::create('/', 'GET', ['search' => 'test product']);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with(\Mockery::type('Closure'))
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });
});

describe('HasRelationFilter Trait', function () {
    it('filters by contact_id', function () {
        $request = Request::create('/', 'GET', ['contact_id' => 5]);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('contact_id', 5)
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });

    it('filters by project_id', function () {
        $request = Request::create('/', 'GET', ['project_id' => 10]);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('project_id', 10)
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });
});

describe('Custom Filter Methods', function () {
    it('applies custom type filter', function () {
        $request = Request::create('/', 'GET', ['type' => 'service']);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('type', 'service')
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });

    it('applies price range filters', function () {
        $request = Request::create('/', 'GET', [
            'min_price' => 100,
            'max_price' => 500,
        ]);
        $filter = new TestProductFilter($request);

        $builder = mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('price', '>=', 100.0)
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('where')
            ->with('price', '<=', 500.0)
            ->once()
            ->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $filter->apply($builder);
    });
});
