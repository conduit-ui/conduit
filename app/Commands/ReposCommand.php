<?php

namespace App\Commands;

use App\Services\GithubAuthService;
use JordanPartridge\GithubClient\Enums\Direction;
use JordanPartridge\GithubClient\Enums\Sort;
use JordanPartridge\GithubClient\Enums\Visibility;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class ReposCommand extends Command
{
    protected $signature = 'repos 
                            {--format=interactive : Output format (interactive, json, table)}
                            {--visibility=all : Repository visibility (all, public, private)}
                            {--sort=updated : Sort by (updated, created, pushed, full_name)}
                            {--direction=desc : Sort direction (asc, desc)}
                            {--limit=50 : Number of repositories to fetch}
                            {--search= : Search repositories by name}
                            {--repo= : Show specific repository (owner/repo)}
                            {--table : Shorthand for --format=table}
                            {--json : Shorthand for --format=json}';

    protected $description = 'Browse and manage repositories';

    private array $allRepos = [];

    public function handle(GithubAuthService $githubAuth): int
    {
        // Ensure we're authenticated
        if (! $githubAuth->isAuthenticated()) {
            error('❌ Not authenticated with GitHub');
            $this->info('💡 Run: gh auth login');

            return 1;
        }

        try {
            // Handle specific repo request
            if ($this->option('repo')) {
                return $this->showSpecificRepository($this->option('repo'));
            }

            $this->allRepos = $this->fetchRepositories();

            if (empty($this->allRepos)) {
                info('📭 No repositories found matching your criteria');

                return 0;
            }

            return $this->displayRepositories();

        } catch (\Exception $e) {
            error("❌ Failed to fetch repositories: {$e->getMessage()}");

            return 1;
        }
    }

    private function showSpecificRepository(string $repoSpec): int
    {
        try {
            info("📡 Fetching repository {$repoSpec}...");

            $repoValueObject = \JordanPartridge\GithubClient\ValueObjects\Repo::fromFullName($repoSpec);
            $repo = Github::repos()->get($repoValueObject);

            $this->showRepoDetailsNonInteractive($repo);

            return 0;

        } catch (\Exception $e) {
            error("❌ Failed to fetch repository {$repoSpec}: {$e->getMessage()}");

            return 1;
        }
    }

    private function fetchRepositories(): array
    {
        $visibility = $this->mapVisibility($this->option('visibility'));
        $sort = $this->mapSort($this->option('sort'));
        $direction = $this->mapDirection($this->option('direction'));
        $limit = (int) $this->option('limit');
        $searchTerm = $this->option('search');

        info('📡 Fetching repositories...');

        if ($searchTerm) {
            return $this->searchRepositories($searchTerm, $limit);
        }

        // Fetch user's repositories with pagination
        $repos = Github::repos()->allWithPagination(
            per_page: min($limit, 100),
            visibility: $visibility,
            sort: $sort,
            direction: $direction
        );

        return array_slice($repos, 0, $limit);
    }

    private function searchRepositories(string $searchTerm, int $limit): array
    {
        // Use GitHub search API for repository search
        $searchResults = Github::repos()->search(
            query: "user:@me {$searchTerm}",
            per_page: min($limit, 100)
        );

        return $searchResults->items ?? [];
    }

    private function mapVisibility(string $visibility): ?Visibility
    {
        return match ($visibility) {
            'public' => Visibility::PUBLIC,
            'private' => Visibility::PRIVATE,
            default => null, // 'all'
        };
    }

    private function mapSort(string $sort): Sort
    {
        return match ($sort) {
            'created' => Sort::CREATED,
            'updated' => Sort::UPDATED,
            'pushed' => Sort::PUSHED,
            'full_name' => Sort::FULL_NAME,
            default => Sort::UPDATED,
        };
    }

    private function mapDirection(string $direction): Direction
    {
        return match ($direction) {
            'asc' => Direction::ASC,
            'desc' => Direction::DESC,
            default => Direction::DESC,
        };
    }

    private function displayRepositories(): int
    {
        // Handle shorthand options
        $format = $this->option('format');
        if ($this->option('table')) {
            $format = 'table';
        } elseif ($this->option('json')) {
            $format = 'json';
        }

        switch ($format) {
            case 'json':
                $this->line(json_encode($this->allRepos, JSON_PRETTY_PRINT));

                return 0;

            case 'table':
                return $this->displayTable();

            case 'interactive':
            default:
                return $this->displayInteractive();
        }
    }

    private function displayTable(): int
    {
        $headers = ['Name', 'Description', 'Language', 'Stars', 'Forks', 'Updated'];
        $rows = [];

        foreach ($this->allRepos as $repo) {
            $rows[] = [
                $repo->name,
                mb_strimwidth($repo->description ?? '', 0, 40, '...'),
                $repo->language ?? 'None',
                $repo->stargazers_count,
                $repo->forks_count,
                $this->formatDate($repo->updated_at),
            ];
        }

        table($headers, $rows);

        return 0;
    }

    private function displayInteractive(): int
    {
        info('📁 Found '.count($this->allRepos).' repositories');

        // Show list of repositories with indexes
        info('📋 Available repositories:');
        foreach ($this->allRepos as $index => $repo) {
            $stars = $repo->stargazers_count > 0 ? " ⭐{$repo->stargazers_count}" : '';
            $language = $repo->language ? " ({$repo->language})" : '';
            $description = $repo->description ? ' - '.mb_strimwidth($repo->description, 0, 40, '...') : '';
            info("  {$index}: {$repo->name}{$language}{$stars}{$description}");
        }

        // If only one repo, just show it directly
        if (count($this->allRepos) === 1) {
            info('💡 Only one repository found, showing details:');
            $this->showRepoDetailsNonInteractive($this->allRepos[0]);

            return 0;
        }

        // For multiple repos, show actionable commands rather than trying interactive prompts
        $this->newLine();
        info('💡 To view a specific repository:');
        foreach ($this->allRepos as $index => $repo) {
            info("   conduit repos --repo={$repo->full_name}");
        }

        info('💡 Or use table format: conduit repos --format=table'.($this->option('search') ? ' --search='.$this->option('search') : ''));

        return 0;
    }

    private function showRepoDetailsNonInteractive($repo): void
    {
        $this->newLine();
        info("📁 {$repo->full_name}");

        if ($repo->description) {
            $this->line("📝 {$repo->description}");
        }

        $this->line("🔗 {$repo->html_url}");
        $this->line('👀 Language: '.($repo->language ?? 'None'));
        $this->line("⭐ Stars: {$repo->stargazers_count}");
        $this->line("🍴 Forks: {$repo->forks_count}");
        $this->line('📅 Updated: '.$this->formatDate($repo->updated_at));
        $this->line("👤 Owner: {$repo->owner->login}");
        $this->line('🔒 Visibility: '.($repo->private ? 'Private' : 'Public'));

        if ($repo->topics) {
            $this->line('🏷️  Topics: '.implode(', ', $repo->topics));
        }

        $this->newLine();

        // Show available actions (but don't prompt for selection)
        info('💡 Available actions (use specific commands):');
        $this->line("   • conduit prs --repo={$repo->full_name}");
        $this->line("   • conduit issues --repo={$repo->full_name}");
        $this->line("   • conduit status --repo={$repo->full_name}");
        $this->line("   • open {$repo->html_url}");
    }

    private function openInBrowser(string $url): void
    {
        $os = PHP_OS_FAMILY;
        try {
            switch ($os) {
                case 'Darwin':
                    shell_exec("open '{$url}' > /dev/null 2>&1");
                    break;
                case 'Windows':
                    shell_exec("start '{$url}' > /dev/null 2>&1");
                    break;
                case 'Linux':
                    shell_exec("xdg-open '{$url}' > /dev/null 2>&1");
                    break;
            }
            info('🌐 Opened in browser');
        } catch (\Exception $e) {
            error('Failed to open browser');
        }
    }

    private function cloneRepository($repo): void
    {
        $cloneUrl = $repo->clone_url;

        // Prefer SSH if we can detect it
        if ($this->prefersSsh()) {
            $cloneUrl = $repo->ssh_url;
        }

        $defaultDir = './'.$repo->name;
        $directory = text('Clone to directory', default: $defaultDir);

        if (is_dir($directory)) {
            if (! confirm("Directory '{$directory}' exists. Continue?")) {
                return;
            }
        }

        info("📥 Cloning {$repo->full_name}...");

        $command = "git clone '{$cloneUrl}' '{$directory}'";
        $output = shell_exec("{$command} 2>&1");

        if (strpos($output, 'fatal') !== false || strpos($output, 'error') !== false) {
            error("Failed to clone: {$output}");

            return;
        }

        info("✅ Successfully cloned to '{$directory}'");

        if (confirm('Change to directory and explore?', true)) {
            // Show what they can do next
            $this->newLine();
            info('🚀 Next steps:');
            $this->line("   cd '{$directory}'");
            $this->line('   conduit status    # See full project status');
            $this->line('   conduit prs       # View pull requests');
            $this->line('   conduit issues    # Manage issues');

            if (confirm('Launch status now?', true)) {
                // Change working directory context and launch status
                $originalDir = getcwd();
                chdir($directory);

                $this->call('status', [
                    '--format' => 'interactive',
                    '--include-repo-stats' => true,
                    '--include-prs' => true,
                ]);

                chdir($originalDir);
            }
        }
    }

    private function prefersSsh(): bool
    {
        // Check if user has SSH keys configured
        $sshConfig = shell_exec('git config --get user.email 2>/dev/null');
        $hasKeys = file_exists(expanduser('~/.ssh/id_rsa')) || file_exists(expanduser('~/.ssh/id_ed25519'));

        return $hasKeys && ! empty($sshConfig);
    }

    private function viewPullRequests($repo): void
    {
        info("🔀 Launching pull requests for {$repo->full_name}...");

        // Call the prs command with the specific repo
        $this->call('prs', [
            '--repo' => $repo->full_name,
            '--format' => 'interactive',
        ]);
    }

    private function viewIssues($repo): void
    {
        info("🐛 Launching issues for {$repo->full_name}...");

        // Call the issues command with the specific repo
        $this->call('issues', [
            '--repo' => $repo->full_name,
            '--format' => 'interactive',
        ]);
    }

    private function viewStatus($repo): void
    {
        info("📊 Launching comprehensive status for {$repo->full_name}...");

        // Call the status command with the specific repo
        $this->call('status', [
            '--repo' => $repo->full_name,
            '--format' => 'interactive',
            '--include-repo-stats' => true,
            '--include-prs' => true,
        ]);
    }

    private function formatDate(string $date): string
    {
        $timestamp = strtotime($date);
        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 3600) {
            return floor($diff / 60).'m ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600).'h ago';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400).'d ago';
        } else {
            return date('M j', $timestamp);
        }
    }
}

// Helper function for expanduser
if (! function_exists('expanduser')) {
    function expanduser(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            return $_SERVER['HOME'].substr($path, 1);
        }

        return $path;
    }
}
