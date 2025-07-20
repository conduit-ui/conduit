<?php

namespace App\Commands;

use App\Services\GithubAuthService;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class IssuesCommand extends Command
{
    protected $signature = 'issues 
                            {--format=interactive : Output format (interactive, json, table)}
                            {--state=open : Issue state (open, closed, all)}
                            {--context=all : Context filter (all, assigned, created, mentioned)}
                            {--repo= : Repository (owner/repo)}
                            {--limit=20 : Number of issues to fetch}
                            {--labels= : Filter by labels (comma-separated)}';

    protected $description = 'Browse and manage issues';

    public function handle(GithubAuthService $githubAuth): int
    {
        // Ensure we're authenticated
        if (! $githubAuth->isAuthenticated()) {
            error('❌ Not authenticated with GitHub');
            $this->info('💡 Run: gh auth login');

            return 1;
        }

        try {
            // Issues API now available in github-client v2.5.0!
            $issues = $this->fetchIssues();

            if (empty($issues)) {
                info('📭 No issues found matching your criteria');

                return 0;
            }

            return $this->displayIssues($issues);

        } catch (\Exception $e) {
            error("❌ Failed to fetch issues: {$e->getMessage()}");

            return 1;
        }
    }

    private function fetchIssues(): array
    {
        $repo = $this->option('repo');
        $state = $this->option('state');
        $context = $this->option('context');
        $limit = (int) $this->option('limit');
        $labels = $this->option('labels');

        // Only auto-detect if no repo specified
        if (! $repo) {
            $repo = $this->detectCurrentRepo();

            if (! $repo) {
                info('📂 No repository specified and none detected from current directory');
                $this->info('💡 Use --repo=owner/repo or run from within a git repository');

                return [];
            }
        }

        [$owner, $repoName] = explode('/', $repo);

        info("📡 Fetching issues from {$repo}...");

        // Build parameters for GitHub API
        $parameters = [
            'state' => $state,
            'per_page' => $limit,
            'sort' => 'updated',
            'direction' => 'desc',
        ];

        if ($labels) {
            $parameters['labels'] = $labels;
        }

        // For GitHub API, issues and PRs are the same endpoint, but we want issues only
        // We'll filter out PRs after fetching
        $allIssues = $this->fetchFromGitHub($owner, $repoName, $parameters);

        // Filter out pull requests (GitHub API returns both)
        $issues = array_filter($allIssues, function ($issue) {
            return ! isset($issue['pull_request']);
        });

        return $this->filterIssuesByContext(array_values($issues), $context);
    }

    private function fetchFromGitHub(string $owner, string $repo, array $parameters): array
    {
        // Note: GitHub's Issues API returns both issues and PRs
        // We'll use the search API for better filtering or implement via direct HTTP calls
        // For now, we'll use a simple approach and filter afterward

        try {
            // Use the new Issues API in github-client v2.5.0
            $state = match ($parameters['state'] ?? 'open') {
                'open' => \JordanPartridge\GithubClient\Enums\Issues\State::OPEN,
                'closed' => \JordanPartridge\GithubClient\Enums\Issues\State::CLOSED,
                default => null,
            };

            $sort = match ($parameters['sort'] ?? 'updated') {
                'created' => \JordanPartridge\GithubClient\Enums\Issues\Sort::CREATED,
                'updated' => \JordanPartridge\GithubClient\Enums\Issues\Sort::UPDATED,
                default => null,
            };

            $direction = match ($parameters['direction'] ?? 'desc') {
                'asc' => \JordanPartridge\GithubClient\Enums\Direction::ASC,
                'desc' => \JordanPartridge\GithubClient\Enums\Direction::DESC,
                default => null,
            };

            $issues = Github::issues()->allForRepo(
                owner: $owner,
                repo: $repo,
                per_page: $parameters['per_page'] ?? 20,
                state: $state,
                labels: $parameters['labels'] ?? null,
                sort: $sort,
                direction: $direction
            );

            // Convert DTOs to arrays for our display methods
            return array_map(fn ($issue) => $issue->toArray(), $issues);
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch issues from {$owner}/{$repo}: ".$e->getMessage());
        }
    }

    private function detectCurrentRepo(): ?string
    {
        if (! $this->isGitRepository()) {
            return null;
        }

        $remoteUrl = trim(shell_exec('git config --get remote.origin.url 2>/dev/null') ?: '');
        if (empty($remoteUrl)) {
            return null;
        }

        return $this->parseGitHubRepo($remoteUrl);
    }

    private function isGitRepository(): bool
    {
        $gitDir = shell_exec('git rev-parse --git-dir 2>/dev/null');

        return ! empty(trim($gitDir ?? ''));
    }

    private function parseGitHubRepo(string $remoteUrl): ?string
    {
        $patterns = [
            '/git@github\.com:([^\/]+)\/(.+)\.git$/',
            '/https:\/\/github\.com\/([^\/]+)\/(.+)\.git$/',
            '/https:\/\/github\.com\/([^\/]+)\/(.+)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $remoteUrl, $matches)) {
                return "{$matches[1]}/{$matches[2]}";
            }
        }

        return null;
    }

    private function filterIssuesByContext(array $issues, string $context): array
    {
        if ($context === 'all') {
            return $issues;
        }

        // Get current user info for filtering
        $currentUser = $this->getCurrentUser();
        if (! $currentUser) {
            return $issues;
        }

        return array_filter($issues, function ($issue) use ($context, $currentUser) {
            switch ($context) {
                case 'assigned':
                    return $issue['assignee'] && $issue['assignee']['login'] === $currentUser;

                case 'created':
                    return $issue['user']['login'] === $currentUser;

                case 'mentioned':
                    // This would require checking issue body/comments for @mentions
                    // For now, we'll include all issues
                    return true;

                default:
                    return true;
            }
        });
    }

    private function getCurrentUser(): ?string
    {
        try {
            // Cache this to avoid repeated API calls
            static $currentUser = null;
            if ($currentUser === null) {
                // Extract from git config for now
                $email = trim(shell_exec('git config --get user.email 2>/dev/null') ?: '');
                if (str_contains($email, '@users.noreply.github.com')) {
                    $currentUser = explode('@', $email)[0];
                }
            }

            return $currentUser;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function displayIssues(array $issues): int
    {
        $format = $this->option('format');

        switch ($format) {
            case 'json':
                $this->line(json_encode($issues, JSON_PRETTY_PRINT));

                return 0;

            case 'table':
                return $this->displayTable($issues);

            case 'interactive':
            default:
                return $this->displayInteractive($issues);
        }
    }

    private function displayTable(array $issues): int
    {
        $headers = ['#', 'Title', 'Author', 'State', 'Labels', 'Updated'];
        $rows = [];

        foreach ($issues as $issue) {
            $labels = array_map(fn ($label) => $label['name'], $issue['labels']);

            $rows[] = [
                $issue['number'],
                mb_strimwidth($issue['title'], 0, 50, '...'),
                $issue['user']['login'],
                $issue['state'],
                implode(', ', array_slice($labels, 0, 3)),
                $this->formatDate($issue['updated_at']),
            ];
        }

        table($headers, $rows);

        return 0;
    }

    private function displayInteractive(array $issues): int
    {
        info('🐛 Found '.count($issues).' issues');

        while (true) {
            // Build options for the select menu
            $options = [];
            foreach ($issues as $index => $issue) {
                $status = $this->getIssueStatusIcon($issue);
                $labels = $this->getTopLabels($issue['labels'], 2);
                $labelsText = $labels ? " [{$labels}]" : '';
                $repo = isset($issue['repository']) ? " ({$issue['repository']})" : '';

                $options[$index] = "{$status} #{$issue['number']} {$issue['title']}{$labelsText}{$repo}";
            }

            $options['quit'] = '🚪 Exit';

            $selected = select(
                label: 'Select an issue:',
                options: $options,
                default: 'quit'
            );

            if ($selected === 'quit') {
                return 0;
            }

            $this->showIssueDetails($issues[$selected]);

            if (! confirm('Continue browsing?', true)) {
                return 0;
            }
        }
    }

    private function getIssueStatusIcon(array $issue): string
    {
        if ($issue['state'] === 'closed') {
            return '✅';
        }

        // Check for priority/severity labels
        $labels = array_map(fn ($label) => strtolower($label['name']), $issue['labels']);

        if (in_array('bug', $labels)) {
            return '🐛';
        }

        if (in_array('enhancement', $labels) || in_array('feature', $labels)) {
            return '✨';
        }

        if (in_array('epic', $labels)) {
            return '🚀';
        }

        return '📋';
    }

    private function getTopLabels(array $labels, int $limit): string
    {
        $labelNames = array_map(fn ($label) => $label['name'], $labels);
        $topLabels = array_slice($labelNames, 0, $limit);

        return implode(', ', $topLabels);
    }

    private function showIssueDetails(array $issue): void
    {
        $this->newLine();
        info("🐛 Issue #{$issue['number']}");
        $this->line("📝 <fg=cyan>{$issue['title']}</>");
        $this->line("👤 Author: {$issue['user']['login']}");
        $this->line('📊 State: '.ucfirst($issue['state']));

        if ($issue['assignee']) {
            $this->line("👨‍💻 Assignee: {$issue['assignee']['login']}");
        }

        if (! empty($issue['labels'])) {
            $labels = array_map(fn ($label) => $label['name'], $issue['labels']);
            $this->line('🏷️  Labels: '.implode(', ', $labels));
        }

        $this->line("💬 Comments: {$issue['comments']}");
        $this->line('📅 Updated: '.$this->formatDate($issue['updated_at']));
        $this->line("🔗 {$issue['html_url']}");

        if (! empty($issue['body'])) {
            $this->newLine();
            $this->line('📋 Description:');
            $this->line(mb_strimwidth($issue['body'], 0, 200, '...'));
        }

        $this->newLine();

        // Quick actions
        $actions = [
            'view' => '👀 View in browser',
            'comment' => '💬 Add comment',
            'assign' => '👨‍💻 Assign to me',
            'close' => '✅ Close issue',
            'back' => '🔙 Back to list',
        ];

        // Modify actions based on issue state
        if ($issue['state'] === 'closed') {
            $actions['reopen'] = '🔄 Reopen issue';
            unset($actions['close']);
        }

        $action = select(
            label: 'What would you like to do?',
            options: $actions,
            default: 'back'
        );

        switch ($action) {
            case 'view':
                $this->openInBrowser($issue['html_url']);
                break;
            case 'comment':
                $this->addComment($issue);
                break;
            case 'assign':
                $this->assignIssue($issue);
                break;
            case 'close':
                $this->closeIssue($issue);
                break;
            case 'reopen':
                $this->reopenIssue($issue);
                break;
        }
    }

    private function openInBrowser(string $url): void
    {
        $os = PHP_OS_FAMILY;
        try {
            switch ($os) {
                case 'Darwin':
                    shell_exec('open ' . escapeshellarg($url) . ' > /dev/null 2>&1');
                    break;
                case 'Windows':
                    shell_exec('start ' . escapeshellarg($url) . ' > /dev/null 2>&1');
                    break;
                case 'Linux':
                    shell_exec('xdg-open ' . escapeshellarg($url) . ' > /dev/null 2>&1');
                    break;
            }
            info('🌐 Opened in browser');
        } catch (\Exception $e) {
            error('Failed to open browser');
        }
    }

    private function addComment(array $issue): void
    {
        info("💬 Adding comment to issue #{$issue['number']} (opens in browser)");
        $this->openInBrowser($issue['html_url'].'#new_comment_field');
    }

    private function assignIssue(array $issue): void
    {
        if (! confirm("Assign issue #{$issue['number']} to yourself?")) {
            return;
        }

        info('👨‍💻 Assignment would be implemented with GitHub Issues API');
        info('🌐 Opening in browser for manual assignment');
        $this->openInBrowser($issue['html_url']);
    }

    private function closeIssue(array $issue): void
    {
        if (! confirm("Close issue #{$issue['number']}?")) {
            return;
        }

        info('✅ Close functionality would be implemented with GitHub Issues API');
        info('🌐 Opening in browser for manual closure');
        $this->openInBrowser($issue['html_url']);
    }

    private function reopenIssue(array $issue): void
    {
        if (! confirm("Reopen issue #{$issue['number']}?")) {
            return;
        }

        info('🔄 Reopen functionality would be implemented with GitHub Issues API');
        info('🌐 Opening in browser for manual reopening');
        $this->openInBrowser($issue['html_url']);
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
