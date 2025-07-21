<?php

namespace App\Services\GitHub\Concerns;

use LaravelZero\Framework\Commands\Command;

trait RendersIssuePreviews
{
    /**
     * Display issue preview before creation
     */
    public function displayIssuePreview(Command $command, array $issueData): void
    {
        // Title
        $command->line("📝 <fg=cyan;options=bold>{$issueData['title']}</fg=cyan;options=bold>");
        $command->newLine();

        // Labels
        if (!empty($issueData['labels'])) {
            $labelText = implode(', ', array_map(fn($label) => "<fg=blue>{$label}</fg=blue>", $issueData['labels']));
            $command->line("🏷️  Labels: {$labelText}");
        }

        // Assignees
        if (!empty($issueData['assignees'])) {
            $assigneeText = implode(', ', array_map(fn($assignee) => "<info>{$assignee}</info>", $issueData['assignees']));
            $command->line("👥 Assignees: {$assigneeText}");
        }

        // Milestone
        if (!empty($issueData['milestone'])) {
            $command->line("🎯 Milestone: <info>{$issueData['milestone']}</info>");
        }

        $command->newLine();

        // Body
        if (!empty($issueData['body'])) {
            $command->line('<comment>Body:</comment>');
            $command->newLine();
            $this->renderMarkdownText($command, $issueData['body']);
        }
    }

    /**
     * Display change preview for editing
     */
    public function displayChangePreview(Command $command, array $currentIssue, array $changes): void
    {
        foreach ($changes as $field => $value) {
            switch ($field) {
                case 'title':
                    $command->line("📝 Title: <fg=red>{$currentIssue['title']}</fg=red> → <fg=green>{$value}</fg=green>");
                    break;
                    
                case 'body':
                    $command->line("📄 Body: <comment>Content will be updated</comment>");
                    break;
                    
                case 'state':
                    $command->line("📊 State: <fg=red>{$currentIssue['state']}</fg=red> → <fg=green>{$value}</fg=green>");
                    break;
                    
                case 'add_labels':
                    if (!empty($value)) {
                        $labels = implode(', ', $value);
                        $command->line("🏷️  Add Labels: <fg=green>+{$labels}</fg=green>");
                    }
                    break;
                    
                case 'remove_labels':
                    if (!empty($value)) {
                        $labels = implode(', ', $value);
                        $command->line("🏷️  Remove Labels: <fg=red>-{$labels}</fg=red>");
                    }
                    break;
                    
                case 'add_assignees':
                    if (!empty($value)) {
                        $assignees = implode(', ', $value);
                        $command->line("👥 Add Assignees: <fg=green>+{$assignees}</fg=green>");
                    }
                    break;
                    
                case 'remove_assignees':
                    if (!empty($value)) {
                        $assignees = implode(', ', $value);
                        $command->line("👥 Remove Assignees: <fg=red>-{$assignees}</fg=red>");
                    }
                    break;
                    
                case 'milestone':
                    $current = $currentIssue['milestone']['title'] ?? 'None';
                    $new = $value === 'none' ? 'None' : $value;
                    $command->line("🎯 Milestone: <fg=red>{$current}</fg=red> → <fg=green>{$new}</fg=green>");
                    break;
            }
        }
    }

    /**
     * Display current issue state
     */
    public function displayCurrentIssue(Command $command, array $issue): void
    {
        $command->line('<comment>📋 Current Issue:</comment>');
        $command->newLine();
        
        $status = $this->getIssueStatusIcon($issue);
        $command->line("{$status} <fg=cyan;options=bold>Issue #{$issue['number']}</fg=cyan;options=bold>");
        $command->line("📝 {$issue['title']}");
        $command->line("📊 State: " . ucfirst($issue['state']));
        
        // Show labels
        if (!empty($issue['labels'])) {
            $formattedLabels = $this->formatLabels($issue['labels']);
            $command->line("🏷️  Labels: " . implode(', ', $formattedLabels));
        }
        
        // Show assignees
        if (!empty($issue['assignees'])) {
            $assignees = array_map(fn($assignee) => $assignee['login'], $issue['assignees']);
            $command->line("👥 Assignees: " . implode(', ', $assignees));
        }
        
        // Show milestone
        if (!empty($issue['milestone'])) {
            $command->line("🎯 Milestone: {$issue['milestone']['title']}");
        }
        
        $command->newLine();
    }

    /**
     * Display success message for issue operations
     */
    public function displaySuccessMessage(Command $command, object $issue, string $operation = 'updated'): void
    {
        $command->newLine();
        $command->info("✅ Issue {$operation} successfully!");
        $command->newLine();
        
        $command->line("📋 <fg=cyan;options=bold>Issue #{$issue->number}</fg=cyan;options=bold>");
        $command->line("📝 <info>{$issue->title}</info>");
        
        if (!empty($issue->assignees)) {
            $assigneeNames = array_map(fn($assignee) => $assignee['login'], $issue->assignees);
            $command->line("👥 Assignees: " . implode(', ', $assigneeNames));
        }
        
        $command->line("🔗 <href={$issue->html_url}>{$issue->html_url}</>");
        $command->newLine();
    }

}