<?php

namespace App\Services\GitHub\Concerns;

use LaravelZero\Framework\Commands\Command;

trait HandlesLabelSelection
{
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
     * Interactive label management for editing
     */
    public function interactiveLabelManagement(Command $command, string $repo, array $currentLabels): array
    {
        $changes = [];
        $currentLabelNames = array_map(fn ($label) => $label['name'], $currentLabels);

        // Add labels
        $availableLabels = $this->getAvailableLabels($repo);
        $availableToAdd = array_filter($availableLabels, fn ($label) => ! in_array($label['name'], $currentLabelNames));

        if (! empty($availableToAdd)) {
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
                if (! empty($addLabels)) {
                    $changes['add_labels'] = $addLabels;
                }
            }
        }

        // Remove labels
        if (! empty($currentLabels)) {
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
                if (! empty($removeLabels)) {
                    $changes['remove_labels'] = $removeLabels;
                }
            }
        }

        return $changes;
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
}
