<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitEnvmanager\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\text;

class EnvSetCommand extends Command
{
    protected $signature = 'env:set {key? : Environment variable key} {value? : Environment variable value} {--create : Create .env if it doesn\'t exist}';

    protected $description = 'Set environment variable in .env file';

    public function handle(): int
    {
        $envPath = getcwd() . '/.env';

        // Check if .env exists
        if (!file_exists($envPath)) {
            if ($this->option('create')) {
                touch($envPath);
                $this->info('Created .env file');
            } else {
                $this->error('❌ .env file not found. Use --create flag to create one.');
                return 1;
            }
        }

        // Get key and value from arguments or prompts
        $key = $this->argument('key') ?: text('Environment variable key');
        $value = $this->argument('value') ?: text("Value for {$key}");

        // Validate key format
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            $this->error('❌ Invalid key format. Use uppercase letters, numbers, and underscores only.');
            return 1;
        }

        // Read current .env content
        $content = file_get_contents($envPath);
        $lines = explode("\n", $content);
        $found = false;

        // Look for existing key and update it
        foreach ($lines as &$line) {
            if (str_starts_with($line, "{$key}=")) {
                $oldValue = substr($line, strlen($key) + 1);
                $line = "{$key}={$value}";
                $found = true;
                $this->info("✅ Updated {$key} (was: {$oldValue})");
                break;
            }
        }

        // If key wasn't found, add it to the end
        if (!$found) {
            $lines[] = "{$key}={$value}";
            $this->info("✅ Added {$key}={$value}");
        }

        // Write back to file
        if (!file_put_contents($envPath, implode("\n", $lines))) {
            $this->error('❌ Failed to write to .env file');
            return 1;
        }

        return 0;
    }
}