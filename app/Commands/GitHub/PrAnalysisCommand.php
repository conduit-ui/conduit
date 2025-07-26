<?php

namespace App\Commands\GitHub;

use App\Commands\GitHub\Concerns\DetectsRepository;
use App\Services\GitHub\PrAnalysisService;
use App\Services\GithubAuthService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\text;

class PrAnalysisCommand extends Command
{
    use DetectsRepository;

    protected $signature = 'prs:analysis 
                           {pr? : PR number to analyze}
                           {--repo= : Repository (owner/repo)}
                           {--health : Show health score}
                           {--format=interactive : Output format (interactive, json)}';

    protected $description = 'Advanced PR analysis with merge readiness and health insights';

    public function handle(
        PrAnalysisService $analysisService,
        GithubAuthService $authService
    ): int {
        if (! $authService->getToken()) {
            $this->error('❌ GitHub authentication required. Run: conduit github:auth');

            return 1;
        }

        $repo = $this->option('repo') ?: $this->detectCurrentRepo();
        if (! $repo) {
            $repo = text('Repository (owner/repo):');
        }

        $prNumber = $this->argument('pr');
        if (! $prNumber) {
            $prNumber = (int) text('PR number to analyze:');
        }

        if ($this->option('health')) {
            return $this->showHealthScore($analysisService, $repo, $prNumber);
        }

        return $this->showAnalysis($analysisService, $repo, $prNumber);
    }

    private function showAnalysis(PrAnalysisService $service, string $repo, int $prNumber): int
    {
        $this->line("🔍 <comment>Analyzing PR #{$prNumber} in {$repo}...</comment>");

        $analysis = $service->analyzeMergeReadiness($repo, $prNumber);

        if (isset($analysis['error'])) {
            $this->error("❌ {$analysis['error']}");

            return 1;
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($analysis, JSON_PRETTY_PRINT));

            return 0;
        }

        $this->displayInteractiveAnalysis($analysis);

        return 0;
    }

    private function showHealthScore(PrAnalysisService $service, string $repo, int $prNumber): int
    {
        $this->line('🏥 <comment>Calculating PR health score...</comment>');

        $health = $service->getHealthScore($repo, $prNumber);

        if (isset($health['error'])) {
            $this->error("❌ {$health['error']}");

            return 1;
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));

            return 0;
        }

        $this->displayHealthScore($health);

        return 0;
    }

    private function displayInteractiveAnalysis(array $analysis): void
    {
        $pr = $analysis['pr_info'];
        $merge = $analysis['merge_analysis'];
        $code = $analysis['code_analysis'];
        $discussion = $analysis['discussion_analysis'];

        // PR Overview
        $this->newLine();
        $this->line("📋 <info>PR #{$pr['number']}: {$pr['title']}</info>");
        $this->line("👤 Author: <comment>{$pr['author']}</comment>");
        $this->line("📊 State: <comment>{$pr['state']}</comment>".($pr['draft'] ? ' <fg=yellow>(Draft)</>' : ''));

        // Merge Analysis
        $this->newLine();
        $this->line('🔀 <info>Merge Analysis</info>');

        $mergeIcon = $merge['ready_to_merge'] ? '✅' : ($merge['has_conflicts'] ? '❌' : '⚠️');
        $this->line("{$mergeIcon} Status: <comment>{$merge['status_description']}</comment>");

        if ($merge['mergeable'] !== null) {
            $this->line('🎯 Mergeable: '.($merge['mergeable'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        }

        if ($merge['can_rebase']) {
            $this->line('🔄 Can Rebase: <fg=green>Yes</>');
        }

        // Code Analysis
        $this->newLine();
        $this->line('💻 <info>Code Changes</info>');
        $this->line("📁 Files Changed: <comment>{$code['files_changed']}</comment>");
        $this->line("➕ Additions: <fg=green>{$code['additions']}</>");
        $this->line("➖ Deletions: <fg=red>{$code['deletions']}</>");
        $this->line("📊 Total Changes: <comment>{$code['total_changes']}</comment>");
        $this->line('📏 Size: <comment>'.ucfirst($code['change_size']).'</comment>');

        // Discussion Analysis
        $this->newLine();
        $this->line('💬 <info>Discussion</info>');
        $this->line("💭 Comments: <comment>{$discussion['regular_comments']}</comment>");
        $this->line("🔍 Review Comments: <comment>{$discussion['review_comments']}</comment>");
        $this->line("📝 Commits: <comment>{$discussion['commits']}</comment>");

        // Recommendations
        if (! empty($analysis['recommendations'])) {
            $this->newLine();
            $this->line('🎯 <info>Recommendations</info>');

            foreach ($analysis['recommendations'] as $rec) {
                $icon = match ($rec['type']) {
                    'critical' => '🚨',
                    'warning' => '⚠️',
                    'suggestion' => '💡',
                    'success' => '✅',
                    'info' => 'ℹ️',
                    default => '•',
                };

                $color = match ($rec['type']) {
                    'critical' => 'red',
                    'warning' => 'yellow',
                    'suggestion' => 'blue',
                    'success' => 'green',
                    'info' => 'cyan',
                    default => 'white',
                };

                $this->line("{$icon} <fg={$color}>{$rec['message']}</>");
            }
        }
    }

    private function displayHealthScore(array $health): void
    {
        $score = $health['health_score'];
        $grade = $health['grade'];
        $status = $health['status'];

        $this->newLine();
        $this->line('🏥 <info>PR Health Report</info>');
        $this->newLine();

        // Health Score with colored output
        $scoreColor = match ($grade) {
            'A' => 'green',
            'B' => 'green',
            'C' => 'yellow',
            'D' => 'yellow',
            'F' => 'red',
            default => 'white',
        };

        $this->line("📊 Health Score: <fg={$scoreColor}>{$score}/100 (Grade: {$grade})</>");
        $this->line("📈 Status: <fg={$scoreColor}>".ucfirst(str_replace('_', ' ', $status)).'</>');

        $this->newLine();
        $this->displayInteractiveAnalysis($health['analysis']);
    }
}
