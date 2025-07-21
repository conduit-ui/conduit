<?php

namespace App\Services\GitHub\Concerns;

trait ValidatesIssueData
{
    /**
     * Validate issue data before creation/update
     */
    public function validateIssueData(array $issueData): array
    {
        $errors = [];

        // Title validation
        if (empty($issueData['title'])) {
            $errors[] = 'Title is required';
        } elseif (strlen($issueData['title']) > 256) {
            $errors[] = 'Title must be 256 characters or less';
        }

        // Body validation (optional but validate if present)
        if (isset($issueData['body']) && strlen($issueData['body']) > 65535) {
            $errors[] = 'Body must be 65535 characters or less';
        }

        // Labels validation
        if (isset($issueData['labels']) && !is_array($issueData['labels'])) {
            $errors[] = 'Labels must be an array';
        }

        // Assignees validation
        if (isset($issueData['assignees']) && !is_array($issueData['assignees'])) {
            $errors[] = 'Assignees must be an array';
        }

        return $errors;
    }

    /**
     * Sanitize issue data
     */
    public function sanitizeIssueData(array $issueData): array
    {
        $sanitized = [];

        // Sanitize title
        if (isset($issueData['title'])) {
            $sanitized['title'] = trim($issueData['title']);
        }

        // Sanitize body
        if (isset($issueData['body'])) {
            $sanitized['body'] = trim($issueData['body']);
        }

        // Sanitize labels
        if (isset($issueData['labels']) && is_array($issueData['labels'])) {
            $sanitized['labels'] = array_filter(
                array_map('trim', $issueData['labels']),
                fn($label) => !empty($label)
            );
        }

        // Sanitize assignees
        if (isset($issueData['assignees']) && is_array($issueData['assignees'])) {
            $sanitized['assignees'] = array_filter(
                array_map('trim', $issueData['assignees']),
                fn($assignee) => !empty($assignee)
            );
        }

        // Pass through other fields
        foreach (['milestone', 'state'] as $field) {
            if (isset($issueData[$field])) {
                $sanitized[$field] = $issueData[$field];
            }
        }

        return $sanitized;
    }

    /**
     * Check if issue data has changes compared to current issue
     */
    public function hasChanges(array $currentIssue, array $newData): bool
    {
        // Check title
        if (isset($newData['title']) && $newData['title'] !== $currentIssue['title']) {
            return true;
        }

        // Check body
        if (isset($newData['body']) && $newData['body'] !== ($currentIssue['body'] ?? '')) {
            return true;
        }

        // Check state
        if (isset($newData['state']) && $newData['state'] !== $currentIssue['state']) {
            return true;
        }

        // Check milestone
        $currentMilestone = $currentIssue['milestone']['title'] ?? null;
        if (isset($newData['milestone']) && $newData['milestone'] !== $currentMilestone) {
            return true;
        }

        // Check labels
        if (isset($newData['add_labels']) && !empty($newData['add_labels'])) {
            return true;
        }
        if (isset($newData['remove_labels']) && !empty($newData['remove_labels'])) {
            return true;
        }

        // Check assignees
        if (isset($newData['add_assignees']) && !empty($newData['add_assignees'])) {
            return true;
        }
        if (isset($newData['remove_assignees']) && !empty($newData['remove_assignees'])) {
            return true;
        }

        return false;
    }

    /**
     * Filter out empty or null values from issue data
     */
    public function filterEmptyValues(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }
            return $value !== null && $value !== '';
        });
    }
}