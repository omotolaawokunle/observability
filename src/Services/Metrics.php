<?php

namespace Lectern\Observability\Services;

use Prometheus\CollectorRegistry;

class Metrics
{
    public function __construct(protected CollectorRegistry $registry) {}

    public function requestDuration(string $route, string $method, int $status, float $seconds): void
    {
        $this->registry
            ->getOrRegisterHistogram(
                'laravel',
                'http_request_duration_seconds',
                'HTTP request duration in seconds',
                ['project', 'route', 'method', 'status'],
                [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
            )
            ->observe($seconds, [config('observability.project'), $route, $method, (string) $status]);
    }

    public function queueJobProcessed(string $jobClass, string $status): void
    {
        $this->registry
            ->getOrRegisterCounter(
                'laravel',
                'queue_jobs_total',
                'Total queue jobs processed',
                ['project', 'job', 'status']
            )
            ->inc([config('observability.project'), $jobClass, $status]);
    }

    public function queueJobDuration(string $jobClass, float $seconds): void
    {
        $this->registry
            ->getOrRegisterHistogram(
                'laravel',
                'queue_job_duration_seconds',
                'Queue job processing duration',
                ['project', 'job'],
                [0.1, 0.5, 1, 5, 10, 30, 60, 300]
            )
            ->observe($seconds, [config('observability.project'), $jobClass]);
    }

    /**
     * Generic counter — for arbitrary domain events.
     * e.g. $metrics->increment('voice_calls_received', ['channel' => 'sms']);
     */
    public function increment(string $name, array $labels = [], float $value = 1.0): void
    {
        $labelNames = array_keys($labels);
        array_unshift($labelNames, 'project');

        $labelValues = array_values($labels);
        array_unshift($labelValues, config('observability.project'));

        $this->registry
            ->getOrRegisterCounter(
                'laravel',
                $this->sanitize($name),
                "Custom counter: {$name}",
                $labelNames
            )
            ->incBy($value, $labelValues);
    }

    /**
     * Generic gauge — for values that go up and down (queue depth, active sessions, etc).
     * e.g. $metrics->gauge('active_calls', 3, ['provider' => 'retell']);
     */
    public function gauge(string $name, float $value, array $labels = []): void
    {
        $labelNames = array_keys($labels);
        array_unshift($labelNames, 'project');

        $labelValues = array_values($labels);
        array_unshift($labelValues, config('observability.project'));

        $this->registry
            ->getOrRegisterGauge(
                'laravel',
                $this->sanitize($name),
                "Custom gauge: {$name}",
                $labelNames
            )
            ->set($value, $labelValues);
    }

    /**
     * Generic histogram — for durations/sizes you want distribution buckets for.
     * e.g. $metrics->histogram('ocr_processing_seconds', 4.2, buckets: [1,5,10,30,60]);
     */
    public function histogram(string $name, float $value, array $labels = [], ?array $buckets = null): void
    {
        $labelNames = array_keys($labels);
        array_unshift($labelNames, 'project');

        $labelValues = array_values($labels);
        array_unshift($labelValues, config('observability.project'));

        $this->registry
            ->getOrRegisterHistogram(
                'laravel',
                $this->sanitize($name),
                "Custom histogram: {$name}",
                $labelNames,
                $buckets ?? [0.1, 0.5, 1, 2.5, 5, 10, 30, 60]
            )
            ->observe($value, $labelValues);
    }

    /**
     * Prometheus metric names must match [a-zA-Z_:][a-zA-Z0-9_:]* — guard against typos
     * like spaces or hyphens breaking the exposition format.
     */
    protected function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_:]/', '_', $name);
    }
}
