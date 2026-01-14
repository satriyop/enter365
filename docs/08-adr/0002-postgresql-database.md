---
adr: "0002"
title: "PostgreSQL as Primary Database"
status: accepted
date: 2024-11-01
deciders: [Architecture Team]
tags: [database, infrastructure, technology]
related_adrs: [0001, 0008, 0011]
related_modules: [all]
impact: high
---

# ADR-0002: PostgreSQL as Primary Database

## AI Agent Quick Reference

**Use this ADR when:**
- Understanding database decisions
- Working with complex queries or data types
- Debugging database-related issues
- Considering database alternatives

**Key takeaway:** PostgreSQL is chosen for its ACID compliance, advanced data types, and robust constraint system essential for accounting accuracy.

---

## Context

Enter365 is an accounting system handling financial data that requires:
- Absolute data integrity (transactions must never be lost or corrupted)
- Complex queries for financial reports
- Strict enforcement of business rules at database level
- Support for 70+ tables with complex relationships

### Forces

- **Financial Data Sensitivity** - Incorrect data = financial loss
- **Complex Relationships** - 70+ models with intricate FK relationships
- **Report Performance** - Complex aggregations for financial statements
- **Audit Requirements** - SAK EMKM compliance requires data integrity

---

## Decision Drivers

1. **Data Integrity** - ACID compliance is non-negotiable for accounting
2. **Referential Integrity** - Proper FK enforcement prevents orphaned records
3. **Complex Queries** - CTEs, window functions for financial reports
4. **JSON Support** - Store flexible metadata without schema changes
5. **Indonesian Context** - Widely supported by Indonesian cloud providers

---

## Considered Options

### Option 1: PostgreSQL (Chosen)

**Description:** Open-source relational database with advanced features

**Pros:**
- Full ACID compliance
- Excellent JSON/JSONB support
- Advanced data types (arrays, ranges, intervals)
- Superior constraint system
- CTEs and window functions for reports
- Strong ecosystem and community
- Free and open source

**Cons:**
- Slightly more complex setup than MySQL
- Less hosting provider options than MySQL

### Option 2: MySQL / MariaDB

**Description:** Popular open-source relational database

**Pros:**
- Very popular, easy to find hosting
- Simple setup
- Good Laravel support

**Cons:**
- Weaker FK enforcement historically
- Less advanced data types
- CTEs only in recent versions

### Option 3: SQLite

**Description:** Embedded database

**Pros:**
- Zero configuration
- Great for testing
- No separate server needed

**Cons:**
- Not suitable for production multi-user
- Limited concurrent writes
- No network access

---

## Decision

**Chosen option:** "PostgreSQL"

PostgreSQL provides the data integrity and advanced features required for a production accounting system.

---

## Rationale

### Why PostgreSQL for Accounting:

1. **ACID Guarantees**
   - Atomicity: Journal entries post completely or not at all
   - Consistency: Constraints prevent invalid data
   - Isolation: Concurrent users don't corrupt each other's work
   - Durability: Committed transactions survive crashes

2. **Constraint System**
   - CHECK constraints for positive amounts
   - UNIQUE constraints for document numbers
   - Foreign keys with proper ON DELETE behavior
   - Exclusion constraints for non-overlapping periods

3. **Advanced Features Used**
   - JSONB for flexible metadata on documents
   - CTEs for complex financial calculations
   - Window functions for running balances
   - Interval types for date calculations

4. **Performance**
   - Excellent query optimizer
   - Proper index usage for large tables
   - EXPLAIN ANALYZE for query tuning

---

## Consequences

### Positive

- Rock-solid data integrity
- No orphaned records from cascade issues
- Complex reports possible with CTEs
- JSONB flexibility where needed
- Strong audit trail support

### Negative

- Fewer cheap hosting options than MySQL
- Slightly higher learning curve
- Some Laravel features MySQL-specific (like `upsert` nuances)

### Neutral

- Need to use PostgreSQL-specific syntax occasionally
- Testing uses SQLite with some adaptations

---

## Implementation Notes

**Configuration:**

```php
// File: /config/database.php

'default' => env('DB_CONNECTION', 'pgsql'),

'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'enter365'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
],
```

**Migration Patterns:**

```php
// File: /database/migrations/example.php

// PostgreSQL-friendly patterns used in Enter365:

// 1. BigInteger for monetary amounts (see ADR-0008)
$table->bigInteger('amount')->default(0);

// 2. Proper FK with explicit actions
$table->foreignId('contact_id')
    ->constrained()
    ->restrictOnDelete();

// 3. JSONB for flexible data
$table->jsonb('metadata')->nullable();

// 4. Composite unique constraints
$table->unique(['quotation_number', 'revision']);

// 5. Indexes for query performance
$table->index(['contact_id', 'status']);
$table->index('created_at');
```

**PostgreSQL-Specific Features:**

```php
// CTE for running balance calculation
DB::statement('
    WITH running_balance AS (
        SELECT
            id,
            amount,
            SUM(amount) OVER (ORDER BY entry_date, id) as balance
        FROM journal_entry_lines
        WHERE account_id = ?
    )
    SELECT * FROM running_balance
');

// JSONB queries
Product::whereJsonContains('metadata->brands', 'ABB')->get();
```

**Key Patterns:**

| Pattern | Usage |
|---------|-------|
| `bigInteger` | All monetary amounts |
| `foreignId()->constrained()` | All relationships |
| `softDeletes()` | Audit-required tables |
| `timestamps()` | All tables |
| `jsonb` | Flexible metadata |

---

## Validation

**Verification Steps:**

1. Check `DB_CONNECTION=pgsql` in `.env`
2. Run `php artisan db:show` to confirm PostgreSQL
3. Verify FK constraints with `\d+ table_name` in psql
4. Check migrations use `bigInteger` for amounts

**Tests:**

```php
// Database integrity is tested via constraint violations
it('prevents orphaned invoice items', function () {
    $invoice = Invoice::factory()->create();

    // This should throw due to FK constraint
    expect(fn() => $invoice->delete())
        ->toThrow(QueryException::class);
});
```

---

## References

- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [Laravel PostgreSQL](https://laravel.com/docs/12.x/database)
- ADR-0008: Integer Currency Storage
- ADR-0011: Double-Entry Bookkeeping

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Architecture Team
**Reviewers:** Backend Team, DBA
