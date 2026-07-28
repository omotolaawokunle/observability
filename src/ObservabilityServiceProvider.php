<?php

namespace Lectern\Observability;

use Illuminate\Support\ServiceProvider;
use Illuminate\Log\LogManager;
use Lectern\Observability\Logging\LokiHandler;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis as PrometheusRedis;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/observability.php', 'observability');

        $this->app->singleton(CollectorRegistry::class, function () {
            PrometheusRedis::setDefaultOptions(array_merge(
                config('observability.metrics.redis'),
                ['prefix' => 'obs:' . config('observability.project') . ':']
            ));

            return new CollectorRegistry(new PrometheusRedis());
        });
        $this->app->singleton(Services\Metrics::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/observability.php' => config_path('observability.php'),
        ], 'observability-config');

        $this->commands([
            Console\Commands\InstallObservability::class,
        ]);

        $this->registerLokiChannel();
        $this->registerMetricsRoute();
        $this->app['router']->pushMiddlewareToGroup('web', Http\Middleware\RecordRequestMetrics::class);
        $this->app['router']->pushMiddlewareToGroup('api', Http\Middleware\RecordRequestMetrics::class);
        $this->registerQueueMetrics();
    }

    protected function registerLokiChannel(): void
    {
        if (! config('observability.loki.enabled')) {
            return;
        }

        $this->app->extend('log', function (LogManager $manager) {
            $manager->extend('lectern-loki', function ($app, array $config) {
                return new \Monolog\Logger('loki', [
                    new LokiHandler(
                        endpoint: config('observability.loki.endpoint'),
                        labels: [
                            'job' => 'laravel',
                            'project' => config('observability.project'),
                            'env' => config('observability.env'),
                        ],
                    ),
                ]);
            });

            return $manager;
        });
    }

    protected function registerMetricsRoute(): void
    {
        if (config('observability.metrics.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/routes/observability.php');
        }
    }

    protected function registerQueueMetrics(): void
    {
        $this->app['events']->listen(\Illuminate\Queue\Events\JobProcessed::class, function ($event) {
            $metrics = $this->app->make(Services\Metrics::class);
            $jobClass = $event->job->resolveName();

            $metrics->queueJobProcessed($jobClass, 'success');
        });

        $this->app['events']->listen(\Illuminate\Queue\Events\JobFailed::class, function ($event) {
            $metrics = $this->app->make(Services\Metrics::class);
            $jobClass = $event->job->resolveName();

            $metrics->queueJobProcessed($jobClass, 'failed');
        });
    }
}
