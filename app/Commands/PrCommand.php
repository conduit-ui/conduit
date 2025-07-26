<?php

namespace App\Commands;

use App\Services\GitHub\PrCreateService;
use App\Services\GithubAuthService;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

class PrCommand extends Command
{
    protected $signature = 'pr {title? : PR title}';

    protected $description = 'Create a pull request from current branch';

    public function handle(GithubAuthService $githubAuth, PrCreateService $prCreateService): int
    {
        // Ensure we're authenticated
        if (! $githubAuth->isAuthenticated()) {
            $this->error('❌ Not authenticated with GitHub');
            $this->info('💡 Run: gh auth login');

            return 1;
        }

        // Get current git context
        $gitContext = $this->getCurrentGitContext();
        if (! $gitContext) {
            return 1;
        }

        // Get PR title
        $title = $this->argument('title') ?: $this->askForTitle($gitContext);
        if (! $title) {
            $this->error('❌ PR title is required');

            return 1;
        }

        try {
            $this->info("🚀 Creating PR: {$title}");
            $this->info("📂 Repository: {$gitContext['repo']}");
            $this->info("🌿 Branch: {$gitContext['branch']} → {$gitContext['base']}");
            $this->newLine();

            // Prepare PR data
            $prData = [
                'title' => $title,
                'head' => $gitContext['branch'],
                'base' => $gitContext['base'],
                'body' => $this->generatePrBody($gitContext),
            ];

            // Create the pull request using service
            $pr = $prCreateService->createPullRequest($gitContext['repo'], $prData);

            if ($pr) {
                $this->info('✅ Pull request created successfully!');
                $this->info("🔗 {$pr->html_url}");

                return 0;
            } else {
                $this->error('❌ Failed to create PR: Unknown error');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to create PR: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->error('Debug: '.$e->getTraceAsString());
            }

            return 1;
        }
    }

    private function getCurrentGitContext(): ?array
    {
        // Check if we're in a git repository
        if (! $this->isGitRepository()) {
            $this->error('❌ Not in a git repository');

            return null;
        }

        // Get current branch
        $branch = trim(shell_exec('git branch --show-current'));
        if (empty($branch)) {
            $this->error('❌ Could not determine current branch');

            return null;
        }

        if ($branch === 'main' || $branch === 'master') {
            $this->error('❌ Cannot create PR from main/master branch');
            $this->info('💡 Create a feature branch first: git checkout -b feature/your-feature');

            return null;
        }

        // Get remote origin URL
        $remoteUrl = trim(shell_exec('git config --get remote.origin.url'));
        if (empty($remoteUrl)) {
            $this->error('❌ No remote origin found');

            return null;
        }

        // Parse GitHub repo from remote URL
        $repoInfo = $this->parseGitHubRepo($remoteUrl);
        if (! $repoInfo) {
            $this->error('❌ Could not parse GitHub repository from remote URL');

            return null;
        }

        // Determine base branch (main or master)
        $baseBranch = $this->getDefaultBranch($repoInfo['owner'], $repoInfo['repo']);

        return [
            'branch' => $branch,
            'repo' => "{$repoInfo['owner']}/{$repoInfo['repo']}",
            'owner' => $repoInfo['owner'],
            'repo_name' => $repoInfo['repo'],
            'base' => $baseBranch,
            'remote_url' => $remoteUrl,
        ];
    }

    private function isGitRepository(): bool
    {
        $gitDir = shell_exec('git rev-parse --git-dir 2>/dev/null');

        return ! empty(trim($gitDir));
    }

    private function parseGitHubRepo(string $remoteUrl): ?array
    {
        // Handle both SSH and HTTPS URLs
        $patterns = [
            '/git@github\.com:([^\/]+)\/(.+)\.git$/',  // SSH: git@github.com:owner/repo.git
            '/https:\/\/github\.com\/([^\/]+)\/(.+)\.git$/',  // HTTPS: https://github.com/owner/repo.git
            '/https:\/\/github\.com\/([^\/]+)\/(.+)$/',  // HTTPS without .git
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $remoteUrl, $matches)) {
                return [
                    'owner' => $matches[1],
                    'repo' => $matches[2],
                ];
            }
        }

        return null;
    }

    private function getDefaultBranch(string $owner, string $repo): string
    {
        try {
            $repoData = Github::repos()->get(\JordanPartridge\GithubClient\ValueObjects\Repo::fromFullName("{$owner}/{$repo}"));

            return $repoData->default_branch ?? 'main';
        } catch (\Exception $e) {
            // Fallback to 'main' if we can't fetch repo info
            return 'main';
        }
    }

    private function askForTitle(array $gitContext): ?string
    {
        // Try to generate title from recent commits
        $suggestedTitle = $this->generateTitleFromCommits($gitContext['branch']);

        if ($suggestedTitle) {
            $title = $this->ask('PR title', $suggestedTitle);
        } else {
            $title = $this->ask('PR title');
        }

        return $title;
    }

    private function generateTitleFromCommits(string $branch): ?string
    {
        // Get commits on this branch that aren't on main/master
        $escapedBranch = escapeshellarg($branch);
        $commits = shell_exec("git log --oneline main..{$escapedBranch} 2>/dev/null || git log --oneline master..{$escapedBranch} 2>/dev/null");

        if (empty($commits)) {
            return null;
        }

        $commitLines = array_filter(explode("\n", trim($commits)));
        if (empty($commitLines)) {
            return null;
        }

        // Use the most recent commit message as suggestion
        $latestCommit = $commitLines[0];
        $commitMessage = preg_replace('/^[a-f0-9]+\s+/', '', $latestCommit);

        // Clean up conventional commit prefixes for PR title
        $commitMessage = preg_replace('/^(feat|fix|docs|style|refactor|test|chore)(\(.+\))?:\s*/', '', $commitMessage);
        $commitMessage = ucfirst($commitMessage);

        return $commitMessage;
    }

    private function generatePrBody(array $gitContext): string
    {
        $body = "## Summary\n\n";

        // Get commit messages for context
        $escapedBranch = escapeshellarg($gitContext['branch']);
        $commits = shell_exec("git log --oneline main..{$escapedBranch} 2>/dev/null || git log --oneline master..{$escapedBranch} 2>/dev/null");

        if (! empty($commits)) {
            $commitLines = array_filter(explode("\n", trim($commits)));
            if (count($commitLines) > 1) {
                $body .= "This PR includes the following changes:\n\n";
                foreach ($commitLines as $commit) {
                    $commitMessage = preg_replace('/^[a-f0-9]+\s+/', '', $commit);
                    $body .= "- {$commitMessage}\n";
                }
            } else {
                $commitMessage = preg_replace('/^[a-f0-9]+\s+/', '', $commitLines[0]);
                $body .= "{$commitMessage}\n";
            }
        }

        $body .= "\n## Test Plan\n- [ ] \n\n";
        $body .= "🤖 Generated with [Claude Code](https://claude.ai/code)\n";

        return $body;
    }
}
