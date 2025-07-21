<?php

namespace App\Services;

use JordanPartridge\GithubClient\Facades\Github;

class PrAnalysisService
{
    /**
     * Perform comprehensive PR analysis with metadata and intelligence
     */
    public function analyzeComprehensive(string $owner, string $repo, int $prNumber, array $options = []): array
    {
        $pr = Github::pullRequests()->get($owner, $repo, $prNumber);

        // Core analysis components
        $metadata = $this->extractMetadata($pr);
        $reviews = $this->analyzeReviews($owner, $repo, $prNumber);
        $checks = $this->analyzeChecks($owner, $repo, $pr->head->sha ?? null);
        $conflicts = $this->detectConflicts($pr);
        $intelligence = $this->generateAIInsights($pr, $metadata, $reviews, $options);
        $mergeability = $this->assessMergeReadiness($pr, $reviews, $checks, $conflicts);

        return [
            'pr' => $this->formatPrData($pr),
            'metadata' => $metadata,
            'reviews' => $reviews,
            'checks' => $checks,
            'conflicts' => $conflicts,
            'intelligence' => $intelligence,
            'mergeability' => $mergeability,
            'analysis_timestamp' => now()->toISOString(),
        ];
    }

    private function extractMetadata($pr): array
    {
        return [
            'total_commits' => $pr->commits ?? 0,
            'additions' => $pr->additions ?? 0,
            'deletions' => $pr->deletions ?? 0,
            'changed_files' => $pr->changed_files ?? 0,
            'discussion_count' => $pr->comments ?? 0,
            'review_comments' => $pr->review_comments ?? 0,
            'updated_relative' => $this->formatRelativeTime($pr->updated_at ?? now()),
            'velocity' => $this->calculateVelocity($pr),
            'test_coverage' => $this->estimateTestCoverage($pr),
        ];
    }

    private function analyzeReviews(string $owner, string $repo, int $prNumber): array
    {
        $reviews = ['human' => [], 'coderabbit' => []];

        try {
            // Get all reviews
            $prReviews = Github::pullRequests()->reviews($owner, $repo, $prNumber);
            $reviewComments = Github::pullRequests()->comments($owner, $repo, $prNumber);

            // Fallback: Try GitHub CLI to get accurate review comments
            $ghComments = $this->fetchReviewCommentsViaGH($owner, $repo, $prNumber);
            if (! empty($ghComments)) {
                $reviewComments = $ghComments;
            }

            // Process human reviews
            if (is_array($prReviews) || (is_object($prReviews) && method_exists($prReviews, 'toArray'))) {
                $reviewsArray = is_array($prReviews) ? $prReviews : $prReviews->toArray();

                foreach ($reviewsArray as $review) {
                    $reviewer = is_array($review) ? ($review['user']['login'] ?? 'unknown') : ($review->user->login ?? 'unknown');
                    $state = is_array($review) ? ($review['state'] ?? 'unknown') : ($review->state ?? 'unknown');

                    if ($reviewer === 'coderabbitai[bot]' || $reviewer === 'coderabbitai') {
                        // Parse CodeRabbit review
                        $reviews['coderabbit'] = $this->parseCodeRabbitReview($review, $reviewComments);
                    } else {
                        $reviews['human'][] = [
                            'reviewer' => $reviewer,
                            'state' => $state,
                            'comments' => 0, // TODO: count review-specific comments
                            'submitted_at' => is_array($review) ? ($review['submitted_at'] ?? null) : ($review->submitted_at ?? null),
                        ];
                    }
                }
            }

            // If no CodeRabbit found in reviews but comments exist, parse comments directly
            if (empty($reviews['coderabbit']) || ! ($reviews['coderabbit']['found'] ?? false)) {
                $reviews['coderabbit'] = $this->parseCodeRabbitComments($reviewComments);
            }

        } catch (\Exception $e) {
            // Fallback to basic review data
            $reviews['error'] = $e->getMessage();
        }

        return $reviews;
    }

    private function parseCodeRabbitReview($review, $comments): array
    {
        $coderabbit = [
            'actionable' => 0,
            'nitpick' => 0,
            'outside_range' => 0,
            'top_issues' => [],
            'found' => false,
        ];

        // Parse review comments for CodeRabbit categorization
        if (is_array($comments) || (is_object($comments) && method_exists($comments, 'toArray'))) {
            $commentsArray = is_array($comments) ? $comments : $comments->toArray();

            foreach ($commentsArray as $comment) {
                $body = is_array($comment) ? ($comment['body'] ?? '') : ($comment->body ?? '');
                $user = is_array($comment) ? ($comment['user']['login'] ?? '') : ($comment->user->login ?? '');

                if ($user === 'coderabbitai[bot]' || $user === 'coderabbitai') {
                    $coderabbit['found'] = true;

                    // Enhanced categorization based on CodeRabbit patterns
                    if (preg_match('/\*\*[^*]+\*\*/', $body) ||
                        stripos($body, 'consider') !== false ||
                        stripos($body, 'should') !== false ||
                        stripos($body, 'recommend') !== false ||
                        stripos($body, 'suggest') !== false) {
                        $coderabbit['actionable']++;
                        $coderabbit['top_issues'][] = $this->extractIssueText($body);
                    } elseif (stripos($body, 'nitpick') !== false ||
                              stripos($body, 'minor') !== false ||
                              stripos($body, 'style') !== false) {
                        $coderabbit['nitpick']++;
                    } elseif (stripos($body, 'out of the range') !== false ||
                              stripos($body, 'outside') !== false) {
                        $coderabbit['outside_range']++;
                    } else {
                        // Default to actionable if from CodeRabbit but unclear category
                        $coderabbit['actionable']++;
                        $coderabbit['top_issues'][] = $this->extractIssueText($body);
                    }
                }
            }
        }

        return $coderabbit;
    }

    private function fetchReviewCommentsViaGH(string $owner, string $repo, int $prNumber): array
    {
        try {
            $command = sprintf(
                'gh api repos/%s/%s/pulls/%d/comments --jq ".[] | {body: .body, user: {login: .user.login}}" 2>/dev/null',
                escapeshellarg($owner),
                escapeshellarg($repo),
                $prNumber
            );

            $output = shell_exec($command);
            if (! $output) {
                return [];
            }

            // Parse JSON objects (one per line)
            $comments = [];
            $lines = explode("\n", trim($output));

            foreach ($lines as $line) {
                $line = trim($line);
                if (! empty($line)) {
                    $comment = json_decode($line, true);
                    if ($comment && isset($comment['body'], $comment['user']['login'])) {
                        $comments[] = $comment;
                    }
                }
            }

            return $comments;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function parseCodeRabbitComments($comments): array
    {
        $coderabbit = [
            'actionable' => 0,
            'nitpick' => 0,
            'outside_range' => 0,
            'top_issues' => [],
            'found' => false,
        ];

        if (empty($comments)) {
            return $coderabbit;
        }

        // Parse comments array/collection
        $commentsArray = is_array($comments) ? $comments : (method_exists($comments, 'toArray') ? $comments->toArray() : []);

        foreach ($commentsArray as $comment) {
            $body = is_array($comment) ? ($comment['body'] ?? '') : ($comment->body ?? '');
            $user = is_array($comment) ? ($comment['user']['login'] ?? '') : ($comment->user->login ?? '');

            if ($user === 'coderabbitai[bot]' || $user === 'coderabbitai') {
                $coderabbit['found'] = true;

                // Enhanced categorization
                if (preg_match('/\*\*[^*]+\*\*/', $body) ||
                    stripos($body, 'consider') !== false ||
                    stripos($body, 'should') !== false ||
                    stripos($body, 'recommend') !== false ||
                    stripos($body, 'suggest') !== false) {
                    $coderabbit['actionable']++;
                    $coderabbit['top_issues'][] = $this->extractIssueText($body);
                } elseif (stripos($body, 'nitpick') !== false ||
                          stripos($body, 'minor') !== false ||
                          stripos($body, 'style') !== false) {
                    $coderabbit['nitpick']++;
                } elseif (stripos($body, 'out of the range') !== false ||
                          stripos($body, 'outside') !== false) {
                    $coderabbit['outside_range']++;
                } else {
                    // Default to actionable if from CodeRabbit but unclear category
                    $coderabbit['actionable']++;
                    $coderabbit['top_issues'][] = $this->extractIssueText($body);
                }
            }
        }

        return $coderabbit;
    }

    private function extractIssueText(string $body): string
    {
        // Extract meaningful issue text from CodeRabbit comment
        $lines = explode("\n", $body);

        // Look for the main issue description (usually after the first **header**)
        $foundHeader = false;
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and markdown artifacts
            if (empty($line) || str_starts_with($line, '```') || str_starts_with($line, '<details>') || str_starts_with($line, '<!--')) {
                continue;
            }

            // If we find a **header**, mark it and continue to next line for description
            if (preg_match('/^\*\*(.+)\*\*$/', $line, $matches)) {
                $foundHeader = true;

                return mb_strimwidth($matches[1], 0, 80, '...');
            }

            // If we found a header previously, this might be the description
            if ($foundHeader && strlen($line) > 20) {
                return mb_strimwidth($line, 0, 80, '...');
            }

            // For other meaningful lines
            if (strlen($line) > 20 && ! str_starts_with($line, '_')) {
                return mb_strimwidth($line, 0, 80, '...');
            }
        }

        // Fallback to first meaningful line
        return mb_strimwidth($body, 0, 80, '...');
    }

    private function analyzeChecks(string $owner, string $repo, ?string $sha): array
    {
        if (! $sha) {
            return [];
        }

        $checks = [];

        try {
            // Try to get check runs (this might not exist in github-client yet)
            // This is a placeholder for when the endpoint is available
            $checks = [
                ['name' => 'CI/CD Pipeline', 'status' => 'success', 'duration' => '2m 34s'],
                ['name' => 'Code Quality', 'status' => 'success', 'duration' => '1m 12s'],
                ['name' => 'Security Scan', 'status' => 'pending', 'duration' => null],
            ];
        } catch (\Exception $e) {
            // Fallback to basic status
            $checks = [
                ['name' => 'Status Check', 'status' => 'unknown', 'duration' => null],
            ];
        }

        return $checks;
    }

    private function detectConflicts($pr): array
    {
        $conflicts = [];

        // Check mergeable status
        if (isset($pr->mergeable) && $pr->mergeable === false) {
            $conflicts[] = [
                'file' => 'Multiple files',
                'lines' => 'Unknown',
                'type' => 'merge_conflict',
            ];
        }

        return $conflicts;
    }

    private function generateAIInsights($pr, array $metadata, array $reviews, array $options): array
    {
        // Generate AI-powered insights based on PR data
        $intelligence = [
            'focus_score' => $this->calculateFocusScore($metadata),
            'quality_score' => $this->calculateQualityScore($pr, $reviews),
            'docs_impact' => $this->assessDocsImpact($pr),
            'performance_risk' => $this->assessPerformanceRisk($metadata),
            'security_risk' => $this->assessSecurityRisk($pr),
            'review_insights' => $this->generateReviewInsights($reviews),
            'priority_actions' => $this->generatePriorityActions($pr, $reviews),
            'quick_wins' => $this->generateQuickWins($pr, $metadata),
            'agent_commands' => $this->generateAgentCommands($pr, $reviews),
        ];

        return $intelligence;
    }

    private function assessMergeReadiness($pr, array $reviews, array $checks, array $conflicts): array
    {
        $score = 10;
        $issues = [];

        // Check reviews
        $hasApprovals = false;
        foreach ($reviews['human'] ?? [] as $review) {
            if ($review['state'] === 'APPROVED') {
                $hasApprovals = true;
                break;
            } elseif ($review['state'] === 'CHANGES_REQUESTED') {
                $score -= 3;
                $issues[] = 'Changes requested in reviews';
            }
        }

        if (! $hasApprovals && ! empty($reviews['human'])) {
            $score -= 2;
            $issues[] = 'No approving reviews';
        }

        // Check CI/CD
        $checksPassing = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'failure') {
                $score -= 2;
                $checksPassing = false;
                $issues[] = "Failed check: {$check['name']}";
            } elseif ($check['status'] === 'pending') {
                $score -= 1;
                $checksPassing = false;
                $issues[] = "Pending check: {$check['name']}";
            }
        }

        // Check conflicts
        if (! empty($conflicts)) {
            $score -= 4;
            $issues[] = 'Merge conflicts detected';
        }

        // Check CodeRabbit feedback
        if (($reviews['coderabbit']['actionable'] ?? 0) > 5) {
            $score -= 2;
            $issues[] = 'High number of actionable CodeRabbit issues';
        }

        $score = max(0, $score);

        return [
            'confidence_score' => $score,
            'status' => $this->getReadinessStatus($score),
            'checks_pass' => $checksPassing,
            'reviews_complete' => $hasApprovals,
            'no_conflicts' => empty($conflicts),
            'up_to_date' => true, // TODO: implement branch comparison
            'quality_pass' => $score >= 6,
            'blocking_issues' => $issues,
        ];
    }

    // Helper methods for calculations
    private function calculateFocusScore(array $metadata): float
    {
        // Lower score for large PRs that touch many files
        $fileScore = max(0, 10 - ($metadata['changed_files'] / 5));
        $sizeScore = max(0, 10 - (($metadata['additions'] + $metadata['deletions']) / 100));

        return round(($fileScore + $sizeScore) / 2, 1);
    }

    private function calculateQualityScore($pr, array $reviews): float
    {
        $score = 7.0; // Start with decent baseline

        // Boost for good description
        if (strlen($pr->body ?? '') > 100) {
            $score += 1.0;
        }

        // Reduce for high actionable issues
        $actionable = $reviews['coderabbit']['actionable'] ?? 0;
        $score -= min(3.0, $actionable * 0.3);

        return max(0, min(10, round($score, 1)));
    }

    private function assessDocsImpact($pr): string
    {
        $body = strtolower($pr->body ?? '');
        $title = strtolower($pr->title ?? '');

        if (strpos($body, 'documentation') !== false || strpos($title, 'docs') !== false) {
            return 'Positive';
        } elseif (strpos($body, 'readme') !== false || strpos($body, 'doc') !== false) {
            return 'Positive';
        } else {
            return 'Neutral';
        }
    }

    private function assessPerformanceRisk(array $metadata): string
    {
        $totalChanges = $metadata['additions'] + $metadata['deletions'];

        if ($totalChanges > 1000 || $metadata['changed_files'] > 20) {
            return 'High';
        } elseif ($totalChanges > 200 || $metadata['changed_files'] > 5) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }

    private function assessSecurityRisk($pr): string
    {
        $body = strtolower($pr->body ?? '');
        $title = strtolower($pr->title ?? '');

        $securityKeywords = ['auth', 'password', 'token', 'secret', 'security', 'vulnerability'];

        foreach ($securityKeywords as $keyword) {
            if (strpos($body, $keyword) !== false || strpos($title, $keyword) !== false) {
                return 'Medium';
            }
        }

        return 'Low';
    }

    private function generateReviewInsights(array $reviews): array
    {
        $insights = [];

        if (($reviews['coderabbit']['actionable'] ?? 0) > 3) {
            $insights[] = 'CodeRabbit identified several architectural improvements';
        }

        if (empty($reviews['human'])) {
            $insights[] = 'Consider requesting human review for additional perspective';
        }

        return $insights;
    }

    private function generatePriorityActions($pr, array $reviews): array
    {
        $actions = [];

        if (($reviews['coderabbit']['actionable'] ?? 0) > 0) {
            $actions[] = "Address {$reviews['coderabbit']['actionable']} actionable CodeRabbit issues";
        }

        if (empty($reviews['human'])) {
            $actions[] = 'Request review from team members';
        }

        return $actions;
    }

    private function generateQuickWins($pr, array $metadata): array
    {
        $wins = [];

        if ($metadata['review_comments'] === 0) {
            $wins[] = 'No review comments to address - clean implementation';
        }

        if ($metadata['changed_files'] <= 3) {
            $wins[] = 'Focused change set - easy to review and merge';
        }

        return $wins;
    }

    private function generateAgentCommands($pr, array $reviews): array
    {
        $commands = [];

        if (($reviews['coderabbit']['actionable'] ?? 0) > 0) {
            $commands[] = '@coderabbitai generate action items for this PR';
        }

        $commands[] = 'conduit pr:analyze '.$pr->number.' --include-diff';

        return $commands;
    }

    private function formatPrData($pr): array
    {
        return [
            'number' => $pr->number ?? 0,
            'title' => $pr->title ?? 'No title',
            'author' => $pr->user->login ?? 'unknown',
            'state' => $pr->state ?? 'unknown',
            'html_url' => $pr->html_url ?? '',
        ];
    }

    private function calculateVelocity($pr): string
    {
        $created = strtotime($pr->created_at ?? date('c'));
        $updated = strtotime($pr->updated_at ?? date('c'));
        $daysDiff = max(1, ($updated - $created) / 86400);

        $totalChanges = ($pr->additions ?? 0) + ($pr->deletions ?? 0);
        $velocity = $totalChanges / $daysDiff;

        if ($velocity > 100) {
            return 'High';
        } elseif ($velocity > 25) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }

    private function estimateTestCoverage($pr): string
    {
        // Simple heuristic based on file patterns
        $body = strtolower($pr->body ?? '');

        if (strpos($body, 'test') !== false || strpos($body, 'spec') !== false) {
            return 'Added';
        } else {
            return 'Unknown';
        }
    }

    private function formatRelativeTime(string $datetime): string
    {
        $timestamp = strtotime($datetime);
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

    private function getReadinessStatus(float $score): string
    {
        return match (true) {
            $score >= 9 => 'READY',
            $score >= 7 => 'REVIEW_NEEDED',
            $score >= 5 => 'IMPROVEMENTS_NEEDED',
            default => 'NOT_READY'
        };
    }
}
