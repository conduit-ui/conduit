<?php

namespace App\Services\GitHub\Concerns;

trait ValidatesPrData
{
    /**
     * Validate PR data before creation/update
     */
    public function validatePrData(array $prData): array
    {
        $errors = [];

        // Title validation
        if (empty($prData['title'])) {
            $errors[] = 'Title is required';
        } elseif (strlen($prData['title']) > 256) {
            $errors[] = 'Title must be 256 characters or less';
        }

        // Head branch validation
        if (empty($prData['head'])) {
            $errors[] = 'Head branch is required';
        } elseif (strlen($prData['head']) > 250) {
            $errors[] = 'Head branch name is too long';
        }

        // Base branch validation
        if (empty($prData['base'])) {
            $errors[] = 'Base branch is required';
        } elseif (strlen($prData['base']) > 250) {
            $errors[] = 'Base branch name is too long';
        }

        // Branch comparison
        if (isset($prData['head'], $prData['base']) && $prData['head'] === $prData['base']) {
            $errors[] = 'Head and base branches cannot be the same';
        }

        // Body validation (optional but validate if present)
        if (isset($prData['body']) && strlen($prData['body']) > 65535) {
            $errors[] = 'Body must be 65535 characters or less';
        }

        // Reviewers validation
        if (isset($prData['reviewers']) && ! is_array($prData['reviewers'])) {
            $errors[] = 'Reviewers must be an array';
        }

        return $errors;
    }

    /**
     * Sanitize PR data
     */
    public function sanitizePrData(array $prData): array
    {
        $sanitized = [];

        // Sanitize title
        if (isset($prData['title'])) {
            $sanitized['title'] = trim($prData['title']);
        }

        // Sanitize body
        if (isset($prData['body'])) {
            $sanitized['body'] = trim($prData['body']);
        }

        // Sanitize branches
        if (isset($prData['head'])) {
            $sanitized['head'] = trim($prData['head']);
        }

        if (isset($prData['base'])) {
            $sanitized['base'] = trim($prData['base']);
        }

        // Sanitize reviewers
        if (isset($prData['reviewers']) && is_array($prData['reviewers'])) {
            $sanitized['reviewers'] = array_filter(
                array_map('trim', $prData['reviewers']),
                fn ($reviewer) => ! empty($reviewer)
            );
        }

        // Pass through boolean/other fields
        foreach (['draft', 'maintainer_can_modify'] as $field) {
            if (isset($prData[$field])) {
                $sanitized[$field] = $prData[$field];
            }
        }

        return $sanitized;
    }

    /**
     * Check if PR data has changes compared to current PR
     */
    public function hasChanges(array $currentPr, array $newData): bool
    {
        // Check title
        if (isset($newData['title']) && $newData['title'] !== $currentPr['title']) {
            return true;
        }

        // Check body
        if (isset($newData['body']) && $newData['body'] !== ($currentPr['body'] ?? '')) {
            return true;
        }

        // Check state
        if (isset($newData['state']) && $newData['state'] !== $currentPr['state']) {
            return true;
        }

        // Check base branch
        if (isset($newData['base']) && $newData['base'] !== $currentPr['base']['ref']) {
            return true;
        }

        // Check reviewers
        if (isset($newData['add_reviewers']) && ! empty($newData['add_reviewers'])) {
            return true;
        }
        if (isset($newData['remove_reviewers']) && ! empty($newData['remove_reviewers'])) {
            return true;
        }

        return false;
    }

    /**
     * Filter out empty or null values from PR data
     */
    public function filterEmptyValues(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                return ! empty($value);
            }

            return $value !== null && $value !== '';
        });
    }

    /**
     * Validate PR state transitions
     */
    public function validateStateTransition(string $currentState, string $newState): array
    {
        $errors = [];

        $validStates = ['open', 'closed'];

        if (! in_array($newState, $validStates)) {
            $errors[] = "Invalid state '{$newState}'. Must be 'open' or 'closed'";
        }

        // Additional business logic can be added here
        // For example, preventing certain state transitions based on conditions

        return $errors;
    }

    /**
     * Validate merge method
     */
    public function validateMergeMethod(string $mergeMethod): array
    {
        $errors = [];

        $validMethods = ['merge', 'squash', 'rebase'];

        if (! in_array($mergeMethod, $validMethods)) {
            $errors[] = "Invalid merge method '{$mergeMethod}'. Must be 'merge', 'squash', or 'rebase'";
        }

        return $errors;
    }
}
