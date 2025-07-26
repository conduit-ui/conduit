<?php

namespace App\Services\GitHub\Concerns;

use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

trait ManagesReviewers
{
    /**
     * Interactive reviewer selection
     */
    public function selectReviewers(?Command $command, array $availableReviewers): array
    {
        if (! $command || empty($availableReviewers)) {
            return [];
        }

        $command->line('<comment>👥 Select reviewers for this PR:</comment>');

        $reviewerOptions = [];
        foreach ($availableReviewers as $reviewer) {
            $reviewerOptions[$reviewer['login']] = "👤 {$reviewer['login']} (".($reviewer['name'] ?? 'No name').')';
        }

        $selectedReviewers = multiselect(
            label: 'Choose reviewers',
            options: $reviewerOptions,
            hint: 'Select one or more reviewers for this PR'
        );

        return array_keys($selectedReviewers);
    }

    /**
     * Add reviewers to PR
     */
    public function addReviewers(string $repo, int $prNumber, array $reviewers): bool
    {
        if (empty($reviewers)) {
            return true;
        }

        try {
            // TODO: Implement when github-client supports reviewer requests
            throw new \BadMethodCallException(
                '🚧 Reviewer requests coming soon! This feature requires github-client v3.0+ with reviewer support.'
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get suggested reviewers based on file changes
     */
    public function getSuggestedReviewers(string $repo, array $changedFiles): array
    {
        // This would analyze file ownership/expertise
        // For now, return empty until we have the data
        return [];
    }

    /**
     * Request review from specific users
     */
    public function requestReviews(?object $command, string $repo, int $prNumber, array $reviewers): bool
    {
        if (! $command || empty($reviewers)) {
            return true;
        }

        $command->info('📤 Requesting reviews from: '.implode(', ', $reviewers));

        try {
            return $this->addReviewers($repo, $prNumber, $reviewers);
        } catch (\Exception $e) {
            $command->warn("⚠️ Could not request reviews: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Display reviewer selection summary
     */
    public function displayReviewerSummary(?Command $command, array $reviewers): void
    {
        if (! $command || empty($reviewers)) {
            return;
        }

        $command->line('<comment>👥 Selected reviewers:</comment>');
        foreach ($reviewers as $reviewer) {
            $command->line("  • {$reviewer}");
        }
        $command->newLine();
    }

    /**
     * Validate reviewer usernames
     */
    public function validateReviewers(array $reviewers): array
    {
        $errors = [];

        foreach ($reviewers as $reviewer) {
            if (empty($reviewer) || ! is_string($reviewer)) {
                $errors[] = 'Reviewer username cannot be empty';

                continue;
            }

            if (strlen($reviewer) > 39) {
                $errors[] = "Reviewer username '{$reviewer}' is too long (max 39 characters)";
            }

            if (! preg_match('/^[a-zA-Z0-9-]+$/', $reviewer)) {
                $errors[] = "Reviewer username '{$reviewer}' contains invalid characters";
            }
        }

        return $errors;
    }
}
