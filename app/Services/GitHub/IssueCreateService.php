<?php

namespace App\Services\GitHub;

use App\Services\GitHub\Concerns\HandlesLabelSelection;
use App\Services\GitHub\Concerns\HandlesMilestones;
use App\Services\GitHub\Concerns\ManagesAssignees;
use App\Services\GitHub\Concerns\ManagesIssueTemplates;
use App\Services\GitHub\Concerns\OpensExternalEditor;
use App\Services\GitHub\Concerns\RendersIssueDetails;
use App\Services\GitHub\Concerns\RendersIssuePreviews;
use App\Services\GitHub\Concerns\ValidatesIssueData;
use JordanPartridge\GithubClient\Facades\Github;

class IssueCreateService
{
    use HandlesLabelSelection;
    use HandlesMilestones;
    use ManagesAssignees;
    use ManagesIssueTemplates;
    use OpensExternalEditor;
    use RendersIssueDetails;
    use RendersIssuePreviews;
    use ValidatesIssueData;

    /** @var array Cache for API responses */
    private array $memoizedData = [];

    /** @var string Current repository for cache validation */
    private ?string $currentRepo = null;

    /**
     * Create a new GitHub issue
     */
    public function createIssue(string $repo, array $issueData): ?object
    {
        [$owner, $repoName] = explode('/', $repo);

        try {
            // Validate and sanitize data
            $issueData = $this->sanitizeIssueData($issueData);
            $errors = $this->validateIssueData($issueData);
            
            if (!empty($errors)) {
                throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
            }

            // Prepare issue data for GitHub API
            $body = $issueData['body'] ?? '';
            
            // Add Conduit attribution if enabled
            if (!empty($body) && $this->shouldAddAttribution()) {
                $body .= "\n\n---\n🤖 Created with [Conduit](https://github.com/conduit-ui/conduit) - Supercharge your developer workflows";
            }

            // Create the issue
            $issue = Github::issues()->create($owner, $repoName, $issueData['title'], $body);
            
            // The DTO has all the data we need - return it directly
            // TODO: Handle labels/assignees/milestone updates when github-client supports it
            return $issue;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get available labels for repository
     */
    public function getAvailableLabels(string $repo): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = 'labels';
        if (!isset($this->memoizedData[$cacheKey])) {
            // TODO: Implement label fetching when github-client supports it
            // For now, return empty array until labels API is available
            $this->memoizedData[$cacheKey] = [];
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Get repository collaborators
     */
    public function getCollaborators(string $repo): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = 'collaborators';
        if (!isset($this->memoizedData[$cacheKey])) {
            // TODO: Implement collaborators fetching when github-client supports it
            $this->memoizedData[$cacheKey] = [];
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Get repository milestones
     */
    public function getMilestones(string $repo): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = 'milestones';
        if (!isset($this->memoizedData[$cacheKey])) {
            // TODO: Implement milestones fetching when github-client supports it
            $this->memoizedData[$cacheKey] = [];
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Check if Conduit attribution should be added
     */
    private function shouldAddAttribution(): bool
    {
        return config('conduit.github.add_attribution', true);
    }

    /**
     * Initialize cache for the current repository
     */
    private function initializeCache(string $repo): void
    {
        if ($this->currentRepo !== $repo) {
            $this->memoizedData = [];
            $this->currentRepo = $repo;
        }
    }
}