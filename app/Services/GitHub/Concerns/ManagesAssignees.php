<?php

namespace App\Services\GitHub\Concerns;

use LaravelZero\Framework\Commands\Command;

trait ManagesAssignees
{
    /**
     * Interactive assignee selection
     */
    public function selectAssignees(Command $command, array $collaborators): array
    {
        if (empty($collaborators)) {
            return [];
        }

        $command->line('👥 <comment>Available Assignees:</comment>');
        $command->newLine();

        $choices = [];
        foreach ($collaborators as $index => $collaborator) {
            $choices[$index] = $collaborator['login'];
            $command->line("  [{$index}] {$collaborator['login']}");
        }

        $command->newLine();
        $selected = $command->ask('👥 Select assignees (comma-separated numbers, or press Enter to skip)');

        if (empty($selected)) {
            return [];
        }

        $selectedIndices = array_map('trim', explode(',', $selected));
        $selectedAssignees = [];

        foreach ($selectedIndices as $index) {
            if (isset($choices[$index])) {
                $selectedAssignees[] = $choices[$index];
            }
        }

        return $selectedAssignees;
    }

    /**
     * Interactive assignee management for editing
     */
    public function interactiveAssigneeManagement(Command $command, string $repo, array $currentAssignees): array
    {
        $changes = [];
        $currentAssigneeLogins = array_map(fn ($assignee) => $assignee['login'], $currentAssignees);

        // Add assignees
        $collaborators = $this->getCollaborators($repo);
        $availableToAdd = array_filter($collaborators, fn ($collab) => ! in_array($collab['login'], $currentAssigneeLogins));

        if (! empty($availableToAdd)) {
            $command->line('👥 <comment>Available assignees to add:</comment>');
            $choices = [];
            foreach ($availableToAdd as $index => $collaborator) {
                $choices[$index] = $collaborator['login'];
                $command->line("  [{$index}] {$collaborator['login']}");
            }

            $selected = $command->ask('👥 Add assignees (comma-separated numbers, or Enter to skip)');
            if ($selected) {
                $selectedIndices = array_map('trim', explode(',', $selected));
                $addAssignees = [];
                foreach ($selectedIndices as $index) {
                    if (isset($choices[$index])) {
                        $addAssignees[] = $choices[$index];
                    }
                }
                if (! empty($addAssignees)) {
                    $changes['add_assignees'] = $addAssignees;
                }
            }
        }

        // Remove assignees
        if (! empty($currentAssignees)) {
            $command->newLine();
            $command->line('👥 <comment>Current assignees to remove:</comment>');
            $choices = [];
            foreach ($currentAssignees as $index => $assignee) {
                $choices[$index] = $assignee['login'];
                $command->line("  [{$index}] {$assignee['login']}");
            }

            $selected = $command->ask('👥 Remove assignees (comma-separated numbers, or Enter to skip)');
            if ($selected) {
                $selectedIndices = array_map('trim', explode(',', $selected));
                $removeAssignees = [];
                foreach ($selectedIndices as $index) {
                    if (isset($choices[$index])) {
                        $removeAssignees[] = $choices[$index];
                    }
                }
                if (! empty($removeAssignees)) {
                    $changes['remove_assignees'] = $removeAssignees;
                }
            }
        }

        return $changes;
    }

    /**
     * Determine assignment changes for commands
     */
    public function determineAssignmentChanges(object $issue, array $options): array
    {
        $currentAssignees = array_map(fn ($assignee) => $assignee['login'], $issue->assignees ?? []);

        // Handle --clear flag
        if ($options['clear'] ?? false) {
            return ['assignees' => []];
        }

        // Handle --me flag
        if ($options['me'] ?? false) {
            $currentUser = $this->getCurrentUser();
            if ($currentUser && ! in_array($currentUser, $currentAssignees)) {
                $currentAssignees[] = $currentUser;
            }
        }

        // Handle --add
        $addUsers = $options['add'] ?? [];
        foreach ($addUsers as $user) {
            if (! in_array($user, $currentAssignees)) {
                $currentAssignees[] = $user;
            }
        }

        // Handle --remove
        $removeUsers = $options['remove'] ?? [];
        $currentAssignees = array_diff($currentAssignees, $removeUsers);

        return ['assignees' => array_values($currentAssignees)];
    }

    /**
     * Get current user (placeholder until github-client supports it)
     */
    protected function getCurrentUser(): ?string
    {
        // TODO: Implement when github-client has user API
        return 'jordanpartridge';
    }

    /**
     * Display assignment preview
     */
    public function displayAssignmentPreview(Command $command, array $changes): void
    {
        $command->newLine();
        $command->line('<comment>📋 Assignment Preview:</comment>');

        if (empty($changes['assignees'])) {
            $command->line('👥 Assignees: <fg=yellow>None (cleared)</fg=yellow>');
        } else {
            $assignees = implode(', ', $changes['assignees']);
            $command->line("👥 Assignees: <fg=green>{$assignees}</fg=green>");
        }
        $command->newLine();
    }
}
