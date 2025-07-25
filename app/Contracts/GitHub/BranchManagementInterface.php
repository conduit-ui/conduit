<?php

namespace App\Contracts\GitHub;

use LaravelZero\Framework\Commands\Command;

interface BranchManagementInterface
{
    /**
     * Get current branch from git
     */
    public function getCurrentBranch(): ?string;

    /**
     * Get available branches for base selection
     */
    public function getAvailableBranches(): array;

    /**
     * Interactive branch selection for PR
     */
    public function selectBranches(?Command $command): array;

    /**
     * Validate branch names
     */
    public function validateBranches(array $branches): array;

    /**
     * Check if branches exist and are different
     */
    public function verifyBranchSetup(?Command $command, array $branches): bool;
}