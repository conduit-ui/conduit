<?php

namespace App\Commands;

use App\Services\GithubAuthService;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class PrsCommand extends Command
{
    protected $signature = 'prs 
                            {--format=interactive : Output format (interactive, json, table)}
                            {--state=open : PR state (open, closed, all)}
                            {--context=all : Context filter (all, mine, review-requested, watching)}
                            {--repo= : Repository (owner/repo)}
                            {--limit=20 : Number of PRs to fetch}';

    protected $description = 'Browse and manage pull requests';

    public function handle(GithubAuthService $githubAuth): int
    {
        // Ensure we're authenticated
        if (! $githubAuth->isAuthenticated()) {
            $this->error('❌ Not authenticated with GitHub');
            $this->info('💡 Run: gh auth login');

            return 1;
        }

        try {
            $prs = $this->fetchPullRequests();

            if (empty($prs)) {
                $this->info('📭 No pull requests found');

                return 0;
            }

            return $this->displayPullRequests($prs);

        } catch (\Exception $e) {
            $this->error("❌ Failed to fetch PRs: {$e->getMessage()}");

            return 1;
        }
    }

    private function fetchPullRequests(): array
    {
        $repo = $this->option('repo');
        $state = $this->option('state');
        $context = $this->option('context');
        $limit = (int) $this->option('limit');

        // If no repo specified, try to detect from current directory
        if (! $repo) {
            $repo = $this->detectCurrentRepo();
        }

        if (! $repo) {
            // Fetch PRs across all accessible repositories based on context
            return $this->fetchPrsAcrossRepos($context, $state, $limit);
        }

        // Build search query
        $query = "repo:{$repo} is:pr";

        if ($state !== 'all') {
            $query .= " is:{$state}";
        }
        if ($context === 'mine') {
            $currentUser = $this->getCurrentUser();
            if ($currentUser) {
                $query = "author:{$currentUser} is:pr is:{$state}";
            }
        }

        // Parse repo into owner/name
        [$owner, $repoName] = explode('/', $repo);

        // Use pull requests recentDetails method to get comment counts
        $searchResult = Github::pullRequests()->recentDetails($owner, $repoName, $limit, $state);
        // Convert Collection to array if needed
        if ($searchResult instanceof \Illuminate\Support\Collection) {
            // Convert each object to array recursively
            return $searchResult->map(function ($pr) {
                return $this->convertToArray($pr);
            })->toArray();
        }

        return $searchResult;
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

    private function fetchPrsAcrossRepos(string $context, string $state, int $limit): array
    {
        // For now, we'll focus on current repo or user's repositories
        // In Phase 2, we can implement cross-repo PR fetching using search API

        // Get user's repositories and fetch PRs from each
        $repos = Github::repos()->allWithPagination(per_page: 50);
        $allPrs = [];

        foreach (array_slice($repos, 0, 10) as $repo) { // Limit to first 10 repos for performance
            try {
                $prs = Github::pullRequests()->all($repo->owner->login, $repo->name, [
                    'state' => $state,
                    'per_page' => 10,
                    'sort' => 'updated',
                    'direction' => 'desc',
                ]);

                foreach ($prs as $pr) {
                    // Convert DTO to array and add repository info
                    $prArray = $pr->toArray();
                    $prArray['repository'] = "{$repo->owner->login}/{$repo->name}";
                    $allPrs[] = $prArray;
                }

                if (count($allPrs) >= $limit) {
                    break;
                }
            } catch (\Exception $e) {
                // Skip repositories we can't access
                continue;
            }
        }

        // Sort by updated date
        usort($allPrs, function ($a, $b) {
            return strtotime($b['updated_at']) <=> strtotime($a['updated_at']);
        });

        return $this->filterPrsByContext(array_slice($allPrs, 0, $limit), $context);
    }

    private function filterPrsByContext(array $prs, string $context): array
    {
        if ($context === 'all') {
            return $prs;
        }

        // Get current user info for filtering
        $currentUser = $this->getCurrentUser();
        if (! $currentUser) {
            return $prs;
        }

        return array_filter($prs, function ($pr) use ($context, $currentUser) {
            switch ($context) {
                case 'mine':
                    return $pr['user']['login'] === $currentUser;

                case 'review-requested':
                    // Check if current user is in requested_reviewers
                    $reviewers = $pr['requested_reviewers'] ?? [];
                    foreach ($reviewers as $reviewer) {
                        if ($reviewer['login'] === $currentUser) {
                            return true;
                        }
                    }

                    return false;

                case 'watching':
                    // This would require additional API calls to check subscription status
                    // For now, we'll include all PRs
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
                // We'd need to add a user endpoint to get current user
                // For now, we'll try to extract from git config
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

    private function displayPullRequests(array $prs): int
    {
        $format = $this->option('format');

        switch ($format) {
            case 'json':
                $this->line(json_encode($prs, JSON_PRETTY_PRINT));

                return 0;

            case 'table':
                return $this->displayTable($prs);

            case 'interactive':
            default:
                return $this->displayInteractive($prs);
        }
    }

    private function displayTable(array $prs): int
    {
        $headers = ['#', 'Title', 'Author', 'Comments', 'State', 'Updated'];
        $rows = [];

        foreach ($prs as $pr) {
            $generalComments = is_array($pr) ? ($pr['comments'] ?? 0) : ($pr->comments ?? 0);
            $reviewComments = is_array($pr) ? ($pr['review_comments'] ?? 0) : ($pr->review_comments ?? 0);
            $number = is_array($pr) ? ($pr['number'] ?? 'N/A') : ($pr->number ?? 'N/A');
            $title = is_array($pr) ? ($pr['title'] ?? 'No title') : ($pr->title ?? 'No title');
            $login = is_array($pr) ? ($pr['user']['login'] ?? 'Unknown') : ($pr->user->login ?? 'Unknown');
            $state = is_array($pr) ? ($pr['state'] ?? 'unknown') : ($pr->state ?? 'unknown');
            $updated = is_array($pr) ? ($pr['updated_at'] ?? date('c')) : ($pr->updated_at ?? date('c'));

            $rows[] = [
                $number,
                mb_strimwidth($title, 0, 40, '...'),
                $login,
                "💬{$generalComments} 📝{$reviewComments}",
                $state,
                $this->formatDate($updated),
            ];
        }

        table($headers, $rows);

        return 0;
    }

    private function displayInteractive(array $prs): int
    {
        $this->info('📋 Found '.count($prs).' pull request'.(count($prs) !== 1 ? 's' : ''));

        // Build options for the select menu
        $options = [];
        foreach ($prs as $index => $pr) {
            $generalComments = $pr['comments'] ?? 0;
            $reviewComments = $pr['review_comments'] ?? 0;
            $updated = $this->formatDate($pr['updated_at'] ?? date('c'));
            $options[] = "#{$pr['number']} • {$pr['title']} • {$pr['user']['login']} • 💬{$generalComments} 📝{$reviewComments} • {$updated}";
        }
        $options[] = '🔙 Back';

        $selected = select(
            'Select a pull request',
            $options,
            $options[count($options) - 1]
        );

        if ($selected === '🔙 Back') {
            return 0;
        }
        // Show PR actions menu
        $actions = [
            '👁️  View Details',
            '✏️  Edit PR',
            '🔀 Check Merge Status',
            '💬 Manage Reviews',
            '🌐 Open in Browser',
            '🔙 Back',
        ];
        $action = select(
            label: 'What would you like to do?',
            options: $actions,
            default: $actions[count($actions) - 1]
        );

        return 0;
    }

    private function getPrStatusIcon(array $pr): string
    {
        if ($pr['draft']) {
            return '📝';
        }

        if ($pr['state'] === 'closed') {
            return $pr['merged'] ?? false ? '✅' : '❌';
        }

        // Check for conflicts, CI status, etc.
        return '🔀';
    }

    private function showPrDetails(array $pr): void
    {
        $this->newLine();
        info("🔀 Pull Request #{$pr['number']}");
        $this->line("📝 <fg=cyan>{$pr['title']}</>");
        $this->line("👤 Author: {$pr['user']['login']}");
        $this->line("🌿 {$pr['head_ref']} → {$pr['base_ref']}");
        $this->line('📅 Updated: '.$this->formatDate($pr['updated_at']));
        $this->line("🔗 {$pr['html_url']}");

        // Show comment counts
        $comments = $pr['comments'] ?? 0;
        $reviewComments = $pr['review_comments'] ?? 0;
        $this->line("💬 Comments: {$comments} | 📝 Review Comments: {$reviewComments}");

        if (! empty($pr['body'])) {
            $this->newLine();
            $this->line('📋 Description:');
            $this->line(mb_strimwidth($pr['body'], 0, 200, '...'));
        }

        $this->newLine();

        // Quick actions
        $actions = [
            'view' => '👀 View in browser',
            'checkout' => '📥 Checkout locally',
            'approve' => '✅ Approve',
            'back' => '🔙 Back to list',
        ];

        // Add review comments option if there are any
        if ($reviewComments > 0) {
            $actions = [
                'comments' => "📝 View {$reviewComments} review comments",
                'view' => '👀 View in browser',
                'checkout' => '📥 Checkout locally',
                'approve' => '✅ Approve',
                'back' => '🔙 Back to list',
            ];
        }

        $action = select(
            label: 'What would you like to do?',
            options: $actions,
            default: 'back'
        );

        switch ($action) {
            case 'comments':
                $this->showReviewComments($pr);
                break;
            case 'view':
                $this->openInBrowser($pr['html_url']);
                break;
            case 'checkout':
                $this->checkoutPr($pr);
                break;
            case 'approve':
                $this->approvePr($pr);
                break;
        }
    }

    private function showReviewComments(array $pr): void
    {
        try {
            $repo = $pr['repository'] ?? $this->detectCurrentRepo();
            if (! $repo) {
                error('Could not determine repository');

                return;
            }

            [$owner, $repoName] = explode('/', $repo);

            info("📝 Fetching review comments for PR #{$pr['number']}...");

            // Fetch review comments using GitHub API
            $reviewComments = Github::pullRequests()->comments(
                owner: $owner,
                repo: $repoName,
                number: $pr['number']
            );

            if (empty($reviewComments)) {
                info('📭 No review comments found');

                return;
            }

            $this->newLine();
            info('📝 Review Comments ('.count($reviewComments).'):');
            $this->newLine();

            foreach ($reviewComments as $index => $comment) {
                $this->line('🔹 Comment #'.($index + 1));
                $line = $comment['line'] ?? $comment['original_line'] ?? 'unknown';
                $this->line("👤 {$comment['user']['login']} commented on {$comment['path']}:{$line}");
                $this->line('📅 '.$this->formatDate($comment['created_at']));
                $this->line('💬 '.mb_strimwidth($comment['body'], 0, 200, '...'));
                $this->newLine();
            }

            info("💡 To see full comments: {$pr['html_url']}/files");

        } catch (\Exception $e) {
            error("❌ Failed to fetch review comments: {$e->getMessage()}");
            info("🌐 You can view them in browser: {$pr['html_url']}/files");
        }
    }

    private function openInBrowser(string $url): void
    {
        $os = PHP_OS_FAMILY;
        try {
            switch ($os) {
                case 'Darwin':
                    shell_exec('open '.escapeshellarg($url).' > /dev/null 2>&1');
                    break;
                case 'Windows':
                    shell_exec('start '.escapeshellarg($url).' > /dev/null 2>&1');
                    break;
                case 'Linux':
                    shell_exec('xdg-open '.escapeshellarg($url).' > /dev/null 2>&1');
                    break;
            }
            info('🌐 Opened in browser');
        } catch (\Exception $e) {
            error('Failed to open browser');
        }
    }

    private function checkoutPr(array $pr): void
    {
        if (! $this->isGitRepository()) {
            error('Not in a git repository');

            return;
        }

        $branchName = "pr-{$pr['number']}-".preg_replace('/[^a-zA-Z0-9_-]/', '_', $pr['head_ref']);

        $escapedBranchName = escapeshellarg($branchName);
        $commands = [
            "git fetch origin pull/{$pr['number']}/head:".$escapedBranchName,
            'git checkout '.$escapedBranchName,
        ];

        foreach ($commands as $command) {
            $output = shell_exec("{$command} 2>&1");
            if (strpos($output, 'error') !== false || strpos($output, 'fatal') !== false) {
                error("Failed to checkout PR: {$output}");

                return;
            }
        }

        info("✅ Checked out PR #{$pr['number']} to branch '{$branchName}'");
    }

    private function approvePr(array $pr): void
    {
        if (! confirm("Approve PR #{$pr['number']}?")) {
            return;
        }

        try {
            $repo = $pr['repository'] ?? $this->detectCurrentRepo();
            if (! $repo) {
                error('Could not determine repository');

                return;
            }

            [$owner, $repoName] = explode('/', $repo);

            Github::pullRequests()->createReview(
                owner: $owner,
                repo: $repoName,
                number: $pr['number'],
                body: 'Approved via Conduit CLI',
                event: 'APPROVE'
            );

            info("✅ PR #{$pr['number']} approved successfully!");
        } catch (\Exception $e) {
            error("Failed to approve PR: {$e->getMessage()}");
        }
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

    private function convertToArray($obj)
    {
        return json_decode(json_encode($obj), true);
    }
}
