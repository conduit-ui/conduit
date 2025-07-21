<?php

namespace App\Contracts\GitHub;

use LaravelZero\Framework\Commands\Command;

interface ReviewerManagementInterface
{
    /**
     * Interactive reviewer selection
     */
    public function selectReviewers(?Command $command, array $availableReviewers): array;

    /**
     * Add reviewers to PR
     */
    public function addReviewers(string $repo, int $prNumber, array $reviewers): bool;

    /**
     * Get suggested reviewers based on file changes
     */
    public function getSuggestedReviewers(string $repo, array $changedFiles): array;

    /**
     * Request review from specific users
     */
    public function requestReviews(?Command $command, string $repo, int $prNumber, array $reviewers): bool;

    /**
     * Validate reviewer usernames
     */
    public function validateReviewers(array $reviewers): array;
}