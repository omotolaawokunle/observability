<?php

namespace Lectern\Observability\Console\Commands;

use Illuminate\Console\Command;

class InstallObservability extends Command
{
    protected $signature = 'observability:install {project : Short project slug, e.g. getlectern}';
    protected $description = 'Set up Loki logging and Prometheus metrics for this project';

    public function handle(): int
    {
        $project = $this->argument('project');

        $this->call('vendor:publish', ['--tag' => 'observability-config']);

        $envPath = base_path('.env');
        $envAdditions = [
            "OBSERVABILITY_PROJECT={$project}",
            "LOKI_URL=http://127.0.0.1:3100",
            "OBSERVABILITY_METRICS_ALLOWED_IPS=127.0.0.1",
            "OBSERVABILITY_REDIS_DB=5",
        ];

        file_put_contents($envPath, "\n\n# Observability\n" . implode("\n", $envAdditions) . "\n", FILE_APPEND);

        // append 'lectern-loki' to the stack channel if not already present
        $this->info("Added observability env vars for project [{$project}].");
        $this->warn("Add 'lectern-loki' to your LOG_CHANNEL stack in config/logging.php manually, e.g.:");
        $this->line("'stack' => ['driver' => 'stack', 'channels' => ['single', 'lectern-loki']]");

        return self::SUCCESS;
    }
}
