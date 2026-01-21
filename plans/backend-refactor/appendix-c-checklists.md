# Appendix C: Migration Checklists

## Per-Phase Completion Checklist

### Phase 0: Foundation & Observability
- [ ] Create `ContextualLoggerInterface` contract
- [ ] Implement `LaravelContextualLogger`
- [ ] Implement `NullContextualLogger` for testing
- [ ] Register logger in service provider
- [ ] Create base `DomainException` class
- [ ] Create `ValidationException`, `BusinessRuleException`, `EntityNotFoundException`
- [ ] Update `DocumentLockedException` and `StateTransitionException`
- [ ] Configure exception handler for API responses
- [ ] Create `RequestIdMiddleware`
- [ ] Create `HealthController` with /health, /ready, /live endpoints
- [ ] Create `LogsPerformance` trait
- [ ] Write tests for health endpoints
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

### Phase 1: Domain Layer Consolidation
- [ ] Create `Money` value object
- [ ] Create `Quantity` value object
- [ ] Create `DateRange` value object
- [ ] Write value object tests
- [ ] Create Manufacturing domain structure
- [ ] Create `WorkOrderCompletionPipeline`
- [ ] Create `MaterialConsumptionHandler`
- [ ] Create `FinishedGoodsHandler`
- [ ] Create `BomExploder`
- [ ] Create Inventory domain structure
- [ ] Create `StockOpnameStateMachine`
- [ ] Create `MovementRecorder`
- [ ] Create `MovementValidator`
- [ ] Create inventory movement events
- [ ] Create base `DomainEvent` class
- [ ] Refactor existing events to extend base
- [ ] Register domain services in container
- [ ] Write domain layer tests
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

### Phase 2: Repository Pattern
- [ ] Create `RepositoryInterface`
- [ ] Create `SpecificationInterface`
- [ ] Create `EloquentRepository` base
- [ ] Create specification classes (And, Or, Not)
- [ ] Create `InvoiceRepositoryInterface`
- [ ] Implement `EloquentInvoiceRepository`
- [ ] Create `WorkOrderRepositoryInterface`
- [ ] Implement `EloquentWorkOrderRepository`
- [ ] Create `ProductStockRepositoryInterface`
- [ ] Implement `EloquentProductStockRepository`
- [ ] Create `InMemoryRepository` base
- [ ] Create `InMemoryInvoiceRepository`
- [ ] Create `RepositoryServiceProvider`
- [ ] Register provider
- [ ] Refactor `InvoiceService` to use repository
- [ ] Write repository tests
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

### Phase 3: Service Layer Refinement
- [ ] Create `ServiceResult` class
- [ ] Create `CreateResult`, `UpdateResult`, `DeleteResult`
- [ ] Create `AbstractApplicationService`
- [ ] Refactor `AbstractDocumentService`
- [ ] Create `AbstractQueryService`
- [ ] Create `InvoiceQueryService`
- [ ] Refactor `InvoiceService` fully
- [ ] Apply pattern to other services
- [ ] Write service tests
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

### Phase 4: Event-Driven Architecture
- [ ] Create `EventServiceProvider`
- [ ] Configure event auto-discovery
- [ ] Create `InvoiceEventSubscriber`
- [ ] Create `QuotationEventSubscriber`
- [ ] Create `WorkOrderEventSubscriber`
- [ ] Create `InventoryMovementSubscriber`
- [ ] Create async notification listeners
- [ ] Create `RecordingEventDispatcher`
- [ ] Remove Event::listen() from AppServiceProvider
- [ ] Register EventServiceProvider
- [ ] Write event tests
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

### Phase 5: Strategy Pattern Expansion
- [ ] Create `NumberGenerationStrategy` interface
- [ ] Implement `SequentialNumberStrategy`
- [ ] Implement `ProjectBasedNumberStrategy`
- [ ] Create `NumberGenerationManager`
- [ ] Create `TaxCalculationStrategy` interface
- [ ] Implement `TaxExclusiveStrategy`
- [ ] Implement `TaxInclusiveStrategy`
- [ ] Create `PricingStrategy` interface
- [ ] Implement `StandardPricingStrategy`
- [ ] Implement `CustomerPricingStrategy`
- [ ] Implement `VolumePricingStrategy`
- [ ] Create `ApprovalStrategy` interface
- [ ] Implement `AmountBasedApprovalStrategy`
- [ ] Create `StrategyServiceProvider`
- [ ] Create configuration files
- [ ] Write strategy tests
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

### Phase 6: State Machine Enhancement
- [ ] Enhance `AbstractStateMachine` with guards/actions
- [ ] Create `StatusHistory` model
- [ ] Create `status_histories` migration
- [ ] Create `HasStatusHistory` trait
- [ ] Update `InvoiceStateMachine` with guards
- [ ] Add `recordHistory()` to state machines
- [ ] Add workflow metadata to state machines
- [ ] Update models to use `HasStatusHistory`
- [ ] Update API resources with workflow data
- [ ] Write state machine tests
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

### Phase 7: Testing Infrastructure
- [ ] Create `InteractsWithDomainEvents` trait
- [ ] Create `InteractsWithRepositories` trait
- [ ] Create `TestsServices` trait
- [ ] Write comprehensive service tests
- [ ] Write state machine tests
- [ ] Write repository tests
- [ ] Write value object tests
- [ ] Update Pest configuration
- [ ] Achieve >70% domain test coverage
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

### Phase 8: API Layer Clean-up
- [ ] Create base `Api\V1\Controller`
- [ ] Refactor controllers to use base
- [ ] Enhance API resources with metadata
- [ ] Create consistent error responses
- [ ] Configure rate limiting
- [ ] Apply rate limiting to routes
- [ ] Update Scramble configuration
- [ ] Add API documentation comments
- [ ] Run API tests
- [ ] Run `vendor/bin/pint`
- [ ] Run full test suite

---

## Before Each Phase

1. Create a new git branch: `git checkout -b refactor/phase-X-name`
2. Review the phase document thoroughly
3. Ensure all tests pass before starting
4. Back up any critical data

## After Each Phase

1. Run `vendor/bin/pint` to fix code style
2. Run `php artisan test` to verify all tests pass
3. Review changes: `git diff --stat`
4. Commit with descriptive message
5. Create PR for review
6. Merge after approval

## Emergency Rollback

If a phase causes critical issues:

1. `git stash` any uncommitted changes
2. `git checkout main`
3. Deploy previous version
4. Investigate issues on branch
5. Fix and re-test before re-attempting

---

## File Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Interface | `{Name}Interface.php` | `InvoiceServiceInterface.php` |
| Strategy Interface | `{Name}Strategy.php` | `COGSRecognitionStrategy.php` |
| Strategy Implementation | `{Type}{Name}Strategy.php` | `COGSOnInvoiceStrategy.php` |
| Event | `{Entity}{Action}.php` | `InvoiceSent.php` |
| Listener | `{Action}{Entity}.php` | `SendInvoiceNotification.php` |
| Subscriber | `{Entity}EventSubscriber.php` | `InvoiceEventSubscriber.php` |
| Repository | `{Entity}Repository.php` | `InvoiceRepository.php` |
| Value Object | `{Name}.php` | `Money.php` |
| State Machine | `{Entity}StateMachine.php` | `InvoiceStateMachine.php` |

---

## Code Review Checklist

- [ ] Code follows established patterns
- [ ] All new code has tests
- [ ] No direct Eloquent in application services
- [ ] Domain events fired for important operations
- [ ] State transitions go through state machine
- [ ] Proper error handling with domain exceptions
- [ ] Logging included for observability
- [ ] No breaking changes to API contracts
- [ ] Documentation updated if needed
