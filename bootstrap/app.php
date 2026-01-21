<?php

use App\Exceptions\Domain\DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        // Add RequestIdMiddleware to API routes for distributed tracing
        $middleware->api(prepend: [
            \App\Http\Middleware\RequestIdMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle domain exceptions with structured JSON response
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json($e->toArray(), $e->getStatusCode());
            }

            return null; // Fall through to default handler for non-JSON requests
        });

        // Handle validation exceptions with consistent API format
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid.',
                    'errors' => $e->errors(),
                ], 422);
            }

            return null;
        });

        // Handle model not found (route model binding failures)
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $model = class_basename($e->getModel());
                $modelName = match ($model) {
                    'Invoice' => 'Faktur',
                    'Bill' => 'Tagihan',
                    'Quotation' => 'Penawaran',
                    'PurchaseOrder' => 'Order pembelian',
                    'WorkOrder' => 'Perintah kerja',
                    'Product' => 'Produk',
                    'Contact' => 'Kontak',
                    'Account' => 'Akun',
                    'JournalEntry' => 'Jurnal',
                    'Payment' => 'Pembayaran',
                    'User' => 'Pengguna',
                    default => 'Data',
                };

                return response()->json([
                    'success' => false,
                    'message' => "{$modelName} tidak ditemukan.",
                ], 404);
            }

            return null;
        });

        // Handle generic not found (routes)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint tidak ditemukan.',
                ], 404);
            }

            return null;
        });
    })->create();
