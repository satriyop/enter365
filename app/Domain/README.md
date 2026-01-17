# Domain Layer

This directory contains domain-driven code organized by business capability.

## Structure

```
Domain/
└── {Module}/
    └── {Aggregate}/
        ├── {Aggregate}.php           # Main aggregate (model)
        ├── {Aggregate}Id.php         # Value object for ID
        ├── {Aggregate}StateMachine.php # Workflow logic
        ├── {Aggregate}Calculator.php # Calculation logic
        └── Events/                   # Domain events
```

## Principles

1. **Single Responsibility** - Each class has one clear purpose
2. **Testability** - Classes can be unit tested in isolation
3. **No Framework Dependencies** - Pure PHP business logic
4. **Explicit Dependencies** - Dependencies injected via constructor

## When to Use

- Complex business logic
- Workflow/state machine patterns
- Calculations with multiple steps
- Validation beyond simple type checking
