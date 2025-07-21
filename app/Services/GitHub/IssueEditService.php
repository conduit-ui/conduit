<?php

namespace App\Services\GitHub;

use App\Services\GitHub\Concerns\HandlesLabelSelection;
use App\Services\GitHub\Concerns\HandlesMilestones;
use App\Services\GitHub\Concerns\ManagesAssignees;
use App\Services\GitHub\Concerns\OpensExternalEditor;
use App\Services\GitHub\Concerns\RendersIssueDetails;
use App\Services\GitHub\Concerns\RendersIssuePreviews;
use App\Services\GitHub\Concerns\ValidatesIssueData;
use JordanPartridge\GithubClient\Facades\Github;

class IssueEditService
{
    use HandlesLabelSelection;
    use HandlesMilestones;
    use ManagesAssignees;
    use OpensExternalEditor;
    use RendersIssueDetails;
    use RendersIssuePreviews;
    use ValidatesIssueData;

    /** @var array Cache for API responses */
    private array $memoizedData = [];

    /** @var string Current repository for cache validation */
    private ?string $currentRepo = null;

    /**
     * Get issue details with memoization
     */
    public function getIssue(string $repo, int $issueNumber): ?array
    {
        $this->initializeCache($repo);
        
        $cacheKey = "issue_{$issueNumber}";
        if (!isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);
            
            try {
                $issue = Github::issues()->get($owner, $repoName, $issueNumber);
                $this->memoizedData[$cacheKey] = $issue->toArray();
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Update an existing GitHub issue
     */
    public function updateIssue(string $repo, int $issueNumber, array $changes): ?array
    {
        [$owner, $repoName] = explode('/', $repo);

        try {
            // Get current issue first
            $currentIssue = $this->getIssue($repo, $issueNumber);
            if (!$currentIssue) {
                return null;
            }

            // Validate changes
            if (!$this->hasChanges($currentIssue, $changes)) {
                return $currentIssue; // No changes needed
            }

            // Prepare update data
            $updateData = $this->prepareUpdateData($repo, $currentIssue, $changes);
            
            // Validate the update data
            $errors = $this->validateIssueData($updateData);
            if (!empty($errors)) {
                throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
            }

            // Update the issue
            $issue = Github::issues()->update($owner, $repoName, $issueNumber, $updateData['title'], $updateData['body']);
            
            // Clear cache for this issue
            unset($this->memoizedData["issue_{$issueNumber}"]);
            
            return $issue->toArray();

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
     * Prepare update data for GitHub API
     */
    private function prepareUpdateData(string $repo, array $currentIssue, array $changes): array
    {
        $updateData = [
            'title' => $currentIssue['title'],
            'body' => $currentIssue['body'] ?? '',
        ];

        // Handle direct updates
        if (isset($changes['title'])) {
            $updateData['title'] = $changes['title'];
        }

        if (isset($changes['body'])) {
            $updateData['body'] = $changes['body'];
        }

        if (isset($changes['state'])) {
            $updateData['state'] = $changes['state'];
        }

        // Handle label changes
        if (isset($changes['add_labels']) || isset($changes['remove_labels'])) {
            $currentLabels = array_map(fn($label) => $label['name'], $currentIssue['labels']);
            
            if (isset($changes['add_labels'])) {
                $currentLabels = array_merge($currentLabels, $changes['add_labels']);
            }
            
            if (isset($changes['remove_labels'])) {
                $currentLabels = array_diff($currentLabels, $changes['remove_labels']);
            }
            
            $updateData['labels'] = array_values(array_unique($currentLabels));
        }

        // Handle assignee changes
        if (isset($changes['add_assignees']) || isset($changes['remove_assignees'])) {
            $currentAssignees = array_map(fn($assignee) => $assignee['login'], $currentIssue['assignees']);
            
            if (isset($changes['add_assignees'])) {
                $currentAssignees = array_merge($currentAssignees, $changes['add_assignees']);
            }
            
            if (isset($changes['remove_assignees'])) {
                $currentAssignees = array_diff($currentAssignees, $changes['remove_assignees']);
            }
            
            $updateData['assignees'] = array_values(array_unique($currentAssignees));
        }

        // Handle milestone changes
        if (isset($changes['milestone'])) {
            if ($changes['milestone'] === 'none') {
                $updateData['milestone'] = null;
            } else {
                $milestoneNumber = $this->processMilestone($repo, $changes['milestone']);
                if ($milestoneNumber) {
                    $updateData['milestone'] = $milestoneNumber;
                }
            }
        }

        return $updateData;
    }

    /**
     * Get appropriate status icon for issue
     */
    private function getIssueStatusIcon(array $issue): string
    {
        if ($issue['state'] === 'closed') {
            return $issue['state_reason'] === 'completed' ? '✅' : '❌';
        }

        // Check for priority/type labels
        $labels = array_map(fn($label) => strtolower($label['name']), $issue['labels']);

        if (in_array('bug', $labels)) {
            return '🐛';
        }

        if (in_array('enhancement', $labels) || in_array('feature', $labels)) {
            return '✨';
        }

        if (in_array('epic', $labels)) {
            return '🚀';
        }

        if (in_array('question', $labels)) {
            return '❓';
        }

        if (in_array('documentation', $labels)) {
            return '📚';
        }

        return '📋';
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