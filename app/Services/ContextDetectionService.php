<?php

namespace App\Services;

/**
 * Detects the context of the current working directory
 *
 * This service identifies project types, version control systems,
 * and other contextual information that components can use to
 * provide context-aware functionality.
 */
class ContextDetectionService
{
    private string $workingDirectory;

    private ?array $cachedContext = null;

    public function __construct(?string $workingDirectory = null)
    {
        $this->workingDirectory = $workingDirectory ?? getcwd();
    }

    /**
     * Get the full context of the current directory
     */
    public function getContext(): array
    {
        if ($this->cachedContext !== null) {
            return $this->cachedContext;
        }

        $context = [
            'working_directory' => $this->workingDirectory,
            'is_git_repo' => $this->isGitRepository(),
            'git' => $this->getGitContext(),
            'project_type' => $this->detectProjectType(),
            'languages' => $this->detectLanguages(),
            'frameworks' => $this->detectFrameworks(),
            'package_managers' => $this->detectPackageManagers(),
            'ci_cd' => $this->detectCiCdTools(),
            'containers' => $this->detectContainerization(),
        ];

        $this->cachedContext = $context;

        return $context;
    }

    /**
     * Check if current directory is a Git repository
     */
    public function isGitRepository(): bool
    {
        return is_dir($this->workingDirectory.'/.git');
    }

    /**
     * Get Git-specific context information
     */
    public function getGitContext(): ?array
    {
        if (! $this->isGitRepository()) {
            return null;
        }

        $context = [
            'current_branch' => $this->getGitBranch(),
            'remote_url' => $this->getGitRemoteUrl(),
            'is_github' => false,
            'github_owner' => null,
            'github_repo' => null,
            'has_uncommitted_changes' => $this->hasUncommittedChanges(),
        ];

        // Parse GitHub information from remote URL
        if ($context['remote_url']) {
            $githubInfo = $this->parseGitHubUrl($context['remote_url']);
            if ($githubInfo) {
                $context['is_github'] = true;
                $context['github_owner'] = $githubInfo['owner'];
                $context['github_repo'] = $githubInfo['repo'];
            }
        }

        return $context;
    }

    /**
     * Get current Git branch
     */
    private function getGitBranch(): ?string
    {
        $headFile = $this->workingDirectory.'/.git/HEAD';
        if (! file_exists($headFile)) {
            return null;
        }

        $head = trim(file_get_contents($headFile));
        if (preg_match('/^ref: refs\/heads\/(.+)$/', $head, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get Git remote URL
     */
    private function getGitRemoteUrl(): ?string
    {
        $configFile = $this->workingDirectory.'/.git/config';
        if (! file_exists($configFile)) {
            return null;
        }

        // Read the config file manually since parse_ini_file doesn't handle git config format well
        $content = file_get_contents($configFile);

        // Look for [remote "origin"] section and extract URL
        if (preg_match('/\[remote\s+"origin"\]\s*\n\s*url\s*=\s*(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Parse GitHub URL to extract owner and repository
     */
    private function parseGitHubUrl(string $url): ?array
    {
        // Handle both HTTPS and SSH URLs
        $patterns = [
            '/^https:\\/\\/github\\.com\\/([^\\/]+)\\/([^\\/]+?)(\\.git)?$/',
            '/^git@github\\.com:([^\\/]+)\\/([^\\/]+?)(\\.git)?$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'owner' => $matches[1],
                    'repo' => $matches[2],
                ];
            }
        }

        return null;
    }

    /**
     * Check for uncommitted changes
     */
    private function hasUncommittedChanges(): bool
    {
        $gitDir = $this->workingDirectory.'/.git';
        if (! is_dir($gitDir)) {
            return false;
        }

        // Simple check - more sophisticated implementation would use git commands
        $indexFile = $gitDir.'/index';
        if (file_exists($indexFile)) {
            // Check if index is newer than HEAD
            $headFile = $gitDir.'/HEAD';
            if (file_exists($headFile)) {
                return filemtime($indexFile) > filemtime($headFile);
            }
        }

        return false;
    }

    /**
     * Detect the type of project
     */
    public function detectProjectType(): ?string
    {
        $indicators = [
            'laravel' => ['artisan', 'composer.json' => ['laravel/framework']],
            'symfony' => ['bin/console', 'composer.json' => ['symfony/framework-bundle']],
            'wordpress' => ['wp-config.php', 'wp-content'],
            'drupal' => ['core/lib/Drupal.php', 'composer.json' => ['drupal/core']],
            'node' => ['package.json'],
            'python' => ['setup.py', 'pyproject.toml', 'requirements.txt'],
            'ruby' => ['Gemfile', 'Rakefile'],
            'rust' => ['Cargo.toml'],
            'go' => ['go.mod'],
            'java' => ['pom.xml', 'build.gradle'],
            'dotnet' => ['*.csproj', '*.sln'],
        ];

        foreach ($indicators as $type => $files) {
            if ($this->checkProjectIndicators($files)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Check for project type indicators
     */
    private function checkProjectIndicators(array $indicators): bool
    {
        foreach ($indicators as $key => $value) {
            if (is_string($value)) {
                // Simple file check
                if ($this->fileExists($value)) {
                    return true;
                }
            } elseif (is_array($value)) {
                // Check composer.json for specific packages
                if ($key === 'composer.json' && file_exists($this->workingDirectory.'/composer.json')) {
                    $composer = json_decode(file_get_contents($this->workingDirectory.'/composer.json'), true);
                    $allDeps = array_merge(
                        $composer['require'] ?? [],
                        $composer['require-dev'] ?? []
                    );

                    foreach ($value as $package) {
                        if (isset($allDeps[$package])) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Detect programming languages used
     */
    public function detectLanguages(): array
    {
        $languages = [];

        $extensions = [
            'php' => ['*.php'],
            'javascript' => ['*.js', '*.jsx', '*.mjs'],
            'typescript' => ['*.ts', '*.tsx'],
            'python' => ['*.py'],
            'ruby' => ['*.rb'],
            'go' => ['*.go'],
            'rust' => ['*.rs'],
            'java' => ['*.java'],
            'csharp' => ['*.cs'],
            'cpp' => ['*.cpp', '*.cc', '*.cxx'],
            'c' => ['*.c', '*.h'],
        ];

        foreach ($extensions as $language => $patterns) {
            foreach ($patterns as $pattern) {
                if ($this->hasFilesMatching($pattern)) {
                    $languages[] = $language;
                    break;
                }
            }
        }

        return $languages;
    }

    /**
     * Detect frameworks in use
     */
    public function detectFrameworks(): array
    {
        $frameworks = [];

        // PHP Frameworks
        if (file_exists($this->workingDirectory.'/artisan')) {
            $frameworks[] = 'laravel';
        }
        if (file_exists($this->workingDirectory.'/bin/console')) {
            $frameworks[] = 'symfony';
        }

        // JavaScript Frameworks
        if (file_exists($this->workingDirectory.'/package.json')) {
            $package = json_decode(file_get_contents($this->workingDirectory.'/package.json'), true);
            $deps = array_merge(
                array_keys($package['dependencies'] ?? []),
                array_keys($package['devDependencies'] ?? [])
            );

            $jsFrameworks = [
                'react' => 'react',
                'vue' => 'vue',
                'angular' => '@angular/core',
                'next' => 'next',
                'nuxt' => 'nuxt',
                'express' => 'express',
                'nestjs' => '@nestjs/core',
            ];

            foreach ($jsFrameworks as $framework => $package) {
                if (in_array($package, $deps)) {
                    $frameworks[] = $framework;
                }
            }
        }

        return $frameworks;
    }

    /**
     * Detect package managers
     */
    public function detectPackageManagers(): array
    {
        $managers = [];

        $indicators = [
            'composer' => ['composer.json', 'composer.lock'],
            'npm' => ['package.json', 'package-lock.json'],
            'yarn' => ['yarn.lock'],
            'pnpm' => ['pnpm-lock.yaml'],
            'pip' => ['requirements.txt', 'Pipfile'],
            'bundler' => ['Gemfile', 'Gemfile.lock'],
            'cargo' => ['Cargo.toml', 'Cargo.lock'],
            'maven' => ['pom.xml'],
            'gradle' => ['build.gradle', 'build.gradle.kts'],
        ];

        foreach ($indicators as $manager => $files) {
            foreach ($files as $file) {
                if (file_exists($this->workingDirectory.'/'.$file)) {
                    $managers[] = $manager;
                    break;
                }
            }
        }

        return $managers;
    }

    /**
     * Detect CI/CD tools
     */
    public function detectCiCdTools(): array
    {
        $tools = [];

        $indicators = [
            'github_actions' => ['.github/workflows'],
            'gitlab_ci' => ['.gitlab-ci.yml'],
            'jenkins' => ['Jenkinsfile'],
            'travis' => ['.travis.yml'],
            'circle_ci' => ['.circleci/config.yml'],
            'bitbucket' => ['bitbucket-pipelines.yml'],
        ];

        foreach ($indicators as $tool => $files) {
            foreach ($files as $file) {
                if ($this->fileExists($file)) {
                    $tools[] = $tool;
                }
            }
        }

        return $tools;
    }

    /**
     * Detect containerization
     */
    public function detectContainerization(): array
    {
        $containers = [];

        if ($this->fileExists('Dockerfile') || $this->fileExists('dockerfile')) {
            $containers[] = 'docker';
        }

        if ($this->fileExists('docker-compose.yml') || $this->fileExists('docker-compose.yaml')) {
            $containers[] = 'docker-compose';
        }

        if ($this->fileExists('kubernetes.yaml') || $this->fileExists('k8s.yaml') || is_dir($this->workingDirectory.'/k8s')) {
            $containers[] = 'kubernetes';
        }

        return $containers;
    }

    /**
     * Check if a file exists (supports wildcards)
     */
    private function fileExists(string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return file_exists($this->workingDirectory.'/'.$pattern) ||
                   is_dir($this->workingDirectory.'/'.$pattern);
        }

        return $this->hasFilesMatching($pattern);
    }

    /**
     * Check if files matching pattern exist
     */
    private function hasFilesMatching(string $pattern): bool
    {
        $files = glob($this->workingDirectory.'/'.$pattern);

        return ! empty($files);
    }

    /**
     * Get activation events based on current context
     */
    public function getActivationEvents(): array
    {
        $events = [];
        $context = $this->getContext();

        // Git events
        if ($context['is_git_repo']) {
            $events[] = 'context:git';
            if ($context['git']['is_github']) {
                $events[] = 'context:github';
            }
        }

        // Project type events
        if ($context['project_type']) {
            $events[] = 'context:'.$context['project_type'];
        }

        // Language events
        foreach ($context['languages'] as $language) {
            $events[] = 'language:'.$language;
        }

        // Framework events
        foreach ($context['frameworks'] as $framework) {
            $events[] = 'framework:'.$framework;
        }

        return $events;
    }
}
