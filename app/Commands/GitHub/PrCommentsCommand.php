<?php

namespace App\Commands\GitHub;

use App\Commands\GitHub\Concerns\DetectsRepository;
use App\Services\GithubAuthService;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\text;

class PrCommentsCommand extends Command
{
    use DetectsRepository;

    protected $signature = 'prs:comments 
                           {pr : PR number}
                           {--repo= : Repository (owner/repo)}
                           {--unresolved : Show only unresolved review comments}
                           {--reviews : Show review status and comments}
                           {--format=interactive : Output format (interactive, json)}';

    protected $description = 'Show PR comments, review status, and unresolved discussions';

    public function handle(GithubAuthService $authService): int
    {
        if (! $authService->getToken()) {
            $this->error('❌ GitHub authentication required. Run: conduit github:auth');

            return 1;
        }

        $repo = $this->option('repo') ?: $this->detectCurrentRepo();
        if (! $repo) {
            $repo = text('Repository (owner/repo):');
        }

        $prNumber = (int) $this->argument('pr');

        $this->line("🔍 <comment>Fetching comments for PR #{$prNumber}...</comment>");

        try {
            [$owner, $repoName] = explode('/', $repo);

            // Get PR details
            $pr = Github::pullRequests()->detail($owner, $repoName, $prNumber);
            if (! $pr) {
                $this->error("❌ PR #{$prNumber} not found in {$repo}");

                return 1;
            }

            // Get comments and reviews
            $comments = $this->fetchComments($owner, $repoName, $prNumber);
            $reviews = $this->fetchReviews($owner, $repoName, $prNumber);

            if ($this->option('format') === 'json') {
                return $this->outputJson($pr, $comments, $reviews);
            }

            return $this->displayInteractive($pr, $comments, $reviews, $repo);

        } catch (\Exception $e) {
            $this->error("❌ Error fetching comments: {$e->getMessage()}");

            return 1;
        }
    }

    private function fetchComments(string $owner, string $repo, int $prNumber): array
    {
        try {
            // Get issue comments (general PR comments)
            $issueComments = Github::issues()->comments($owner, $repo, $prNumber);

            // Get review comments (code-specific comments)
            $reviewComments = Github::pullRequests()->comments($owner, $repo, $prNumber);

            return [
                'issue_comments' => array_map(fn ($comment) => $comment->toArray(), $issueComments),
                'review_comments' => array_map(fn ($comment) => $comment->toArray(), $reviewComments),
            ];
        } catch (\Exception $e) {
            return [
                'issue_comments' => [],
                'review_comments' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function fetchReviews(string $owner, string $repo, int $prNumber): array
    {
        try {
            $reviews = Github::pullRequests()->reviews($owner, $repo, $prNumber);

            return $reviews->map(fn ($review) => $review->toArray())->toArray();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function displayInteractive($pr, array $comments, array $reviews, string $repo): int
    {
        $this->newLine();
        $this->line("💬 <info>PR #{$pr->number}: {$pr->title}</info>");
        $this->line("👤 Author: <comment>{$pr->user->login}</comment>");
        $this->line("📊 State: <comment>{$pr->state}</comment>");

        // Show review status
        $this->showReviewStatus($reviews);

        // Show comments summary
        $this->showCommentsSummary($comments, $reviews);

        // Show unresolved comments if requested
        if ($this->option('unresolved')) {
            $this->showUnresolvedComments($comments, $repo);
        }

        // Show review comments if requested
        if ($this->option('reviews')) {
            $this->showReviews($reviews);
        }

        // Show all comments if no specific filter
        if (! $this->option('unresolved') && ! $this->option('reviews')) {
            $this->showAllComments($comments, $repo);
        }

        return 0;
    }

    private function showReviewStatus(array $reviews): void
    {
        $this->newLine();
        $this->line('📋 <info>Review Status</info>');

        if (empty($reviews) || isset($reviews['error'])) {
            $this->line('   <fg=yellow>No reviews found</>');

            return;
        }

        $reviewSummary = $this->analyzeReviews($reviews);

        $this->line("   ✅ Approved: <fg=green>{$reviewSummary['approved']}</>");
        $this->line("   ❌ Changes Requested: <fg=red>{$reviewSummary['changes_requested']}</>");
        $this->line("   💭 Comments: <fg=yellow>{$reviewSummary['commented']}</>");
        $this->line("   ⏳ Pending: <fg=cyan>{$reviewSummary['pending']}</>");

        if ($reviewSummary['approved'] > 0 && $reviewSummary['changes_requested'] === 0) {
            $this->line('   🎉 <fg=green>PR has approvals and no blocking reviews!</>');
        } elseif ($reviewSummary['changes_requested'] > 0) {
            $this->line('   ⚠️  <fg=yellow>PR has requested changes that need to be addressed</>');
        }
    }

    private function showCommentsSummary(array $comments, array $reviews): void
    {
        $this->newLine();
        $this->line('📊 <info>Comments Summary</info>');

        $issueCount = count($comments['issue_comments'] ?? []);
        $reviewCount = count($comments['review_comments'] ?? []);
        $totalReviews = count($reviews) - (isset($reviews['error']) ? 1 : 0);

        $this->line("   💬 General Comments: <comment>{$issueCount}</comment>");
        $this->line("   🔍 Review Comments: <comment>{$reviewCount}</comment>");
        $this->line("   📋 Reviews: <comment>{$totalReviews}</comment>");

        // Show unresolved count
        $unresolvedCount = $this->countUnresolvedComments($comments);
        if ($unresolvedCount > 0) {
            $this->line("   ⚠️  Unresolved: <fg=yellow>{$unresolvedCount}</>");
        } else {
            $this->line('   ✅ All discussions resolved');
        }
    }

    private function showUnresolvedComments(array $comments, string $repo): void
    {
        $this->newLine();
        $this->line('⚠️  <info>Unresolved Comments</info>');

        $unresolved = $this->getUnresolvedComments($comments);

        if (empty($unresolved)) {
            $this->line('   ✅ <fg=green>No unresolved comments found!</>');

            return;
        }

        foreach ($unresolved as $i => $comment) {
            $line = isset($comment['position']) ? $comment['position'] : ($comment['original_position'] ?? 'unknown');
            $this->line('   '.($i + 1).". 👤 {$comment['user']['login']} <fg=yellow>(Line {$line})</>");
            $this->line('      📅 '.$this->formatDate($comment['created_at']));

            // Render multiline markdown comment
            $this->displayMultilineComment($comment['body'], '      ');

            // Add file and comment links
            if (isset($comment['path'])) {
                $fileUrl = $this->getFileUrl($repo, $comment);
                $this->line("      📄 File: <fg=cyan>{$comment['path']}</> <href={$fileUrl}>(View File)</>");
            }
            if (isset($comment['html_url'])) {
                $this->line("      🔗 <href={$comment['html_url']}>View Comment</>");
            }
            $this->newLine();
        }
    }

    private function showReviews(array $reviews): void
    {
        $this->newLine();
        $this->line('📋 <info>Reviews</info>');

        if (empty($reviews) || isset($reviews['error'])) {
            $this->line('   <fg=yellow>No reviews found</>');

            return;
        }

        foreach ($reviews as $review) {
            $stateIcon = match ($review['state']) {
                'APPROVED' => '✅',
                'CHANGES_REQUESTED' => '❌',
                'COMMENTED' => '💭',
                'PENDING' => '⏳',
                default => '📝',
            };

            $stateColor = match ($review['state']) {
                'APPROVED' => 'green',
                'CHANGES_REQUESTED' => 'red',
                'COMMENTED' => 'yellow',
                'PENDING' => 'cyan',
                default => 'white',
            };

            $this->line("   {$stateIcon} <fg={$stateColor}>{$review['state']}</> by {$review['user']['login']}");

            if (! empty($review['body'])) {
                $this->line('      💭 '.$this->truncateText($review['body'], 80));
            }

            $this->line('      📅 '.$this->formatDate($review['submitted_at']));
            $this->newLine();
        }
    }

    private function showAllComments(array $comments, string $repo): void
    {
        $this->newLine();
        $this->line('💬 <info>All Comments</info>');

        // Show issue comments
        if (! empty($comments['issue_comments'])) {
            $this->line('   <info>General Comments:</info>');
            foreach ($comments['issue_comments'] as $comment) {
                $this->line("   • 👤 {$comment['user']['login']} - ".$this->formatDate($comment['created_at']));

                // Render multiline markdown comment
                $this->displayMultilineComment($comment['body'], '     ');
                $this->newLine();
            }
        }

        // Show review comments
        if (! empty($comments['review_comments'])) {
            $this->line('   <info>Review Comments:</info>');
            foreach ($comments['review_comments'] as $comment) {
                $line = isset($comment['position']) ? " (Line {$comment['position']})" : (isset($comment['original_position']) ? " (Line {$comment['original_position']})" : '');
                $this->line("   • 👤 {$comment['user']['login']}{$line} - ".$this->formatDate($comment['created_at']));

                // Render multiline markdown comment
                $this->displayMultilineComment($comment['body'], '     ');

                // Add file and comment links
                if (isset($comment['path'])) {
                    $fileUrl = $this->getFileUrl($repo, $comment);
                    $this->line("     📄 File: <fg=cyan>{$comment['path']}</> <href={$fileUrl}>(View File)</>");
                }
                if (isset($comment['html_url'])) {
                    $this->line("     🔗 <href={$comment['html_url']}>View Comment</>");
                }
                $this->newLine();
            }
        }
    }

    private function analyzeReviews(array $reviews): array
    {
        $summary = [
            'approved' => 0,
            'changes_requested' => 0,
            'commented' => 0,
            'pending' => 0,
        ];

        foreach ($reviews as $review) {
            $state = strtolower($review['state'] ?? 'pending');
            if ($state === 'approved') {
                $summary['approved']++;
            } elseif ($state === 'changes_requested') {
                $summary['changes_requested']++;
            } elseif ($state === 'commented') {
                $summary['commented']++;
            } else {
                $summary['pending']++;
            }
        }

        return $summary;
    }

    private function countUnresolvedComments(array $comments): int
    {
        // For now, we'll count all review comments as potentially unresolved
        // GitHub's API doesn't directly provide resolution status
        return count($comments['review_comments'] ?? []);
    }

    private function getUnresolvedComments(array $comments): array
    {
        // For now, return all review comments as potentially unresolved
        // In a more advanced implementation, we could check for resolution patterns
        return $comments['review_comments'] ?? [];
    }

    private function outputJson($pr, array $comments, array $reviews): int
    {
        $output = [
            'pr' => [
                'number' => $pr->number,
                'title' => $pr->title,
                'state' => $pr->state,
                'author' => $pr->user->login,
            ],
            'comments' => $comments,
            'reviews' => $reviews,
            'summary' => [
                'review_status' => $this->analyzeReviews($reviews),
                'comment_counts' => [
                    'issue_comments' => count($comments['issue_comments'] ?? []),
                    'review_comments' => count($comments['review_comments'] ?? []),
                    'total_reviews' => count($reviews) - (isset($reviews['error']) ? 1 : 0),
                ],
                'unresolved_count' => $this->countUnresolvedComments($comments),
            ],
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT));

        return 0;
    }

    private function formatDate(string $date): string
    {
        return date('M j, Y \a\t g:i A', strtotime($date));
    }

    private function truncateText(string $text, int $maxLength): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return strlen($text) > $maxLength ? substr($text, 0, $maxLength).'...' : $text;
    }

    private function getFileUrl(string $repo, array $comment): string
    {
        $commitId = $comment['commit_id'] ?? 'HEAD';
        $path = $comment['path'] ?? '';
        $line = $comment['position'] ?? $comment['original_position'] ?? null;

        $url = "https://github.com/{$repo}/blob/{$commitId}/{$path}";
        if ($line) {
            $url .= "#L{$line}";
        }

        return $url;
    }

    private function renderMarkdown(string $text): string
    {
        // Remove remaining HTML tags (collapsible sections handled earlier)
        $text = preg_replace('/<\/?[^>]+>/', '', $text);

        // Basic markdown rendering for CLI output
        $text = preg_replace('/\*\*(.*?)\*\*/', '<options=bold>$1</>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<fg=yellow>$1</>', $text);
        $text = preg_replace('/`(.*?)`/', '<fg=green>$1</>', $text);
        $text = preg_replace('/_(.*?)_/', '<fg=cyan>$1</>', $text);

        return $text;
    }

    private function displayMultilineComment(string $body, string $prefix = '     '): void
    {
        // Pre-process collapsible sections
        $body = $this->formatCollapsibleSections($body);

        // Split on newlines and render each line with proper indentation
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $renderedLine = $this->renderMarkdown(trim($line));
            if (! empty($renderedLine)) {
                $this->line("{$prefix}💭 {$renderedLine}");
            } else {
                $this->line($prefix); // Empty line for spacing
            }
        }
    }

    private function formatCollapsibleSections(string $text): string
    {
        // Handle <details><summary>...</summary>content</details> sections
        $pattern = '/<details>\s*<summary>(.*?)<\/summary>(.*?)<\/details>/s';
        $text = preg_replace_callback($pattern, function ($matches) {
            $summary = trim($matches[1]);
            $content = trim($matches[2]);

            return "\n📁 [Expandable] {$summary}\n{$content}\n";
        }, $text);

        // Handle standalone summary tags
        $text = preg_replace('/<summary>(.*?)<\/summary>/', '📋 $1', $text);

        // Handle standalone details tags
        $text = preg_replace('/<details>/', '📁 [Collapsible Section]', $text);
        $text = preg_replace('/<\/details>/', '', $text);

        return $text;
    }
}
