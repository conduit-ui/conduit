<?php

namespace App\Services\GitHub\Concerns;

use LaravelZero\Framework\Commands\Command;

trait RendersIssueDetails
{
    /**
     * Render markdown-like text with basic formatting
     */
    protected function renderMarkdownText(Command $command, string $text): void
    {
        $lines = explode("\n", $text);
        $inCodeBlock = false;
        $codeBlockLanguage = '';

        foreach ($lines as $line) {
            // Handle code blocks
            if (preg_match('/^```(\w*)/', $line, $matches)) {
                if (! $inCodeBlock) {
                    $inCodeBlock = true;
                    $codeBlockLanguage = $matches[1] ?? '';
                    $command->line('<fg=gray>┌─ Code'.($codeBlockLanguage ? " ({$codeBlockLanguage})" : '').'</fg=gray>');
                } else {
                    $inCodeBlock = false;
                    $command->line('<fg=gray>└─</fg=gray>');
                }

                continue;
            }

            if ($inCodeBlock) {
                $command->line('<fg=yellow>│ '.htmlspecialchars($line).'</fg=yellow>');

                continue;
            }

            // Handle headers
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $headerText = $matches[2];

                switch ($level) {
                    case 1:
                        $command->line("<fg=cyan;options=bold>🔸 {$headerText}</fg=cyan;options=bold>");
                        break;
                    case 2:
                        $command->line("<fg=cyan;options=bold>  🔹 {$headerText}</fg=cyan;options=bold>");
                        break;
                    default:
                        $command->line("<fg=cyan>    • {$headerText}</fg=cyan>");
                        break;
                }

                continue;
            }

            // Handle bullet points
            if (preg_match('/^[\s]*[-*+]\s+(.+)$/', $line, $matches)) {
                $text = $matches[1];
                $command->line("  • {$text}");

                continue;
            }

            // Handle numbered lists
            if (preg_match('/^[\s]*\d+\.\s+(.+)$/', $line, $matches)) {
                $text = $matches[1];
                $command->line("  {$text}");

                continue;
            }

            // Handle inline code
            $line = preg_replace('/`([^`]+)`/', '<fg=yellow>$1</fg=yellow>', $line);

            // Handle bold text
            $line = preg_replace('/\*\*([^*]+)\*\*/', '<comment>$1</comment>', $line);

            // Handle italic text (simplified)
            $line = preg_replace('/\*([^*]+)\*/', '<fg=gray>$1</fg=gray>', $line);

            // Handle links
            $line = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<href=$2>$1</href>', $line);

            // Empty lines
            if (trim($line) === '') {
                $command->newLine();

                continue;
            }

            $command->line($line);
        }
    }

    /**
     * Get priority/severity indicators from labels
     */
    protected function getPriorityIndicator(array $labels): string
    {
        $labelNames = array_map(fn ($label) => strtolower($label['name']), $labels);

        if (in_array('critical', $labelNames) || in_array('p0', $labelNames)) {
            return '<fg=red;options=bold>🔥 CRITICAL</fg=red;options=bold>';
        }

        if (in_array('high', $labelNames) || in_array('p1', $labelNames)) {
            return '<fg=red>⚠️  HIGH</fg=red>';
        }

        if (in_array('medium', $labelNames) || in_array('p2', $labelNames)) {
            return '<fg=yellow>⚡ MEDIUM</fg=yellow>';
        }

        if (in_array('low', $labelNames) || in_array('p3', $labelNames)) {
            return '<fg=green>📋 LOW</fg=green>';
        }

        return '';
    }

    /**
     * Create a visual separator
     */
    protected function renderSeparator(Command $command, string $title = '', int $width = 50): void
    {
        if ($title) {
            $titleLen = strlen($title);
            $padding = max(0, ($width - $titleLen - 2) / 2);
            $leftPad = str_repeat('─', floor($padding));
            $rightPad = str_repeat('─', ceil($padding));
            $command->line("<fg=gray>{$leftPad} {$title} {$rightPad}</fg=gray>");
        } else {
            $command->line('<fg=gray>'.str_repeat('─', $width).'</fg=gray>');
        }
    }
}
