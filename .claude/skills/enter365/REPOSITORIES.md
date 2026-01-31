# Data Access Patterns Reference

Data access patterns in Enter365. **This application does NOT use a repository layer.** Services access data directly via Eloquent models.

---

## Architecture: Service-Based (No Repositories)

Enter365 uses a **service-based architecture** where services interact directly with Eloquent models. There is no `app/Contracts/Repositories/` directory and no repository interfaces.

### Why No Repository Layer

| Concern | How It's Handled |
|---------|-----------------|
| Testability | Feature tests with `RefreshDatabase`, factory states |
| Query encapsulation | Model scopes + Filter classes |
| Aggregations | `DB::table()` in report/dashboard services |
| Decoupling | Service interfaces (`app/Contracts/`) abstract business logic |

### The Actual Pattern

```
Controller → Service (via Interface) → Eloquent Model
                                    → DB::table() (for aggregations)
```

```php
// Service injects Eloquent models directly
class InvoiceService extends BaseService implements InvoiceServiceInterface
{
    public function findByNumber(string $number): ?Invoice
    {
        return Invoice::where('invoice_number', $number)->first();
    }

    public function getOverdueInvoices(): Collection
    {
        return Invoice::where('status', DocumentStatus::Sent)
            ->where('due_date', '<', now()->toDateString())
            ->get();
    }
}
```

---

## Query Patterns

### Model Scopes for Reusable Queries

```php
// In Model
class Quotation extends Model
{
    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DocumentStatus::Draft,
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
        ]);
    }
}

// In Service
$quotations = Quotation::needsFollowUp()->orderBy('next_follow_up_at')->get();
```

### Filter Classes for API Filtering

Located in `app/Filters/`. Applied via `HasFilters` trait on models.

```php
// Controller
$query = Invoice::query()->filter($request->all());
```

### DB::table() for Aggregations

**Use `DB::table()` for read-only aggregation queries** to avoid Eloquent hydration overhead:

```php
// In dashboard/report services
$stats = DB::table('quotations')
    ->select('status')
    ->selectRaw('COUNT(*) as count')
    ->selectRaw('COALESCE(SUM(total), 0) as total_value')
    ->groupBy('status')
    ->get();
```

### When to Use Each

| Use Case | Method | Why |
|----------|--------|-----|
| CRUD operations | Eloquent Model | Need events, casts, mutators |
| Aggregations (SUM, COUNT, AVG) | `DB::table()` | No model needed for numbers |
| Dashboard statistics | `DB::table()` | Performance critical |
| Reports with 100+ rows | `DB::table()` | Avoids memory overhead |
| Simple existence checks | Either | Low impact |

---

## Domain Query Method Naming

Use descriptive names that express business intent in services:

| Bad | Good | Why |
|-----|------|-----|
| `getByStatus('submitted')` | `findPendingApproval()` | Expresses business concept |
| `getWhereFollowUpPast()` | `findNeedingFollowUp()` | Action-oriented |
| `getAllActive()` | `findActive()` | Consistent `find` prefix |
| `getStats()` | `getWinRateStats()` | Specific about what stats |

---

## Testing Data Access

### Feature Tests with RefreshDatabase

```php
uses(RefreshDatabase::class);

it('finds overdue invoices', function () {
    Invoice::factory()->create([
        'status' => DocumentStatus::Sent,
        'due_date' => now()->subDays(5),
    ]);
    Invoice::factory()->create([
        'status' => DocumentStatus::Sent,
        'due_date' => now()->addDays(5),
    ]);

    $overdue = $service->getOverdueInvoices();

    expect($overdue)->toHaveCount(1);
});
```

### Testing with RecordingEventDispatcher

Services use `EventDispatcherInterface` which can be swapped for testing:

```php
$dispatcher = new RecordingEventDispatcher();
$service = new InvoiceService($dispatcher, $logger, ...);

$service->send($invoice);

$dispatcher->assertDispatched(InvoiceSent::class);
```
