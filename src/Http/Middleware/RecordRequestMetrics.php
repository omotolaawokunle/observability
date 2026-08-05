<?php

declare(strict_types=1);

namespace Lectern\Observability\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lectern\Observability\Services\Metrics;
use Lectern\Observability\Support\RouteLabelResolver;
use Symfony\Component\HttpFoundation\Response;

class RecordRequestMetrics
{
    public function __construct(
        protected Metrics $metrics,
        protected RouteLabelResolver $routeLabelResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('observability.metrics.enabled')) {
            return $next($request);
        }

        $metricsRoute = trim((string) config('observability.metrics.route', '/metrics'), '/');

        if ($metricsRoute !== '' && $request->is($metricsRoute)) {
            return $next($request);
        }

        $start = microtime(true);

        $response = $next($request);

        $this->metrics->requestDuration(
            route: $this->routeLabelResolver->resolve($request),
            method: $request->method(),
            status: $response->getStatusCode(),
            seconds: microtime(true) - $start,
        );

        return $response;
    }
}
