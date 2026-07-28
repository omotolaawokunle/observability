<?php

namespace Lectern\Observability\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lectern\Observability\Services\Metrics;
use Symfony\Component\HttpFoundation\Response;

class RecordRequestMetrics
{
    public function __construct(protected Metrics $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('observability.metrics.enabled')) {
            return $next($request);
        }
        
        $start = microtime(true);

        $response = $next($request);

        $route = $request->route()?->getName() ?? $request->path();

        $this->metrics->requestDuration(
            route: $route,
            method: $request->method(),
            status: $response->getStatusCode(),
            seconds: microtime(true) - $start,
        );

        return $response;
    }
}
