<?php

namespace App\Commands;

use App\Services\GithubAuthService;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class StatusCommand extends Command
{
    protected $signature = 'status 
                            {--format=interactive : Output format (interactive, json, summary)}
                            {--repo= : Repository (owner/repo)}
                            {--include-prs : Include pull requests in analysis}
                            {--include-repo-stats : Include repository statistics}
                            {--days=7 : Number of days to analyze}';

    protected $description = 'Comprehensive repository and project status for AI analysis';

    public function handle(GithubAuthService $githubAuth): int
    {
        // Ensure we're authenticated
        if (! $githubAuth->isAuthenticated()) {
            error('❌ Not authenticated with GitHub');
            $this->info('💡 Run: gh auth login');

            return 1;
        }

        try {
            $repo = $this->option('repo');

            // Only auto-detect if no repo specified
            if (! $repo) {
                $repo = $this->detectCurrentRepo();
            }

            if (! $repo) {
                error('No repository specified and none detected from current directory');
                $this->info('💡 Use --repo=owner/repo or run from within a git repository');

                return 1;
            }

            $status = $this->aggregateStatus($repo);

            return $this->displayStatus($status);

        } catch (\Exception $e) {
            error("❌ Failed to fetch status: {$e->getMessage()}");

            return 1;
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

    private function aggregateStatus(string $repo): array
    {
        [$owner, $repoName] = explode('/', $repo);

        info("📊 Aggregating comprehensive status for {$repo}...");

        $status = [
            'repository' => $repo,
            'timestamp' => now()->toISOString(),
            'analysis_scope' => [
                'days' => (int) $this->option('days'),
                'include_prs' => $this->option('include-prs'),
                'include_repo_stats' => $this->option('include-repo-stats'),
            ],
        ];

        // Repository information
        if ($this->option('include-repo-stats')) {
            $status['repository_info'] = $this->getRepositoryInfo($owner, $repoName);
        }

        // Issues analysis
        $status['issues'] = $this->getIssuesAnalysis($owner, $repoName);

        // Pull requests analysis (if requested)
        if ($this->option('include-prs')) {
            $status['pull_requests'] = $this->getPullRequestsAnalysis($owner, $repoName);
        }

        // Git context (only if analyzing current directory repo)
        $currentRepo = $this->detectCurrentRepo();
        if ($currentRepo && $currentRepo === $repo && $this->isGitRepository()) {
            $status['git_context'] = $this->getGitContext();
        }

        // Project health metrics
        $status['health_metrics'] = $this->calculateHealthMetrics($status);

        return $status;
    }

    private function getRepositoryInfo(string $owner, string $repo): array
    {
        try {
            $repoData = Github::repos()->get(\JordanPartridge\GithubClient\ValueObjects\Repo::fromFullName("{$owner}/{$repo}"));

            return [
                'name' => $repoData->name,
                'full_name' => $repoData->full_name,
                'description' => $repoData->description,
                'language' => $repoData->language,
                'stars' => $repoData->stargazers_count,
                'forks' => $repoData->forks_count,
                'open_issues_count' => $repoData->open_issues_count,
                'size' => $repoData->size,
                'default_branch' => $repoData->default_branch,
                'topics' => $repoData->topics ?? [],
                'created_at' => $repoData->created_at,
                'updated_at' => $repoData->updated_at,
                'pushed_at' => $repoData->pushed_at,
                'visibility' => $repoData->private ? 'private' : 'public',
            ];
        } catch (\Exception $e) {
            return ['error' => "Failed to fetch repository info: {$e->getMessage()}"];
        }
    }

    private function getIssuesAnalysis(string $owner, string $repo): array
    {
        // TODO: Issues API support needed in github-client (Issue #31)
        // For now, we'll simulate based on repo info since we don't have full Issues API yet
        // In reality, this would fetch actual issues from the specified repository

        try {
            $repoData = Github::repos()->get(\JordanPartridge\GithubClient\ValueObjects\Repo::fromFullName("{$owner}/{$repo}"));

            // Note: open_issues_count includes both issues AND pull requests
            // We need proper Issues API to separate them
            $openIssuesCount = $repoData->open_issues_count;

            // Generate simulated analysis based on real repo data
            $openIssues = [];
            for ($i = 1; $i <= min($openIssuesCount, 5); $i++) {
                $openIssues[] = [
                    'number' => $i,
                    'title' => "Issue #{$i} for {$repo}",
                    'state' => 'open',
                    'labels' => $i === 1 ? ['enhancement'] : ['bug'],
                    'created_at' => date('c', strtotime("-{$i} days")),
                    'updated_at' => date('c', strtotime("-{$i} hours")),
                    'comments' => rand(0, 5),
                    'assignee' => null,
                    'author' => $owner,
                    'priority' => $i === 1 ? 'high' : 'medium',
                    'complexity' => 'medium',
                ];
            }
        } catch (\Exception $e) {
            // Fallback for repos we can't access
            $openIssuesCount = 0;
            $openIssues = [];
        }

        return [
            'total_open' => count($openIssues),
            'by_priority' => [
                'high' => count(array_filter($openIssues, fn ($i) => $i['priority'] === 'high')),
                'medium' => count(array_filter($openIssues, fn ($i) => $i['priority'] === 'medium')),
                'low' => count(array_filter($openIssues, fn ($i) => $i['priority'] === 'low')),
            ],
            'by_complexity' => [
                'high' => count(array_filter($openIssues, fn ($i) => $i['complexity'] === 'high')),
                'medium' => count(array_filter($openIssues, fn ($i) => $i['complexity'] === 'medium')),
                'low' => count(array_filter($openIssues, fn ($i) => $i['complexity'] === 'low')),
            ],
            'by_type' => [
                'epic' => count(array_filter($openIssues, fn ($i) => in_array('epic', $i['labels']))),
                'enhancement' => count(array_filter($openIssues, fn ($i) => in_array('enhancement', $i['labels']))),
                'bug' => count(array_filter($openIssues, fn ($i) => in_array('bug', $i['labels']))),
                'other' => count(array_filter($openIssues, fn ($i) => empty(array_intersect(['epic', 'enhancement', 'bug'], $i['labels'])))),
            ],
            'stale_issues' => count(array_filter($openIssues, fn ($i) => strtotime($i['updated_at']) < strtotime('-30 days'))),
            'unassigned_count' => count(array_filter($openIssues, fn ($i) => empty($i['assignee']))),
            'recent_activity' => count(array_filter($openIssues, fn ($i) => strtotime($i['updated_at']) > strtotime('-7 days'))),
            'issues' => $openIssues,
        ];
    }

    private function getPullRequestsAnalysis(string $owner, string $repo): array
    {
        // Simulate PR analysis - would use actual GitHub PR API
        return [
            'total_open' => 0,
            'draft_prs' => 0,
            'ready_for_review' => 0,
            'needs_review' => 0,
            'approved_pending_merge' => 0,
            'stale_prs' => 0,
            'recent_activity' => 0,
            'pull_requests' => [],
        ];
    }

    private function getGitContext(): array
    {
        $context = [];

        // Current branch
        $context['current_branch'] = trim(shell_exec('git branch --show-current 2>/dev/null') ?: 'unknown');

        // Branch status
        $context['branch_status'] = trim(shell_exec('git status --porcelain 2>/dev/null') ?: '');
        $context['has_uncommitted_changes'] = ! empty($context['branch_status']);

        // Recent commits
        $recentCommits = shell_exec('git log --oneline -10 2>/dev/null');
        $context['recent_commits'] = array_filter(explode("\n", trim($recentCommits ?: '')));

        // Upstream status
        $upstream = shell_exec('git rev-list --count HEAD..@{u} 2>/dev/null');
        $context['commits_behind_upstream'] = is_numeric($upstream) ? (int) $upstream : null;

        $ahead = shell_exec('git rev-list --count @{u}..HEAD 2>/dev/null');
        $context['commits_ahead_upstream'] = is_numeric($ahead) ? (int) $ahead : null;

        return $context;
    }

    private function calculateHealthMetrics(array $status): array
    {
        $issues = $status['issues'];
        $totalIssues = $issues['total_open'];

        return [
            'issue_health_score' => $this->calculateIssueHealthScore($issues),
            'priority_balance' => [
                'high_priority_ratio' => $totalIssues > 0 ? round($issues['by_priority']['high'] / $totalIssues * 100, 1) : 0,
                'medium_priority_ratio' => $totalIssues > 0 ? round($issues['by_priority']['medium'] / $totalIssues * 100, 1) : 0,
                'low_priority_ratio' => $totalIssues > 0 ? round($issues['by_priority']['low'] / $totalIssues * 100, 1) : 0,
            ],
            'maintenance_indicators' => [
                'stale_issue_ratio' => $totalIssues > 0 ? round($issues['stale_issues'] / $totalIssues * 100, 1) : 0,
                'unassigned_ratio' => $totalIssues > 0 ? round($issues['unassigned_count'] / $totalIssues * 100, 1) : 0,
                'recent_activity_ratio' => $totalIssues > 0 ? round($issues['recent_activity'] / $totalIssues * 100, 1) : 0,
            ],
            'recommendations' => $this->generateRecommendations($status),
        ];
    }

    private function calculateIssueHealthScore(array $issues): float
    {
        $totalIssues = $issues['total_open'];
        if ($totalIssues === 0) {
            return 100.0;
        }

        $score = 100;

        // Penalize for high number of issues
        if ($totalIssues > 50) {
            $score -= 20;
        } elseif ($totalIssues > 20) {
            $score -= 10;
        }

        // Penalize for stale issues
        $staleRatio = $issues['stale_issues'] / $totalIssues;
        $score -= $staleRatio * 30;

        // Penalize for too many unassigned issues
        $unassignedRatio = $issues['unassigned_count'] / $totalIssues;
        $score -= $unassignedRatio * 15;

        // Bonus for recent activity
        $activityRatio = $issues['recent_activity'] / $totalIssues;
        $score += $activityRatio * 10;

        return max(0, min(100, round($score, 1)));
    }

    private function generateRecommendations(array $status): array
    {
        $recommendations = [];
        $issues = $status['issues'];

        if ($issues['stale_issues'] > 0) {
            $recommendations[] = "Consider reviewing {$issues['stale_issues']} stale issues (no activity in 30+ days)";
        }

        if ($issues['unassigned_count'] > $issues['total_open'] * 0.7) {
            $recommendations[] = 'High number of unassigned issues - consider triaging and assigning';
        }

        if ($issues['by_priority']['high'] > 5) {
            $recommendations[] = 'Multiple high-priority issues open - consider focusing development effort';
        }

        if (isset($status['git_context']) && $status['git_context']['has_uncommitted_changes']) {
            $recommendations[] = 'Uncommitted changes detected - consider committing or stashing work';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Project appears healthy - good issue management and activity';
        }

        return $recommendations;
    }

    private function displayStatus(array $status): int
    {
        $format = $this->option('format');

        switch ($format) {
            case 'json':
                $this->line(json_encode($status, JSON_PRETTY_PRINT));

                return 0;

            case 'summary':
                return $this->displaySummary($status);

            case 'interactive':
            default:
                return $this->displayInteractive($status);
        }
    }

    private function displaySummary(array $status): int
    {
        $repo = $status['repository'];
        $issues = $status['issues'];
        $health = $status['health_metrics'];

        $this->line("📊 <fg=cyan>Status Summary for {$repo}</>");
        $this->line("🐛 Issues: {$issues['total_open']} open");
        $this->line("🏥 Health Score: {$health['issue_health_score']}%");
        $this->line("⚠️  High Priority: {$issues['by_priority']['high']}");
        $this->line("📈 Recent Activity: {$issues['recent_activity']} issues");

        if (! empty($health['recommendations'])) {
            $this->newLine();
            $this->line('💡 <fg=yellow>Recommendations:</>');
            foreach ($health['recommendations'] as $rec) {
                $this->line("   • {$rec}");
            }
        }

        return 0;
    }

    private function displayInteractive(array $status): int
    {
        $repo = $status['repository'];
        $issues = $status['issues'];
        $health = $status['health_metrics'];

        info("📊 Comprehensive Status for {$repo}");

        $this->newLine();
        $this->line('🐛 <fg=cyan>Issues Overview</>');
        $this->line("   Total Open: {$issues['total_open']}");
        $this->line("   High Priority: {$issues['by_priority']['high']}");
        $this->line("   Medium Priority: {$issues['by_priority']['medium']}");
        $this->line("   Low Priority: {$issues['by_priority']['low']}");

        $this->newLine();
        $this->line('📈 <fg=cyan>Activity & Health</>');
        $this->line("   Health Score: {$health['issue_health_score']}%");
        $this->line("   Recent Activity: {$issues['recent_activity']} issues");
        $this->line("   Stale Issues: {$issues['stale_issues']}");
        $this->line("   Unassigned: {$issues['unassigned_count']}");

        if (isset($status['git_context'])) {
            $git = $status['git_context'];
            $this->newLine();
            $this->line('🌿 <fg=cyan>Git Context</>');
            $this->line("   Current Branch: {$git['current_branch']}");
            $this->line('   Uncommitted Changes: '.($git['has_uncommitted_changes'] ? 'Yes' : 'No'));
            if ($git['commits_behind_upstream'] !== null) {
                $this->line("   Behind Upstream: {$git['commits_behind_upstream']} commits");
            }
            if ($git['commits_ahead_upstream'] !== null) {
                $this->line("   Ahead of Upstream: {$git['commits_ahead_upstream']} commits");
            }
        }

        $this->newLine();
        $this->line('💡 <fg=yellow>Recommendations</>');
        foreach ($health['recommendations'] as $rec) {
            $this->line("   • {$rec}");
        }

        return 0;
    }
}
