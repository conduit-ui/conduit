<?php

namespace App\Services\GitHub;

use App\Services\GitHub\Concerns\RendersIssueDetails;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

class IssueEditService
{
    use RendersIssueDetails;

    /** @var array Cache for API responses */
    private array $memoizedData = [];

    /** @var string Current repository for cache validation */
    private ?string $currentRepo = null;

    /**
     * Get issue details with memoization
     */
    public function getIssue(string $repo, int $issueNumber): ?array
    {
        $this->initializeCache($repo);
        
        $cacheKey = "issue_{$issueNumber}";
        if (!isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);
            
            try {
                $issue = Github::issues()->get($owner, $repoName, $issueNumber);
                $this->memoizedData[$cacheKey] = $issue->toArray();
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Update an existing GitHub issue
     */
    public function updateIssue(string $repo, int $issueNumber, array $changes): ?array
    {
        [$owner, $repoName] = explode('/', $repo);

        try {
            // Get current issue first
            $currentIssue = $this->getIssue($repo, $issueNumber);
            if (!$currentIssue) {
                return null;
            }

            // Prepare update data
            $updateData = $this->prepareUpdateData($repo, $currentIssue, $changes);

            // Update the issue
            $issue = Github::issues()->update($owner, $repoName, $issueNumber, $updateData);
            
            // Clear cache for this issue
            unset($this->memoizedData["issue_{$issueNumber}"]);
            
            return $issue->toArray();

        } catch (\Exception $e) {
            return null;
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
     * Display change preview
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
     * Open issue editor with structured format
     */
    public function openIssueEditor(array $currentIssue): array
    {
        $template = $this->generateEditTemplate($currentIssue);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'conduit_issue_edit_') . '.md';
        file_put_contents($tempFile, $template);

        $editor = $_ENV['EDITOR'] ?? $_ENV['VISUAL'] ?? 'vim';
        system("{$editor} {$tempFile}");

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $this->parseEditTemplate($content);
    }

    /**
     * Open external editor for markdown editing
     */
    public function openEditor(string $initialContent = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'conduit_edit_') . '.md';
        file_put_contents($tempFile, $initialContent);

        $editor = $_ENV['EDITOR'] ?? $_ENV['VISUAL'] ?? 'vim';
        system("{$editor} {$tempFile}");

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content ?: $initialContent;
    }

    /**
     * Interactive label management
     */
    public function interactiveLabelManagement(Command $command, string $repo, array $currentLabels): array
    {
        $changes = [];
        $currentLabelNames = array_map(fn($label) => $label['name'], $currentLabels);
        
        // Add labels
        $availableLabels = $this->getAvailableLabels($repo);
        $availableToAdd = array_filter($availableLabels, fn($label) => !in_array($label['name'], $currentLabelNames));
        
        if (!empty($availableToAdd)) {
            $command->line('🏷️  <comment>Available labels to add:</comment>');
            $choices = [];
            foreach ($availableToAdd as $index => $label) {
                $color = $this->mapGitHubColorToTerminal($label['color'] ?? '000000');
                $choices[$index] = $label['name'];
                $command->line("  [{$index}] <fg={$color}>{$label['name']}</fg={$color}>");
            }
            
            $selected = $command->ask('🏷️  Add labels (comma-separated numbers, or Enter to skip)');
            if ($selected) {
                $selectedIndices = array_map('trim', explode(',', $selected));
                $addLabels = [];
                foreach ($selectedIndices as $index) {
                    if (isset($choices[$index])) {
                        $addLabels[] = $choices[$index];
                    }
                }
                if (!empty($addLabels)) {
                    $changes['add_labels'] = $addLabels;
                }
            }
        }
        
        // Remove labels
        if (!empty($currentLabels)) {
            $command->newLine();
            $command->line('🏷️  <comment>Current labels to remove:</comment>');
            $choices = [];
            foreach ($currentLabels as $index => $label) {
                $color = $this->mapGitHubColorToTerminal($label['color'] ?? '000000');
                $choices[$index] = $label['name'];
                $command->line("  [{$index}] <fg={$color}>{$label['name']}</fg={$color}>");
            }
            
            $selected = $command->ask('🏷️  Remove labels (comma-separated numbers, or Enter to skip)');
            if ($selected) {
                $selectedIndices = array_map('trim', explode(',', $selected));
                $removeLabels = [];
                foreach ($selectedIndices as $index) {
                    if (isset($choices[$index])) {
                        $removeLabels[] = $choices[$index];
                    }
                }
                if (!empty($removeLabels)) {
                    $changes['remove_labels'] = $removeLabels;
                }
            }
        }
        
        return $changes;
    }

    /**
     * Interactive assignee management
     */
    public function interactiveAssigneeManagement(Command $command, string $repo, array $currentAssignees): array
    {
        $changes = [];
        $currentAssigneeLogins = array_map(fn($assignee) => $assignee['login'], $currentAssignees);
        
        // Add assignees
        $collaborators = $this->getCollaborators($repo);
        $availableToAdd = array_filter($collaborators, fn($collab) => !in_array($collab['login'], $currentAssigneeLogins));
        
        if (!empty($availableToAdd)) {
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
                if (!empty($addAssignees)) {
                    $changes['add_assignees'] = $addAssignees;
                }
            }
        }
        
        // Remove assignees
        if (!empty($currentAssignees)) {
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
                if (!empty($removeAssignees)) {
                    $changes['remove_assignees'] = $removeAssignees;
                }
            }
        }
        
        return $changes;
    }

    /**
     * Interactive milestone selection
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
     * Get available labels for repository
     */
    public function getAvailableLabels(string $repo): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = 'labels';
        if (!isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);
            
            try {
                $labels = Github::issues()->labels($owner, $repoName);
                $this->memoizedData[$cacheKey] = array_map(fn($label) => $label->toArray(), $labels);
            } catch (\Exception $e) {
                $this->memoizedData[$cacheKey] = [];
            }
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Get repository collaborators
     */
    public function getCollaborators(string $repo): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = 'collaborators';
        if (!isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);
            
            try {
                $collaborators = Github::repos()->collaborators($owner, $repoName);
                $this->memoizedData[$cacheKey] = array_map(fn($collab) => $collab->toArray(), $collaborators);
            } catch (\Exception $e) {
                $this->memoizedData[$cacheKey] = [];
            }
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Get repository milestones
     */
    public function getMilestones(string $repo): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = 'milestones';
        if (!isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);
            
            try {
                $milestones = Github::issues()->milestones($owner, $repoName);
                $this->memoizedData[$cacheKey] = array_map(fn($milestone) => $milestone->toArray(), $milestones);
            } catch (\Exception $e) {
                $this->memoizedData[$cacheKey] = [];
            }
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Get appropriate status icon for issue
     */
    private function getIssueStatusIcon(array $issue): string
    {
        if ($issue['state'] === 'closed') {
            return $issue['state_reason'] === 'completed' ? '✅' : '❌';
        }

        // Check for priority/type labels
        $labels = array_map(fn($label) => strtolower($label['name']), $issue['labels']);

        if (in_array('bug', $labels)) {
            return '🐛';
        }

        if (in_array('enhancement', $labels) || in_array('feature', $labels)) {
            return '✨';
        }

        if (in_array('epic', $labels)) {
            return '🚀';
        }

        if (in_array('question', $labels)) {
            return '❓';
        }

        if (in_array('documentation', $labels)) {
            return '📚';
        }

        return '📋';
    }

    /**
     * Prepare update data for GitHub API
     */
    private function prepareUpdateData(string $repo, array $currentIssue, array $changes): array
    {
        $updateData = [];

        // Handle direct updates
        if (isset($changes['title'])) {
            $updateData['title'] = $changes['title'];
        }

        if (isset($changes['body'])) {
            $updateData['body'] = $changes['body'];
        }

        if (isset($changes['state'])) {
            $updateData['state'] = $changes['state'];
        }

        // Handle label changes
        if (isset($changes['add_labels']) || isset($changes['remove_labels'])) {
            $currentLabels = array_map(fn($label) => $label['name'], $currentIssue['labels']);
            
            if (isset($changes['add_labels'])) {
                $currentLabels = array_merge($currentLabels, $changes['add_labels']);
            }
            
            if (isset($changes['remove_labels'])) {
                $currentLabels = array_diff($currentLabels, $changes['remove_labels']);
            }
            
            $updateData['labels'] = array_values(array_unique($currentLabels));
        }

        // Handle assignee changes
        if (isset($changes['add_assignees']) || isset($changes['remove_assignees'])) {
            $currentAssignees = array_map(fn($assignee) => $assignee['login'], $currentIssue['assignees']);
            
            if (isset($changes['add_assignees'])) {
                $currentAssignees = array_merge($currentAssignees, $changes['add_assignees']);
            }
            
            if (isset($changes['remove_assignees'])) {
                $currentAssignees = array_diff($currentAssignees, $changes['remove_assignees']);
            }
            
            $updateData['assignees'] = array_values(array_unique($currentAssignees));
        }

        // Handle milestone changes
        if (isset($changes['milestone'])) {
            if ($changes['milestone'] === 'none') {
                $updateData['milestone'] = null;
            } else {
                $milestones = $this->getMilestones($repo);
                $milestone = $this->findMilestoneByNameOrNumber($milestones, $changes['milestone']);
                if ($milestone) {
                    $updateData['milestone'] = $milestone['number'];
                }
            }
        }

        return $updateData;
    }

    /**
     * Generate edit template for structured editing
     */
    private function generateEditTemplate(array $issue): string
    {
        return <<<TEMPLATE
# Issue Edit Template
# Lines starting with # are comments and will be ignored
# Edit the title and body below, then save and close

TITLE: {$issue['title']}

BODY:
{$issue['body']}
TEMPLATE;
    }

    /**
     * Parse edit template content
     */
    private function parseEditTemplate(string $content): array
    {
        $lines = explode("\n", $content);
        $result = ['title' => '', 'body' => ''];
        $inBody = false;
        $bodyLines = [];

        foreach ($lines as $line) {
            // Skip comments
            if (preg_match('/^#/', $line)) {
                continue;
            }

            if (preg_match('/^TITLE:\s*(.+)$/', $line, $matches)) {
                $result['title'] = trim($matches[1]);
                continue;
            }

            if (preg_match('/^BODY:\s*$/', $line)) {
                $inBody = true;
                continue;
            }

            if ($inBody) {
                $bodyLines[] = $line;
            }
        }

        $result['body'] = implode("\n", $bodyLines);
        return $result;
    }

    /**
     * Find milestone by name or number
     */
    private function findMilestoneByNameOrNumber(array $milestones, string $identifier): ?array
    {
        foreach ($milestones as $milestone) {
            if ($milestone['title'] === $identifier || $milestone['number'] == $identifier) {
                return $milestone;
            }
        }
        return null;
    }

    /**
     * Initialize cache for the current repository
     */
    private function initializeCache(string $repo): void
    {
        if ($this->currentRepo !== $repo) {
            $this->memoizedData = [];
            $this->currentRepo = $repo;
        }
    }
}