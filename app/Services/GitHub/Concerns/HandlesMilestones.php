<?php

namespace App\Services\GitHub\Concerns;

use LaravelZero\Framework\Commands\Command;

trait HandlesMilestones
{
    /**
     * Interactive milestone selection
     */
    public function selectMilestone(Command $command, array $milestones): ?string
    {
        if (empty($milestones)) {
            return null;
        }

        $command->line('🎯 <comment>Available Milestones:</comment>');
        $command->newLine();

        $choices = [];
        foreach ($milestones as $index => $milestone) {
            $choices[$index] = $milestone['title'];
            $dueDate = isset($milestone['due_on']) ? ' (due ' . date('M j', strtotime($milestone['due_on'])) . ')' : '';
            $command->line("  [{$index}] {$milestone['title']}{$dueDate}");
        }

        $command->newLine();
        $selected = $command->ask('🎯 Select milestone (number, or press Enter to skip)');

        if (empty($selected) || !isset($choices[$selected])) {
            return null;
        }

        return $choices[$selected];
    }

    /**
     * Interactive milestone selection for editing
     */
    public function interactiveMilestoneSelection(Command $command, string $repo, ?array $currentMilestone): ?string
    {
        $milestones = $this->getMilestones($repo);
        
        $command->line('🎯 <comment>Available milestones:</comment>');
        $command->line('  [none] Remove milestone');
        
        $choices = ['none' => 'none'];
        foreach ($milestones as $index => $milestone) {
            $choices[$index] = $milestone['title'];
            $current = $currentMilestone && $milestone['title'] === $currentMilestone['title'] ? ' (current)' : '';
            $dueDate = isset($milestone['due_on']) ? ' (due ' . date('M j', strtotime($milestone['due_on'])) . ')' : '';
            $command->line("  [{$index}] {$milestone['title']}{$dueDate}{$current}");
        }
        
        $selected = $command->ask('🎯 Select milestone (number/none, or Enter to skip)');
        
        if ($selected === null || $selected === '') {
            return null;
        }
        
        return $choices[$selected] ?? null;
    }

    /**
     * Find milestone by name or number
     */
    public function findMilestoneByNameOrNumber(array $milestones, string $identifier): ?array
    {
        foreach ($milestones as $milestone) {
            if ($milestone['title'] === $identifier || $milestone['number'] == $identifier) {
                return $milestone;
            }
        }
        return null;
    }

    /**
     * Process milestone for API calls
     */
    public function processMilestone(string $repo, ?string $milestone): ?int
    {
        if (empty($milestone)) {
            return null;
        }

        if ($milestone === 'none') {
            return null;
        }

        $milestones = $this->getMilestones($repo);
        $milestoneObj = $this->findMilestoneByNameOrNumber($milestones, $milestone);
        
        return $milestoneObj ? $milestoneObj['number'] : null;
    }

    /**
     * Display milestone changes in preview
     */
    public function displayMilestoneChange(Command $command, ?array $currentMilestone, string $newMilestone): void
    {
        $current = $currentMilestone['title'] ?? 'None';
        $new = $newMilestone === 'none' ? 'None' : $newMilestone;
        $command->line("🎯 Milestone: <fg=red>{$current}</fg=red> → <fg=green>{$new}</fg=green>");
    }
}