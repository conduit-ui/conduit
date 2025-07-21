<?php

namespace App\Commands\GitHub;

use App\Commands\GitHub\Concerns\DetectsRepository;
use App\Services\GitHub\PrAnalysisService;
use App\Services\GithubAuthService;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\text;

class PrStatusCommand extends Command
{
    use DetectsRepository;

    protected $signature = 'prs:status 
                           {pr : PR number to check}
                           {--repo= : Repository (owner/repo)}
                           {--format=interactive : Output format (interactive, json)}';

    protected $description = 'Quick PR merge status check with conflict detection';

    public function handle(
        PrAnalysisService $analysisService,
        GithubAuthService $authService
    ): int {
        if (!$authService->getToken()) {
            $this->error('❌ GitHub authentication required. Run: conduit github:auth');
            return 1;
        }

        $repo = $this->option('repo') ?: $this->detectRepository();
        if (!$repo) {
            $repo = text('Repository (owner/repo):');
        }

        $prNumber = (int) $this->argument('pr');
        
        $this->line("⏳ <comment>Checking merge status for PR #{$prNumber}...</comment>");

        $analysis = $analysisService->analyzeMergeReadiness($repo, $prNumber);

        if (isset($analysis['error'])) {
            $this->error("❌ {$analysis['error']}");
            return 1;
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'pr' => $prNumber,
                'repo' => $repo,
                'merge_status' => $analysis['merge_analysis']
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        $this->displayStatus($analysis);
        return 0;
    }

    private function displayStatus(array $analysis): void
    {
        $pr = $analysis['pr_info'];
        $merge = $analysis['merge_analysis'];

        $this->newLine();
        $this->line("🔍 <info>PR #{$pr['number']}: {$pr['title']}</info>");
        $this->line("👤 Author: <comment>{$pr['author']}</comment>");
        
        $this->newLine();
        
        // Main status with colored icons
        if ($merge['ready_to_merge']) {
            $this->line("✅ <fg=green>Ready to Merge</> - No conflicts detected");
        } elseif ($merge['has_conflicts']) {
            $this->line("❌ <fg=red>Has Merge Conflicts</> - Requires resolution");
        } else {
            $this->line("⚠️  <fg=yellow>Merge Status Uncertain</> - {$merge['status_description']}");
        }

        // Additional details
        $this->newLine();
        $this->line("📊 <info>Details:</info>");
        
        if ($merge['mergeable'] !== null) {
            $mergeableText = $merge['mergeable'] ? '<fg=green>Yes</>' : '<fg=red>No</>';
            $this->line("   Mergeable: {$mergeableText}");
        } else {
            $this->line("   Mergeable: <fg=yellow>Checking...</>");
        }
        
        if ($merge['mergeable_state']) {
            $this->line("   State: <comment>{$merge['mergeable_state']}</comment>");
        }
        
        if ($merge['rebaseable'] !== null) {
            $rebaseableText = $merge['rebaseable'] ? '<fg=green>Yes</>' : '<fg=red>No</>';
            $this->line("   Can Rebase: {$rebaseableText}");
        }

        if ($pr['draft']) {
            $this->newLine();
            $this->line("📝 <fg=yellow>Note: This is a draft PR</>");
        }

        // Quick recommendations
        $this->newLine();
        if ($merge['ready_to_merge']) {
            $this->line("🎉 <fg=green>This PR is ready to merge!</>");
        } elseif ($merge['has_conflicts']) {
            $this->line("🔧 <fg=yellow>Action needed: Resolve merge conflicts before merging</>");
        } elseif ($merge['can_rebase']) {
            $this->line("💡 <fg=blue>Tip: Consider rebasing to update with latest changes</>");
        }
    }
}