<?php

namespace App\Contracts\GitHub;

use JordanPartridge\GithubClient\Data\Pulls\PullRequestDTO;

interface PrCreateInterface extends BranchManagementInterface, ReviewerManagementInterface
{
    /**
     * Create a new GitHub pull request
     */
    public function createPullRequest(string $repo, array $prData): ?PullRequestDTO;

    /**
     * Get available reviewers for repository
     */
    public function getAvailableReviewers(string $repo): array;

    /**
     * Validate PR data before creation
     */
    public function validatePrData(array $prData): array;

    /**
     * Sanitize PR data
     */
    public function sanitizePrData(array $prData): array;
}
