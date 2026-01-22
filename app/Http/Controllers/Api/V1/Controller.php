<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base API controller with standardized response methods.
 *
 * Provides consistent response formatting across all API endpoints:
 * - success(): Standard success response
 * - created(): Resource creation response (201)
 * - error(): Standard error response
 * - deleted(): Resource deletion response
 */
abstract class Controller extends BaseController
{
    /**
     * Return standardized success response.
     *
     * @param  mixed  $data  Response data (array, collection, or null)
     * @param  string  $message  Success message
     * @param  int  $status  HTTP status code
     */
    protected function success(
        mixed $data = null,
        string $message = 'Operasi berhasil.',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return created response with resource.
     *
     * @param  JsonResource  $resource  The created resource
     * @param  string  $message  Success message
     */
    protected function created(
        JsonResource $resource,
        string $message = 'Data berhasil dibuat.'
    ): JsonResponse {
        return $resource->response()
            ->setStatusCode(201)
            ->header('X-Message', $message);
    }

    /**
     * Return standardized error response.
     *
     * @param  string  $message  Error message
     * @param  int  $status  HTTP status code
     * @param  array<string, array<string>|string>  $errors  Validation or field errors
     */
    protected function error(
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Return deleted response.
     *
     * @param  string  $message  Success message
     */
    protected function deleted(string $message = 'Data berhasil dihapus.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Return not found response.
     *
     * @param  string  $entity  Entity name for message
     */
    protected function notFound(string $entity = 'Data'): JsonResponse
    {
        return $this->error("{$entity} tidak ditemukan.", 404);
    }

    /**
     * Return conflict response (for operations that can't proceed).
     *
     * @param  string  $message  Conflict explanation
     */
    protected function conflict(string $message): JsonResponse
    {
        return $this->error($message, 409);
    }

    /**
     * Return forbidden response.
     *
     * @param  string  $message  Permission error message
     */
    protected function forbidden(string $message = 'Akses tidak diizinkan.'): JsonResponse
    {
        return $this->error($message, 403);
    }
}
