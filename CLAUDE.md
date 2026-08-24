

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.


## 🛑 STOP - READ THIS BEFORE EVERY IMPLEMENTATION DECISION
**This codebase may not align the business workflow. Always consider what this code workflow belongs to, peruse the code diligently**

### Mandatory Questions Before Adding or Fixing Code

1. **How Does Laravel way to solve this?**
    - many use case already battery included using laravel convention and solution. Prioritize this.

2. **Am I following a pattern just because it exists here?**
   - Existing patterns may be WRONG. Question them.
   - "Consistency" with a bad pattern = more bad code
   - Ask: "If I started fresh, would I suggest it this way?"

3. **Is this going to solve the REAL FIXING ROOT CAUSE or just Fixing the SYMPTOM?**
   - Dont hide the underlying problem, dont be lazy, seek what causing this issue ?
   - again, always consider and questioning, what is the impact for the current business/domain workflow ?
   
## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.14
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- larastan/larastan (LARASTAN) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== volt/core rules ===

## Livewire Volt

- This project uses Livewire Volt for interactivity within its pages. New pages requiring interactivity must also use Livewire Volt. There is documentation available for it.
- Make new Volt components using `php artisan make:volt [name] [--test] [--pest]`
- Volt is a **class-based** and **functional** API for Livewire that supports single-file components, allowing a component's PHP logic and Blade templates to co-exist in the same file
- Livewire Volt allows PHP logic and Blade templates in one file. Components use the `@volt` directive.
- You must check existing Volt components to determine if they're functional or class based. If you can't detect that, ask the user which they prefer before writing a Volt component.

### Volt Functional Component Example

<code-snippet name="Volt Functional Component Example" lang="php">
@volt
<?php
use function Livewire\Volt\{state, computed};

state(['count' => 0]);

$increment = fn () => $this->count++;
$decrement = fn () => $this->count--;

$double = computed(fn () => $this->count * 2);
?>

<div>
    <h1>Count: {{ $count }}</h1>
    <h2>Double: {{ $this->double }}</h2>
    <button wire:click="increment">+</button>
    <button wire:click="decrement">-</button>
</div>
@endvolt
</code-snippet>


### Volt Class Based Component Example
To get started, define an anonymous class that extends Livewire\Volt\Component. Within the class, you may utilize all of the features of Livewire using traditional Livewire syntax:


<code-snippet name="Volt Class-based Volt Component Example" lang="php">
use Livewire\Volt\Component;

new class extends Component {
    public $count = 0;

    public function increment()
    {
        $this->count++;
    }
} ?>

<div>
    <h1>{{ $count }}</h1>
    <button wire:click="increment">+</button>
</div>
</code-snippet>


### Testing Volt & Volt Components
- Use the existing directory for tests if it already exists. Otherwise, fallback to `tests/Feature/Volt`.

<code-snippet name="Livewire Test Example" lang="php">
use Livewire\Volt\Volt;

test('counter increments', function () {
    Volt::test('counter')
        ->assertSee('Count: 0')
        ->call('increment')
        ->assertSee('Count: 1');
});
</code-snippet>


<code-snippet name="Volt Component Test Using Pest" lang="php">
declare(strict_types=1);

use App\Models\{User, Product};
use Livewire\Volt\Volt;

test('product form creates product', function () {
    $user = User::factory()->create();

    Volt::test('pages.products.create')
        ->actingAs($user)
        ->set('form.name', 'Test Product')
        ->set('form.description', 'Test Description')
        ->set('form.price', 99.99)
        ->call('create')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Test Product')->exists())->toBeTrue();
});
</code-snippet>


### Common Patterns


<code-snippet name="CRUD With Volt" lang="php">
<?php

use App\Models\Product;
use function Livewire\Volt\{state, computed};

state(['editing' => null, 'search' => '']);

$products = computed(fn() => Product::when($this->search,
    fn($q) => $q->where('name', 'like', "%{$this->search}%")
)->get());

$edit = fn(Product $product) => $this->editing = $product->id;
$delete = fn(Product $product) => $product->delete();

?>

<!-- HTML / UI Here -->
</code-snippet>

<code-snippet name="Real-Time Search With Volt" lang="php">
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Search..."
    />
</code-snippet>

<code-snippet name="Loading States With Volt" lang="php">
    <flux:button wire:click="save" wire:loading.attr="disabled">
        <span wire:loading.remove>Save</span>
        <span wire:loading>Saving...</span>
    </flux:button>
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.
<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>

<!-- Project-Specific Instructions (Not managed by Laravel Boost) -->

## ⚠️ Application Status: PRE-PRODUCTION

**This application is NOT in production yet.** This has important implications:

| Aspect | Implication |
|--------|-------------|
| **Backward Compatibility** | Not required - breaking changes are acceptable |
| **Deprecation Strategy** | Not needed - can remove/replace code directly |
| **Data Migration** | No production data to worry about |
| **Refactoring** | Can be aggressive - prioritize clean architecture over compatibility |
| **API Versioning** | Can make breaking changes to existing endpoints |

When refactoring or making architectural changes, prioritize **code quality and correctness** over backward compatibility concerns.

---

## Project Structure

This is a **full-stack application** with separate frontend and backend:

| Component | Directory | Stack |
|-----------|-----------|-------|
| **Backend (API)** | `/Users/satriyo/dev/laravel-project/enter365` | Laravel 12, Livewire 3, Volt |
| **Frontend (SPA)** | `/Users/satriyo/dev/laravel-project/front-end-enter365` | Vue.js, TypeScript |

### Working Directory Aliases

| Alias | Path |
|-------|------|
| `$BE` | `/Users/satriyo/dev/laravel-project/enter365` |
| `$FE` | `/Users/satriyo/dev/laravel-project/front-end-enter365` |

When working on frontend features or debugging API integration, read/edit files directly in the frontend directory. Both repos can be worked on simultaneously in a single session.

---

## Architecture Reference (Skills)

This project has detailed architecture documentation in `.claude/skills/enter365/`:

| Skill File | Purpose |
|------------|---------|
| **SKILL.md** | Main entry with 31 gotchas, architecture overview |
| **STATE_MACHINES.md** | 16 state machines with transitions, events, templates |
| **STRATEGIES.md** | COGS, Inventory, Manufacturing accounting strategies |
| **EVENTS.md** | 95 domain events, event dispatcher pattern |
| **MODELS.md** | 81 models with relationships, casts, scopes |
| **REPOSITORIES.md** | Repository pattern, domain queries, DB::table() for stats |
| **ARCHITECTURE_PATTERNS.md** | OperationContext, Domain Factory, Coordinator pattern |
| **SERVICE_BINDINGS.md** | All interface → implementation bindings |
| **CODE_REVIEW_ANTIPATTERNS.md** | Top code smells, detection checklist, fixes applied |
| **REFACTORING_HISTORY.md** | Changelog of architectural fixes with rationale |

These skills are auto-loaded by Claude Code when working on this project.

### Key Architecture Patterns

- **Service Layer**: All business logic in `app/Services/{Domain}/`
- **State Machines**: Document workflows via `app/Domain/{Domain}/{Entity}/`
- **Contracts**: Always inject interfaces, not concrete classes
- **Indonesian Messages**: User-facing errors in Indonesian

### Accounting Side-Effect Reversal — Service Layer Only

**NEVER bypass service methods to reverse accounting side effects directly.** Documents with cascade void logic (Invoice, DO, SR) have side effects (JEs, inventory movements, paid_amount adjustments) that must be reversed in a specific order within a DB transaction.

| DO NOT | DO INSTEAD |
|--------|------------|
| `$journalService->reverseEntry($invoice->journalEntry)` | `$invoiceService->void($invoice, $reason)` |
| `$inventoryService->stockIn(...)` to undo a shipment | `$deliveryOrderService->reverseShipment($do, $reason)` |
| `$sr->transitionTo(Cancelled)` on an Approved SR | `$salesReturnService->cancel($sr, $reason)` |

**Why:** Calling low-level reversal methods directly skips the cascade order, leaves orphaned JEs, and causes `Debits != Credits` in the trial balance. The service methods handle the full cascade atomically inside `DB::transaction()`.

**Key cascade services:**
- `InvoiceService::void()` — reverses shipped DOs, approved SRs, COGS JEs, payments, DPs, and the AR/Revenue JE
- `DeliveryOrderService::reverseShipment()` — restores inventory + reverses DO-level COGS JE
- `SalesReturnService::cancel()` — calls `reverseApprovalSideEffects()` for approved SRs (JE + inventory + paid_amount)

### DB:: vs Eloquent Override

Despite Boost guidelines, prefer `DB::table()` over Eloquent for:
- Dashboard aggregations (SUM, AVG, COUNT)
- Reports with 100+ rows (avoids hydration overhead)
- Bulk read-only operations

See `~/.claude/CLAUDE.md` for detailed performance patterns.

---

## API Documentation with Scramble

This project uses [Scramble](https://scramble.dedoc.co/) for automatic OpenAPI documentation generation.

### Automated API Contract Validation

**IMPORTANT:** This project has automated API contract validation to ensure consistency between backend and frontend.

#### Quick Workflow

When modifying API Resources or Controllers:

```bash
# Run automated integration check (recommended)
./scripts/check-api-integration.sh

# Or manually:
php artisan scramble:export --path=api.json
php check-api-mismatches.php
```

The pre-commit hook will automatically validate API contracts before commit. CI/CD validates on every PR.

#### Integration Check Script

The `scripts/check-api-integration.sh` script automates the full validation process:

```bash
# Full check (recommended)
./scripts/check-api-integration.sh

# Skip tests (faster)
./scripts/check-api-integration.sh --no-tests

# Skip PHPStan (faster, but no type checking)
./scripts/check-api-integration.sh --no-phpstan
```

**What it does:**
1. Generates OpenAPI schema via Scramble
2. Checks for contract mismatches
3. Validates api.json format
4. Runs PHPStan on API Resources
5. Runs API tests
6. Provides summary and next steps

#### Pre-commit Hook

A pre-commit hook automatically validates API contracts before each commit:

- Detects when API files are modified
- Generates OpenAPI schema
- Checks for mismatches
- Blocks commit if issues found
- Reminds to stage `api.json` if modified

**Installation:**
```bash
./scripts/install-pre-commit-hook.sh
```

#### CI/CD Integration

GitHub Actions automatically validates API contracts on:
- Pull requests that modify API files
- Pushes to `main` or `develop` branches

The workflow (`.github/workflows/api-contract-check.yml`) ensures broken contracts cannot be merged.

### After Creating or Modifying API Endpoints

**IMPORTANT:** After creating or modifying any API endpoints:

1. **Run integration check:**
   ```bash
   ./scripts/check-api-integration.sh
   ```

2. **Or manually:**
   ```bash
   php artisan scramble:export --path=api.json
   php check-api-mismatches.php
   ```

3. **Update tests** if field names/types changed

4. **Regenerate frontend types** (in frontend directory):
   ```bash
   cd ../front-end-enter365
   npm run types:generate
   ```

The OpenAPI specification (`api.json`) is used by the Vue frontend for TypeScript type generation.

### Scramble Best Practices

1. **Add PHPDoc annotations** to controller methods for better documentation:
   - `@bodyParam` for request body parameters
   - `@queryParam` for query string parameters
   - `@response` for response examples
   - `@unauthenticated` for public endpoints

2. **Use Form Request classes** - Scramble automatically extracts validation rules from Form Requests

3. **Use API Resources** - Scramble understands Laravel API Resources for response documentation

4. **Keep Resources and Schema in sync** - The mismatch checker (`check-api-mismatches.php`) helps identify inconsistencies

### Example Controller Documentation

```php
/**
 * List all products.
 *
 * Returns a paginated list of products with optional filtering.
 *
 * @queryParam search string Search by product name or SKU. Example: MCB-16A
 * @queryParam category string Filter by category. Example: circuit_breaker
 * @queryParam per_page int Items per page. Default: 15. Example: 25
 *
 * @response 200 {
 *   "data": [{"id": 1, "name": "MCB 16A", "sku": "MCB-16A-1P"}],
 *   "meta": {"current_page": 1, "total": 50}
 * }
 */
public function index(Request $request): AnonymousResourceCollection
```

### API Contract Consistency

**Field Naming Standards:**
- Use `_amount` suffix for monetary values: `total_amount`, `discount_amount`, `tax_amount`
- Be consistent across all Resources
- Database column names should match Resource field names

**Validation:**
- Run `./scripts/check-api-integration.sh` before committing API changes
- Pre-commit hook enforces validation automatically
- CI/CD blocks PRs with broken contracts
- **Runtime validation** (optional): Enable `API_RESPONSE_VALIDATION_ENABLED=true` in development
- **Contract tests**: Run `php artisan test --filter=ApiContractTest` to validate responses

**Response Validation Middleware:**
- Validates API responses against OpenAPI schema at runtime
- Enabled via `API_RESPONSE_VALIDATION_ENABLED=true` in `.env`
- Logs validation failures (doesn't block responses by default)
- Strict mode available for development: `API_RESPONSE_VALIDATION_STRICT=true`

**Contract Testing:**
- Automated tests in `tests/Contract/ApiContractTest.php`
- Validates actual API responses match schema
- Runs automatically in integration check script
- Great for regression testing

**Documentation:**
- Run `./scripts/check-api-integration.sh` for full API contract validation
- See `README_PHPSTAN.md` for PHPStan setup guide

---

## Static Analysis with Larastan (PHPStan)

This project uses [Larastan](https://github.com/larastan/larastan) for static analysis to catch bugs before runtime.

### Running PHPStan

**Recommended:** Use the helper script for consistent execution:

```bash
# Check specific file or directory (recommended)
./scripts/phpstan-check.sh app/Services/Sales/

# Check full codebase
./scripts/phpstan-check.sh
```

**Manual execution:**

```bash
# Run full analysis
vendor/bin/phpstan analyse

# Analyze specific file or directory
vendor/bin/phpstan analyse app/Services/Sales/

# With more memory for large analysis
vendor/bin/phpstan analyse --memory-limit=1G
```

**Note:** PHPStan is configured to run in single-process mode (`maximumNumberOfProcesses: 0`) to avoid TCP server permission issues on macOS. See `README_PHPSTAN.md` for details.

### Configuration

- **Config file**: `phpstan.neon`
- **Baseline file**: `phpstan-baseline.neon` (tracks ~53 existing errors)
- **Level**: 5 (good balance of strictness vs noise)

### When to Run PHPStan

**IMPORTANT:** Run PHPStan after writing or modifying PHP code:

```bash
# After modifying files, check for new errors (recommended)
./scripts/phpstan-check.sh app/Services/YourModifiedService.php

# Or manually
vendor/bin/phpstan analyse app/Services/YourModifiedService.php
```

**Automated checks:**
- API Resources are automatically checked via `./scripts/check-api-integration.sh`
- CI/CD can be configured to run PHPStan on PRs (optional)

### What PHPStan Catches

| Error Type | Example |
|------------|---------|
| **Type mismatches** | Passing `string` where `int` expected |
| **Missing methods** | Calling undefined method on model |
| **Null safety** | Accessing property on possibly null object |
| **Return types** | Function doesn't return declared type |
| **Argument counts** | Wrong number of arguments to function |

### Baseline Strategy

The project uses a baseline file (`phpstan-baseline.neon`) to track existing errors:

- **New code must pass** - PHPStan will fail if you introduce NEW errors
- **Existing errors tracked** - ~53 legacy errors are baselined
- **Gradual improvement** - Fix baselined errors over time

### Regenerating Baseline

If you fix baselined errors, regenerate the baseline:

```bash
vendor/bin/phpstan analyse --generate-baseline=phpstan-baseline.neon --memory-limit=2G
```

### Adding PHPDoc for Better Analysis

When PHPStan can't infer types, add PHPDoc:

```php
/**
 * @param array{
 *     contact_id: int,
 *     quotation_date: string,
 *     items: list<array{product_id: int, quantity: float}>
 * } $data
 * @return Collection<int, Quotation>
 */
public function createBatch(array $data): Collection
```

### Ignored Errors

These patterns are intentionally ignored in `phpstan.neon`:

| Pattern | Reason |
|---------|--------|
| `Unsafe usage of new static` | Common Laravel pattern |
| `Call to undefined method.*Builder` | Eloquent dynamic methods |
| `Generic type.*does not specify all template types` | Too noisy, low value |
| `Trait.*is used zero times` | Traits analyzed when used |

### Excluded Files

Files excluded from analysis (incomplete implementations):

- `app/Services/Accounting/Strategies/Manufacturing/*.php` - TODO: Complete these strategies

---

## Development Workflow

### Code Quality Checks

Before committing code, ensure:

1. **Tests pass:**
   ```bash
   php artisan test --filter=YourTest
   ```

2. **Code formatted:**
   ```bash
   vendor/bin/pint --dirty
   ```

3. **Type checking (for modified files):**
   ```bash
   ./scripts/phpstan-check.sh app/YourModifiedFile.php
   ```

4. **API contracts valid (if modifying API):**
   ```bash
   ./scripts/check-api-integration.sh
   ```

5. **App boots cleanly (web smoke test):**
   ```bash
   curl -s -o /dev/null -w "%{http_code}" https://enter365.test
   ```
   Must return `200` or `301` (redirect). **Do NOT rely on `php artisan` commands alone** — CLI and web server can behave differently (different bootstrap paths, PHP-FPM vs CLI).
   Always run this after modifying `bootstrap/app.php`, service providers, or config files.

### Automated Validation

**Pre-commit Hook:**
- Automatically validates API contracts when API files are modified
- Install: `./scripts/install-pre-commit-hook.sh`
- Can be skipped with `git commit --no-verify` (not recommended)

**CI/CD:**
- GitHub Actions validates API contracts on PRs
- Runs automatically when API files are modified
- Blocks merge if contracts are broken

### Helper Scripts

| Script | Purpose |
|--------|---------|
| `./scripts/check-api-integration.sh` | Full API contract validation |
| `./scripts/phpstan-check.sh [path]` | PHPStan type checking |
| `./scripts/install-pre-commit-hook.sh` | Install pre-commit hook |
| `php check-api-mismatches.php` | Check API Resource vs Schema mismatches |

### Documentation

- **API Integration:** `docs/04-api/integration-check/` - Complete integration check workflow
- **Development Tools:** `docs/04-api/tools/` - Scramble, PHPStan usage guides
- **Architecture:** `.claude/skills/enter365/` - Domain patterns, state machines, events

---

## E2E Testing Strategy

> **Tracker:** Use `tasks/` (roadmap / audit / backlog / done / artifact).  
> Legacy `plans/` is gitignored and no longer the source of truth.

### Testing Priorities for ERP Applications

| Priority | Category | Why It Matters |
|----------|----------|----------------|
| **1** | Master Data (Products, Contacts) | All transactional tests depend on these foundations |
| **2** | Inventory | Affects both Sales (delivery) and Purchasing (receiving) |
| **3** | Chain Tests | Individual module tests can pass while cross-module flows fail |
| **4** | Edge Cases | Users hit fiscal period locks, negative stock, duplicates in production |

### Key Insights

1. **Master Data First**
   - All transactional tests implicitly depend on products/contacts being created correctly
   - Testing CRUD for Products, Contacts, Warehouses, and CoA catches issues early
   - Don't assume factory data is enough — test the actual SPA forms

2. **Chain Tests Are Critical**
   - Individual module tests can pass while cross-module flows fail due to event/state machine issues
   - Example: Quotation → Invoice → DO → Payment must work as a complete flow
   - Always assert trial balance after financial operations

3. **Edge Cases Matter for ERP**
   - Users will hit fiscal period locks in production — test the error UX
   - Negative stock prevention must show clear error messages
   - Duplicate document numbers must be caught and explained
   - Overpayment scenarios need defined behavior

4. **Accounting Assertions Are Non-Negotiable**
   - Every financial test must verify: `Trial Balance Debits = Credits`
   - Check JE lines explicitly: correct accounts, correct amounts, correct signs
   - Use DB assertions, not just UI checks

### Test Structure

```
tests/Browser/
├── Auth/                    # Login, logout, protected routes
├── Sales/                   # Quotations, Invoices, DO, Returns
├── Purchasing/              # PO, GRN, Bills, Returns
├── Inventory/               # Stock levels, movements, opname
├── Accounting/              # JE, fiscal periods, reports
├── Manufacturing/           # BOM, WO, MR, subcontracting
├── Chain/                   # Cross-module integration tests
└── Edge/                    # Error handling, edge cases
```

### Running E2E Tests

```bash
# Run all browser tests
php artisan test tests/Browser/

# Run specific module
php artisan test tests/Browser/Sales/

# Run with filter
php artisan test --filter=QuotationWorkflow
```

## Agent skills

### Issue tracker

GitHub Issues for `satriyop/enter365` via `gh`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five-role vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: root `CONTEXT.md` (when present) + ADRs in `docs/08-adr/`. See `docs/agents/domain.md`.
