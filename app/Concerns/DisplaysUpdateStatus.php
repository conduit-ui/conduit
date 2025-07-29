<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Collection;

/**
 * Trait for displaying component update status
 */
trait DisplaysUpdateStatus
{
    /**
     * Display update status to the user
     */
    public function showUpdateStatus(Collection $updates): void
    {
        if ($updates->isEmpty()) {
            if ($this->isVerbose()) {
                echo "✅ All components up to date\n";
            }
            return;
        }

        echo "📦 " . $updates->count() . " component update(s) available:\n";
        
        foreach ($updates as $update) {
            $priority = $this->formatPriority($update['priority'] ?? 'normal');
            echo "  • {$update['name']} {$update['current']} → {$update['latest']}{$priority}\n";
        }
        
        echo "\n💡 Run 'conduit update' to install updates\n";
        echo str_repeat('─', 50) . "\n";
    }

    /**
     * Display updates in table format
     */
    public function displayUpdatesTable(Collection $updates): void
    {
        if ($updates->isEmpty()) {
            echo "✅ All components are up to date!\n";
            return;
        }

        echo "📦 Available updates:\n\n";

        $rows = $updates->map(function ($update) {
            $priority = match($update['priority'] ?? 'normal') {
                'security' => '<fg=red>Security</>', 
                'breaking' => '<fg=yellow>Breaking</>', 
                default => 'Normal'
            };

            return [
                'Component' => $update['name'],
                'Current' => $update['current'],
                'Latest' => $update['latest'], 
                'Priority' => $priority,
            ];
        })->values()->toArray();

        // Use Laravel Zero's table helper if available
        if (method_exists($this, 'table')) {
            $this->table(['Component', 'Current', 'Latest', 'Priority'], $rows);
        } else {
            // Fallback to simple display
            foreach ($rows as $row) {
                echo sprintf(
                    "%-15s %-10s → %-10s (%s)\n",
                    $row['Component'],
                    $row['Current'],
                    $row['Latest'],
                    strip_tags($row['Priority'])
                );
            }
        }
    }

    /**
     * Format priority indicator
     */
    private function formatPriority(string $priority): string
    {
        return match($priority) {
            'security' => ' (security)',
            'breaking' => ' (breaking changes)',
            default => ''
        };
    }

    /**
     * Check if verbose output is enabled
     */
    private function isVerbose(): bool
    {
        return app()->bound('command') && app('command')->option('verbose');
    }
}