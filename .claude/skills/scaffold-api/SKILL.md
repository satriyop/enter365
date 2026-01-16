# Scaffold API Skill

Generate a complete API endpoint stack following enter365 patterns.

## Trigger

Use when user says:
- `/scaffold-api`
- "create API for X"
- "scaffold API endpoint for Y"

## Required Information

Prompt the user for:
1. **Model name** - e.g., `ShippingMethod`, `PaymentTerm`
2. **Domain** - Sales, Purchasing, Manufacturing, Inventory, Projects, Solar, Accounting, Contacts, Shared
3. **Key fields** - What are the main fields? (for validation rules and resource)
4. **Relationships** - Any belongsTo relationships to load?
5. **Filter needs** - Search fields? Status filter? Date range? Foreign keys?

## Files Generated

```
app/Http/Controllers/Api/V1/{Model}Controller.php
app/Http/Requests/Api/V1/Store{Model}Request.php
app/Http/Requests/Api/V1/Update{Model}Request.php
app/Http/Resources/Api/V1/{Model}Resource.php
app/Filters/{Model}Filter.php
tests/Feature/Api/V1/{Model}ApiTest.php
+ Route registration guidance
```

---

## Template: Controller

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\Domains\{Model}ServiceInterface;
use App\Filters\{Model}Filter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Store{Model}Request;
use App\Http\Requests\Api\V1\Update{Model}Request;
use App\Http\Resources\Api\V1\{Model}Resource;
use App\Models\{Domain}\{Model};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class {Model}Controller extends Controller
{
    public function __construct(
        private {Model}ServiceInterface ${model}Service
    ) {}

    /**
     * Display a listing of {models}.
     */
    public function index({Model}Filter $filter): AnonymousResourceCollection
    {
        ${models} = {Model}::query()
            ->with([/* relationships */])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return {Model}Resource::collection(${models});
    }

    /**
     * Store a newly created {model}.
     */
    public function store(Store{Model}Request $request): JsonResponse
    {
        ${model} = $this->{model}Service->create($request->validated());

        return (new {Model}Resource(${model}))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified {model}.
     */
    public function show({Model} ${model}): {Model}Resource
    {
        return new {Model}Resource(
            ${model}->load([/* relationships */])
        );
    }

    /**
     * Update the specified {model}.
     */
    public function update(Update{Model}Request $request, {Model} ${model}): {Model}Resource
    {
        ${model} = $this->{model}Service->update(${model}, $request->validated());

        return new {Model}Resource(${model});
    }

    /**
     * Remove the specified {model}.
     */
    public function destroy({Model} ${model}): JsonResponse
    {
        $this->{model}Service->delete(${model});

        return response()->json(null, 204);
    }
}
```

### Controller Without Service (Simple CRUD)

If no service exists, use direct Eloquent:

```php
public function store(Store{Model}Request $request): JsonResponse
{
    ${model} = {Model}::create($request->validated());

    return (new {Model}Resource(${model}))
        ->response()
        ->setStatusCode(201);
}

public function update(Update{Model}Request $request, {Model} ${model}): {Model}Resource
{
    ${model}->update($request->validated());

    return new {Model}Resource(${model}->fresh());
}

public function destroy({Model} ${model}): JsonResponse
{
    ${model}->delete();

    return response()->json(null, 204);
}
```

---

## Template: StoreRequest

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Store{Model}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Required fields
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:50', 'unique:{table},code'],

            // Optional fields
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],

            // Foreign keys
            // 'contact_id' => ['required', 'exists:contacts,id'],
            // 'warehouse_id' => ['nullable', 'exists:warehouses,id'],

            // Numeric fields
            // 'amount' => ['required', 'integer', 'min:0'],
            // 'percentage' => ['numeric', 'min:0', 'max:100'],

            // Date fields
            // '{model}_date' => ['required', 'date'],
            // 'due_date' => ['nullable', 'date', 'after_or_equal:{model}_date'],

            // Enum fields
            // 'type' => ['required', Rule::in(['type_a', 'type_b'])],
            // 'status' => ['sometimes', Rule::enum(DocumentStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'code.unique' => 'Kode sudah digunakan.',
            // Add Indonesian messages for key validations
        ];
    }
}
```

---

## Template: UpdateRequest

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Update{Model}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Use 'sometimes' for optional updates
            'name' => ['sometimes', 'string', 'max:200'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('{table}', 'code')->ignore($this->route('{model}')),
            ],

            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],

            // Foreign keys (sometimes)
            // 'contact_id' => ['sometimes', 'exists:contacts,id'],

            // Other fields follow same pattern as Store but with 'sometimes'
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode sudah digunakan.',
        ];
    }
}
```

---

## Template: Resource

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\{Domain}\{Model}
 */
class {Model}Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Core fields
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            // Status/flags
            'is_active' => $this->is_active,

            // Numeric fields (cast if needed)
            // 'amount' => $this->amount,
            // 'percentage' => (float) $this->percentage,

            // Foreign keys + relationships
            // 'contact_id' => $this->contact_id,
            // 'contact' => new ContactResource($this->whenLoaded('contact')),

            // Computed properties (if model has accessors)
            // 'display_name' => $this->display_name,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

---

## Template: Filter

```php
<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;
// use App\Filters\Traits\HasDateRangeFilter;
// use App\Filters\Traits\HasRelationFilter;

/**
 * Filter for {Model} queries.
 *
 * Supported filters:
 * - search: Search by {searchable fields}
 * - is_active: Active status
 * - {custom filters}
 */
class {Model}Filter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;
    // use HasDateRangeFilter;
    // use HasRelationFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'code', 'description'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'name';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'name',
            'code',
            'created_at',
            'updated_at',
        ];
    }

    // Add custom filter methods as needed:

    // /**
    //  * Filter by type.
    //  */
    // public function type(string $value): void
    // {
    //     $this->builder->where('type', $value);
    // }

    // /**
    //  * Filter by foreign key.
    //  */
    // public function contactId(int|string $value): void
    // {
    //     $this->builder->where('contact_id', $value);
    // }

    // /**
    //  * Filter by boolean flag.
    //  */
    // public function isActive(bool|string $value): void
    // {
    //     $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    // }
}
```

### Filter Trait Reference

| Trait | Provides | Use When |
|-------|----------|----------|
| `HasSearchFilter` | `search()` method | Model has searchable text fields |
| `HasStatusFilter` | `status()`, `isActive()` methods | Model has status/active flag |
| `HasDateRangeFilter` | `startDate()`, `endDate()` methods | Model has date field |
| `HasRelationFilter` | `contactId()`, etc. | Model has foreign keys |

---

## Template: API Test

```php
<?php

use App\Models\{Domain}\{Model};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Add seeders if needed
    // $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
});

describe('{Model} API', function () {

    describe('index', function () {
        it('lists all {models}', function () {
            {Model}::factory()->count(5)->create();

            $response = $this->getJson('/api/v1/{models}');

            $response->assertOk()
                ->assertJsonCount(5, 'data');
        });

        it('paginates results', function () {
            {Model}::factory()->count(30)->create();

            $response = $this->getJson('/api/v1/{models}?per_page=10');

            $response->assertOk()
                ->assertJsonCount(10, 'data')
                ->assertJsonPath('meta.total', 30);
        });

        it('searches by name', function () {
            {Model}::factory()->create(['name' => 'Alpha Item']);
            {Model}::factory()->create(['name' => 'Beta Item']);
            {Model}::factory()->create(['name' => 'Alpha Widget']);

            $response = $this->getJson('/api/v1/{models}?search=alpha');

            $response->assertOk()
                ->assertJsonCount(2, 'data');
        });

        it('filters by is_active', function () {
            {Model}::factory()->count(3)->create(['is_active' => true]);
            {Model}::factory()->count(2)->create(['is_active' => false]);

            $response = $this->getJson('/api/v1/{models}?is_active=true');

            $response->assertOk()
                ->assertJsonCount(3, 'data');
        });

        // Add more filter tests based on your filter methods
    });

    describe('store', function () {
        it('creates a {model}', function () {
            $response = $this->postJson('/api/v1/{models}', [
                'name' => 'Test {Model}',
                // Add required fields
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.name', 'Test {Model}');

            $this->assertDatabaseHas('{table}', [
                'name' => 'Test {Model}',
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/{models}', []);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['name']);
        });

        it('validates unique fields', function () {
            {Model}::factory()->create(['code' => 'UNIQUE-001']);

            $response = $this->postJson('/api/v1/{models}', [
                'name' => 'Test',
                'code' => 'UNIQUE-001',
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['code']);
        });
    });

    describe('show', function () {
        it('shows a single {model}', function () {
            ${model} = {Model}::factory()->create();

            $response = $this->getJson("/api/v1/{models}/{${model}->id}");

            $response->assertOk()
                ->assertJsonPath('data.id', ${model}->id);
        });

        it('returns 404 for non-existent {model}', function () {
            $response = $this->getJson('/api/v1/{models}/99999');

            $response->assertNotFound();
        });
    });

    describe('update', function () {
        it('updates a {model}', function () {
            ${model} = {Model}::factory()->create();

            $response = $this->putJson("/api/v1/{models}/{${model}->id}", [
                'name' => 'Updated Name',
            ]);

            $response->assertOk()
                ->assertJsonPath('data.name', 'Updated Name');
        });

        it('validates unique fields on update', function () {
            ${model}1 = {Model}::factory()->create(['code' => 'CODE-001']);
            ${model}2 = {Model}::factory()->create(['code' => 'CODE-002']);

            $response = $this->putJson("/api/v1/{models}/{${model}2->id}", [
                'code' => 'CODE-001',
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['code']);
        });
    });

    describe('destroy', function () {
        it('deletes a {model}', function () {
            ${model} = {Model}::factory()->create();

            $response = $this->deleteJson("/api/v1/{models}/{${model}->id}");

            $response->assertNoContent();

            // For soft deletes:
            $this->assertSoftDeleted('{table}', ['id' => ${model}->id]);

            // For hard deletes:
            // $this->assertDatabaseMissing('{table}', ['id' => ${model}->id]);
        });
    });

});
```

---

## Route Registration

Add to `routes/api.php`:

```php
Route::middleware('auth:sanctum')->group(function () {
    // Basic CRUD
    Route::apiResource('{models}', {Model}Controller::class);

    // Custom actions (if needed)
    // Route::post('{models}/{model}/approve', [{Model}Controller::class, 'approve']);
    // Route::get('{models}/{model}/export', [{Model}Controller::class, 'export']);
});
```

---

## Execution Checklist

When scaffolding a new API endpoint:

1. [ ] Confirm model exists with factory in correct domain folder
2. [ ] Create `Store{Model}Request.php`
3. [ ] Create `Update{Model}Request.php`
4. [ ] Create `{Model}Resource.php`
5. [ ] Create `{Model}Filter.php`
6. [ ] Create `{Model}Controller.php`
7. [ ] Add routes to `routes/api.php`
8. [ ] Create `{Model}ApiTest.php`
9. [ ] Run `vendor/bin/pint` to format
10. [ ] Run `php artisan test --filter={Model}Api` to verify
11. [ ] Run `php artisan scramble:export --path=api.json` to update OpenAPI

---

## Domain Reference

| Domain | Model Location | Typical Models |
|--------|----------------|----------------|
| Sales | `App\Models\Sales\` | Invoice, Quotation, DeliveryOrder |
| Purchasing | `App\Models\Purchasing\` | Bill, PurchaseOrder, GoodsReceiptNote |
| Manufacturing | `App\Models\Manufacturing\` | Bom, WorkOrder, MaterialRequisition |
| Inventory | `App\Models\Inventory\` | Product, Warehouse, StockOpname |
| Projects | `App\Models\Projects\` | Project, ProjectCost |
| Solar | `App\Models\Solar\` | SolarProposal |
| Contacts | `App\Models\Contacts\` | Contact |
| Accounting | `App\Models\Accounting\` | Account, FiscalPeriod |
| Shared | `App\Models\Shared\` | Payment, Attachment |

---

## Example Usage

**User:** `/scaffold-api`

**Claude:** I'll create a complete API endpoint. Please provide:
1. Model name?
2. Domain? (Sales, Purchasing, Manufacturing, etc.)
3. Key fields for this model?
4. Any relationships to eager-load?
5. What filters are needed? (search fields, status, date range, foreign keys)

**User:**
- Model: PaymentTerm
- Domain: Shared
- Fields: name, code, days, is_default, is_active
- Relationships: none
- Filters: search (name, code), is_active

**Claude:** Creating PaymentTerm API with:
- Controller with service injection
- Store/Update requests with validation
- Resource for JSON output
- Filter with search and status traits
- Complete test suite
- Route registration
