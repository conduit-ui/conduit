<?php

namespace App\Services\GitHub;

use App\Services\GitHub\Concerns\RendersIssueDetails;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

class IssueCreateService
{
    use RendersIssueDetails;

    /** @var array Cache for API responses */
    private array $memoizedData = [];

    /** @var string Current repository for cache validation */
    private ?string $currentRepo = null;

    /**
     * Create a new GitHub issue
     */
    public function createIssue(string $repo, array $issueData): ?object
    {
        [$owner, $repoName] = explode('/', $repo);

        try {
            // Prepare issue data for GitHub API
            $body = $issueData['body'] ?? '';
            
            // Add Conduit attribution
            if (!empty($body)) {
                $body .= "\n\n---\n🤖 Created with [Conduit](https://github.com/conduit-ui/conduit) - Supercharge your developer workflows";
            }
            
            $apiData = [
                'title' => $issueData['title'],
                'body' => $body,
            ];

            // Add labels if specified
            if (! empty($issueData['labels'])) {
                $apiData['labels'] = $issueData['labels'];
            }

            // Add assignees if specified
            if (! empty($issueData['assignees'])) {
                $apiData['assignees'] = $issueData['assignees'];
            }

            // Add milestone if specified
            if (! empty($issueData['milestone'])) {
                $milestones = $this->getMilestones($repo);
                $milestone = $this->findMilestoneByNameOrNumber($milestones, $issueData['milestone']);
                if ($milestone) {
                    $apiData['milestone'] = $milestone['number'];
                }
            }

            $issue = Github::issues()->create($owner, $repoName, $apiData['title'], $apiData['body']);
            
            // The DTO has all the data we need - return it directly
            // TODO: Handle labels/assignees/milestone updates when github-client supports it
            return $issue;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get issue template by type
     */
    public function getTemplate(string $templateType): ?array
    {
        $templates = [
            'bug' => [
                'title' => '🐛 Bug: ',
                'body' => $this->getBugTemplate(),
                'labels' => ['bug'],
            ],
            'feature' => [
                'title' => '✨ Feature: ',
                'body' => $this->getFeatureTemplate(),
                'labels' => ['enhancement'],
            ],
            'epic' => [
                'title' => '🚀 Epic: ',
                'body' => $this->getEpicTemplate(),
                'labels' => ['epic'],
            ],
            'question' => [
                'title' => '❓ Question: ',
                'body' => $this->getQuestionTemplate(),
                'labels' => ['question'],
            ],
        ];

        return $templates[$templateType] ?? null;
    }

    /**
     * Get available labels for repository
     */
    public function getAvailableLabels(string $repo): array
    {
        $this->initializeCache($repo);

        $cacheKey = 'labels';
        if (! isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);

            try {
                // TODO: Implement label fetching when github-client supports it
                // For now, return empty array until labels API is available
                $this->memoizedData[$cacheKey] = [];
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
        if (! isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);

            try {
                // TODO: Implement collaborators fetching when github-client supports it
                $this->memoizedData[$cacheKey] = [];
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
        if (! isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);

            try {
                // TODO: Implement milestones fetching when github-client supports it
                $this->memoizedData[$cacheKey] = [];
            } catch (\Exception $e) {
                $this->memoizedData[$cacheKey] = [];
            }
        }

        return $this->memoizedData[$cacheKey];
    }

    /**
     * Interactive label selection
     */
    public function selectLabels(Command $command, array $availableLabels): array
    {
        if (empty($availableLabels)) {
            return [];
        }

        $command->line('🏷️  <comment>Available Labels:</comment>');
        $command->newLine();

        $choices = [];
        foreach ($availableLabels as $index => $label) {
            $color = $this->mapGitHubColorToTerminal($label['color'] ?? '000000');
            $choices[$index] = $label['name'];
            $command->line("  [{$index}] <fg={$color}>{$label['name']}</fg={$color}> - {$label['description']}");
        }

        $command->newLine();
        $selected = $command->ask('🏷️  Select labels (comma-separated numbers, or press Enter to skip)');

        if (empty($selected)) {
            return [];
        }

        $selectedIndices = array_map('trim', explode(',', $selected));
        $selectedLabels = [];

        foreach ($selectedIndices as $index) {
            if (isset($choices[$index])) {
                $selectedLabels[] = $choices[$index];
            }
        }

        return $selectedLabels;
    }

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
            $dueDate = isset($milestone['due_on']) ? ' (due '.date('M j', strtotime($milestone['due_on'])).')' : '';
            $command->line("  [{$index}] {$milestone['title']}{$dueDate}");
        }

        $command->newLine();
        $selected = $command->ask('🎯 Select milestone (number, or press Enter to skip)');

        if (empty($selected) || ! isset($choices[$selected])) {
            return null;
        }

        return $choices[$selected];
    }

    /**
     * Open external editor for markdown editing
     */
    public function openEditor(string $initialContent = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'conduit_issue_').'.md';
        file_put_contents($tempFile, $initialContent);

        $editor = $_ENV['EDITOR'] ?? $_ENV['VISUAL'] ?? 'vim';
        system("{$editor} {$tempFile}");

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content ?: $initialContent;
    }

    /**
     * Display issue preview
     */
    public function displayIssuePreview(Command $command, array $issueData): void
    {
        // Title
        $command->line("📝 <fg=cyan;options=bold>{$issueData['title']}</fg=cyan;options=bold>");
        $command->newLine();

        // Labels
        if (! empty($issueData['labels'])) {
            $labelText = implode(', ', array_map(fn ($label) => "<fg=blue>{$label}</fg=blue>", $issueData['labels']));
            $command->line("🏷️  Labels: {$labelText}");
        }

        // Assignees
        if (! empty($issueData['assignees'])) {
            $assigneeText = implode(', ', array_map(fn ($assignee) => "<info>{$assignee}</info>", $issueData['assignees']));
            $command->line("👥 Assignees: {$assigneeText}");
        }

        // Milestone
        if (! empty($issueData['milestone'])) {
            $command->line("🎯 Milestone: <info>{$issueData['milestone']}</info>");
        }

        $command->newLine();

        // Body
        if (! empty($issueData['body'])) {
            $command->line('<comment>Body:</comment>');
            $command->newLine();
            $this->renderMarkdownText($command, $issueData['body']);
        }
    }

    /**
     * Get bug report template
     */
    private function getBugTemplate(): string
    {
        return <<<'MARKDOWN'
## 🐛 Bug Description
A clear and concise description of what the bug is.

## 🔄 Steps to Reproduce
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

## ✅ Expected Behavior
A clear and concise description of what you expected to happen.

## ❌ Actual Behavior
A clear and concise description of what actually happened.

## 📸 Screenshots
If applicable, add screenshots to help explain your problem.

## 🌍 Environment
- OS: [e.g. macOS, Ubuntu]
- Version: [e.g. v1.2.3]
- PHP Version: [e.g. 8.1]

## 📋 Additional Context
Add any other context about the problem here.
MARKDOWN;
    }

    /**
     * Get feature request template
     */
    private function getFeatureTemplate(): string
    {
        return <<<'MARKDOWN'
## ✨ Feature Summary
A clear and concise description of the feature you'd like to see.

## 🎯 Problem Statement
What problem does this feature solve? What use case does it address?

## 💡 Proposed Solution
Describe the solution you'd like to see implemented.

## 🔄 User Stories
- As a [type of user], I want [some goal] so that [some reason]
- As a [type of user], I want [some goal] so that [some reason]

## 🎨 Mockups/Examples
If applicable, add mockups, wireframes, or examples.

## ✅ Acceptance Criteria
- [ ] Criterion 1
- [ ] Criterion 2
- [ ] Criterion 3

## 🚀 Additional Context
Add any other context, alternatives considered, or related issues.
MARKDOWN;
    }

    /**
     * Get epic template
     */
    private function getEpicTemplate(): string
    {
        return <<<'MARKDOWN'
## 🚀 Epic Overview
High-level description of the epic and its goals.

## 🎯 Business Value
Why is this epic important? What value does it deliver?

## 👥 Target Users
Who will benefit from this epic?

## 📋 User Stories
Break down the epic into user stories:

### Core Features
- [ ] #[issue-number] Story 1
- [ ] #[issue-number] Story 2
- [ ] #[issue-number] Story 3

### Nice to Have
- [ ] #[issue-number] Story 4
- [ ] #[issue-number] Story 5

## ✅ Definition of Done
- [ ] All user stories completed
- [ ] Tests written and passing
- [ ] Documentation updated
- [ ] Performance requirements met

## 🗓️ Timeline
Target completion: [Date]

## 📊 Success Metrics
How will we measure success?
MARKDOWN;
    }

    /**
     * Get question template
     */
    private function getQuestionTemplate(): string
    {
        return <<<'MARKDOWN'
## ❓ Question
What would you like to know?

## 🔍 Context
Provide context about what you're trying to achieve.

## 🤔 What I've Tried
Describe what you've already attempted or researched.

## 📚 Documentation Checked
- [ ] README
- [ ] API Documentation
- [ ] Examples
- [ ] Related Issues

## 💭 Additional Information
Any other details that might be helpful.
MARKDOWN;
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
