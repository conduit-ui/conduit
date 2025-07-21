<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\NarrationContent;

// use Illuminate\Process\Process;

class ClaudeNarrationService
{
    public function generateNarration(NarrationContent $content, string $prompt): string
    {
        $contextData = $this->buildContext($content);

        $claudePrompt = $this->buildClaudePrompt($contextData, $prompt);

        return $this->callClaude($claudePrompt);
    }

    private function buildContext(NarrationContent $content): array
    {
        $context = [
            'type' => $content->type,
            'number' => $content->number,
            'title' => $content->title,
            'description' => $content->description,
            'state' => $content->state,
            'author' => $content->author,
        ];

        // Add type-specific context
        if ($content->type === 'pull_request') {
            $context['stats'] = [
                'additions' => $content->metadata['additions'] ?? 0,
                'deletions' => $content->metadata['deletions'] ?? 0,
                'changed_files' => $content->metadata['changed_files'] ?? 0,
                'commits' => $content->metadata['commits'] ?? 0,
                'draft' => $content->metadata['draft'] ?? false,
                'mergeable' => $content->metadata['mergeable'] ?? null,
            ];
        }

        if ($content->type === 'issue') {
            $context['labels'] = $content->metadata['labels'] ?? [];
            $context['assignees'] = $content->metadata['assignees'] ?? [];
        }

        // Add comments summary if available
        if ($content->comments && $content->comments->isNotEmpty()) {
            $context['comments_summary'] = $content->getCommentsSummary();
        }

        // Add reviews summary for PRs
        if ($content->reviews && $content->reviews->isNotEmpty()) {
            $context['reviews_summary'] = $content->getReviewsSummary();
        }

        return $context;
    }

    private function buildClaudePrompt(array $context, string $userPrompt): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a voice narrator for a developer tool. Your job is to create engaging spoken narration about GitHub issues and pull requests.

CONTEXT DATA:
{$contextJson}

USER REQUEST: {$userPrompt}

REQUIREMENTS:
1. Create a narration that's meant to be spoken aloud (text-to-speech)
2. Keep it under 200 words for good listening experience
3. Include the key information (title, author, status, etc.)
4. Match the requested style/personality from the user
5. Make it engaging and entertaining
6. Avoid excessive markdown or formatting (this will be spoken)

Generate the narration now:
PROMPT;
    }

    private function callClaude(string $prompt): string
    {
        // Use Claude Code CLI with shell_exec (Laravel Zero compatible)
        $escapedPrompt = escapeshellarg($prompt);

        $command = "claude -p {$escapedPrompt} 2>/dev/null";
        $output = shell_exec($command);

        if (! $output) {
            throw new \Exception('Claude Code CLI failed or not available');
        }

        $output = trim($output);

        // Clean up any Claude formatting
        $output = $this->cleanClaudeOutput($output);

        return $output;
    }

    private function cleanClaudeOutput(string $output): string
    {
        // Remove common Claude response patterns
        $patterns = [
            '/^Here\'s.*?:\s*/i',
            '/^I\'ll.*?:\s*/i',
            '/^Let me.*?:\s*/i',
            '/^```.*?```/s',
            '/\*\*(.*?)\*\*/', // Bold markdown
            '/\*(.*?)\*/',     // Italic markdown
        ];

        $replacements = [
            '',
            '',
            '',
            '',
            '$1',
            '$1',
        ];

        $cleaned = preg_replace($patterns, $replacements, $output);

        // Normalize whitespace
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }
}
