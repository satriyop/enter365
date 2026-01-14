---
adr: "0041"
title: "Audit Trail"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [audit, security]
related_adrs: [0011]
related_modules: [core]
impact: high
---

# ADR-0041: Audit Trail

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing activity logging
- Tracking model changes
- Building audit reports
- Understanding data history

**Key takeaway:** All model changes are logged with before/after values for audit compliance.

---

## Decision

Implement comprehensive audit trail logging for all significant model changes.

---

## Context

Audit logging needed for:
1. Regulatory compliance (SAK EMKM)
2. Fraud detection
3. Change history
4. Debugging

---

## Implementation

### Activity Log Model

```php
// activity_logs table
$table->morphs('subject');               // Model being changed
$table->morphs('causer');                // User making change
$table->string('event');                 // created, updated, deleted
$table->json('old_values')->nullable();  // Before state
$table->json('new_values')->nullable();  // After state
$table->string('ip_address')->nullable();
$table->string('user_agent')->nullable();
$table->timestamp('created_at');
```

### Auditable Trait

```php
// app/Traits/Auditable.php
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->logActivity('created'));
        static::updated(fn ($model) => $model->logActivity('updated'));
        static::deleted(fn ($model) => $model->logActivity('deleted'));
    }

    protected function logActivity(string $event): void
    {
        ActivityLog::create([
            'subject_type' => static::class,
            'subject_id' => $this->id,
            'causer_type' => auth()->check() ? get_class(auth()->user()) : null,
            'causer_id' => auth()->id(),
            'event' => $event,
            'old_values' => $event === 'created' ? null : $this->getOriginal(),
            'new_values' => $event === 'deleted' ? null : $this->getAttributes(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

### Model Usage

```php
class Invoice extends Model
{
    use Auditable;

    // Only log specific attributes
    protected array $auditExclude = ['updated_at'];
    protected array $auditInclude = ['status', 'total', 'contact_id'];
}
```

### Activity Log Query

```php
// Get activity for a model
$invoice = Invoice::find(1);
$activities = ActivityLog::where('subject_type', Invoice::class)
    ->where('subject_id', $invoice->id)
    ->orderBy('created_at', 'desc')
    ->get();

// Get user activity
$userActivities = ActivityLog::where('causer_id', $userId)
    ->latest()
    ->limit(100)
    ->get();
```

### Change Diff Display

```php
public function getChanges(): array
{
    if (!$this->old_values || !$this->new_values) {
        return [];
    }

    $changes = [];
    foreach ($this->new_values as $key => $new) {
        $old = $this->old_values[$key] ?? null;
        if ($old !== $new) {
            $changes[$key] = [
                'old' => $old,
                'new' => $new,
            ];
        }
    }

    return $changes;
}
```

### Audit Report

```php
// Get audit trail for date range
public function getAuditReport(Carbon $from, Carbon $to): Collection
{
    return ActivityLog::whereBetween('created_at', [$from, $to])
        ->with(['subject', 'causer'])
        ->orderBy('created_at')
        ->get()
        ->groupBy('subject_type');
}
```

### Sensitive Data Handling

```php
// In model, mask sensitive data
protected array $auditMasked = ['password', 'api_token'];

protected function getMaskedAttributes(array $attributes): array
{
    foreach ($this->auditMasked as $key) {
        if (isset($attributes[$key])) {
            $attributes[$key] = '******';
        }
    }
    return $attributes;
}
```

### Key Audit Points

| Event | What to Log |
|-------|-------------|
| Invoice created | Full record |
| Invoice approved | Status change, approver |
| Payment received | Amount, method |
| Journal posted | All lines, who posted |
| User login | IP, user agent, success/fail |

---

## References

- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)
- [Indonesian Context](../02-domain/indonesian-context.md)

