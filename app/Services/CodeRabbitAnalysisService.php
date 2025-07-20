<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\CodeRabbitAnalysis;
// use Illuminate\Process\Process;
use Illuminate\Support\Collection;

class CodeRabbitAnalysisService
{
    public function analyzeCodeRabbitFeedback(int $prNumber, string $owner, string $repo): CodeRabbitAnalysis
    {
        // Fetch all CodeRabbit comments
        $reviewComments = $this->fetchCodeRabbitComments($owner, $repo, $prNumber);
        $issueComments = $this->fetchCodeRabbitIssueComments($owner, $repo, $prNumber);
        
        // Combine and categorize
        $allComments = $this->categorizeComments($reviewComments, $issueComments);
        
        // Generate Claude analysis
        $aiSummary = $this->generateClaudeAnalysis($allComments, $prNumber);
        
        return new CodeRabbitAnalysis(
            prNumber: $prNumber,
            repository: "{$owner}/{$repo}",
            totalComments: $allComments->count(),
            commentsByFile: $this->groupByFile($allComments),
            commentsByCategory: $this->groupByCategory($allComments),
            aiSummary: $aiSummary,
            rawComments: $allComments
        );
    }

    private function fetchCodeRabbitComments(string $owner, string $repo, int $prNumber): Collection
    {
        $command = "gh api repos/{$owner}/{$repo}/pulls/{$prNumber}/comments --paginate 2>/dev/null";
        $output = shell_exec($command);
        
        if (!$output) {
            return collect();
        }
        
        $comments = json_decode($output, true);
        if (!is_array($comments)) {
            return collect();
        }
        
        return collect($comments)
            ->filter(fn($comment) => ($comment['user']['login'] ?? '') === 'coderabbitai[bot]')
            ->map(fn($comment) => $this->normalizeComment($comment, 'review'));
    }

    private function fetchCodeRabbitIssueComments(string $owner, string $repo, int $prNumber): Collection
    {
        $command = "gh api repos/{$owner}/{$repo}/issues/{$prNumber}/comments --paginate 2>/dev/null";
        $output = shell_exec($command);
        
        if (!$output) {
            return collect();
        }
        
        $comments = json_decode($output, true);
        if (!is_array($comments)) {
            return collect();
        }
        
        return collect($comments)
            ->filter(fn($comment) => ($comment['user']['login'] ?? '') === 'coderabbitai[bot]')
            ->map(fn($comment) => $this->normalizeComment($comment, 'issue'));
    }

    private function normalizeComment(array $comment, string $type): array
    {
        $body = $comment['body'] ?? '';
        
        return [
            'id' => $comment['id'],
            'type' => $type,
            'file' => $comment['path'] ?? null,
            'line' => $comment['line'] ?? $comment['original_line'] ?? null,
            'body' => $body,
            'category' => $this->categorizeComment($body),
            'priority' => $this->determinePriority($body),
            'suggestion_type' => $this->determineSuggestionType($body),
            'created_at' => $comment['created_at'] ?? null,
            'url' => $comment['html_url'] ?? null,
        ];
    }

    private function categorizeComment(string $body): string
    {
        $lower = strtolower($body);
        
        if (str_contains($lower, 'security') || str_contains($lower, 'vulnerability')) {
            return 'security';
        }
        
        if (str_contains($lower, 'performance') || str_contains($lower, 'optimization')) {
            return 'performance';
        }
        
        if (str_contains($lower, 'duplication') || str_contains($lower, 'duplicate')) {
            return 'duplication';
        }
        
        if (str_contains($lower, 'style') || str_contains($lower, 'formatting')) {
            return 'style';
        }
        
        if (str_contains($lower, 'error handling') || str_contains($lower, 'exception')) {
            return 'error_handling';
        }
        
        if (str_contains($lower, 'test') || str_contains($lower, 'testing')) {
            return 'testing';
        }
        
        return 'general';
    }

    private function determinePriority(string $body): string
    {
        $lower = strtolower($body);
        
        if (str_contains($lower, 'critical') || str_contains($lower, 'security') || str_contains($lower, 'vulnerability')) {
            return 'high';
        }
        
        if (str_contains($lower, 'important') || str_contains($lower, 'performance') || str_contains($lower, 'bug')) {
            return 'medium';
        }
        
        return 'low';
    }

    private function determineSuggestionType(string $body): string
    {
        $lower = strtolower($body);
        
        if (str_contains($lower, 'refactor') || str_contains($lower, 'extract')) {
            return 'refactoring';
        }
        
        if (str_contains($lower, 'add') || str_contains($lower, 'implement')) {
            return 'enhancement';
        }
        
        if (str_contains($lower, 'fix') || str_contains($lower, 'correct')) {
            return 'bug_fix';
        }
        
        if (str_contains($lower, 'remove') || str_contains($lower, 'delete')) {
            return 'removal';
        }
        
        return 'suggestion';
    }

    private function categorizeComments(Collection $reviewComments, Collection $issueComments): Collection
    {
        return $reviewComments->concat($issueComments)->sortBy('created_at');
    }

    private function groupByFile(Collection $comments): array
    {
        return $comments
            ->groupBy('file')
            ->map(fn($fileComments) => [
                'count' => $fileComments->count(),
                'priorities' => $fileComments->groupBy('priority')->map->count()->toArray(),
                'categories' => $fileComments->groupBy('category')->map->count()->toArray(),
                'comments' => $fileComments->toArray()
            ])
            ->toArray();
    }

    private function groupByCategory(Collection $comments): array
    {
        return $comments
            ->groupBy('category')
            ->map(fn($categoryComments) => [
                'count' => $categoryComments->count(),
                'priorities' => $categoryComments->groupBy('priority')->map->count()->toArray(),
                'files_affected' => $categoryComments->pluck('file')->filter()->unique()->count(),
                'comments' => $categoryComments->toArray()
            ])
            ->toArray();
    }

    private function generateClaudeAnalysis(Collection $comments, int $prNumber): array
    {
        if ($comments->isEmpty()) {
            return [
                'executive_summary' => 'No CodeRabbit feedback found for this PR.',
                'key_themes' => [],
                'action_priorities' => [],
                'technical_debt_assessment' => 'No technical debt identified.',
                'overall_code_quality' => 'No assessment available.'
            ];
        }

        $analysisData = $this->prepareAnalysisData($comments);
        $claudePrompt = $this->buildAnalysisPrompt($analysisData, $prNumber);
        
        $analysis = $this->callClaude($claudePrompt);
        
        return $this->parseClaudeAnalysis($analysis);
    }

    private function prepareAnalysisData(Collection $comments): array
    {
        return [
            'total_comments' => $comments->count(),
            'by_priority' => $comments->groupBy('priority')->map->count()->toArray(),
            'by_category' => $comments->groupBy('category')->map->count()->toArray(),
            'by_file' => $comments->groupBy('file')->map->count()->toArray(),
            'suggestion_types' => $comments->groupBy('suggestion_type')->map->count()->toArray(),
            'sample_comments' => $comments->take(10)->map(function ($comment) {
                return [
                    'file' => $comment['file'],
                    'line' => $comment['line'],
                    'category' => $comment['category'],
                    'priority' => $comment['priority'],
                    'snippet' => substr($comment['body'], 0, 200) . '...'
                ];
            })->toArray()
        ];
    }

    private function buildAnalysisPrompt(array $data, int $prNumber): string
    {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
You are an expert code reviewer analyzing CodeRabbit feedback for PR #{$prNumber}.

CODERABBIT FEEDBACK DATA:
{$dataJson}

Please provide a comprehensive analysis with the following structure:

1. EXECUTIVE_SUMMARY: A 2-3 sentence high-level overview of the feedback
2. KEY_THEMES: The main patterns/themes in the feedback (max 5 bullet points)
3. ACTION_PRIORITIES: What should be addressed first, second, third (prioritized list)
4. TECHNICAL_DEBT_ASSESSMENT: Assessment of technical debt implications
5. OVERALL_CODE_QUALITY: Overall assessment of code quality based on feedback

Format as JSON with these exact keys:
- executive_summary
- key_themes (array of strings)
- action_priorities (array of strings)
- technical_debt_assessment
- overall_code_quality

Focus on being actionable and concise. This will be used for voice narration.
PROMPT;
    }

    private function callClaude(string $prompt): string
    {
        $escapedPrompt = escapeshellarg($prompt);
        
        $command = "claude -p {$escapedPrompt} 2>/dev/null";
        $output = shell_exec($command);
        
        if (!$output) {
            throw new \Exception('Claude Code CLI failed or not available');
        }

        return trim($output);
    }

    private function parseClaudeAnalysis(string $analysis): array
    {
        // Try to extract JSON from Claude's response
        if (preg_match('/\{.*\}/s', $analysis, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }
        
        // Fallback to basic parsing
        return [
            'executive_summary' => 'Analysis completed but could not parse structured response.',
            'key_themes' => ['Various code improvements suggested'],
            'action_priorities' => ['Review all CodeRabbit comments'],
            'technical_debt_assessment' => 'Assessment unavailable.',
            'overall_code_quality' => 'Review required.'
        ];
    }
}