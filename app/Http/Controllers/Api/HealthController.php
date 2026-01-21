<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Health check endpoints for monitoring and orchestration.
 *
 * Provides:
 * - /health - Full health check with all dependencies
 * - /health/ready - Readiness probe (can accept traffic)
 * - /health/live - Liveness probe (process is running)
 */
class HealthController extends Controller
{
    /**
     * Full health check with all dependencies.
     *
     * Returns 200 if all checks pass, 503 if any check fails.
     */
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'version' => config('app.version', '1.0.0'),
        ], $healthy ? 200 : 503);
    }

    /**
     * Readiness probe - can the application accept traffic?
     */
    public function ready(): JsonResponse
    {
        return response()->json([
            'status' => 'ready',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Liveness probe - is the process running?
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'live',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Check database connectivity.
     *
     * @return array{status: string, latency_ms?: float, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Check cache connectivity.
     *
     * @return array{status: string, latency_ms?: float, message?: string}
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_'.time();
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => $value === 'ok' ? 'ok' : 'error',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache connection failed',
            ];
        }
    }

    /**
     * Check storage writability.
     *
     * @return array{status: string, message?: string}
     */
    private function checkStorage(): array
    {
        try {
            $path = storage_path('app/.health_check');
            file_put_contents($path, 'ok');
            $content = file_get_contents($path);
            unlink($path);

            return [
                'status' => $content === 'ok' ? 'ok' : 'error',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Storage write failed',
            ];
        }
    }
}
