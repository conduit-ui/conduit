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
                if (!$inCodeBlock) {
                    $inCodeBlock = true;
                    $codeBlockLanguage = $matches[1] ?? '';
                    $command->line("<fg=gray>┌─ Code" . ($codeBlockLanguage ? " ({$codeBlockLanguage})" : '') . "</fg=gray>");
                } else {
                    $inCodeBlock = false;
                    $command->line("<fg=gray>└─</fg=gray>");
                }
                continue;
            }
            
            if ($inCodeBlock) {
                $command->line("<fg=yellow>│ " . htmlspecialchars($line) . "</fg=yellow>");
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
        $labelNames = array_map(fn($label) => strtolower($label['name']), $labels);
        
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
     * Format labels with GitHub's actual colors mapped to terminal colors
     */
    protected function formatLabels(array $labels): array
    {
        $formatted = [];
        
        foreach ($labels as $label) {
            $name = $label['name'];
            $color = $label['color'] ?? '000000';
            
            // Map GitHub hex colors to terminal colors
            $terminalColor = $this->mapGitHubColorToTerminal($color);
            
            // Also add semantic color overrides for common label types
            if (in_array(strtolower($name), ['bug', 'critical', 'p0'])) {
                $terminalColor = 'red';
            } elseif (in_array(strtolower($name), ['enhancement', 'feature'])) {
                $terminalColor = 'green';
            } elseif (in_array(strtolower($name), ['documentation', 'docs'])) {
                $terminalColor = 'blue';
            } elseif (in_array(strtolower($name), ['question', 'help'])) {
                $terminalColor = 'yellow';
            }
            
            $formatted[] = "<fg={$terminalColor}>{$name}</fg={$terminalColor}>";
        }
        
        return $formatted;
    }
    
    /**
     * Map GitHub hex colors to nearest terminal colors
     */
    protected function mapGitHubColorToTerminal(string $hexColor): string
    {
        // Remove # if present
        $hex = ltrim($hexColor, '#');
        
        // Convert hex to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2)); 
        $b = hexdec(substr($hex, 4, 2));
        
        // Calculate brightness
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
        
        // Map to nearest terminal color based on dominant channel and brightness
        if ($brightness < 60) {
            return 'black';
        } elseif ($brightness > 200) {
            return 'white';
        } elseif ($r > $g && $r > $b) {
            return $r > 180 ? 'red' : 'red';
        } elseif ($g > $r && $g > $b) {
            return $g > 180 ? 'green' : 'green';
        } elseif ($b > $r && $b > $g) {
            return $b > 180 ? 'blue' : 'blue';
        } elseif ($r > 150 && $g > 150) {
            return 'yellow';
        } elseif ($r > 150 && $b > 150) {
            return 'magenta';
        } elseif ($g > 150 && $b > 150) {
            return 'cyan';
        } else {
            return 'gray';
        }
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
            $command->line('<fg=gray>' . str_repeat('─', $width) . '</fg=gray>');
        }
    }
}