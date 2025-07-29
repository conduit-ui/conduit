<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitEnvmanager\Commands;

use Illuminate\Console\Command;

class EnvGetCommand extends Command
{
    protected $signature = 'env:get {key? : Environment variable key} {--all : Show all variables} {--json : Output as JSON}';

    protected $description = 'Get environment variable from .env file';

    public function handle(): int
    {
        $envPath = getcwd() . '/.env';

        if (!file_exists($envPath)) {
            $this->error('❌ .env file not found');
            return 1;
        }

        $content = file_get_contents($envPath);
        $envVars = $this->parseEnvFile($content);

        if ($this->option('all')) {
            return $this->showAllVars($envVars);
        }

        $key = $this->argument('key');
        if (!$key) {
            $this->error('❌ Please specify a key or use --all flag');
            return 1;
        }

        if (!isset($envVars[$key])) {
            $this->error("❌ Environment variable '{$key}' not found");
            return 1;
        }

        if ($this->option('json')) {
            $this->line(json_encode([$key => $envVars[$key]], JSON_PRETTY_PRINT));
        } else {
            $this->info("{$key}={$envVars[$key]}");
        }

        return 0;
    }

    private function parseEnvFile(string $content): array
    {
        $vars = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $vars[trim($key)] = trim($value);
            }
        }

        return $vars;
    }

    private function showAllVars(array $envVars): int
    {
        if (empty($envVars)) {
            $this->info('No environment variables found');
            return 0;
        }

        if ($this->option('json')) {
            $this->line(json_encode($envVars, JSON_PRETTY_PRINT));
        } else {
            $this->info('Environment Variables:');
            $this->newLine();
            
            foreach ($envVars as $key => $value) {
                // Hide sensitive values
                $displayValue = $this->shouldHideValue($key) ? '***' : $value;
                $this->line("  <fg=green>{$key}</> = {$displayValue}");
            }
        }

        return 0;
    }

    private function shouldHideValue(string $key): bool
    {
        $sensitiveKeys = [
            'PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'PASS', 'PRIVATE',
            'DB_PASSWORD', 'APP_KEY', 'JWT_SECRET', 'API_KEY'
        ];

        foreach ($sensitiveKeys as $sensitive) {
            if (str_contains(strtoupper($key), $sensitive)) {
                return true;
            }
        }

        return false;
    }
}