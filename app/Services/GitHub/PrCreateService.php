<?php

namespace App\Services\GitHub;

use App\Contracts\GitHub\PrCreateInterface;
use App\Services\GitHub\Concerns\ManagesBranches;
use App\Services\GitHub\Concerns\ManagesPrTemplates;
use App\Services\GitHub\Concerns\ManagesReviewers;
use App\Services\GitHub\Concerns\OpensExternalEditor;
use App\Services\GitHub\Concerns\RendersIssueDetails;
use App\Services\GitHub\Concerns\RendersIssuePreviews;
use App\Services\GitHub\Concerns\ValidatesPrData;
use JordanPartridge\GithubClient\Data\Pulls\PullRequestDetailDTO;
use JordanPartridge\GithubClient\Facades\Github;

class PrCreateService implements PrCreateInterface
{
    use ManagesBranches;
    use ManagesPrTemplates;
    use ManagesReviewers;
    use OpensExternalEditor;
    use RendersIssueDetails;
    use RendersIssuePreviews;
    use ValidatesPrData;

    /** @var array Cache for API responses */
    private array $memoizedData = [];

    /** @var string Current repository for cache validation */
    private ?string $currentRepo = null;

    /**
     * Create a new GitHub pull request
     */
    public function createPullRequest(string $repo, array $prData): ?PullRequestDetailDTO
    {
        [$owner, $repoName] = explode('/', $repo);

        try {
            // Validate and sanitize data
            $prData = $this->sanitizePrData($prData);
            $errors = $this->validatePrData($prData);
            
            if (!empty($errors)) {
                throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
            }

            // Prepare PR data for GitHub API
            $body = $prData['body'] ?? '';
            
            // Add Conduit attribution if enabled
            if (!empty($body) && $this->shouldAddAttribution()) {
                $body .= "\n\n---\n🤖 Created with [Conduit](https://github.com/conduit-ui/conduit) - Supercharge your developer workflows";
            }

            // Create the pull request
            $pr = Github::pullRequests()->create(
                $owner, 
                $repoName, 
                $prData['title'], 
                $prData['head'], 
                $prData['base'], 
                $body
            );
            
            return $pr;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get available reviewers for repository
     */
    public function getAvailableReviewers(string $repo): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = 'reviewers';
        if (!isset($this->memoizedData[$cacheKey])) {
            // TODO: Implement reviewers fetching when github-client supports it
            $this->memoizedData[$cacheKey] = [];
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Check if Conduit attribution should be added
     */
    public function shouldAddAttribution(): bool
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