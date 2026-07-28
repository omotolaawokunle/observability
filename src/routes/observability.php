<?php

use Illuminate\Support\Facades\Route;
use Lectern\Observability\Http\Middleware\RestrictMetricsAccess;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

Route::get(config('observability.metrics.route', '/metrics'), function (CollectorRegistry $registry) {
    $renderer = new RenderTextFormat();

    return response($renderer->render($registry->getMetricFamilySamples()))
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
})->middleware(RestrictMetricsAccess::class);
