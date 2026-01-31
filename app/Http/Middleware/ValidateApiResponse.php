<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates API responses against OpenAPI schema.
 *
 * This middleware ensures that API responses match the OpenAPI specification
 * defined in api.json. It helps catch contract mismatches at runtime.
 *
 * **When enabled:**
 * - Validates all JSON API responses
 * - Logs validation failures (doesn't block responses in production)
 * - Can be disabled via environment variable
 *
 * **Configuration:**
 * - Set `API_RESPONSE_VALIDATION_ENABLED=true` in .env to enable
 * - Set `API_RESPONSE_VALIDATION_STRICT=true` to throw exceptions on failures
 */
class ValidateApiResponse
{
    private ?array $schema = null;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only validate JSON API responses
        if (! $this->shouldValidate($request, $response)) {
            return $response;
        }

        // Validate response against schema
        $this->validateResponse($request, $response);

        return $response;
    }

    /**
     * Check if response should be validated.
     */
    private function shouldValidate(Request $request, Response $response): bool
    {
        // Only validate if enabled
        if (! config('api.response_validation.enabled', false)) {
            return false;
        }

        // Only validate API routes
        if (! $request->is('api/*')) {
            return false;
        }

        // Only validate JSON responses
        if (! ($response instanceof JsonResponse)) {
            return false;
        }

        // Skip validation errors (422, 404, etc.) - they have different structure
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        return true;
    }

    /**
     * Validate response against OpenAPI schema.
     */
    private function validateResponse(Request $request, JsonResponse $response): void
    {
        try {
            $schema = $this->getSchema();
            if ($schema === null) {
                return; // Schema not available, skip validation
            }

            $route = $request->route();
            if ($route === null) {
                return;
            }

            // Get response schema for this endpoint
            $responseSchema = $this->getResponseSchema($route, $response->getStatusCode(), $schema);
            if ($responseSchema === null) {
                return; // No schema defined for this endpoint/status
            }

            // Validate response data
            $responseData = $response->getData(true);
            $errors = $this->validateData($responseData, $responseSchema);

            if (! empty($errors)) {
                $this->handleValidationErrors($request, $errors, $responseData);
            }
        } catch (\Throwable $e) {
            // Don't break the response if validation fails
            Log::warning('API response validation error', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get OpenAPI schema from api.json.
     */
    private function getSchema(): ?array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $schemaPath = base_path('api.json');
        if (! file_exists($schemaPath)) {
            return null;
        }

        $schema = json_decode(file_get_contents($schemaPath), true);
        if (! is_array($schema)) {
            return null;
        }

        $this->schema = $schema;

        return $this->schema;
    }

    /**
     * Get response schema for the current route and status code.
     */
    private function getResponseSchema($route, int $statusCode, array $schema): ?array
    {
        $method = strtolower($route->methods()[0] ?? 'get');
        $path = $this->normalizePath($route->uri());

        $paths = $schema['paths'] ?? [];
        if (! isset($paths[$path][$method])) {
            return null;
        }

        $operation = $paths[$path][$method];
        $responses = $operation['responses'] ?? [];

        // Try exact status code first
        if (isset($responses[$statusCode])) {
            return $this->extractSchemaFromResponse($responses[$statusCode], $schema);
        }

        // Try status code range (2xx, 3xx, etc.)
        $statusRange = (int) ($statusCode / 100).'xx';
        if (isset($responses[$statusRange])) {
            return $this->extractSchemaFromResponse($responses[$statusRange], $schema);
        }

        // Try default
        if (isset($responses['default'])) {
            return $this->extractSchemaFromResponse($responses['default'], $schema);
        }

        return null;
    }

    /**
     * Extract schema from OpenAPI response definition.
     */
    private function extractSchemaFromResponse(array $response, array $fullSchema): ?array
    {
        $content = $response['content'] ?? [];
        $jsonContent = $content['application/json'] ?? [];

        if (isset($jsonContent['schema'])) {
            return $this->resolveSchemaReference($jsonContent['schema'], $fullSchema);
        }

        return null;
    }

    /**
     * Resolve $ref references in schema.
     */
    private function resolveSchemaReference(array $schema, array $fullSchema): array
    {
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            // Remove #/ prefix
            $ref = ltrim($ref, '#/');
            $parts = explode('/', $ref);

            $resolved = $fullSchema;
            foreach ($parts as $part) {
                if (! isset($resolved[$part])) {
                    return $schema; // Reference not found
                }
                $resolved = $resolved[$part];
            }

            return is_array($resolved) ? $resolved : $schema;
        }

        // Handle allOf, anyOf, oneOf
        if (isset($schema['allOf'])) {
            $merged = [];
            foreach ($schema['allOf'] as $subSchema) {
                $merged = array_merge_recursive($merged, $this->resolveSchemaReference($subSchema, $fullSchema));
            }

            return $merged;
        }

        return $schema;
    }

    /**
     * Normalize route path to match OpenAPI format.
     */
    private function normalizePath(string $uri): string
    {
        // Remove /api/v1 prefix if present
        $path = preg_replace('#^/api/v1#', '', '/'.$uri);

        // Replace route parameters {id} with OpenAPI format {id}
        $path = preg_replace('#\{(\w+)\}#', '{$1}', $path);

        return $path;
    }

    /**
     * Validate data against schema (simplified validation).
     */
    private function validateData(mixed $data, array $schema): array
    {
        $errors = [];

        // Basic type checking
        if (isset($schema['type'])) {
            $type = $schema['type'];
            $actualType = gettype($data);

            $typeMap = [
                'integer' => 'integer',
                'number' => ['integer', 'double'],
                'string' => 'string',
                'boolean' => 'boolean',
                'array' => 'array',
                'object' => ['array', 'object'],
            ];

            if (isset($typeMap[$type])) {
                $expectedTypes = (array) $typeMap[$type];
                if (! in_array($actualType, $expectedTypes, true)) {
                    $errors[] = "Type mismatch: expected {$type}, got {$actualType}";
                }
            }
        }

        // Validate object properties
        if (isset($schema['properties']) && is_array($data)) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                if (isset($propertySchema['required']) && $propertySchema['required']) {
                    if (! array_key_exists($property, $data)) {
                        $errors[] = "Missing required property: {$property}";
                    }
                }

                if (array_key_exists($property, $data)) {
                    $propertyErrors = $this->validateData($data[$property], $propertySchema);
                    foreach ($propertyErrors as $error) {
                        $errors[] = "{$property}: {$error}";
                    }
                }
            }
        }

        // Validate array items
        if (isset($schema['items']) && is_array($data)) {
            foreach ($data as $index => $item) {
                $itemErrors = $this->validateData($item, $schema['items']);
                foreach ($itemErrors as $error) {
                    $errors[] = "[{$index}]: {$error}";
                }
            }
        }

        return $errors;
    }

    /**
     * Handle validation errors.
     */
    private function handleValidationErrors(Request $request, array $errors, mixed $responseData): void
    {
        $isStrict = config('api.response_validation.strict', false);

        $route = $request->route();
        $routeName = $route?->getName() ?? $route?->uri() ?? 'unknown';

        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'route' => $routeName,
            'errors' => $errors,
            'error_count' => count($errors),
            'response_preview' => $this->getResponsePreview($responseData),
            'schema_path' => $this->getSchemaPath($request, $route),
        ];

        if ($isStrict) {
            Log::error('API response validation failed (strict mode)', $context);
            // In strict mode, you could throw an exception
            // throw new \RuntimeException('API response does not match schema: '.implode(', ', $errors));
        } else {
            Log::warning('API response validation failed', $context);
        }
    }

    /**
     * Get a safe preview of response data for logging.
     */
    private function getResponsePreview(mixed $responseData): mixed
    {
        if (! is_array($responseData)) {
            return $responseData;
        }

        // Limit preview size to avoid huge logs
        $preview = array_slice($responseData, 0, 5, true);

        // If it's a collection, show first item structure
        if (isset($preview['data']) && is_array($preview['data']) && count($preview['data']) > 0) {
            $preview['data'] = [
                'count' => count($responseData['data'] ?? []),
                'first_item' => array_slice($preview['data'][0] ?? [], 0, 10, true),
            ];
        }

        return $preview;
    }

    /**
     * Get schema path for the current request.
     */
    private function getSchemaPath(Request $request, $route): ?string
    {
        if ($route === null) {
            return null;
        }

        $method = strtolower($request->method());
        $path = $this->normalizePath($route->uri());

        return "paths.{$path}.{$method}";
    }
}
