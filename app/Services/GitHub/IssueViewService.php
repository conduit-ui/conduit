<?php

namespace App\Services\GitHub;

use App\Services\GitHub\Concerns\RendersIssueDetails;
use App\Services\GitHub\Concerns\RendersIssueComments;
use JordanPartridge\GithubClient\Facades\Github;
use LaravelZero\Framework\Commands\Command;

class IssueViewService
{
    use RendersIssueDetails;
    use RendersIssueComments;

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
     * Get issue comments with memoization
     */
    public function getIssueComments(string $repo, int $issueNumber): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = "issue_comments_{$issueNumber}";
        if (!isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);
            
            try {
                $comments = Github::issues()->comments($owner, $repoName, $issueNumber);
                $this->memoizedData[$cacheKey] = array_map(fn($comment) => $comment->toArray(), $comments);
            } catch (\Exception $e) {
                $this->memoizedData[$cacheKey] = [];
            }
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Get issue events with memoization
     */
    public function getIssueEvents(string $repo, int $issueNumber): array
    {
        $this->initializeCache($repo);
        
        $cacheKey = "issue_events_{$issueNumber}";
        if (!isset($this->memoizedData[$cacheKey])) {
            [$owner, $repoName] = explode('/', $repo);
            
            try {
                // Note: Events API would need to be implemented in github-client
                // For now, return empty array
                $this->memoizedData[$cacheKey] = [];
            } catch (\Exception $e) {
                $this->memoizedData[$cacheKey] = [];
            }
        }
        
        return $this->memoizedData[$cacheKey];
    }

    /**
     * Display issue header with rich formatting
     */
    public function displayIssueHeader(Command $command, array $issue): void
    {
        $command->newLine();
        
        $status = $this->getIssueStatusIcon($issue);
        $command->info("{$status} Issue #{$issue['number']}");
        
        $command->line("📝 <fg=cyan;options=bold>{$issue['title']}</>");
    }

    /**
     * Display issue metadata
     */
    public function displayIssueMetadata(Command $command, array $issue): void
    {
        $command->newLine();
        
        // Author and dates
        $command->line("👤 Author: <info>{$issue['user']['login']}</info>");
        $command->line("📊 State: <info>" . ucfirst($issue['state']) . "</info>");
        $command->line("📅 Created: <info>{$this->formatDate($issue['created_at'])}</info>");
        $command->line("📅 Updated: <info>{$this->formatDate($issue['updated_at'])}</info>");
        
        // Assignees
        if (!empty($issue['assignees'])) {
            $assignees = array_map(fn($assignee) => $assignee['login'], $issue['assignees']);
            $command->line("👨‍💻 Assignees: <info>" . implode(', ', $assignees) . "</info>");
        }
        
        // Labels
        if (!empty($issue['labels'])) {
            $labels = array_map(fn($label) => $label['name'], $issue['labels']);
            $command->line("🏷️  Labels: <info>" . implode(', ', $labels) . "</info>");
        }
        
        // Milestone
        if (!empty($issue['milestone'])) {
            $command->line("🎯 Milestone: <info>{$issue['milestone']['title']}</info>");
        }
        
        // Comments count
        $command->line("💬 Comments: <info>{$issue['comments']}</info>");
        
        // Links
        $command->line("🔗 URL: <href={$issue['html_url']}>{$issue['html_url']}</>");
    }

    /**
     * Display issue body with markdown-like formatting
     */
    public function displayIssueBody(Command $command, array $issue): void
    {
        if (empty($issue['body'])) {
            $command->newLine();
            $command->line('<fg=gray>No description provided</fg=gray>');
            return;
        }

        $command->newLine();
        $command->line('<options=bold>Description:</options>');
        $command->newLine();
        
        $this->renderMarkdownText($command, $issue['body']);
    }

    /**
     * Display comments with threading and formatting
     */
    public function displayComments(Command $command, array $comments): void
    {
        $command->line("<options=bold>💬 Comments (" . count($comments) . "):</options>");
        $command->newLine();
        
        foreach ($comments as $index => $comment) {
            $this->renderComment($command, $comment, $index + 1);
            
            if ($index < count($comments) - 1) {
                $command->newLine();
                $command->line('<fg=gray>' . str_repeat('─', 50) . '</fg=gray>');
                $command->newLine();
            }
        }
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
     * Format date for display
     */
    private function formatDate(string $date): string
    {
        $timestamp = strtotime($date);
        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . 'd ago';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
}