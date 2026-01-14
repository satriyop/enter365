---
adr: "0004"
title: "Laravel Sanctum for API Authentication"
status: accepted
date: 2024-11-01
deciders: [Architecture Team]
tags: [api, authentication, security]
related_adrs: [0001, 0034, 0043]
related_modules: [auth, api]
impact: high
---

# ADR-0004: Laravel Sanctum for API Authentication

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing authentication features
- Working with API tokens
- Understanding the auth middleware
- Debugging authentication issues

**Key takeaway:** Sanctum provides simple, stateless token-based authentication for the API. All authenticated routes use `auth:sanctum` middleware.

---

## Context

Enter365 is an API-first application that:
- Serves a Vue.js SPA frontend
- May have future mobile apps
- Needs token-based authentication (no sessions)
- Requires multi-device support (user logs in from multiple devices)
- Must support token revocation

### Forces

- **API-First** - All interactions through RESTful API
- **Multi-Device** - Users may have multiple tokens
- **Security** - Tokens must be revocable
- **Simplicity** - Avoid OAuth complexity for internal APIs

---

## Decision Drivers

1. **Laravel Integration** - First-party package
2. **Token Management** - Easy creation and revocation
3. **Stateless** - No server-side session storage
4. **Multi-Device** - Multiple tokens per user
5. **SPA Support** - Built-in SPA authentication mode

---

## Considered Options

### Option 1: Laravel Sanctum (Chosen)

**Description:** Laravel's lightweight token authentication package

**Pros:**
- First-party Laravel package
- Simple API token generation
- Multiple tokens per user
- Token abilities (permissions)
- SPA authentication via cookies
- Easy revocation
- Minimal configuration

**Cons:**
- Simpler than OAuth (if OAuth needed later)
- No refresh tokens (by design)

### Option 2: Laravel Passport (OAuth 2.0)

**Description:** Full OAuth 2.0 server implementation

**Pros:**
- Industry-standard OAuth 2.0
- Refresh tokens
- Personal access tokens
- Authorization code flow

**Cons:**
- Complex for internal APIs
- Overkill for SPA + internal use
- More database tables
- Higher learning curve

### Option 3: JWT (tymon/jwt-auth)

**Description:** JSON Web Tokens via third-party package

**Pros:**
- Stateless tokens
- Industry standard
- No database lookups

**Cons:**
- Token revocation complex
- Third-party dependency
- Token size larger

---

## Decision

**Chosen option:** "Laravel Sanctum"

Sanctum provides the right balance of simplicity and features for Enter365's API authentication needs.

---

## Rationale

### Why Sanctum:

1. **First-Party Support**
   - Maintained by Laravel team
   - Updates with Laravel releases
   - Excellent documentation

2. **API Token Features**
   - Easy token creation: `$user->createToken('device-name')`
   - Token abilities for permissions
   - Revocation: `$user->tokens()->delete()`

3. **Multi-Device Support**
   - Each login creates unique token
   - User can manage their tokens
   - Revoke specific devices

4. **Simplicity**
   - Single middleware: `auth:sanctum`
   - Minimal configuration
   - No complex OAuth flows

---

## Consequences

### Positive

- Simple authentication implementation
- Easy token management
- Multi-device support out of box
- Clean integration with Laravel
- Minimal overhead

### Negative

- No refresh tokens (requires re-login when expired)
- Not suitable if third-party OAuth clients needed
- Token stored in database (requires lookup)

### Neutral

- Tokens stored in `personal_access_tokens` table
- Token abilities used sparingly (prefer RBAC - ADR-0043)

---

## Implementation Notes

**Route Protection:**

```php
// File: /routes/api.php

Route::prefix('v1')->group(function () {
    // Public routes (no auth)
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });

    // Protected routes (require token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/user', [AuthController::class, 'user']);

        // All other API routes...
        Route::apiResource('quotations', QuotationController::class);
        Route::apiResource('invoices', InvoiceController::class);
        // ... 400+ more routes
    });
});
```

**Login Controller:**

```php
// File: /app/Http/Controllers/Api/V1/AuthController.php

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Kredensial tidak valid.',
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken(
            $request->device_name ?? 'api-token'
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Berhasil logout.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
}
```

**Token Usage (Frontend):**

```javascript
// Store token after login
localStorage.setItem('token', response.data.token);

// Include in requests
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

// API calls now authenticated
const quotations = await axios.get('/api/v1/quotations');
```

**Configuration:**

```php
// File: /config/sanctum.php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,127.0.0.1'
    )),

    'guard' => ['web'],

    'expiration' => null, // Tokens don't expire by default

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
```

**Key Patterns:**

| Pattern | Usage |
|---------|-------|
| `auth:sanctum` | Middleware for protected routes |
| `$user->createToken()` | Generate new token |
| `$request->user()` | Get authenticated user |
| `currentAccessToken()->delete()` | Revoke current token |
| `$user->tokens()->delete()` | Revoke all tokens |

---

## Validation

**Verification Steps:**

1. Check `config/sanctum.php` exists
2. Verify `auth:sanctum` middleware in routes
3. Confirm `personal_access_tokens` table exists
4. Test login returns token

**Tests:**

```php
// File: /tests/Feature/Auth/LoginTest.php

it('returns token on valid login', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

it('rejects invalid credentials', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'wrong@example.com',
        'password' => 'wrong',
    ])->assertUnauthorized();
});

it('protected routes require token', function () {
    $this->getJson('/api/v1/quotations')
        ->assertUnauthorized();
});
```

---

## References

- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- ADR-0043: Role-Based Access Control
- ADR-0034: API Versioning
- `/app/Http/Controllers/Api/V1/AuthController.php`

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Architecture Team
**Reviewers:** Security Team, Backend Team
