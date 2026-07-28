<?php

namespace Lectern\Observability\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class LokiHandler extends AbstractProcessingHandler
{
    public function __construct(
        protected string $endpoint,
        protected array $labels = [],
        int|string|\Monolog\Level $level = \Monolog\Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $payload = json_encode([
            'streams' => [[
                'stream' => array_merge($this->labels, [
                    'level' => strtolower($record->level->getName()),
                ]),
                'values' => [[
                    (string) ($record->datetime->getTimestamp() * 1_000_000_000),
                    $record->formatted ?? $record->message,
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $url = rtrim($this->endpoint, '/').'/loki/api/v1/push';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }
}
