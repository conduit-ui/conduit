<?php

namespace App\Listeners;

use App\Events\KnowledgeCaptured;
use Symfony\Component\Process\Process;

class KnowledgeAutomationListener
{
    /**
     * Handle the knowledge captured event.
     */
    public function handle(KnowledgeCaptured $event): void
    {
        // Only process auto-captured commits
        if (!$event->isAutoCapture() || !$event->commitSha) {
            return;
        }

        // Example automations:
        $this->analyzeCommitPatterns($event);
        $this->checkForBreakingChanges($event);
        $this->updateKnowledgeGraph($event);
        $this->triggerWebhooks($event);
    }

    private function analyzeCommitPatterns(KnowledgeCaptured $event): void
    {
        // Detect patterns like "fix:", "feat:", "BREAKING:"
        if (str_contains(strtolower($event->content), 'breaking')) {
            // Trigger breaking change workflow
            $this->notifyTeam('breaking-change', $event);
        }

        if (str_contains(strtolower($event->content), 'security')) {
            // Trigger security review
            $this->runSecurityScan($event->commitSha);
        }
    }

    private function checkForBreakingChanges(KnowledgeCaptured $event): void
    {
        // Get files changed in commit
        $process = new Process([
            'git', 'diff-tree', '--no-commit-id', '--name-only', '-r', $event->commitSha
        ]);
        $process->run();
        
        $files = explode("\n", trim($process->getOutput()));
        
        // Check for API changes, schema migrations, etc.
        foreach ($files as $file) {
            if (str_ends_with($file, 'Controller.php') || 
                str_contains($file, 'migrations/') ||
                str_contains($file, 'api.php')) {
                // Flag for review
                $this->createReviewTask($event, $file);
            }
        }
    }

    private function updateKnowledgeGraph(KnowledgeCaptured $event): void
    {
        // Find related commits
        $process = new Process([
            'git', 'log', '--grep=' . escapeshellarg($event->content), 
            '--format=%H', '-n', '5'
        ]);
        $process->run();
        
        // Link related knowledge entries
        // This would update the knowledge_relationships table
    }

    private function triggerWebhooks(KnowledgeCaptured $event): void
    {
        // Send to configured webhooks
        $webhooks = config('conduit.webhooks.knowledge_captured', []);
        
        foreach ($webhooks as $webhook) {
            // POST to webhook with commit details
            // Could integrate with Zapier, IFTTT, GitHub Actions, etc.
        }
    }

    private function runSecurityScan(string $commitSha): void
    {
        // Example: Run security tools on the specific commit
        // - Snyk
        // - GitHub security scanning
        // - Custom security rules
    }

    private function notifyTeam(string $type, KnowledgeCaptured $event): void
    {
        // Send notifications via:
        // - Slack
        // - Email
        // - GitHub notifications
        // - Desktop notifications
    }

    private function createReviewTask(KnowledgeCaptured $event, string $file): void
    {
        // Auto-create GitHub issue or PR comment
        // Add to project board
        // Assign to relevant team member
    }
}