<?php

namespace App\Commands;

use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ComponentNewCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'component:new {name? : Component name} {--fix : Fix/upgrade existing component} {--delete : Delete component and GitHub repo}';

    protected $description = 'Scaffold a new Conduit component with proper structure and GitHub setup';

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => 'Component name (e.g., env-manager, spotify-client)',
        ];
    }

    protected function hasComponentConfig(): bool
    {
        return config('components.github_username') &&
               config('components.php_namespace') &&
               config('components.author_email');
    }

    public function handle(): int
    {
        $this->info('🚀 Creating new Conduit component...');

        // Check if component configuration exists
        if (! $this->hasComponentConfig()) {
            $this->warn('⚠️  Component configuration not found.');
            $this->line('Please set up your GitHub username and PHP namespace preferences.');
            $this->newLine();

            if (confirm('Run component configuration setup now?', default: true)) {
                $this->call('component:config');
                $this->newLine();
            } else {
                $this->error('Component configuration is required to create components.');

                return self::FAILURE;
            }
        }

        // Get component name (PromptsForMissingInput handles this automatically)
        $name = $this->argument('name');

        // If name is still missing, prompt manually
        if (! $name) {
            $name = text(
                label: 'Component name',
                placeholder: 'e.g., env-manager, spotify-client',
                required: true,
                hint: 'Component name (lowercase with hyphens)'
            );
        }

        // Strip "conduit-" prefix if user provided it (we add it automatically)
        if (str_starts_with($name, 'conduit-')) {
            $name = substr($name, 8); // Remove "conduit-" prefix
            $this->info("Detected 'conduit-' prefix, using component name: {$name}");
        }

        // Validate name
        if (! preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $name)) {
            $this->error('Component name must be lowercase with hyphens (e.g., env-manager)');

            return 1;
        }

        // Get GitHub organization
        $organization = $this->getGitHubOrganization();

        // Validate inputs
        $this->validateComponentName($name);
        $this->validateOrganization($organization);

        $namespace = $this->generateNamespace($name, $organization);
        $className = $this->generateClassName($name);
        $packageName = "{$organization}/conduit-{$name}";

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
            if ($this->option('delete')) {
                $this->info("🗑️ Deleting component: {$name}");

                return $this->deleteComponent($componentPath, $name, $organization);
            } elseif ($this->option('fix')) {
                $this->info("🔧 Fixing existing component: {$name}");

                return $this->fixExistingComponent($componentPath, $name, $organization);
            } else {
                $this->error("Component directory already exists: {$componentPath}");
                $this->line('Use --fix flag to repair/upgrade existing component');
                $this->line('Use --delete flag to completely remove component and GitHub repo');

                return 1;
            }
        }

        if ($this->option('delete')) {
            $this->error("Component {$name} does not exist locally");
            if (confirm('Delete GitHub repo anyway?', default: false)) {
                return $this->deleteGitHubRepo($name, $organization);
            }

            return 1;
        }

        mkdir($componentPath, 0755, true);

        // Generate all files
        $this->generateConduitManifest($componentPath, $name, $description, $commands, $organization);
        $this->generateComposerJson($componentPath, $packageName, $description, $namespace, $commands, $organization);
        $this->generateServiceProvider($componentPath, $namespace, $commands);
        $this->generateReadme($componentPath, $name, $description, $commands, $organization);
        $this->generateClaudeMd($componentPath, $name, $description);
        $this->generateLicense($componentPath, $organization);
        $this->generateGitIgnore($componentPath);
        $this->generateDirectories($componentPath);

        // Generate sample command
        if (! empty($commands)) {
            $this->generateSampleCommand($componentPath, $namespace, $commands[0], $name);
        }

        $this->info("✅ Component '{$name}' scaffolded successfully!");
        $this->line("📁 Location: {$componentPath}");

        // Ask about GitHub setup
        if (confirm('Create GitHub repository and push initial commit?', default: true)) {
            $this->setupGitHub($componentPath, $name, $organization);
        }

        $this->displayNextSteps($componentPath);

        return 0;
    }

    protected function getGitHubOrganization(): string
    {
        // Try to get GitHub organizations the user has access to
        $organizations = $this->getGitHubOrganizations();

        if (empty($organizations)) {
            return text(
                label: 'GitHub username/organization',
                placeholder: 'jordanpartridge',
                default: 'jordanpartridge'
            );
        }

        return select(
            label: 'Select GitHub organization/user',
            options: $organizations,
            default: in_array('jordanpartridge', $organizations) ? 'jordanpartridge' : $organizations[0]
        );
    }

    protected function getGitHubOrganizations(): array
    {
        $this->ensureGitHubCliAvailable();

        $orgs = [];

        // Get organizations
        try {
            $process = new Process(['gh', 'api', 'user/orgs', '--jq', '.[].login']);
            $process->run();

            if ($process->isSuccessful() && $process->getOutput()) {
                $orgs = array_filter(explode("\n", trim($process->getOutput())));
            }
        } catch (\Exception $e) {
            $this->warn("Could not fetch organizations: {$e->getMessage()}");
        }

        // Add current user
        try {
            $process = new Process(['gh', 'api', 'user', '--jq', '.login']);
            $process->run();

            if ($process->isSuccessful() && $process->getOutput()) {
                $user = trim($process->getOutput());
                if ($user) {
                    array_unshift($orgs, $user);
                }
            }
        } catch (\Exception $e) {
            $this->warn("Could not fetch current user: {$e->getMessage()}");
        }

        return array_unique($orgs);
    }

    protected function generateNamespace(string $name, string $organization): string
    {
        // Use configured PHP namespace or fall back to studly case
        $configuredNamespace = config('components.php_namespace');

        if ($configuredNamespace && config('components.github_username') === $organization) {
            $orgNamespace = $configuredNamespace;
        } else {
            $orgNamespace = Str::studly(str_replace(['-', '_'], '', $organization));
        }

        return $orgNamespace.'\\Conduit'.Str::studly(str_replace('-', '', $name));
    }

    protected function generateClassName(string $name): string
    {
        return Str::studly(str_replace('-', '', $name));
    }

    protected function getCommands(string $name): array
    {
        $namespace = $this->getCommandNamespace($name);

        $suggestions = [
            'init',
            'configure',
            'list',
            'status',
        ];

        return multiselect(
            label: 'Which commands should this component provide?',
            options: $suggestions,
            default: [$suggestions[0]]
        );
    }

    protected function generateConduitManifest(string $path, string $name, string $description, array $commands, string $organization): void
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
                'name' => $this->getAuthorName($organization),
                'email' => $this->getAuthorEmail($organization),
            ],
            'homepage' => "https://github.com/{$organization}/conduit-{$name}",
            'repository' => "https://github.com/{$organization}/conduit-{$name}",
            'license' => 'MIT',
        ];

        file_put_contents(
            $path.'/conduit.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function generateComposerJson(string $path, string $packageName, string $description, string $namespace, array $commands, string $organization): void
    {
        // Properly escape namespace for JSON
        $escapedNamespace = addslashes($namespace);

        $composerData = [
            'name' => $packageName,
            'description' => $description,
            'type' => 'library',
            'keywords' => ['conduit', 'laravel', 'cli', 'component', 'conduit-component'],
            'license' => 'MIT',
            'authors' => [
                [
                    'name' => $this->getAuthorName($organization),
                    'email' => $this->getAuthorEmail($organization),
                ],
            ],
            'require' => [
                'php' => '^8.2',
                'laravel-zero/framework' => '^11.0',
                'illuminate/console' => '^11.0',
            ],
            'require-dev' => [
                'laravel/pint' => '^1.18',
                'pestphp/pest' => '^3.0',
                'phpstan/phpstan' => '^1.12',
            ],
            'autoload' => [
                'psr-4' => [
                    $namespace.'\\' => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $namespace.'\\Tests\\' => 'tests/',
                ],
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [
                        $namespace.'\\ServiceProvider',
                    ],
                ],
                'conduit' => [
                    'component' => true,
                    'commands' => $commands,
                ],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];

        $content = json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path.'/composer.json', $content);
    }

    protected function generateServiceProvider(string $path, string $namespace, array $commands): void
    {
        $srcDir = $path.'/src';
        mkdir($srcDir, 0755, true);

        $commandClasses = array_map(function ($cmd) use ($namespace) {
            $cmdClass = Str::studly(str_replace([':', '-'], ['', ''], $cmd)).'Command';

            return "{$namespace}\\Commands\\{$cmdClass}";
        }, $commands);

        $useStatements = implode("\n", array_map(fn ($class) => "use {$class};", $commandClasses));
        $commandsList = implode(",\n                ", array_map(function ($class) {
            $parts = explode('\\', $class);

            return end($parts).'::class';
        }, $commandClasses));

        $content = $this->getStub('service-provider', [
            'namespace' => $namespace,
            'useStatements' => $useStatements,
            'commandsList' => $commandsList,
        ]);

        file_put_contents($srcDir.'/ServiceProvider.php', $content);
    }

    protected function generateSampleCommand(string $path, string $namespace, string $commandName, string $componentName): void
    {
        $commandsDir = $path.'/src/Commands';
        mkdir($commandsDir, 0755, true);

        $className = Str::studly(str_replace([':', '-'], ['', ''], $commandName)).'Command';

        $content = $this->getStub('sample-command', [
            'namespace' => $namespace,
            'className' => $className,
            'commandName' => $commandName,
            'componentName' => $componentName,
        ]);

        file_put_contents($commandsDir."/{$className}.php", $content);
    }

    protected function generateReadme(string $path, string $name, string $description, array $commands, string $organization): void
    {
        $commandsList = implode("\n", array_map(fn ($cmd) => "- `conduit {$cmd}`", $commands));
        $firstCommand = $commands[0] ?? 'example';

        $content = $this->getStub('readme', [
            'name' => $name,
            'description' => $description,
            'commandsList' => $commandsList,
            'firstCommand' => $firstCommand,
            'organization' => $organization,
        ]);

        file_put_contents($path.'/README.md', $content);
    }

    protected function generateClaudeMd(string $path, string $name, string $description): void
    {
        $content = $this->getStub('claude', [
            'name' => $name,
            'description' => $description,
        ]);

        file_put_contents($path.'/CLAUDE.md', $content);
    }

    protected function generateLicense(string $path, string $organization): void
    {
        $year = date('Y');
        $content = <<<LICENSE
MIT License

Copyright (c) {$year} {$this->getAuthorName($organization)}

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

        file_put_contents($path.'/LICENSE', $content);
    }

    protected function generateGitIgnore(string $path): void
    {
        $content = <<<'GITIGNORE'
/vendor
/.idea
/.vscode
.phpunit.result.cache
.env
.phpunit.cache/
GITIGNORE;

        file_put_contents($path.'/.gitignore', $content);
    }

    protected function generateDirectories(string $path): void
    {
        mkdir($path.'/tests', 0755, true);
        mkdir($path.'/config', 0755, true);
    }

    protected function setupGitHub(string $path, string $name, string $organization): void
    {
        $this->validatePath($path);
        $this->validateComponentName($name);

        // Initialize git repo
        $this->info('🔄 Initializing Git repository...');

        try {
            $initProcess = new Process(['git', 'init'], $path);
            $initProcess->run();

            if (! $initProcess->isSuccessful()) {
                throw new \RuntimeException("Failed to initialize git repository: {$initProcess->getErrorOutput()}");
            }

            $addProcess = new Process(['git', 'add', '.'], $path);
            $addProcess->run();

            if (! $addProcess->isSuccessful()) {
                throw new \RuntimeException("Failed to add files: {$addProcess->getErrorOutput()}");
            }

            $commitMessage = "Initial commit: Conduit {$name} component scaffold";
            $commitProcess = new Process(['git', 'commit', '-m', $commitMessage], $path);
            $commitProcess->run();

            if (! $commitProcess->isSuccessful()) {
                throw new \RuntimeException("Failed to create initial commit: {$commitProcess->getErrorOutput()}");
            }

            $this->info('✅ Git repository initialized successfully');

        } catch (\Exception $e) {
            $this->error("Git initialization failed: {$e->getMessage()}");

            return;
        }

        // Create GitHub repository automatically
        $this->createGitHubRepository($path, $name, $organization);
        $this->pushToGitHub($path, $name, $organization);
        $this->createInitialRelease($path, $name, $organization);
        $this->setupPackagistPublishing($name, $organization);
    }

    protected function displayNextSteps(string $path): void
    {
        $this->newLine();
        $this->info('🎉 Next steps:');
        $this->line("1. cd {$path}");
        $this->line('2. composer install');
        $this->line('3. Implement your commands in src/Commands/');
        $this->line('4. Add tests in tests/');
        $this->line("5. Test locally: Add to main Conduit's composer.json");
        $this->line('6. Publish to GitHub + Packagist when ready');
        $this->newLine();
        $this->line('📖 Documentation: Update CLAUDE.md and README.md');
    }

    /**
     * Get stub content with replacements
     */
    protected function getStub(string $stub, array $replacements = []): string
    {
        $stubPath = base_path("stubs/component/{$stub}.stub");

        if (! file_exists($stubPath)) {
            throw new \InvalidArgumentException("Stub file not found: {$stubPath}");
        }

        $content = file_get_contents($stubPath);

        foreach ($replacements as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        return $content;
    }

    protected function getCommandNamespace(string $name): string
    {
        // Remove common suffixes to create clean command namespaces
        $cleanName = preg_replace('/-?(manager|client|service|component)$/', '', $name);

        // If nothing left after removing suffix, use original name
        return $cleanName ?: $name;
    }

    protected function validatePath(string $path): void
    {
        if (empty($path)) {
            throw new \InvalidArgumentException('Path cannot be empty');
        }

        // Prevent path traversal attacks
        $realPath = realpath(dirname($path));
        $basePath = realpath(base_path('conduit-components'));

        if (! $realPath || ! $basePath || ! str_starts_with($realPath, $basePath)) {
            throw new \InvalidArgumentException('Invalid path: path traversal detected');
        }

        // Validate path doesn't contain dangerous characters
        if (preg_match('/[<>:"|?*]/', basename($path))) {
            throw new \InvalidArgumentException('Path contains invalid characters');
        }
    }

    protected function validateComponentName(string $name): void
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Component name cannot be empty');
        }

        // Validate component name format (already done in handle but adding here for security)
        if (! preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $name)) {
            throw new \InvalidArgumentException('Component name must be lowercase with hyphens only (e.g., env-manager)');
        }

        // Prevent excessively long names
        if (strlen($name) > 50) {
            throw new \InvalidArgumentException('Component name must be 50 characters or less');
        }
    }

    protected function validateOrganization(string $organization): void
    {
        if (empty($organization)) {
            throw new \InvalidArgumentException('Organization cannot be empty');
        }

        // GitHub username/organization validation
        if (! preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]){0,38}$/', $organization)) {
            throw new \InvalidArgumentException('Invalid GitHub organization/username format');
        }
    }

    protected function ensureGitHubCliAvailable(): void
    {
        if (! $this->commandExists('gh')) {
            $this->error('GitHub CLI is required but not installed.');
            $this->line('Install from: https://cli.github.com/');
            throw new \RuntimeException('Missing GitHub CLI dependency');
        }

        if (! $this->isGitHubAuthenticated()) {
            $this->error('GitHub CLI is not authenticated.');
            $this->line('Please run: gh auth login');
            throw new \RuntimeException('GitHub CLI not authenticated');
        }
    }

    protected function commandExists(string $command): bool
    {
        $process = new Process(['which', $command]);
        $process->run();

        return $process->isSuccessful();
    }

    protected function isGitHubAuthenticated(): bool
    {
        try {
            $process = new Process(['gh', 'auth', 'status']);
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception) {
            return false;
        }
    }

    protected function getAuthorName(string $organization): string
    {
        // Try to get real name from GitHub API
        try {
            $process = new Process(['gh', 'api', "users/{$organization}", '--jq', '.name // .login']);
            $process->run();

            if ($process->isSuccessful() && $process->getOutput()) {
                $name = trim($process->getOutput());
                if ($name && $name !== 'null') {
                    return $name;
                }
            }
        } catch (\Exception) {
            // Fall through to default
        }

        // Fallback to organization name with proper formatting
        return ucwords(str_replace(['-', '_'], ' ', $organization));
    }

    protected function getAuthorEmail(string $organization): string
    {
        // Check if this is the current user and get their git config email
        try {
            $process = new Process(['gh', 'api', 'user', '--jq', '.login']);
            $process->run();

            if ($process->isSuccessful() && trim($process->getOutput()) === $organization) {
                $gitProcess = new Process(['git', 'config', 'user.email']);
                $gitProcess->run();

                if ($gitProcess->isSuccessful() && $gitProcess->getOutput()) {
                    $email = trim($gitProcess->getOutput());
                    if ($email) {
                        return $email;
                    }
                }
            }
        } catch (\Exception) {
            // Fall through to default
        }

        // Fallback to GitHub noreply email format
        return "{$organization}@users.noreply.github.com";
    }

    protected function createGitHubRepository(string $path, string $name, string $organization): void
    {
        $repoName = "conduit-{$name}";

        try {
            $this->info('🌐 Creating GitHub repository...');

            $createProcess = new Process([
                'gh', 'repo', 'create', $repoName,
                '--public',
                '--description', "Conduit component for {$name} functionality",
                '--clone=false',
            ]);
            $createProcess->run();

            if (! $createProcess->isSuccessful()) {
                throw new \RuntimeException("Failed to create GitHub repository: {$createProcess->getErrorOutput()}");
            }

            $this->info("✅ GitHub repository created: https://github.com/{$organization}/{$repoName}");

            // Add topics/tags
            $this->addGitHubTopics($repoName, $organization);

        } catch (\Exception $e) {
            $this->error("GitHub repository creation failed: {$e->getMessage()}");
            $this->line('You can create it manually at: https://github.com/new');
        }
    }

    protected function pushToGitHub(string $path, string $name, string $organization): void
    {
        $repoName = "conduit-{$name}";

        try {
            $this->info('📤 Pushing to GitHub...');

            // Add remote
            $remoteProcess = new Process([
                'git', 'remote', 'add', 'origin',
                "git@github.com:{$organization}/{$repoName}.git",
            ], $path);
            $remoteProcess->run();

            if (! $remoteProcess->isSuccessful()) {
                throw new \RuntimeException("Failed to add remote: {$remoteProcess->getErrorOutput()}");
            }

            // Push to GitHub
            $pushProcess = new Process(['git', 'push', '-u', 'origin', 'master'], $path);
            $pushProcess->run();

            if (! $pushProcess->isSuccessful()) {
                throw new \RuntimeException("Failed to push: {$pushProcess->getErrorOutput()}");
            }

            $this->info('✅ Code pushed to GitHub successfully!');
            $this->line("🔗 Repository: https://github.com/{$organization}/{$repoName}");

        } catch (\Exception $e) {
            $this->error("GitHub push failed: {$e->getMessage()}");
            $this->line('Manual push commands:');
            $this->line("   cd {$path}");
            $this->line("   git remote add origin git@github.com:{$organization}/{$repoName}.git");
            $this->line('   git push -u origin master');
        }
    }

    protected function addGitHubTopics(string $repoName, string $organization): void
    {
        try {
            $this->info('🏷️ Adding repository topics...');

            $topics = [
                'conduit',
                'conduit-component',
                'laravel',
                'cli',
                'php',
                'laravel-zero',
            ];

            // Use Process with stdin for JSON input
            $topicsProcess = new Process([
                'gh', 'api', "repos/{$organization}/{$repoName}/topics",
                '--method', 'PUT',
                '--input', '-',
            ]);
            $topicsProcess->setInput(json_encode(['names' => $topics]));
            $topicsProcess->run();

            if ($topicsProcess->isSuccessful()) {
                $this->info('✅ Repository topics added successfully');
            } else {
                $this->warn("Could not add topics: {$topicsProcess->getErrorOutput()}");
            }

        } catch (\Exception $e) {
            $this->warn("Failed to add repository topics: {$e->getMessage()}");
        }
    }

    protected function fixExistingComponent(string $componentPath, string $name, string $organization): int
    {
        $this->info('🔍 Analyzing component for issues...');

        $issues = $this->detectComponentIssues($componentPath, $name);

        if (empty($issues)) {
            $this->info('✅ Component looks good! No issues detected.');

            return 0;
        }

        $this->warn('Found '.count($issues).' issue(s):');
        foreach ($issues as $issue) {
            $this->line("  - {$issue}");
        }

        if (! confirm('Fix these issues?', default: true)) {
            return 0;
        }

        $this->applyComponentFixes($componentPath, $name, $organization, $issues);

        $this->info('✅ Component fixed successfully!');

        return 0;
    }

    protected function detectComponentIssues(string $componentPath, string $name): array
    {
        $issues = [];

        // Check if conduit.json exists and has proper structure
        $manifestPath = $componentPath.'/conduit.json';
        if (! file_exists($manifestPath)) {
            $issues[] = 'Missing conduit.json manifest file';
        } else {
            $manifest = json_decode(file_get_contents($manifestPath), true);

            if (! isset($manifest['namespace'])) {
                $issues[] = 'Missing namespace in conduit.json';
            }

            if (! isset($manifest['commands']) || empty($manifest['commands'])) {
                $issues[] = 'Missing or empty commands array in conduit.json';
            } else {
                // Check if commands use old naming pattern
                $namespace = $this->getCommandNamespace($name);
                foreach ($manifest['commands'] as $command) {
                    if (str_starts_with($command, "{$name}:") && ! str_starts_with($command, "{$namespace}:")) {
                        $issues[] = "Commands using old naming pattern (should be {$namespace}:* not {$name}:*)";
                        break;
                    }
                }
            }

            if (! isset($manifest['author'])) {
                $issues[] = 'Missing author information in conduit.json';
            }
        }

        // Check composer.json
        $composerPath = $componentPath.'/composer.json';
        if (! file_exists($composerPath)) {
            $issues[] = 'Missing composer.json file';
        } else {
            $composer = json_decode(file_get_contents($composerPath), true);

            if (! isset($composer['extra']['conduit'])) {
                $issues[] = 'Missing Conduit configuration in composer.json';
            }

            if (str_contains($composer['name'] ?? '', 'jordanpartridge/') && ! str_contains($composer['name'], 'jordanpartridge/conduit-')) {
                $issues[] = "Package name doesn't follow jordanpartridge/conduit-* pattern";
            }
        }

        // Check for service provider
        $serviceProviderPath = $componentPath.'/src/ServiceProvider.php';
        if (! file_exists($serviceProviderPath)) {
            $issues[] = 'Missing ServiceProvider.php';
        }

        // Check for README with proper organization
        $readmePath = $componentPath.'/README.md';
        if (! file_exists($readmePath)) {
            $issues[] = 'Missing README.md file';
        } else {
            $readme = file_get_contents($readmePath);
            if (str_contains($readme, 'jordanpartridge/conduit-') && ! str_contains($readme, "jordanpartridge/conduit-{$name}")) {
                $issues[] = 'README has incorrect package references';
            }
        }

        return $issues;
    }

    protected function applyComponentFixes(string $componentPath, string $name, string $organization, array $issues): void
    {
        $this->validateComponentName($name);
        $this->validateOrganization($organization);

        $namespace = $this->generateNamespace($name, $organization);
        $className = $this->generateClassName($name);
        $packageName = "{$organization}/conduit-{$name}";

        // Get updated command list with proper namespace
        $commandNamespace = $this->getCommandNamespace($name);
        $commands = ["{$commandNamespace}:init"];

        $description = "A Conduit component for {$name} functionality";

        // Fix each detected issue
        foreach ($issues as $issue) {
            if (str_contains($issue, 'conduit.json')) {
                $this->info('🔧 Fixing conduit.json...');
                $this->generateConduitManifest($componentPath, $name, $description, $commands, $organization);
            }

            if (str_contains($issue, 'composer.json')) {
                $this->info('🔧 Fixing composer.json...');
                $this->generateComposerJson($componentPath, $packageName, $description, $namespace, $commands, $organization);
            }

            if (str_contains($issue, 'ServiceProvider')) {
                $this->info('🔧 Fixing ServiceProvider...');
                $this->generateServiceProvider($componentPath, $namespace, $commands);
            }

            if (str_contains($issue, 'README')) {
                $this->info('🔧 Fixing README...');
                $this->generateReadme($componentPath, $name, $description, $commands, $organization);
            }

            if (str_contains($issue, 'old naming pattern')) {
                $this->info('🔧 Updating command naming...');
                $this->updateCommandNaming($componentPath, $name, $commandNamespace);
            }
        }

        // Ensure all standard files exist
        if (! file_exists($componentPath.'/CLAUDE.md')) {
            $this->generateClaudeMd($componentPath, $name, $description);
        }

        if (! file_exists($componentPath.'/LICENSE')) {
            $this->generateLicense($componentPath, $organization);
        }

        if (! file_exists($componentPath.'/.gitignore')) {
            $this->generateGitIgnore($componentPath);
        }
    }

    protected function updateCommandNaming(string $componentPath, string $name, string $newNamespace): void
    {
        // Update any existing command files
        $commandsDir = $componentPath.'/src/Commands';
        if (is_dir($commandsDir)) {
            $files = glob($commandsDir.'/*.php');
            foreach ($files as $file) {
                $content = file_get_contents($file);

                // Update command signatures
                $oldPattern = "'{$name}:";
                $newPattern = "'{$newNamespace}:";

                if (str_contains($content, $oldPattern)) {
                    $content = str_replace($oldPattern, $newPattern, $content);
                    file_put_contents($file, $content);
                    $this->line('  Updated command signatures in '.basename($file));
                }
            }
        }
    }

    protected function createInitialRelease(string $path, string $name, string $organization): void
    {
        $repoName = "conduit-{$name}";

        try {
            $this->info('🏷️ Creating initial release v1.0.0...');

            // Create and push tag
            $tagProcess = new Process(['git', 'tag', 'v1.0.0'], $path);
            $tagProcess->run();

            if (! $tagProcess->isSuccessful()) {
                throw new \RuntimeException("Failed to create tag: {$tagProcess->getErrorOutput()}");
            }

            $pushTagProcess = new Process(['git', 'push', 'origin', 'v1.0.0'], $path);
            $pushTagProcess->run();

            if (! $pushTagProcess->isSuccessful()) {
                throw new \RuntimeException("Failed to push tag: {$pushTagProcess->getErrorOutput()}");
            }

            // Create GitHub release
            $releaseProcess = new Process([
                'gh', 'release', 'create', 'v1.0.0',
                '--repo', "{$organization}/{$repoName}",
                '--title', 'Initial Release v1.0.0',
                '--notes', "🚀 Initial release of the {$name} Conduit component.\n\nThis component provides {$name} functionality for Conduit CLI applications.\n\n## Installation\n\n```bash\ncomposer require {$organization}/{$repoName}\n```",
            ]);
            $releaseProcess->run();

            if ($releaseProcess->isSuccessful()) {
                $this->info('✅ GitHub release v1.0.0 created successfully!');
            } else {
                $this->warn("Could not create GitHub release: {$releaseProcess->getErrorOutput()}");
            }

        } catch (\Exception $e) {
            $this->warn("Failed to create release: {$e->getMessage()}");
        }
    }

    protected function setupPackagistPublishing(string $name, string $organization): void
    {
        $repoName = "conduit-{$name}";
        $packageName = "{$organization}/{$repoName}";
        $repoUrl = "https://github.com/{$organization}/{$repoName}";

        $this->info('📦 Setting up Packagist publishing...');

        // Setup Packagist webhook for future automation
        if ($this->setupPackagistWebhook($name, $organization)) {
            $this->info('✅ Packagist webhook configured for auto-updates!');
        }

        // For now, manual submission required

        // Provide manual instructions
        $this->warn('📦 Manual Packagist setup required:');
        $this->line('');
        $this->line('1. Visit: https://packagist.org/packages/submit');
        $this->line("2. Enter repository URL: {$repoUrl}");
        $this->line('3. Click "Check" then "Submit"');
        $this->line('');
        $this->line('Or run this command if you have packagist CLI:');
        $this->line("   packagist submit {$repoUrl}");
        $this->line('');

        // Auto-open browser with prefilled URL (try multiple common parameter names)
        if ($this->commandExists('open')) {
            $this->line('Opening Packagist submit page with prefilled URL...');
            $submitUrl = 'https://packagist.org/packages/submit?url='.urlencode($repoUrl);
            $openProcess = new Process(['open', $submitUrl]);
            $openProcess->run();

            // Also copy URL to clipboard for easy pasting
            $this->copyToClipboard($repoUrl);
            $this->line("📋 Repository URL copied to clipboard: {$repoUrl}");
        }

        $this->info("🎉 Component {$name} is ready for use!");
        $this->line("📚 Documentation: https://github.com/{$organization}/{$repoName}");
        $this->line("🚀 Once on Packagist: conduit components install {$repoName}");
    }

    protected function attemptPackagistSubmission(string $packageName, string $repoUrl): bool
    {
        if (! confirm('Attempt automated Packagist submission via browser automation?', default: true)) {
            return false;
        }

        try {
            $this->info('🤖 Starting automated Packagist submission...');

            return $this->automatePackagistSubmission($repoUrl);
        } catch (\Exception $e) {
            $this->warn("Browser automation failed: {$e->getMessage()}");

            return false;
        }
    }

    protected function automatePackagistSubmission(string $repoUrl): bool
    {
        $this->info('📦 Manual Packagist submission required:');
        $this->line('   1. Visit: https://packagist.org/packages/submit');
        $this->line("   2. Enter repository URL: {$repoUrl}");
        $this->line("   3. Click 'Check' then 'Submit'");
        $this->newLine();

        return confirm('Have you submitted the package to Packagist?', default: false);
    }

    protected function setupPackagistWebhook(string $name, string $organization): bool
    {
        $repoName = "conduit-{$name}";

        try {
            $this->info('🔗 Setting up Packagist webhook...');

            // Get Packagist username from config or default to organization
            $packagistUser = config('packagist.username', $organization);
            $webhookUrl = "https://packagist.org/api/github?username={$packagistUser}";

            $webhookProcess = new Process([
                'gh', 'api', "repos/{$organization}/{$repoName}/hooks",
                '--method', 'POST',
                '--field', 'name=web',
                '--field', 'active=true',
                '--field', 'events[]=push',
                '--field', 'events[]=release',
                '--field', "config[url]={$webhookUrl}",
                '--field', 'config[content_type]=json',
            ]);
            $webhookProcess->run();

            if ($webhookProcess->isSuccessful()) {
                $this->info('🎉 GitHub webhook configured for Packagist auto-updates!');

                return true;
            } else {
                $this->warn("Could not setup webhook: {$webhookProcess->getErrorOutput()}");

                return false;
            }

        } catch (\Exception $e) {
            $this->warn("Webhook setup failed: {$e->getMessage()}");

            return false;
        }
    }

    protected function deleteComponent(string $componentPath, string $name, string $organization): int
    {
        $this->warn('⚠️  This will permanently delete:');
        $this->line("   - Local component directory: {$componentPath}");
        $this->line("   - GitHub repository: https://github.com/{$organization}/conduit-{$name}");
        $this->line('   - All git history and releases');
        $this->newLine();

        if (! confirm('Are you absolutely sure you want to delete everything?', default: false)) {
            $this->info('Deletion cancelled.');

            return 0;
        }

        // Delete GitHub repo first
        $this->deleteGitHubRepo($name, $organization);

        // Delete local directory
        $this->info('🗑️ Deleting local component directory...');

        try {
            $this->deleteDirectory($componentPath);
            $this->info('✅ Local component deleted successfully!');
        } catch (\Exception $e) {
            $this->error("Failed to delete local directory: {$e->getMessage()}");

            return 1;
        }

        $this->info("🎉 Component {$name} completely removed!");

        return 0;
    }

    protected function deleteGitHubRepo(string $name, string $organization): int
    {
        $repoName = "conduit-{$name}";

        try {
            $this->ensureGitHubCliAvailable();

            $this->info("🗑️ Deleting GitHub repository: {$organization}/{$repoName}");

            $deleteProcess = new Process([
                'gh', 'repo', 'delete', "{$organization}/{$repoName}",
                '--yes',
            ]);
            $deleteProcess->run();

            if ($deleteProcess->isSuccessful()) {
                $this->info('✅ GitHub repository deleted successfully!');

                return 0;
            } else {
                $this->error("Failed to delete GitHub repository: {$deleteProcess->getErrorOutput()}");

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("GitHub deletion failed: {$e->getMessage()}");

            return 1;
        }
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($path);
    }

    protected function copyToClipboard(string $text): void
    {
        try {
            if ($this->commandExists('pbcopy')) {
                $process = new Process(['pbcopy']);
                $process->setInput($text);
                $process->run();
            } elseif ($this->commandExists('xclip')) {
                $process = new Process(['xclip', '-selection', 'clipboard']);
                $process->setInput($text);
                $process->run();
            }
        } catch (\Exception) {
            // Clipboard copy failed, but not critical
        }
    }
}
