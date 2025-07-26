<?php

namespace App\Services\GitHub\Concerns;

use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\textarea;

trait OpensExternalEditor
{
    /**
     * Interactive markdown editor with preview
     */
    public function openEditor(string $initialContent = '', ?Command $command = null): string
    {
        return $this->promptForMarkdown($command, 'Enter content', $initialContent);
    }

    /**
     * Prompt for markdown content with interactive editing
     */
    protected function promptForMarkdown(?Command $command, string $label, string $default = ''): string
    {
        if (! $command) {
            return $default;
        }

        if (! empty($default)) {
            $command->line('<fg=yellow>Current content:</fg=yellow>');
            $this->renderMarkdownPreview($command, $default);
            $command->newLine();

            if (! confirm('Edit current content?', true)) {
                return $default;
            }
        }

        $content = textarea(
            label: $label,
            placeholder: $default ?: 'Enter your markdown content...',
            hint: 'Markdown supported: **bold**, *italic*, `code`, [links](url)',
            default: $default
        );

        if (! empty($content)) {
            $command->newLine();
            $command->line('<comment>Preview:</comment>');
            $this->renderMarkdownPreview($command, $content);
            $command->newLine();

            if (! confirm('Use this content?', true)) {
                return $this->promptForMarkdown($command, $label, $content);
            }
        }

        return $content ?: $default;
    }

    /**
     * Collect multiline input from user
     */
    protected function collectMultilineInput(?Command $command): string
    {
        return $this->collectMultilineInputAdvanced($command, 'Enter your content');
    }

    /**
     * Render markdown preview in console
     */
    protected function renderMarkdownPreview(?Command $command, string $content): void
    {
        if (! $command) {
            return;
        }

        if (empty($content)) {
            $command->line('<fg=gray>  (empty)</fg=gray>');

            return;
        }

        $command->line('<fg=gray>┌─ Preview</fg=gray>');
        $this->renderMarkdownText($command, $content);
        $command->line('<fg=gray>└─</fg=gray>');
    }

    /**
     * Interactive issue editor with structured prompts
     */
    public function openIssueEditor(?Command $command, array $currentIssue): array
    {
        if (! $command) {
            return $currentIssue;
        }

        $command->line('<comment>📝 Interactive Issue Editor</comment>');
        $command->newLine();

        // Edit title
        $title = $command->ask('Issue title', $currentIssue['title']);

        // Edit body with markdown support
        $command->newLine();
        $body = $this->promptForMarkdown($command, 'Issue body', $currentIssue['body'] ?? '');

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    /**
     * Enhanced multiline input using Laravel prompts
     */
    protected function collectMultilineInputAdvanced(?Command $command, string $prompt): string
    {
        $content = textarea(
            label: $prompt,
            placeholder: 'Enter your content...',
            hint: 'Supports markdown: **bold**, *italic*, `code`, [links](url), etc.'
        );

        if (! empty($content) && $command) {
            $command->newLine();
            $command->line('<comment>Preview:</comment>');
            $this->renderMarkdownPreview($command, $content);
            $command->newLine();

            if (! confirm('Use this content?', true)) {
                return $this->collectMultilineInputAdvanced($command, $prompt);
            }
        }

        return $content;
    }
}
