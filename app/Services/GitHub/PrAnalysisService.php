<?php

namespace App\Services\GitHub;

use JordanPartridge\GithubClient\Data\Pulls\PullRequestDetailDTO;
use JordanPartridge\GithubClient\Facades\Github;

class PrAnalysisService
{
    /**
     * Analyze PR merge readiness with detailed insights
     */
    public function analyzeMergeReadiness(string $repo, int $prNumber): array
    {
        [$owner, $repoName] = explode('/', $repo);
        $pr = Github::pullRequests()->detail($owner, $repoName, $prNumber);

        if (! $pr) {
            return ['error' => 'Pull request not found'];
        }

        return [
            'pr_info' => [
                'number' => $pr->number,
                'title' => $pr->title,
                'author' => $pr->user->login,
                'state' => $pr->state,
                'draft' => $pr->draft,
            ],
            'merge_analysis' => [
                'ready_to_merge' => $pr->isReadyToMerge(),
                'has_conflicts' => $pr->hasMergeConflicts(),
                'can_rebase' => $pr->canRebase(),
                'status_description' => $pr->getMergeStatusDescription(),
                'mergeable' => $pr->mergeable,
                'mergeable_state' => $pr->mergeable_state,
                'rebaseable' => $pr->rebaseable,
            ],
            'code_analysis' => [
                'files_changed' => $pr->changed_files,
                'additions' => $pr->additions,
                'deletions' => $pr->deletions,
                'total_changes' => $pr->getTotalLinesChanged(),
                'addition_ratio' => $pr->getAdditionRatio(),
                'change_size' => $this->getChangeSizeCategory($pr),
            ],
            'discussion_analysis' => [
                'total_comments' => ($pr->comments ?? 0) + ($pr->review_comments ?? 0),
                'regular_comments' => $pr->comments,
                'review_comments' => $pr->review_comments,
                'has_discussion' => ($pr->comments ?? 0) + ($pr->review_comments ?? 0) > 0,
                'commits' => $pr->commits,
            ],
            'recommendations' => $this->getRecommendations($pr),
        ];
    }

    /**
     * Get PR health score and recommendations
     */
    public function getHealthScore(string $repo, int $prNumber): array
    {
        $analysis = $this->analyzeMergeReadiness($repo, $prNumber);

        if (isset($analysis['error'])) {
            return $analysis;
        }

        $score = $this->calculateHealthScore($analysis);

        return [
            'health_score' => $score,
            'grade' => $this->getGrade($score),
            'status' => $this->getStatus($score),
            'analysis' => $analysis,
        ];
    }

    /**
     * Check multiple PRs for merge readiness
     */
    public function batchAnalyze(string $repo, array $prNumbers): array
    {
        $results = [];

        foreach ($prNumbers as $prNumber) {
            $results[$prNumber] = $this->analyzeMergeReadiness($repo, $prNumber);
        }

        return [
            'repository' => $repo,
            'analyzed_prs' => count($prNumbers),
            'results' => $results,
            'summary' => $this->generateBatchSummary($results),
        ];
    }

    /**
     * Get intelligent merge recommendations
     */
    private function getRecommendations(PullRequestDetailDTO $pr): array
    {
        $recommendations = [];

        // Handle closed/merged PRs first
        if ($pr->state === 'closed') {
            if ($pr->merged) {
                $recommendations[] = [
                    'type' => 'success',
                    'action' => 'already_merged',
                    'message' => '✅ PR successfully merged'.($pr->merged_at ? ' on '.date('M j, Y', strtotime($pr->merged_at)) : ''),
                ];
            } else {
                $recommendations[] = [
                    'type' => 'info',
                    'action' => 'closed_unmerged',
                    'message' => '🚫 PR was closed without merging',
                ];
            }

            return $recommendations;
        }

        // Recommendations for open PRs
        if ($pr->hasMergeConflicts()) {
            $recommendations[] = [
                'type' => 'critical',
                'action' => 'resolve_conflicts',
                'message' => 'Resolve merge conflicts before proceeding',
            ];
        }

        if ($pr->draft) {
            $recommendations[] = [
                'type' => 'warning',
                'action' => 'mark_ready',
                'message' => 'Mark as ready for review when complete',
            ];
        }

        if (! $pr->hasComments() && $pr->getTotalLinesChanged() > 100) {
            $recommendations[] = [
                'type' => 'suggestion',
                'action' => 'request_review',
                'message' => 'Consider requesting reviews for large changes',
            ];
        }

        if ($pr->isReadyToMerge()) {
            $recommendations[] = [
                'type' => 'success',
                'action' => 'ready_to_merge',
                'message' => 'PR is ready to merge - no conflicts detected',
            ];
        }

        if ($pr->canRebase() && ! $pr->isReadyToMerge()) {
            $recommendations[] = [
                'type' => 'info',
                'action' => 'consider_rebase',
                'message' => 'Consider rebasing to update with latest changes',
            ];
        }

        return $recommendations;
    }

    /**
     * Categorize change size
     */
    private function getChangeSizeCategory(PullRequestDetailDTO $pr): string
    {
        $totalChanges = $pr->getTotalLinesChanged();

        return match (true) {
            $totalChanges < 10 => 'tiny',
            $totalChanges < 50 => 'small',
            $totalChanges < 200 => 'medium',
            $totalChanges < 500 => 'large',
            $totalChanges < 1000 => 'huge',
            default => 'massive',
        };
    }

    /**
     * Calculate overall health score (0-100)
     */
    private function calculateHealthScore(array $analysis): int
    {
        $score = 100;

        // Merge readiness (40% weight)
        if ($analysis['merge_analysis']['has_conflicts']) {
            $score -= 40;
        } elseif (! $analysis['merge_analysis']['ready_to_merge']) {
            $score -= 20;
        }

        // Code quality indicators (30% weight)
        $changeSize = $analysis['code_analysis']['change_size'];
        if (in_array($changeSize, ['huge', 'massive'])) {
            $score -= 15;
        } elseif ($changeSize === 'large') {
            $score -= 5;
        }

        // Discussion quality (20% weight)
        $totalChanges = $analysis['code_analysis']['total_changes'];
        $hasDiscussion = $analysis['discussion_analysis']['has_discussion'];

        if ($totalChanges > 100 && ! $hasDiscussion) {
            $score -= 20;
        } elseif ($totalChanges > 50 && ! $hasDiscussion) {
            $score -= 10;
        }

        // Draft status (10% weight)
        if ($analysis['pr_info']['draft']) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }

    /**
     * Convert score to letter grade
     */
    private function getGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }

    /**
     * Get status based on score
     */
    private function getStatus(int $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 80 => 'good',
            $score >= 70 => 'fair',
            $score >= 60 => 'needs_attention',
            default => 'problematic',
        };
    }

    /**
     * Generate summary for batch analysis
     */
    private function generateBatchSummary(array $results): array
    {
        $total = count($results);
        $readyToMerge = 0;
        $hasConflicts = 0;
        $drafts = 0;

        foreach ($results as $result) {
            if (isset($result['merge_analysis'])) {
                if ($result['merge_analysis']['ready_to_merge']) {
                    $readyToMerge++;
                }
                if ($result['merge_analysis']['has_conflicts']) {
                    $hasConflicts++;
                }
                if ($result['pr_info']['draft']) {
                    $drafts++;
                }
            }
        }

        return [
            'total_prs' => $total,
            'ready_to_merge' => $readyToMerge,
            'has_conflicts' => $hasConflicts,
            'drafts' => $drafts,
            'merge_ready_percentage' => $total > 0 ? round(($readyToMerge / $total) * 100, 1) : 0,
        ];
    }
}
