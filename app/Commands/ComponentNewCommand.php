<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class ComponentNewCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'component:new {name? : Component name}';

    protected $description = 'Scaffold a new Conduit component with proper structure and GitHub setup';

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => 'Component name (e.g., env-manager, spotify-client)',
        ];
    }

    public function handle(): int
    {
        $this->info('🚀 Creating new Conduit component...');

        // Get component name (PromptsForMissingInput handles this automatically)
        $name = $this->argument('name');

        // Validate name
        if (!preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $name)) {
            $this->error('Component name must be lowercase with hyphens (e.g., env-manager)');
            return 1;
        }

        $namespace = $this->generateNamespace($name);
        $className = $this->generateClassName($name);
        $packageName = "jordanpartridge/conduit-{$name}";

        // Get additional info
        $description = text(
            label: 'Component description',
            placeholder: 'A helpful description of what this component does',
            default: "A Conduit component for {$name} functionality"
        );

        $commands = $this->getCommands($name);

        // Create component directory
        $componentPath = base_path("conduit-components/{$name}");
        
        if (is_dir($componentPath)) {
            $this->error("Component directory already exists: {$componentPath}");
            return 1;
        }

        mkdir($componentPath, 0755, true);

        // Generate all files
        $this->generateConduitManifest($componentPath, $name, $description, $commands);
        $this->generateComposerJson($componentPath, $packageName, $description, $namespace, $commands);
        $this->generateServiceProvider($componentPath, $namespace, $className, $commands);
        $this->generateReadme($componentPath, $name, $description, $commands);
        $this->generateClaudeMd($componentPath, $name, $description);
        $this->generateLicense($componentPath);
        $this->generateGitIgnore($componentPath);
        $this->generateDirectories($componentPath);

        // Generate sample command
        if (!empty($commands)) {
            $this->generateSampleCommand($componentPath, $namespace, $commands[0], $name);
        }

        $this->info("✅ Component '{$name}' scaffolded successfully!");
        $this->line("📁 Location: {$componentPath}");
        
        // Ask about GitHub setup
        if (confirm('Create GitHub repository and push initial commit?', default: true)) {
            $this->setupGitHub($componentPath, $name, $packageName);
        }

        $this->displayNextSteps($name, $componentPath);

        return 0;
    }

    protected function generateNamespace(string $name): string
    {
        return 'JordanPartridge\\Conduit' . Str::studly(str_replace('-', '', $name));
    }

    protected function generateClassName(string $name): string
    {
        return Str::studly(str_replace('-', '', $name));
    }

    protected function getCommands(string $name): array
    {
        $suggestions = [
            "{$name}:init",
            "{$name}:configure", 
            "{$name}:list",
            "{$name}:status"
        ];

        return multiselect(
            label: 'Which commands should this component provide?',
            options: $suggestions,
            default: [$suggestions[0]]
        );
    }

    protected function generateConduitManifest(string $path, string $name, string $description, array $commands): void
    {
        $manifest = [
            'namespace' => $name,
            'name' => ucwords(str_replace('-', ' ', $name)),
            'description' => $description,
            'version' => '1.0.0',
            'commands' => $commands,
            'min_conduit_version' => '2.0.0',
            'dependencies' => [],
            'tags' => ['conduit', 'cli'],
            'author' => [
                'name' => 'Jordan Partridge',
                'email' => 'jordanpartridge@users.noreply.github.com'
            ],
            'homepage' => "https://github.com/jordanpartridge/conduit-{$name}",
            'repository' => "https://github.com/jordanpartridge/conduit-{$name}",
            'license' => 'MIT'
        ];

        file_put_contents(
            $path . '/conduit.json', 
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function generateComposerJson(string $path, string $packageName, string $description, string $namespace, array $commands): void
    {
        $commandsJson = json_encode($commands, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        $content = <<<JSON
{
    "name": "{$packageName}",
    "description": "{$description}",
    "type": "library",
    "keywords": ["conduit", "laravel", "cli", "component"],
    "license": "MIT",
    "authors": [
        {
            "name": "Jordan Partridge",
            "email": "jordanpartridge@users.noreply.github.com"
        }
    ],
    "require": {
        "php": "^8.2",
        "laravel-zero/framework": "^11.0",
        "illuminate/console": "^11.0"
    },
    "require-dev": {
        "laravel/pint": "^1.18",
        "pestphp/pest": "^3.0",
        "phpstan/phpstan": "^1.12"
    },
    "autoload": {
        "psr-4": {
            "{$namespace}\\\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "{$namespace}\\\\Tests\\\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "{$namespace}\\\\ServiceProvider"
            ]
        },
        "conduit": {
            "component": true,
            "commands": {$commandsJson}
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
JSON;

        file_put_contents($path . '/composer.json', $content);
    }

    protected function generateServiceProvider(string $path, string $namespace, string $className, array $commands): void
    {
        $srcDir = $path . '/src';
        mkdir($srcDir, 0755, true);

        $commandClasses = array_map(function($cmd) use ($namespace) {
            $cmdClass = Str::studly(str_replace([':', '-'], ['', ''], $cmd)) . 'Command';
            return "{$namespace}\\Commands\\{$cmdClass}";
        }, $commands);

        $useStatements = implode("\n", array_map(fn($class) => "use {$class};", $commandClasses));
        $commandsList = implode(",\n                ", array_map(fn($class) => $class . '::class', $commandClasses));

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
{$useStatements}

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (\$this->app->runningInConsole()) {
            \$this->commands([
                {$commandsList}
            ]);
        }
    }
}
PHP;

        file_put_contents($srcDir . '/ServiceProvider.php', $content);
    }

    protected function generateSampleCommand(string $path, string $namespace, string $commandName, string $componentName): void
    {
        $commandsDir = $path . '/src/Commands';
        mkdir($commandsDir, 0755, true);

        $className = Str::studly(str_replace([':', '-'], ['', ''], $commandName)) . 'Command';
        
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Commands;

use Illuminate\Console\Command;

class {$className} extends Command
{
    protected \$signature = '{$commandName}';

    protected \$description = 'Sample command for {$componentName} component';

    public function handle(): int
    {
        \$this->info('🚀 {$componentName} component is working!');
        \$this->line('This is a sample command. Implement your logic here.');
        
        return 0;
    }
}
PHP;

        file_put_contents($commandsDir . "/{$className}.php", $content);
    }

    protected function generateReadme(string $path, string $name, string $description, array $commands): void
    {
        $commandsList = implode("\n", array_map(fn($cmd) => "- `conduit {$cmd}`", $commands));
        
        $content = <<<MD
# Conduit {$name}

{$description}

## Installation

```bash
# Via Conduit component system
conduit components install {$name}

# Via Composer (if published)
composer require jordanpartridge/conduit-{$name}
```

## Commands

{$commandsList}

## Usage

```bash
# Example usage
conduit {$commands[0]} --help
```

## Development

```bash
# Install dependencies
composer install

# Run tests
./vendor/bin/pest

# Code formatting
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyze
```

## License

MIT License. See [LICENSE](LICENSE) for details.
MD;

        file_put_contents($path . '/README.md', $content);
    }

    protected function generateClaudeMd(string $path, string $name, string $description): void
    {
        $content = <<<MD
# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with this Conduit component.

## Component: {$name}

{$description}

## Development Commands

```bash
# Install dependencies
composer install

# Code quality and testing
./vendor/bin/pint          # Laravel PHP formatter
./vendor/bin/phpstan analyze   # Static analysis  
./vendor/bin/pest          # Run tests (Pest framework)
```

## Architecture

This is a Conduit component that follows the standard patterns:
- **ServiceProvider**: Registers commands with Laravel/Conduit
- **Commands/**: Contains CLI command implementations
- **Tests/**: Pest-based test suite

## Integration

This component integrates with Conduit through:
- Service provider auto-discovery
- Command registration via Conduit's component system
- Standard Laravel Zero command patterns

## Configuration

Add any configuration requirements here.
MD;

        file_put_contents($path . '/CLAUDE.md', $content);
    }

    protected function generateLicense(string $path): void
    {
        $year = date('Y');
        $content = <<<LICENSE
MIT License

Copyright (c) {$year} Jordan Partridge

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
LICENSE;

        file_put_contents($path . '/LICENSE', $content);
    }

    protected function generateGitIgnore(string $path): void
    {
        $content = <<<GITIGNORE
/vendor
/.idea
/.vscode
.phpunit.result.cache
.env
.phpunit.cache/
GITIGNORE;

        file_put_contents($path . '/.gitignore', $content);
    }

    protected function generateDirectories(string $path): void
    {
        mkdir($path . '/tests', 0755, true);
        mkdir($path . '/config', 0755, true);
    }

    protected function setupGitHub(string $path, string $name, string $packageName): void
    {
        // Initialize git repo
        $this->info('🔄 Initializing Git repository...');
        exec("cd {$path} && git init");
        exec("cd {$path} && git add .");
        exec("cd {$path} && git commit -m 'Initial commit: Conduit {$name} component scaffold'");

        // TODO: Add GitHub repo creation via GitHub CLI or API
        $this->line("📋 Next: Create GitHub repo manually at:");
        $this->line("   https://github.com/new");
        $this->line("   Repository name: conduit-{$name}");
        $this->line("   Then run:");
        $this->line("   cd {$path}");
        $this->line("   git remote add origin git@github.com:jordanpartridge/conduit-{$name}.git");
        $this->line("   git push -u origin main");
    }

    protected function displayNextSteps(string $name, string $path): void
    {
        $this->newLine();
        $this->info('🎉 Next steps:');
        $this->line("1. cd {$path}");
        $this->line("2. composer install");
        $this->line("3. Implement your commands in src/Commands/");
        $this->line("4. Add tests in tests/");
        $this->line("5. Test locally: Add to main Conduit's composer.json");
        $this->line("6. Publish to GitHub + Packagist when ready");
        $this->newLine();
        $this->line("📖 Documentation: Update CLAUDE.md and README.md");
    }
}