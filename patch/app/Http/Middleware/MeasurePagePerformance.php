<?php

namespace App\Http\Middleware;

use App\Support\RequestPerformance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasurePagePerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('performance.enabled')
            || ! in_array($request->getPathInfo(), ['/', '/roadmap', '/navigate'], true)
            || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $metrics = new RequestPerformance;
        $request->attributes->set(RequestPerformance::ATTRIBUTE, $metrics);
        $started = hrtime(true);
        $response = null;
        try {
            return $response = $next($request);
        } finally {
            $elapsed = (hrtime(true) - $started) / 1e6;
            $request->attributes->remove(RequestPerformance::ATTRIBUTE);
            try {
                Log::info('canovia.performance', [
                    'path' => $request->getPathInfo(),
                    'method' => $request->method(),
                    'status' => $response?->getStatusCode(),
                    'request_id' => preg_match('/^[a-f0-9]{32}$/', (string) $request->header('X-Request-ID'))
                        ? $request->header('X-Request-ID') : null,
                    'network_retry' => $request->query('_canovia_network') === '1' || $request->query('_pk_network') === '1',
                    'navigation_preload' => $request->hasHeader('Service-Worker-Navigation-Preload'),
                    'prefetch' => str_contains(strtolower($request->header('Sec-Purpose', '').' '.$request->header('Purpose', '')), 'prefetch'),
                    'elapsed_ms' => round($elapsed, 2),
                    'query_count' => $metrics->queryCount,
                    'query_ms' => round($metrics->queryMs, 2),
                    'session_query_count' => $metrics->sessionQueries,
                    'session_query_ms' => round($metrics->sessionMs, 2),
                    'query_shapes' => $metrics->shapes,
                ]);
            } catch (\Throwable) {
                // Diagnosis must not make a normal page unavailable.
            }
        }
    }
}
