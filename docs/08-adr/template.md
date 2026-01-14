# ADR Template

> **Use this template when creating new Architecture Decision Records**

---

## Template

Copy the content below when creating a new ADR:

```markdown
---
adr: "XXXX"
title: "Short Descriptive Title"
status: proposed  # proposed | accepted | deprecated | superseded
date: YYYY-MM-DD
deciders: [Team/Person who made decision]
tags: [category, subcategory]
related_adrs: []
related_modules: []
impact: medium  # high | medium | low
supersedes: null  # ADR number if this supersedes another
superseded_by: null  # ADR number if superseded
---

# ADR-XXXX: Short Descriptive Title

## AI Agent Quick Reference

**Use this ADR when:**
- [Specific situation when this decision applies]
- [Another situation]

**Key takeaway:** [One sentence summary of the decision]

---

## Context

<!-- What is the issue/problem that needs to be addressed? -->
<!-- Include business context, technical constraints, and team considerations -->

[Describe the context and problem statement]

### Forces

<!-- What forces are at play? What constraints exist? -->

- **Business Requirements:** [List relevant business needs]
- **Technical Constraints:** [List technical limitations]
- **Team Context:** [Team skills, experience, preferences]

---

## Decision Drivers

<!-- What criteria influenced this decision? -->

1. [Driver 1]
2. [Driver 2]
3. [Driver 3]

---

## Considered Options

### Option 1: [Name]

**Description:** [Brief description]

**Pros:**
- [Advantage 1]
- [Advantage 2]

**Cons:**
- [Disadvantage 1]
- [Disadvantage 2]

### Option 2: [Name]

**Description:** [Brief description]

**Pros:**
- [Advantage 1]

**Cons:**
- [Disadvantage 1]

### Option 3: [Name] (if applicable)

[Similar structure]

---

## Decision

**Chosen option:** "[Option Name]"

[Explain the decision in detail]

---

## Rationale

<!-- Why was this option chosen over alternatives? -->

### Why [Chosen Option]:

1. [Reason 1]
2. [Reason 2]
3. [Reason 3]

### Why not [Rejected Option]:

1. [Reason for rejection]

---

## Consequences

### Positive

- [Benefit 1]
- [Benefit 2]

### Negative

- [Drawback 1]
- [Drawback 2]

### Neutral

- [Neutral consequence]

---

## Implementation Notes

<!-- How is this decision implemented in the codebase? -->

**File Locations:**
- `[path/to/file1]` - [Purpose]
- `[path/to/file2]` - [Purpose]

**Key Code:**

\`\`\`php
// File: /app/path/to/file.php

// Example implementation
\`\`\`

**Configuration:**

\`\`\`php
// File: /config/example.php

return [
    'setting' => 'value',
];
\`\`\`

---

## Validation

<!-- How can we verify this decision is correctly implemented? -->

**Tests:**
- `[test file path]`

**Verification:**
- [How to verify the decision is working]

---

## References

- [Link to external documentation]
- [Link to related internal docs]
- [Link to discussion/issue]

---

## Metadata

**Last Updated:** YYYY-MM-DD
**Author:** [Name/Team]
**Reviewers:** [Names/Teams]
```

---

## Status Definitions

| Status | Description |
|--------|-------------|
| **proposed** | Under discussion, not yet decided |
| **accepted** | Decided and currently active |
| **deprecated** | No longer recommended but may exist in code |
| **superseded** | Replaced by another ADR (link in `superseded_by`) |

---

## Impact Levels

| Level | Description | Examples |
|-------|-------------|----------|
| **high** | Fundamental to architecture, hard to reverse | Framework choice, database, auth system |
| **medium** | Significant but changeable with effort | Service patterns, API conventions |
| **low** | Local impact, easy to change | Naming conventions, minor patterns |

---

## Naming Convention

```
docs/08-adr/XXXX-short-kebab-case-title.md
```

Examples:
- `0001-laravel-framework.md`
- `0002-postgresql-database.md`
- `0009-bom-variant-groups.md`

---

## Category Tags

Use these standard tags for categorization:

| Category | Tags |
|----------|------|
| Technology | `framework`, `database`, `infrastructure`, `tooling` |
| Architecture | `architecture`, `patterns`, `structure` |
| API | `api`, `authentication`, `versioning` |
| Domain | `domain`, `business-logic`, `accounting`, `manufacturing`, `solar` |
| Data | `data-model`, `schema`, `migration` |
| Indonesian | `indonesian-context`, `compliance`, `localization` |
| Frontend | `frontend`, `ui`, `livewire` |
| Testing | `testing`, `quality` |

---

## Writing Guidelines

1. **Be Specific**: Include file paths, code examples, configuration
2. **Include Context**: Explain why the decision was needed
3. **Document Alternatives**: Show what was considered
4. **Link Related ADRs**: Cross-reference related decisions
5. **Update Status**: Keep status current (especially `superseded`)
6. **Date Changes**: Update "Last Updated" when modifying

---

## Example: Quick ADR

For simple decisions, a shorter format is acceptable:

```markdown
---
adr: "0050"
title: "Use Pest for Testing"
status: accepted
date: 2024-01-15
tags: [testing, tooling]
impact: medium
---

# ADR-0050: Use Pest for Testing

## Context

Need a testing framework for the Laravel application.

## Decision

Use Pest v4 for all tests.

## Rationale

- Cleaner syntax than PHPUnit
- Laravel ecosystem alignment
- Better error messages
- Browser testing support in v4

## Consequences

- All tests use Pest syntax
- Team needs Pest familiarity

## Files

- `/tests/` - All test files
- `/phpunit.xml` - Test configuration
```
