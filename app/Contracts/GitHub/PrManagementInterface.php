<?php

namespace App\Contracts\GitHub;

interface PrManagementInterface extends BranchManagementInterface, ReviewerManagementInterface
{
    /**
     * Get pull request details
     */
    public function getPullRequest(string $repo, int $prNumber): ?array;

    /**
     * Update pull request
     */
    public function updatePullRequest(string $repo, int $prNumber, array $changes): ?array;

    /**
     * Merge pull request
     */
    public function mergePullRequest(string $repo, int $prNumber, string $mergeMethod = 'merge'): ?array;

    /**
     * Close pull request
     */
    public function closePullRequest(string $repo, int $prNumber): ?array;
}
