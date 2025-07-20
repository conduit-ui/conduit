<?php

namespace App\Commands\GitHub;

use App\Services\GitHub\IssueViewService;
use App\Services\GithubAuthService;
use LaravelZero\Framework\Commands\Command;

class IssueViewCommand extends Command
{
    protected $signature = 'issues:view 
                           {issue : Issue number to view}
                           {--repo= : Repository (owner/repo)}
                           {--with-comments : Show issue comments}
                           {--format=interactive : Output format (interactive, json)}';

    protected $description = 'View detailed issue information with comments and formatting';

    public function handle(GithubAuthService $githubAuth): int
    {
        if (!$githubAuth->isAuthenticated()) {
            $this->error('❌ Not authenticated with GitHub');
            $this->info('💡 Run: gh auth login');
            return 1;
        }

        $issueNumber = (int) $this->argument('issue');
        $repo = $this->option('repo');
        
        if (!$repo) {
            $repo = $this->detectCurrentRepo();
            if (!$repo) {
                $this->error('📂 No repository specified and none detected from current directory');
                $this->info('💡 Use --repo=owner/repo or run from within a git repository');
                return 1;
            }
        }

        $issueViewService = new IssueViewService();

        try {
            if ($this->option('format') === 'json') {
                return $this->showJson($issueViewService, $repo, $issueNumber);
            }

            if ($this->option('with-comments')) {
                return $this->showWithComments($issueViewService, $repo, $issueNumber);
            }

            return $this->showIssueDetails($issueViewService, $repo, $issueNumber);

        } catch (\Exception $e) {
            $this->error("❌ Failed to fetch issue: {$e->getMessage()}");
            return 1;
        }
    }

    private function showIssueDetails(IssueViewService $service, string $repo, int $issueNumber): int
    {
        $this->info("🔍 Fetching issue #{$issueNumber} from {$repo}...");
        
        $issue = $service->getIssue($repo, $issueNumber);
        
        if (!$issue) {
            $this->error("❌ Issue #{$issueNumber} not found in {$repo}");
            return 1;
        }

        $service->displayIssueHeader($this, $issue);
        $service->displayIssueMetadata($this, $issue);
        $service->displayIssueBody($this, $issue);
        
        return 0;
    }

    private function showWithComments(IssueViewService $service, string $repo, int $issueNumber): int
    {
        $this->info("🔍 Fetching issue #{$issueNumber} with comments from {$repo}...");
        
        $issue = $service->getIssue($repo, $issueNumber);
        
        if (!$issue) {
            $this->error("❌ Issue #{$issueNumber} not found in {$repo}");
            return 1;
        }

        $service->displayIssueHeader($this, $issue);
        $service->displayIssueMetadata($this, $issue);
        $service->displayIssueBody($this, $issue);
        
        if ($issue['comments'] > 0) {
            $this->newLine();
            $comments = $service->getIssueComments($repo, $issueNumber);
            $service->displayComments($this, $comments);
        } else {
            $this->newLine();
            $this->line('💬 No comments yet');
        }
        
        return 0;
    }

    private function showJson(IssueViewService $service, string $repo, int $issueNumber): int
    {
        $issue = $service->getIssue($repo, $issueNumber);
        
        if (!$issue) {
            $this->error("❌ Issue #{$issueNumber} not found in {$repo}");
            return 1;
        }

        $output = ['issue' => $issue];
        
        if ($this->option('with-comments')) {
            $output['comments'] = $service->getIssueComments($repo, $issueNumber);
        }
        
        $this->line(json_encode($output, JSON_PRETTY_PRINT));
        return 0;
    }

    private function detectCurrentRepo(): ?string
    {
        if (!$this->isGitRepository()) {
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
        return !empty(trim($gitDir ?? ''));
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
}