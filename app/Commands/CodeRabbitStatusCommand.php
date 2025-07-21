<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;
use JordanPartridge\GithubClient\Github;

class CodeRabbitStatusCommand extends Command
{
    protected $signature = 'coderabbit:status 
                           {--pr= : PR number}
                           {--repo= : Repository (owner/repo)}
                           {--format=visual : Output format (visual, json, summary)}
                           {--show-fixed : Show only fixed issues}
                           {--show-remaining : Show only remaining issues}';

    protected $description = 'Track CodeRabbit issue resolution status';

    protected Github $github;

    public function __construct(Github $github)
    {
        parent::__construct();
        $this->github = $github;
    }

    public function handle(): int
    {
        $prNumber = $this->option('pr');
        $repo = $this->option('repo') ?? $this->detectRepository();

        if (! $prNumber) {
            $this->error('❌ PR number is required');

            return 1;
        }

        if (! $repo) {
            $this->error('❌ Repository is required');

            return 1;
        }

        [$owner, $repoName] = explode('/', $repo);

        try {
            $this->info("🤖 Analyzing CodeRabbit resolution status for PR #{$prNumber}...");

            $statusData = $this->analyzeCodeRabbitStatus($owner, $repoName, (int) $prNumber);

            $this->displayStatus($statusData);

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to analyze CodeRabbit status: {$e->getMessage()}");

            return 1;
        }
    }

    private function detectRepository(): ?string
    {
        try {
            $remote = trim(shell_exec('git remote get-url origin 2>/dev/null') ?? '');
            if (preg_match('/github\.com[\/:]([^\/]+)\/([^\/]+?)(?:\.git)?$/', $remote, $matches)) {
                return $matches[1].'/'.$matches[2];
            }
        } catch (\Exception $e) {
            // Ignore git errors
        }

        return null;
    }

    private function analyzeCodeRabbitStatus(string $owner, string $repo, int $prNumber): array
    {
        // Get PR review comments via GitHub CLI (github-client doesn't support this yet)
        $reviewComments = $this->fetchReviewCommentsViaGH($owner, $repo, $prNumber);

        $coderabbitComments = array_filter($reviewComments, function ($comment) {
            return isset($comment['user']['login']) && $comment['user']['login'] === 'coderabbitai[bot]';
        });

        $issues = [];
        $resolvedIssues = [];
        $remainingIssues = [];

        foreach ($coderabbitComments as $comment) {
            $issue = $this->parseCodeRabbitComment($comment);
            if ($issue) {
                $issues[] = $issue;

                // Check if issue has been resolved (has reply indicating fix)
                if ($this->isIssueResolved($comment, $reviewComments)) {
                    $resolvedIssues[] = $issue;
                } else {
                    $remainingIssues[] = $issue;
                }
            }
        }

        // Categorize by priority
        $highPriority = array_filter($remainingIssues, fn ($issue) => $issue['priority'] === 'high');
        $mediumPriority = array_filter($remainingIssues, fn ($issue) => $issue['priority'] === 'medium');
        $lowPriority = array_filter($remainingIssues, fn ($issue) => $issue['priority'] === 'low');

        return [
            'pr_number' => $prNumber,
            'repository' => "$owner/$repo",
            'total_issues' => count($issues),
            'resolved_count' => count($resolvedIssues),
            'remaining_count' => count($remainingIssues),
            'resolved_issues' => $resolvedIssues,
            'remaining_issues' => $remainingIssues,
            'priority_breakdown' => [
                'high' => count($highPriority),
                'medium' => count($mediumPriority),
                'low' => count($lowPriority),
            ],
            'categorized_remaining' => [
                'high' => $highPriority,
                'medium' => $mediumPriority,
                'low' => $lowPriority,
            ],
        ];
    }

    private function parseCodeRabbitComment(array $comment): ?array
    {
        $body = $comment['body'] ?? '';

        // Skip if it's just a summary comment
        if (str_contains($body, '## Summary') || str_contains($body, '## Performance') || str_contains($body, 'Commits')) {
            return null;
        }

        // Determine priority based on keywords
        $priority = 'medium';
        if (str_contains($body, 'security') || str_contains($body, 'vulnerability') || str_contains($body, 'injection')) {
            $priority = 'high';
        } elseif (str_contains($body, 'style') || str_contains($body, 'formatting') || str_contains($body, 'suggestion')) {
            $priority = 'low';
        }

        // Extract category
        $category = 'general';
        if (str_contains($body, 'security')) {
            $category = 'security';
        } elseif (str_contains($body, 'performance')) {
            $category = 'performance';
        } elseif (str_contains($body, 'style')) {
            $category = 'style';
        } elseif (str_contains($body, 'duplication')) {
            $category = 'duplication';
        } elseif (str_contains($body, 'error')) {
            $category = 'error_handling';
        }

        return [
            'id' => $comment['id'],
            'file' => $comment['path'] ?? 'unknown',
            'line' => $comment['line'] ?? $comment['original_line'] ?? 0,
            'priority' => $priority,
            'category' => $category,
            'description' => $this->extractDescription($body),
            'created_at' => $comment['created_at'],
            'url' => $comment['html_url'],
        ];
    }

    private function extractDescription(string $body): string
    {
        // Remove markdown and extract first meaningful line
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $line = trim($line);
            if (! empty($line) && ! str_starts_with($line, '#') && ! str_starts_with($line, '```')) {
                return substr($line, 0, 100).(strlen($line) > 100 ? '...' : '');
            }
        }

        return 'CodeRabbit feedback';
    }

    private function fetchReviewCommentsViaGH(string $owner, string $repo, int $prNumber): array
    {
        $escapedOwner = escapeshellarg($owner);
        $escapedRepo = escapeshellarg($repo);
        $escapedPrNumber = escapeshellarg((string) $prNumber);

        $command = "gh api repos/{$escapedOwner}/{$escapedRepo}/pulls/{$escapedPrNumber}/comments --paginate 2>/dev/null";
        $output = shell_exec($command);

        if (! $output) {
            $this->warn('⚠️  Could not fetch review comments via GitHub CLI');

            return [];
        }

        $comments = json_decode($output, true);

        return is_array($comments) ? $comments : [];
    }

    private function isIssueResolved(array $comment, array $allComments): bool
    {
        $commentId = $comment['id'];

        // Look for replies to this comment indicating resolution
        foreach ($allComments as $otherComment) {
            if (isset($otherComment['in_reply_to_id']) && $otherComment['in_reply_to_id'] == $commentId) {
                $body = strtolower($otherComment['body'] ?? '');
                if (str_contains($body, 'fixed') || str_contains($body, 'resolved') || str_contains($body, 'addressed')) {
                    return true;
                }
            }
        }

        // Check if the line has been modified since the comment
        // This is a simple heuristic - could be improved with actual diff analysis
        return false;
    }

    private function displayStatus(array $status): void
    {
        $format = $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));

            return;
        }

        $this->displayVisualStatus($status);
    }

    private function displayVisualStatus(array $status): void
    {
        $this->newLine();
        $this->info("🤖 CodeRabbit Resolution Status - PR #{$status['pr_number']}");
        $this->comment("Repository: {$status['repository']}");
        $this->newLine();

        // Overall summary
        $resolvedPercent = $status['total_issues'] > 0
            ? round(($status['resolved_count'] / $status['total_issues']) * 100, 1)
            : 0;

        $this->info('📊 Overall Progress:');
        $this->line("   ✅ Resolved: {$status['resolved_count']}/{$status['total_issues']} ({$resolvedPercent}%)");
        $this->line("   ⏳ Remaining: {$status['remaining_count']}");
        $this->newLine();

        // Priority breakdown
        if ($status['remaining_count'] > 0) {
            $this->error('🔥 Remaining Issues by Priority:');
            $this->line("   🚨 High Priority: {$status['priority_breakdown']['high']} (security, critical)");
            $this->line("   ⚠️  Medium Priority: {$status['priority_breakdown']['medium']} (architecture, performance)");
            $this->line("   💡 Low Priority: {$status['priority_breakdown']['low']} (style, suggestions)");
            $this->newLine();

            // Show high priority issues first
            if (! empty($status['categorized_remaining']['high'])) {
                $this->error('🚨 HIGH PRIORITY ISSUES (Immediate Action Required):');
                foreach ($status['categorized_remaining']['high'] as $issue) {
                    $this->line("   📁 {$issue['file']}:{$issue['line']} - {$issue['description']}");
                    $this->line("      🔗 {$issue['url']}");
                }
                $this->newLine();
            }

            // Show medium priority if requested or if no filters
            if (! $this->option('show-fixed') && ! empty($status['categorized_remaining']['medium'])) {
                $this->comment('⚠️  MEDIUM PRIORITY ISSUES:');
                foreach (array_slice($status['categorized_remaining']['medium'], 0, 5) as $issue) {
                    $this->line("   📁 {$issue['file']}:{$issue['line']} - {$issue['description']}");
                }
                if (count($status['categorized_remaining']['medium']) > 5) {
                    $this->line('   ... and '.(count($status['categorized_remaining']['medium']) - 5).' more');
                }
                $this->newLine();
            }
        }

        // Show resolved issues if requested
        if ($this->option('show-fixed') && ! empty($status['resolved_issues'])) {
            $this->info('✅ RESOLVED ISSUES:');
            foreach ($status['resolved_issues'] as $issue) {
                $this->line("   📁 {$issue['file']}:{$issue['line']} - {$issue['description']}");
            }
            $this->newLine();
        }

        // Next steps
        if ($status['remaining_count'] > 0) {
            $this->comment('💡 Next Steps:');
            if ($status['priority_breakdown']['high'] > 0) {
                $this->line("   1. Address {$status['priority_breakdown']['high']} high-priority security issues first");
            }
            if ($status['priority_breakdown']['medium'] > 0) {
                $this->line("   2. Review {$status['priority_breakdown']['medium']} medium-priority architectural improvements");
            }
            if ($status['priority_breakdown']['low'] > 0) {
                $this->line("   3. Consider {$status['priority_breakdown']['low']} low-priority style suggestions");
            }
            $this->line("   4. Run 'conduit coderabbit:status --pr={$status['pr_number']} --show-fixed' to see progress");
        } else {
            $this->info('🎉 All CodeRabbit issues have been addressed! PR is ready for review.');
        }
    }
}
