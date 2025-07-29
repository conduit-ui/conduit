<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitEnvmanager\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class EnvinitCommand extends Command
{
    protected $signature = 'env:init {--force : Overwrite existing .env file}';

    protected $description = 'Initialize .env file from .env.example template';

    public function handle(): int
    {
        $envPath = getcwd() . '/.env';
        $examplePath = getcwd() . '/.env.example';

        // Check if .env.example exists
        if (!file_exists($examplePath)) {
            $this->error('❌ .env.example file not found in current directory');
            
            if (confirm('Create a basic .env.example template?', true)) {
                $this->createExampleTemplate($examplePath);
                $this->info('✅ Created .env.example template');
            } else {
                return 1;
            }
        }

        // Check if .env already exists
        if (file_exists($envPath) && !$this->option('force')) {
            if (!confirm('⚠️  .env file already exists. Overwrite?', false)) {
                $this->info('Operation cancelled');
                return 0;
            }
        }

        // Copy .env.example to .env
        if (!copy($examplePath, $envPath)) {
            $this->error('❌ Failed to create .env file');
            return 1;
        }

        $this->info('🎉 Successfully initialized .env file from .env.example');
        
        // Ask if user wants to edit key values interactively
        if (confirm('Set environment values interactively?', true)) {
            $this->setInteractiveValues($envPath);
        }

        return 0;
    }

    private function createExampleTemplate(string $path): void
    {
        $template = <<<'ENV'
# Application
APP_NAME="My Application"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Cache & Session
CACHE_DRIVER=file
SESSION_DRIVER=file

# Mail
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
ENV;

        file_put_contents($path, $template);
    }

    private function setInteractiveValues(string $envPath): void
    {
        $content = file_get_contents($envPath);
        $lines = explode("\n", $content);
        $modified = false;

        foreach ($lines as &$line) {
            if (empty(trim($line)) || str_starts_with(trim($line), '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $currentValue = trim($value);

                // Skip if already has a value (unless it's empty or placeholder)
                if (!empty($currentValue) && $currentValue !== '""' && $currentValue !== "''") {
                    continue;
                }

                $newValue = text(
                    label: "Set value for {$key}",
                    placeholder: $currentValue ?: 'Enter value...',
                    default: $currentValue
                );

                if ($newValue !== $currentValue) {
                    $line = "{$key}={$newValue}";
                    $modified = true;
                }
            }
        }

        if ($modified) {
            file_put_contents($envPath, implode("\n", $lines));
            $this->info('✅ Environment values updated');
        }
    }
}
