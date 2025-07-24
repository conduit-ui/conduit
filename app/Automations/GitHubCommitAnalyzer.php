<?php

namespace App\Automations;

use Symfony\Component\Process\Process;

class GitHubCommitAnalyzer
{
    public function analyzeCommit(string $repo, string $commitSha): array
    {
        $insights = [];
        
        // 1. Get commit details
        $commitInfo = $this->getCommitInfo($commitSha);
        
        // 2. Analyze code complexity
        $complexity = $this->analyzeComplexity($commitSha);
        if ($complexity['score'] > 8) {
            $insights[] = "⚠️ High complexity change detected (score: {$complexity['score']}/10)";
        }
        
        // 3. Check for common issues
        $issues = $this->detectCommonIssues($commitSha);
        $insights = array_merge($insights, $issues);
        
        // 4. Find related PRs
        $relatedPRs = $this->findRelatedPRs($repo, $commitSha);
        if (!empty($relatedPRs)) {
            $insights[] = "🔗 Related PRs: " . implode(', ', $relatedPRs);
        }
        
        // 5. Generate actionable tasks
        $tasks = $this->generateTasks($commitInfo, $complexity, $issues);
        
        return [
            'commit' => $commitSha,
            'insights' => $insights,
            'tasks' => $tasks,
            'automation_suggestions' => $this->suggestAutomations($commitInfo)
        ];
    }
    
    private function getCommitInfo(string $sha): array
    {
        // Get detailed commit information
        $process = new Process(['git', 'show', '--stat', $sha]);
        $process->run();
        
        return [
            'files_changed' => $this->parseFilesChanged($process->getOutput()),
            'insertions' => $this->parseInsertions($process->getOutput()),
            'deletions' => $this->parseDeletions($process->getOutput())
        ];
    }
    
    private function analyzeComplexity(string $sha): array
    {
        // Analyze cyclomatic complexity of changed methods
        // Could integrate with tools like PHPMD, PHPStan, etc.
        $score = 0;
        
        // Get changed PHP files
        $process = new Process([
            'git', 'diff-tree', '--no-commit-id', '--name-only', '-r', 
            '--diff-filter=AM', $sha
        ]);
        $process->run();
        
        $files = array_filter(
            explode("\n", trim($process->getOutput())),
            fn($f) => str_ends_with($f, '.php')
        );
        
        // Simple heuristic: more files = higher complexity
        $score = min(10, count($files) * 2);
        
        return ['score' => $score, 'files' => count($files)];
    }
    
    private function detectCommonIssues(string $sha): array
    {
        $issues = [];
        
        // Check for common anti-patterns
        $diff = new Process(['git', 'show', $sha]);
        $diff->run();
        $content = $diff->getOutput();
        
        // Security checks
        if (preg_match('/password\s*=|api_key\s*=|secret\s*=/i', $content)) {
            $issues[] = "🔐 Potential hardcoded credentials detected";
        }
        
        // TODO comments
        if (preg_match('/\+.*TODO|FIXME|HACK/i', $content)) {
            $issues[] = "📝 New TODO/FIXME comments added";
        }
        
        // Large methods
        if (preg_match('/function\s+\w+\s*\([^)]*\)\s*\{[^}]{500,}/s', $content)) {
            $issues[] = "📏 Large method detected (consider refactoring)";
        }
        
        return $issues;
    }
    
    private function findRelatedPRs(string $repo, string $sha): array
    {
        // Use GitHub CLI to find PRs containing this commit
        $process = new Process([
            'gh', 'pr', 'list', 
            '--repo', $repo,
            '--search', $sha,
            '--json', 'number,title',
            '--limit', '5'
        ]);
        $process->run();
        
        if (!$process->isSuccessful()) {
            return [];
        }
        
        $prs = json_decode($process->getOutput(), true) ?: [];
        return array_map(fn($pr) => "#{$pr['number']}", $prs);
    }
    
    private function generateTasks(array $info, array $complexity, array $issues): array
    {
        $tasks = [];
        
        if ($complexity['score'] > 8) {
            $tasks[] = [
                'type' => 'refactor',
                'priority' => 'medium',
                'description' => 'Consider breaking down complex changes into smaller commits'
            ];
        }
        
        if (in_array("📝 New TODO/FIXME comments added", $issues)) {
            $tasks[] = [
                'type' => 'follow-up',
                'priority' => 'low',
                'description' => 'Create GitHub issues for new TODOs'
            ];
        }
        
        if ($info['deletions'] > 100) {
            $tasks[] = [
                'type' => 'review',
                'priority' => 'high',
                'description' => 'Review large deletion for potential data loss'
            ];
        }
        
        return $tasks;
    }
    
    private function suggestAutomations(array $info): array
    {
        $suggestions = [];
        
        if ($info['files_changed'] > 10) {
            $suggestions[] = "Enable PR size limits to encourage smaller, focused changes";
        }
        
        if ($info['insertions'] > 500) {
            $suggestions[] = "Set up automated code review for large changes";
        }
        
        return $suggestions;
    }
    
    private function parseFilesChanged(string $output): int
    {
        if (preg_match('/(\d+) files? changed/', $output, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }
    
    private function parseInsertions(string $output): int
    {
        if (preg_match('/(\d+) insertions?/', $output, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }
    
    private function parseDeletions(string $output): int
    {
        if (preg_match('/(\d+) deletions?/', $output, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }
}