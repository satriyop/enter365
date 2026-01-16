# Scaffold API Test Skill

Generate a comprehensive API test suite for an endpoint following enter365 patterns.

## Trigger

Use when user says:
- `/scaffold-api-test`
- "create tests for X API"
- "write API tests for Y"

## Required Information

Prompt the user for:
1. **Model name** - e.g., `Invoice`, `Warehouse`
2. **Domain** - Sales, Purchasing, Manufacturing, Inventory, Projects, Solar, Contacts, Shared
3. **Required seeders** - Does it need ChartOfAccountsSeeder or others?
4. **Filter tests** - What filters exist? (search, status, date range, foreign keys)
5. **Custom actions** - Any non-CRUD endpoints? (approve, submit, duplicate, etc.)
6. **Validation rules** - What fields are required/unique?

## File Generated

```
tests/Feature/Api/V1/{Model}ApiTest.php
```

---

## Template: Basic API Test

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

    // ─────────────────────────────────────────────────────────────
    // Index Tests
    // ─────────────────────────────────────────────────────────────

    it('can list all {models}', function () {
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

    // ─────────────────────────────────────────────────────────────
    // Filter Tests
    // ─────────────────────────────────────────────────────────────

    it('can search by name', function () {
        {Model}::factory()->create(['name' => 'Alpha Item']);
        {Model}::factory()->create(['name' => 'Beta Item']);
        {Model}::factory()->create(['name' => 'Alpha Widget']);

        $response = $this->getJson('/api/v1/{models}?search=alpha');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter by is_active', function () {
        {Model}::factory()->count(3)->create(['is_active' => true]);
        {Model}::factory()->count(2)->inactive()->create();

        $response = $this->getJson('/api/v1/{models}?is_active=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    // Add status filter test if model has status
    // it('can filter by status', function () {
    //     {Model}::factory()->count(3)->create(['status' => DocumentStatus::Draft]);
    //     {Model}::factory()->count(2)->create(['status' => DocumentStatus::Approved]);
    //
    //     $response = $this->getJson('/api/v1/{models}?status=draft');
    //
    //     $response->assertOk()
    //         ->assertJsonCount(3, 'data');
    // });

    // Add foreign key filter test if model has relationships
    // it('can filter by contact_id', function () {
    //     $contact = Contact::factory()->create();
    //     {Model}::factory()->count(3)->create(['contact_id' => $contact->id]);
    //     {Model}::factory()->count(2)->create();
    //
    //     $response = $this->getJson("/api/v1/{models}?contact_id={$contact->id}");
    //
    //     $response->assertOk()
    //         ->assertJsonCount(3, 'data');
    // });

    // Add date range filter test if model has date field
    // it('can filter by date range', function () {
    //     {Model}::factory()->create(['{model}_date' => now()->subDays(10)]);
    //     {Model}::factory()->create(['{model}_date' => now()]);
    //     {Model}::factory()->create(['{model}_date' => now()->addDays(10)]);
    //
    //     $startDate = now()->subDays(5)->format('Y-m-d');
    //     $endDate = now()->addDays(5)->format('Y-m-d');
    //
    //     $response = $this->getJson("/api/v1/{models}?start_date={$startDate}&end_date={$endDate}");
    //
    //     $response->assertOk()
    //         ->assertJsonCount(1, 'data');
    // });

    // ─────────────────────────────────────────────────────────────
    // Store Tests
    // ─────────────────────────────────────────────────────────────

    it('can create a {model}', function () {
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

    it('validates required fields when creating', function () {
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

    // Add foreign key validation if applicable
    // it('validates foreign key exists', function () {
    //     $response = $this->postJson('/api/v1/{models}', [
    //         'name' => 'Test',
    //         'contact_id' => 99999,
    //     ]);
    //
    //     $response->assertUnprocessable()
    //         ->assertJsonValidationErrors(['contact_id']);
    // });

    // ─────────────────────────────────────────────────────────────
    // Show Tests
    // ─────────────────────────────────────────────────────────────

    it('can show a single {model}', function () {
        ${model} = {Model}::factory()->create();

        $response = $this->getJson("/api/v1/{models}/{${model}->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', ${model}->id)
            ->assertJsonPath('data.name', ${model}->name);
    });

    it('returns 404 for non-existent {model}', function () {
        $response = $this->getJson('/api/v1/{models}/99999');

        $response->assertNotFound();
    });

    // Add relationship loading test if applicable
    // it('includes relationships when loaded', function () {
    //     $contact = Contact::factory()->create();
    //     ${model} = {Model}::factory()->create(['contact_id' => $contact->id]);
    //
    //     $response = $this->getJson("/api/v1/{models}/{${model}->id}");
    //
    //     $response->assertOk()
    //         ->assertJsonPath('data.contact.id', $contact->id);
    // });

    // ─────────────────────────────────────────────────────────────
    // Update Tests
    // ─────────────────────────────────────────────────────────────

    it('can update a {model}', function () {
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

    it('allows same unique value on own record', function () {
        ${model} = {Model}::factory()->create(['code' => 'CODE-001']);

        $response = $this->putJson("/api/v1/{models}/{${model}->id}", [
            'code' => 'CODE-001',
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    });

    // Add status restriction test for document models
    // it('cannot update non-draft {model}', function () {
    //     ${model} = {Model}::factory()->create(['status' => DocumentStatus::Approved]);
    //
    //     $response = $this->putJson("/api/v1/{models}/{${model}->id}", [
    //         'name' => 'Updated Name',
    //     ]);
    //
    //     $response->assertUnprocessable();
    // });

    // ─────────────────────────────────────────────────────────────
    // Destroy Tests
    // ─────────────────────────────────────────────────────────────

    it('can delete a {model}', function () {
        ${model} = {Model}::factory()->create();

        $response = $this->deleteJson("/api/v1/{models}/{${model}->id}");

        $response->assertNoContent();

        // For soft deletes:
        $this->assertSoftDeleted('{table}', ['id' => ${model}->id]);

        // For hard deletes:
        // $this->assertDatabaseMissing('{table}', ['id' => ${model}->id]);
    });

    // Add deletion restriction tests if applicable
    // it('cannot delete {model} with related data', function () {
    //     ${model} = {Model}::factory()->create();
    //     RelatedModel::factory()->create(['{model}_id' => ${model}->id]);
    //
    //     $response = $this->deleteJson("/api/v1/{models}/{${model}->id}");
    //
    //     $response->assertUnprocessable()
    //         ->assertJsonPath('message', 'Cannot delete {model} with related records.');
    // });

    // Add status restriction test for document models
    // it('cannot delete approved {model}', function () {
    //     ${model} = {Model}::factory()->create(['status' => DocumentStatus::Approved]);
    //
    //     $response = $this->deleteJson("/api/v1/{models}/{${model}->id}");
    //
    //     $response->assertUnprocessable();
    // });

});
```

---

## Template: Document Model Tests (Invoice, Quotation, etc.)

For models with workflow states (Draft → Submitted → Approved):

```php
<?php

use App\Enums\DocumentStatus;
use App\Models\{Domain}\{Model};
use App\Models\Contacts\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
});

describe('{Model} API', function () {

    // ... basic CRUD tests ...

    describe('workflow actions', function () {

        it('can submit a draft {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Draft]);

            $response = $this->postJson("/api/v1/{models}/{${model}->id}/submit");

            $response->assertOk()
                ->assertJsonPath('data.status', 'submitted');

            $this->assertDatabaseHas('{table}', [
                'id' => ${model}->id,
                'status' => DocumentStatus::Submitted->value,
            ]);
        });

        it('cannot submit a non-draft {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Approved]);

            $response = $this->postJson("/api/v1/{models}/{${model}->id}/submit");

            $response->assertUnprocessable();
        });

        it('can approve a submitted {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Submitted]);

            $response = $this->postJson("/api/v1/{models}/{${model}->id}/approve");

            $response->assertOk()
                ->assertJsonPath('data.status', 'approved');
        });

        it('cannot approve a draft {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Draft]);

            $response = $this->postJson("/api/v1/{models}/{${model}->id}/approve");

            $response->assertUnprocessable();
        });

        it('can cancel a {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Draft]);

            $response = $this->postJson("/api/v1/{models}/{${model}->id}/cancel", [
                'reason' => 'Customer request',
            ]);

            $response->assertOk()
                ->assertJsonPath('data.status', 'cancelled');
        });

    });

});
```

---

## Common Test Patterns

### Testing Search by Multiple Fields

```php
it('can search by {model}_number', function () {
    {Model}::factory()->create(['{model}_number' => '{MODEL}/2024/0001']);
    {Model}::factory()->create(['{model}_number' => '{MODEL}/2024/0002']);

    $response = $this->getJson('/api/v1/{models}?search=0001');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});
```

### Testing Factory States

```php
it('can filter by factory state', function () {
    {Model}::factory()->count(3)->draft()->create();
    {Model}::factory()->count(2)->approved()->create();

    $response = $this->getJson('/api/v1/{models}?status=draft');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});
```

### Testing Computed Fields

```php
it('returns computed fields', function () {
    ${model} = {Model}::factory()->create([
        'subtotal' => 100000,
        'tax_rate' => 11.00,
    ]);

    $response = $this->getJson("/api/v1/{models}/{${model}->id}");

    $response->assertOk()
        ->assertJsonPath('data.tax_amount', 11000)
        ->assertJsonPath('data.total', 111000);
});
```

### Testing Response Structure

```php
it('returns correct structure', function () {
    ${model} = {Model}::factory()->create();

    $response = $this->getJson("/api/v1/{models}/{${model}->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'code',
                // Add expected fields
                'created_at',
                'updated_at',
            ],
        ]);
});
```

### Testing Custom Actions

```php
it('can duplicate a {model}', function () {
    ${model} = {Model}::factory()->create(['name' => 'Original']);

    $response = $this->postJson("/api/v1/{models}/{${model}->id}/duplicate");

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Original (Copy)');

    expect({Model}::count())->toBe(2);
});
```

---

## Assertion Reference

| Method | Use Case |
|--------|----------|
| `assertOk()` | 200 response (GET, PUT) |
| `assertCreated()` | 201 response (POST store) |
| `assertNoContent()` | 204 response (DELETE) |
| `assertNotFound()` | 404 response |
| `assertUnprocessable()` | 422 response (validation) |
| `assertForbidden()` | 403 response (authorization) |
| `assertJsonCount(n, 'data')` | Count items in data array |
| `assertJsonPath('data.name', $value)` | Check specific value |
| `assertJsonValidationErrors(['field'])` | Check validation errors |
| `assertJsonStructure([...])` | Check response structure |
| `assertDatabaseHas('table', [...])` | Record exists |
| `assertDatabaseMissing('table', [...])` | Record doesn't exist |
| `assertSoftDeleted('table', [...])` | Record soft deleted |

---

## Factory States Reference

Common factory states to use in tests:

| State | Purpose | Example |
|-------|---------|---------|
| `->inactive()` | is_active = false | Filter tests |
| `->draft()` | status = Draft | Workflow tests |
| `->submitted()` | status = Submitted | Workflow tests |
| `->approved()` | status = Approved | Restriction tests |
| `->cancelled()` | status = Cancelled | Exclusion tests |
| `->default()` | is_default = true | Default behavior |
| `->lowStock()` | below min_stock | Inventory tests |

---

## Execution Checklist

When scaffolding API tests:

1. [ ] Confirm model and factory exist
2. [ ] Check factory states available
3. [ ] Determine required seeders
4. [ ] Create test file at `tests/Feature/Api/V1/{Model}ApiTest.php`
5. [ ] Add index tests (list, pagination)
6. [ ] Add filter tests (search, status, relations, date)
7. [ ] Add store tests (valid data, validation errors, unique)
8. [ ] Add show tests (found, not found, relationships)
9. [ ] Add update tests (valid data, validation, restrictions)
10. [ ] Add destroy tests (success, restrictions)
11. [ ] Add workflow tests if applicable (submit, approve, cancel)
12. [ ] Add custom action tests if applicable
13. [ ] Run `php artisan test --filter={Model}Api` to verify
14. [ ] Run `vendor/bin/pint` to format

---

## Example Usage

**User:** `/scaffold-api-test`

**Claude:** I'll create an API test suite. Please provide:
1. Model name?
2. Domain? (Sales, Purchasing, Manufacturing, etc.)
3. Required seeders? (ChartOfAccountsSeeder, etc.)
4. What filters exist? (search fields, status, date range, foreign keys)
5. Custom actions? (submit, approve, duplicate, etc.)
6. Key validation rules? (required fields, unique fields)

**User:**
- Model: Warehouse
- Domain: Inventory
- Seeders: none
- Filters: search (name, code), is_active
- Custom actions: set-default
- Validation: name required, code unique

**Claude:** Creating WarehouseApiTest with:
- CRUD tests (index, store, show, update, destroy)
- Filter tests (search, is_active)
- Validation tests (name required, code unique)
- Custom action test (set-default)
