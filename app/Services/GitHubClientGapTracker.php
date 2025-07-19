<?php

namespace App\Services;

use JordanPartridge\GithubClient\Facades\Github;

class GitHubClientGapTracker
{
    private array $discoveredGaps = [];
    private array $missingEndpoints = [];
    private array $incompleteData = [];

    /**
     * Test PR analysis capabilities and identify gaps
     */
    public function analyzePrCapabilities(string $owner, string $repo, int $prNumber): array
    {
        $gaps = [];
        
        // Test basic PR data completeness
        $gaps['pr_data'] = $this->testPrDataCompleteness($owner, $repo, $prNumber);
        
        // Test review capabilities
        $gaps['review_data'] = $this->testReviewDataCompleteness($owner, $repo, $prNumber);
        
        // Test check status capabilities
        $gaps['check_data'] = $this->testCheckStatusCapabilities($owner, $repo, $prNumber);
        
        // Test diff analysis capabilities
        $gaps['diff_data'] = $this->testDiffAnalysisCapabilities($owner, $repo, $prNumber);
        
        // Test merge analysis capabilities
        $gaps['merge_data'] = $this->testMergeAnalysisCapabilities($owner, $repo, $prNumber);

        return [
            'gaps_found' => $gaps,
            'missing_endpoints' => $this->missingEndpoints,
            'incomplete_data' => $this->incompleteData,
            'recommended_issues' => $this->generateRecommendedIssues($gaps)
        ];
    }

    private function testPrDataCompleteness(string $owner, string $repo, int $prNumber): array
    {
        $gaps = [];

        try {
            // Test individual PR fetch
            $pr = Github::pullRequests()->get($owner, $repo, $prNumber);
            
            // Check for missing fields we need
            $requiredFields = [
                'comments' => 'Comment count for discussion analysis',
                'review_comments' => 'Review comment count for code feedback analysis', 
                'commits' => 'Commit count for change complexity analysis',
                'additions' => 'Lines added for size impact analysis',
                'deletions' => 'Lines deleted for size impact analysis',
                'changed_files' => 'File count for scope analysis',
                'mergeable' => 'Merge conflict status',
                'mergeable_state' => 'Detailed merge status',
                'merge_commit_sha' => 'Merge commit reference',
            ];

            foreach ($requiredFields as $field => $purpose) {
                if (!isset($pr->$field) || $pr->$field === null) {
                    $gaps['missing_pr_fields'][] = [
                        'field' => $field,
                        'purpose' => $purpose,
                        'current_value' => $pr->$field ?? 'null'
                    ];
                }
            }

            // Test if comment counts are accurate
            if (isset($pr->comments) && $pr->comments === 0) {
                $gaps['potential_comment_bug'] = [
                    'issue' => 'Comment count shows 0 but may be inaccurate',
                    'test_needed' => 'Compare with GitHub API direct response'
                ];
            }

        } catch (\Exception $e) {
            $gaps['pr_fetch_error'] = [
                'error' => $e->getMessage(),
                'endpoint' => "GET /repos/{$owner}/{$repo}/pulls/{$prNumber}"
            ];
        }

        return $gaps;
    }

    private function testReviewDataCompleteness(string $owner, string $repo, int $prNumber): array
    {
        $gaps = [];

        try {
            // Test review list endpoint
            $reviews = Github::pullRequests()->reviews($owner, $repo, $prNumber);
            
            if (is_array($reviews) && empty($reviews)) {
                $gaps['no_reviews_data'] = [
                    'issue' => 'No reviews returned but PR may have reviews',
                    'investigation' => 'Check if endpoint exists or returns correct data'
                ];
            } else {
                // Check if we got Collection or array
                if (is_array($reviews)) {
                    $gaps['review_data_type'] = [
                        'issue' => 'Reviews endpoint returns array instead of Collection',
                        'expected' => 'Collection with isEmpty() method',
                        'actual' => 'Array'
                    ];
                }
                
                // Check review data completeness
                $reviewsArray = is_array($reviews) ? $reviews : $reviews->toArray();
                foreach ($reviewsArray as $review) {
                    $missingReviewFields = [];
                    $requiredReviewFields = [
                        'state' => 'Approval status (APPROVED, CHANGES_REQUESTED, etc)',
                        'submitted_at' => 'Review timestamp for timeline analysis',
                        'user' => 'Reviewer information',
                        'body' => 'Review summary for AI analysis'
                    ];

                    foreach ($requiredReviewFields as $field => $purpose) {
                        $fieldValue = is_array($review) ? ($review[$field] ?? null) : ($review->$field ?? null);
                        if ($fieldValue === null) {
                            $missingReviewFields[] = ['field' => $field, 'purpose' => $purpose];
                        }
                    }

                    if (!empty($missingReviewFields)) {
                        $reviewId = is_array($review) ? ($review['id'] ?? 'unknown') : ($review->id ?? 'unknown');
                        $gaps['incomplete_review_data'][] = [
                            'review_id' => $reviewId,
                            'missing_fields' => $missingReviewFields
                        ];
                    }
                }
            }

            // Test review comments endpoint
            $reviewComments = Github::pullRequests()->comments($owner, $repo, $prNumber);
            
            if ((is_array($reviewComments) && empty($reviewComments)) || 
                (is_object($reviewComments) && method_exists($reviewComments, 'isEmpty') && $reviewComments->isEmpty())) {
                $gaps['no_review_comments'] = [
                    'issue' => 'No review comments returned',
                    'investigation' => 'May be missing endpoint or data mapping issue'
                ];
            }

        } catch (\Exception $e) {
            $gaps['review_fetch_error'] = [
                'error' => $e->getMessage(),
                'missing_methods' => [
                    'Github::pullRequests()->reviews()',
                    'Github::pullRequests()->comments()'
                ]
            ];

            $this->missingEndpoints[] = [
                'endpoint' => "GET /repos/{$owner}/{$repo}/pulls/{$prNumber}/reviews",
                'purpose' => 'Fetch PR reviews for approval analysis',
                'priority' => 'HIGH'
            ];
        }

        return $gaps;
    }

    private function testCheckStatusCapabilities(string $owner, string $repo, int $prNumber): array
    {
        $gaps = [];

        try {
            // Get PR to find head SHA
            $pr = Github::pullRequests()->get($owner, $repo, $prNumber);
            $headSha = $pr->head->sha ?? null;

            if (!$headSha) {
                $gaps['no_head_sha'] = 'Cannot get commit SHA for check status';
                return $gaps;
            }

            // Test check runs endpoint
            try {
                if (method_exists(Github::class, 'checks')) {
                    $checkRuns = Github::checks()->runs($owner, $repo, $headSha);
                } else {
                    throw new \Exception('checks() resource does not exist');
                }
            } catch (\Exception $e) {
                $gaps['missing_check_runs'] = [
                    'error' => $e->getMessage(),
                    'needed_endpoint' => "GET /repos/{$owner}/{$repo}/commits/{$headSha}/check-runs",
                    'purpose' => 'CI/CD status analysis'
                ];

                $this->missingEndpoints[] = [
                    'endpoint' => "GET /repos/{$owner}/{$repo}/commits/{$headSha}/check-runs",
                    'purpose' => 'Get CI/CD check status for merge readiness',
                    'priority' => 'HIGH'
                ];
            }

            // Test status checks endpoint  
            try {
                if (method_exists(Github::class, 'commits')) {
                    $statuses = Github::commits()->status($owner, $repo, $headSha);
                } else {
                    throw new \Exception('commits() resource does not exist');
                }
            } catch (\Exception $e) {
                $gaps['missing_commit_status'] = [
                    'error' => $e->getMessage(),
                    'needed_endpoint' => "GET /repos/{$owner}/{$repo}/commits/{$headSha}/status",
                    'purpose' => 'Legacy status checks'
                ];

                $this->missingEndpoints[] = [
                    'endpoint' => "GET /repos/{$owner}/{$repo}/commits/{$headSha}/status", 
                    'purpose' => 'Get commit status for legacy CI systems',
                    'priority' => 'MEDIUM'
                ];
            }

        } catch (\Exception $e) {
            $gaps['check_status_error'] = $e->getMessage();
        }

        return $gaps;
    }

    private function testDiffAnalysisCapabilities(string $owner, string $repo, int $prNumber): array
    {
        $gaps = [];

        try {
            // Test if we can get PR diff data
            $pr = Github::pullRequests()->get($owner, $repo, $prNumber);
            
            // Check if diff URLs are available
            if (!isset($pr->diff_url) || !isset($pr->patch_url)) {
                $gaps['missing_diff_urls'] = 'No diff/patch URLs for file analysis';
            }

            // Test if we can fetch actual diff content
            try {
                // This method probably doesn't exist in github-client
                if (method_exists(Github::pullRequests(), 'diff')) {
                    $diff = Github::pullRequests()->diff($owner, $repo, $prNumber);
                } else {
                    throw new \Exception('diff() method does not exist on PullRequestResource');
                }
            } catch (\Exception $e) {
                $gaps['missing_diff_content'] = [
                    'error' => $e->getMessage(),
                    'needed_endpoint' => "GET /repos/{$owner}/{$repo}/pulls/{$prNumber}/files",
                    'purpose' => 'File-by-file diff analysis for AI insights'
                ];

                $this->missingEndpoints[] = [
                    'endpoint' => "GET /repos/{$owner}/{$repo}/pulls/{$prNumber}/files",
                    'purpose' => 'Get detailed file changes for diff analysis',
                    'priority' => 'HIGH'
                ];
            }

        } catch (\Exception $e) {
            $gaps['diff_analysis_error'] = $e->getMessage();
        }

        return $gaps;
    }

    private function testMergeAnalysisCapabilities(string $owner, string $repo, int $prNumber): array
    {
        $gaps = [];

        try {
            $pr = Github::pullRequests()->get($owner, $repo, $prNumber);

            // Check merge-related fields
            $mergeFields = [
                'mergeable' => 'Can be merged without conflicts',
                'mergeable_state' => 'Detailed merge status (clean, dirty, etc)',
                'rebaseable' => 'Can be rebased',
                'merge_commit_sha' => 'Preview merge commit'
            ];

            foreach ($mergeFields as $field => $purpose) {
                if (!isset($pr->$field)) {
                    $gaps['missing_merge_fields'][] = [
                        'field' => $field,
                        'purpose' => $purpose
                    ];
                }
            }

            // Test merge simulation (probably doesn't exist)
            try {
                if (method_exists(Github::pullRequests(), 'mergePreview')) {
                    $mergePreview = Github::pullRequests()->mergePreview($owner, $repo, $prNumber);
                } else {
                    throw new \Exception('mergePreview() method does not exist');
                }
            } catch (\Exception $e) {
                $gaps['missing_merge_preview'] = [
                    'error' => $e->getMessage(),
                    'needed_method' => 'mergePreview()',
                    'purpose' => 'Simulate merge to check for conflicts'
                ];
            }

        } catch (\Exception $e) {
            $gaps['merge_analysis_error'] = $e->getMessage();
        }

        return $gaps;
    }

    private function generateRecommendedIssues(array $gaps): array
    {
        $issues = [];

        // High priority: Comment count accuracy
        if (isset($gaps['pr_data']['potential_comment_bug'])) {
            $issues[] = [
                'title' => 'PullRequest DTO comment fields returning 0 despite actual comments',
                'priority' => 'HIGH',
                'description' => 'The comments and review_comments fields show 0 even when PR has actual comments',
                'labels' => ['bug', 'high priority'],
                'endpoint_affected' => 'GET /repos/{owner}/{repo}/pulls/{number}'
            ];
        }

        // Missing endpoints for comprehensive analysis
        foreach ($this->missingEndpoints as $endpoint) {
            $issues[] = [
                'title' => "Add support for {$endpoint['endpoint']}",
                'priority' => $endpoint['priority'],
                'description' => "Need {$endpoint['endpoint']} endpoint for: {$endpoint['purpose']}",
                'labels' => ['enhancement', 'high priority'],
                'endpoint_needed' => $endpoint['endpoint']
            ];
        }

        // Missing data fields - handle various field structures
        foreach ($gaps as $category => $categoryGaps) {
            $missingFields = [];
            
            // Check for different field structures
            if (isset($categoryGaps['missing_pr_fields'])) {
                $missingFields = $categoryGaps['missing_pr_fields'];
            } elseif (isset($categoryGaps['missing_merge_fields'])) {
                $missingFields = $categoryGaps['missing_merge_fields'];
            } elseif (isset($categoryGaps['missing_review_fields'])) {
                $missingFields = $categoryGaps['missing_review_fields'];
            }
            
            if (!empty($missingFields)) {
                $fieldNames = array_column($missingFields, 'field');
                $issues[] = [
                    'title' => "Add missing {$category} fields: " . implode(', ', $fieldNames),
                    'priority' => 'HIGH', 
                    'description' => "Missing fields in {$category} DTO needed for comprehensive PR analysis",
                    'labels' => ['enhancement', 'high priority'],
                    'missing_fields' => $missingFields,
                    'category' => $category
                ];
            }
            
            // Handle data type issues
            if (isset($categoryGaps['review_data_type'])) {
                $issues[] = [
                    'title' => 'Fix reviews endpoint to return Collection instead of array',
                    'priority' => 'MEDIUM',
                    'description' => 'Reviews endpoint returns array instead of Collection, breaking isEmpty() calls',
                    'labels' => ['bug', 'enhancement'],
                    'endpoint_affected' => 'GET /repos/{owner}/{repo}/pulls/{number}/reviews'
                ];
            }
        }

        return $issues;
    }

    /**
     * Auto-submit issues to github-client repository
     */
    public function submitDiscoveredIssues(array $recommendedIssues): array
    {
        $submitted = [];
        
        foreach ($recommendedIssues as $issue) {
            try {
                // Format issue body with detailed information
                $body = $this->formatIssueBody($issue);
                
                // Submit via GitHub CLI (if available) or API
                $issueUrl = $this->submitIssue($issue['title'], $body, $issue['labels']);
                
                $submitted[] = [
                    'title' => $issue['title'],
                    'url' => $issueUrl,
                    'priority' => $issue['priority']
                ];
                
            } catch (\Exception $e) {
                $submitted[] = [
                    'title' => $issue['title'],
                    'error' => $e->getMessage(),
                    'priority' => $issue['priority']
                ];
            }
        }

        return $submitted;
    }

    private function formatIssueBody(array $issue): string
    {
        $body = "## Issue Description\n{$issue['description']}\n\n";
        
        if (isset($issue['endpoint_needed'])) {
            $body .= "## Missing Endpoint\n`{$issue['endpoint_needed']}`\n\n";
        }
        
        if (isset($issue['endpoint_affected'])) {
            $body .= "## Affected Endpoint\n`{$issue['endpoint_affected']}`\n\n";
        }
        
        if (isset($issue['missing_fields'])) {
            $body .= "## Missing Fields\n";
            foreach ($issue['missing_fields'] as $field) {
                $body .= "- `{$field['field']}`: {$field['purpose']}\n";
            }
            $body .= "\n";
        }
        
        $body .= "## Priority\n{$issue['priority']}\n\n";
        $body .= "## Generated By\nConduit PR Analysis Gap Detection\n";
        
        return $body;
    }

    private function submitIssue(string $title, string $body, array $labels): string
    {
        // Try GitHub CLI first
        $labelsStr = implode(',', $labels);
        $command = sprintf(
            'gh issue create --repo jordanpartridge/github-client --title %s --body %s --label %s',
            escapeshellarg($title),
            escapeshellarg($body), 
            escapeshellarg($labelsStr)
        );
        
        $result = shell_exec($command);
        
        if ($result && filter_var(trim($result), FILTER_VALIDATE_URL)) {
            return trim($result);
        }
        
        throw new \Exception('Failed to submit issue via GitHub CLI');
    }
}