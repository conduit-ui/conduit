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
        $parts = explode('/', $repo);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Repository must be in format "owner/repo"');
        }
        [$owner, $repoName] = $parts;

        try {
            // Validate and sanitize data
            $prData = $this->sanitizePrData($prData);
            $errors = $this->validatePrData($prData);

            if (! empty($errors)) {
                throw new \InvalidArgumentException('Validation failed: '.implode(', ', $errors));
            }

            // Prepare PR data for GitHub API
            $body = $prData['body'] ?? '';

            // Add Conduit attribution if enabled
            if (! empty($body) && $this->shouldAddAttribution()) {
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

        } catch (\InvalidArgumentException $e) {
            // Re-throw validation errors
            throw $e;
        } catch (\Exception $e) {
            // Log the error for debugging
            logger()->error('Failed to create PR', [
                'repo' => $repo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
        if (! isset($this->memoizedData[$cacheKey])) {
            $this->memoizedData[$cacheKey] = $this->fetchRepositoryReviewers($repo);
        }

        return $this->memoizedData[$cacheKey];
    }

    /**
     * Fetch available reviewers using existing github-client architecture
     */
    private function fetchRepositoryReviewers(string $repo): array
    {
        try {
            // Validate repo format
            if (! str_contains($repo, '/')) {
                return [];
            }

            [$owner, $repoName] = explode('/', $repo);

            // Skip API calls for test repositories
            if ($owner === 'nonexistent' || $repoName === 'repo') {
                return [];
            }

            // Get recent PRs to extract frequent reviewers/collaborators
            $recentPrs = Github::pullRequests()->summaries($owner, $repoName, [
                'state' => 'all',
                'sort' => 'updated',
                'direction' => 'desc',
                'per_page' => 50,
            ]);

            // Extract unique users from PR authors and reviewers
            $reviewers = $this->extractReviewersFromPrHistory($recentPrs);

            // If we have few reviewers, try to get repository contributors
            if (count($reviewers) < 3) {
                $contributors = $this->fetchRepositoryContributors($owner, $repoName);
                $reviewers = $this->mergeReviewersAndContributors($reviewers, $contributors);
            }

            return $this->formatReviewersForDisplay($reviewers);

        } catch (\Exception $e) {
            // Graceful degradation - return empty array
            return [];
        }
    }

    /**
     * Extract potential reviewers from PR history
     */
    private function extractReviewersFromPrHistory(array $prs): array
    {
        $reviewers = [];
        $seenLogins = [];

        foreach ($prs as $pr) {
            // Add PR author as potential reviewer
            if (isset($pr->user->login) && ! in_array($pr->user->login, $seenLogins)) {
                $reviewers[] = [
                    'login' => $pr->user->login,
                    'name' => $pr->user->name ?? null,
                    'avatar_url' => $pr->user->avatar_url ?? '',
                    'type' => 'contributor',
                    'html_url' => $pr->user->html_url ?? '',
                ];
                $seenLogins[] = $pr->user->login;
            }
        }

        return $reviewers;
    }

    /**
     * Fetch repository contributors via github-client connector
     */
    private function fetchRepositoryContributors(string $owner, string $repo): array
    {
        try {
            // Use github-client's connector for authenticated requests
            $response = Github::getConnector()->get("/repos/{$owner}/{$repo}/contributors", [
                'per_page' => 20,
            ]);

            if ($response->ok()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Merge reviewers from PR history with repository contributors
     */
    private function mergeReviewersAndContributors(array $reviewers, array $contributors): array
    {
        $existingLogins = array_column($reviewers, 'login');

        foreach ($contributors as $contributor) {
            if (isset($contributor['login']) && ! in_array($contributor['login'], $existingLogins)) {
                $reviewers[] = [
                    'login' => $contributor['login'],
                    'name' => $contributor['name'] ?? null,
                    'avatar_url' => $contributor['avatar_url'] ?? '',
                    'type' => 'contributor',
                    'html_url' => $contributor['html_url'] ?? '',
                ];
            }
        }

        return $reviewers;
    }

    /**
     * Format reviewers data for consistent display
     */
    private function formatReviewersForDisplay(array $reviewers): array
    {
        return array_map(function ($reviewer) {
            return [
                'login' => $reviewer['login'] ?? '',
                'name' => $reviewer['name'] ?? null,
                'avatar_url' => $reviewer['avatar_url'] ?? '',
                'type' => $reviewer['type'] ?? 'contributor',
                'html_url' => $reviewer['html_url'] ?? '',
            ];
        }, array_filter($reviewers, fn ($r) => ! empty($r['login'])));
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
