<?php

namespace Lectern\Observability\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictMetricsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('observability.metrics.allowed_ips', ['127.0.0.1']);

        if (! in_array($request->ip(), $allowed, true)) {
            abort(404);
        }

        return $next($request);
    }
}
