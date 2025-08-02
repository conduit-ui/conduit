<?php

namespace App\Commands;

use App\Services\PrAnalysisService;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

class PrAnalyzeCommand extends Command
{
    protected $signature = 'pr:analyze 
                           {number : PR number}
                           {--repo= : Repository (owner/repo)}
                           {--format=visual : Output format (visual, json, summary)}
                           {--include-diff : Include diff analysis}
                           {--include-ai-insights : Include AI-generated insights}';

    protected $description = 'Comprehensive AI-powered PR analysis with rich metadata visualization';

    public function handle(): int
    {
        $prNumber = (int) $this->argument('number');
        $repoSpec = $this->option('repo') ?? $this->detectRepository();

        if (! $repoSpec) {
            error('Could not detect repository. Use --repo=owner/repo');

            return 1;
        }

        [$owner, $repo] = explode('/', $repoSpec);

        try {
            $analysisService = new PrAnalysisService;
            $analysis = $analysisService->analyzeComprehensive($owner, $repo, $prNumber, [
                'include_diff' => $this->option('include-diff'),
                'include_ai_insights' => $this->option('include-ai-insights'),
            ]);

            return match ($this->option('format')) {
                'json' => $this->outputJson($analysis),
                'summary' => $this->outputSummary($analysis),
                default => $this->outputVisual($analysis)
            };

        } catch (\Exception $e) {
            error("Analysis failed: {$e->getMessage()}");

            return 1;
        }
    }

    private function outputVisual(array $analysis): int
    {
        $pr = $analysis['pr'];
        $meta = $analysis['metadata'];
        $intelligence = $analysis['intelligence'];

        // Header with PR overview
        $this->displayHeader($pr, $meta);

        // Core metrics dashboard
        $this->displayMetricsDashboard($meta, $intelligence);

        // Review analysis with AI insights
        $this->displayReviewAnalysis($analysis['reviews'], $intelligence);

        // Technical health check
        $this->displayTechnicalHealth($analysis['checks'], $analysis['conflicts']);

        // AI-powered recommendations
        $this->displayAIRecommendations($intelligence);

        // Merge readiness assessment
        $this->displayMergeReadiness($analysis['mergeability']);

        return 0;
    }

    private function displayHeader(array $pr, array $meta): void
    {
        $this->line('');
        $this->line('<bg=blue;fg=white>                                                              </bg=blue;fg=white>');
        $this->line('<bg=blue;fg=white>  🔍 COMPREHENSIVE PR ANALYSIS                               </bg=blue;fg=white>');
        $this->line('<bg=blue;fg=white>                                                              </bg=blue;fg=white>');
        $this->line('');

        // PR Title and basic info
        $this->line("<options=bold>#{$pr['number']}: {$pr['title']}</options>");
        $this->line("<fg=gray>by {$pr['author']} • {$pr['state']} • Updated {$meta['updated_relative']}</fg>");
        $this->line('');

        // Quick stats bar
        $stats = [
            "📝 {$meta['total_commits']} commits",
            "📊 +{$meta['additions']} -{$meta['deletions']}",
            "📁 {$meta['changed_files']} files",
            "💬 {$meta['discussion_count']} comments",
            "📋 {$meta['review_comments']} reviews",
        ];
        $this->line('<fg=cyan>'.implode(' • ', $stats).'</fg>');
        $this->line('');
    }

    private function displayMetricsDashboard(array $meta, array $intelligence): void
    {
        $this->line('<options=bold>📊 METRICS DASHBOARD</options>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Create metrics grid
        $leftColumn = [
            $this->formatMetric('📈 Size Impact', $this->calculateSizeImpact($meta), $this->getSizeColor($meta)),
            $this->formatMetric('🎯 Focus Score', $intelligence['focus_score'].'/10', $this->getScoreColor($intelligence['focus_score'])),
            $this->formatMetric('🔄 Change Velocity', $meta['velocity'], 'cyan'),
            $this->formatMetric('📝 Documentation', $intelligence['docs_impact'], $this->getImpactColor($intelligence['docs_impact'])),
        ];

        $rightColumn = [
            $this->formatMetric('🧪 Test Coverage', $meta['test_coverage'] ?? 'Unknown', 'yellow'),
            $this->formatMetric('⚡ Performance Impact', $intelligence['performance_risk'], $this->getRiskColor($intelligence['performance_risk'])),
            $this->formatMetric('🔒 Security Risk', $intelligence['security_risk'], $this->getRiskColor($intelligence['security_risk'])),
            $this->formatMetric('🎨 Code Quality', $intelligence['quality_score'].'/10', $this->getScoreColor($intelligence['quality_score'])),
        ];

        // Display side by side
        for ($i = 0; $i < count($leftColumn); $i++) {
            $left = str_pad($leftColumn[$i], 35);
            $right = $rightColumn[$i] ?? '';
            $this->line("  {$left}  {$right}");
        }
        $this->line('');
    }

    private function displayReviewAnalysis(array $reviews, array $intelligence): void
    {
        $this->line('<options=bold>🔍 REVIEW ANALYSIS</options>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Review breakdown
        if (! empty($reviews['coderabbit']) && ($reviews['coderabbit']['found'] ?? false)) {
            $this->displayCodeRabbitAnalysis($reviews['coderabbit']);
        }

        // Human reviews
        if (! empty($reviews['human'])) {
            $this->line('<fg=green>👥 HUMAN REVIEWS:</fg>');
            foreach ($reviews['human'] as $review) {
                $icon = $this->getReviewIcon($review['state']);
                $this->line("  {$icon} {$review['reviewer']} - {$review['state']} ({$review['comments']} comments)");
            }
            $this->line('');
        }

        // AI insights on review patterns
        if (! empty($intelligence['review_insights'])) {
            $this->line('<fg=blue>🤖 AI REVIEW INSIGHTS:</fg>');
            foreach ($intelligence['review_insights'] as $insight) {
                $this->line("  💡 {$insight}");
            }
            $this->line('');
        }
    }

    private function displayCodeRabbitAnalysis(array $coderabbit): void
    {
        $this->line('<fg=yellow>🤖 CODERABBIT ANALYSIS:</fg>');

        // Summary stats
        $total = $coderabbit['actionable'] + $coderabbit['nitpick'] + $coderabbit['outside_range'];
        $this->line("  📋 Total Issues: {$total} ({$coderabbit['actionable']} actionable, {$coderabbit['nitpick']} nitpicks)");

        // Category breakdown with progress bars
        $categories = [
            '🚨 Critical Issues' => ['count' => $coderabbit['actionable'], 'color' => 'red'],
            '🔧 Nitpicks' => ['count' => $coderabbit['nitpick'], 'color' => 'yellow'],
            '📍 Out of Range' => ['count' => $coderabbit['outside_range'], 'color' => 'gray'],
        ];

        foreach ($categories as $label => $data) {
            $bar = $this->createProgressBar($data['count'], $total, 20);
            $this->line("  {$label}: <fg={$data['color']}>{$bar}</fg> {$data['count']}");
        }

        // Top actionable items
        if (! empty($coderabbit['top_issues'])) {
            $this->line('');
            $this->line('  <fg=red>🎯 TOP ACTIONABLE ITEMS:</fg>');
            foreach (array_slice($coderabbit['top_issues'], 0, 3) as $issue) {
                $this->line("    • {$issue}");
            }
        }
        $this->line('');
    }

    private function displayTechnicalHealth(array $checks, array $conflicts): void
    {
        $this->line('<options=bold>⚙️ TECHNICAL HEALTH</options>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // CI/CD Status
        $this->line('<fg=green>🔄 CI/CD PIPELINE:</fg>');
        foreach ($checks as $check) {
            $icon = $check['status'] === 'success' ? '✅' : ($check['status'] === 'failure' ? '❌' : '⏳');
            $duration = $check['duration'] ? " ({$check['duration']})" : '';
            $this->line("  {$icon} {$check['name']}{$duration}");
        }
        $this->line('');

        // Merge conflicts
        if (! empty($conflicts)) {
            $this->line('<fg=red>⚠️ MERGE CONFLICTS:</fg>');
            foreach ($conflicts as $conflict) {
                $this->line("  🔴 {$conflict['file']} ({$conflict['lines']} lines)");
            }
        } else {
            $this->line('<fg=green>✅ No merge conflicts detected</fg>');
        }
        $this->line('');
    }

    private function displayAIRecommendations(array $intelligence): void
    {
        $this->line('<options=bold>🤖 AI RECOMMENDATIONS</options>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Prioritized actions
        if (! empty($intelligence['priority_actions'])) {
            $this->line('<fg=red>🎯 PRIORITY ACTIONS:</fg>');
            foreach ($intelligence['priority_actions'] as $action) {
                $this->line("  🔥 {$action}");
            }
            $this->line('');
        }

        // Quick wins
        if (! empty($intelligence['quick_wins'])) {
            $this->line('<fg=green>⚡ QUICK WINS:</fg>');
            foreach ($intelligence['quick_wins'] as $win) {
                $this->line("  ✨ {$win}");
            }
            $this->line('');
        }

        // Agent commands
        if (! empty($intelligence['agent_commands'])) {
            $this->line('<fg=blue>🤖 AGENT COMMANDS:</fg>');
            foreach ($intelligence['agent_commands'] as $cmd) {
                $this->line("  <fg=cyan>$</fg> {$cmd}");
            }
            $this->line('');
        }
    }

    private function displayMergeReadiness(array $mergeability): void
    {
        $this->line('<options=bold>🚀 MERGE READINESS</options>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Overall score with visual indicator
        $score = $mergeability['confidence_score'];
        $color = $score >= 8 ? 'green' : ($score >= 6 ? 'yellow' : 'red');
        $emoji = $score >= 8 ? '🎉' : ($score >= 6 ? '⚠️' : '🚫');

        $this->line("<fg={$color}>{$emoji} OVERALL SCORE: {$score}/10 ({$mergeability['status']})</fg>");
        $this->line('');

        // Readiness checklist
        $checklist = [
            'All checks passing' => $mergeability['checks_pass'],
            'Required reviews' => $mergeability['reviews_complete'],
            'No conflicts' => $mergeability['no_conflicts'],
            'Branch up-to-date' => $mergeability['up_to_date'],
            'Quality threshold met' => $mergeability['quality_pass'],
        ];

        foreach ($checklist as $item => $status) {
            $icon = $status ? '✅' : '❌';
            $this->line("  {$icon} {$item}");
        }
        $this->line('');

        // Final recommendation
        $recommendation = $this->generateMergeRecommendation($mergeability);
        $this->line("<options=bold>{$recommendation}</options>");
        $this->line('');
    }

    // Helper methods for formatting and calculations
    private function formatMetric(string $label, string $value, string $color): string
    {
        return "{$label}: <fg={$color}>{$value}</fg>";
    }

    private function createProgressBar(int $current, int $total, int $width): string
    {
        if ($total === 0) {
            return str_repeat('░', $width);
        }

        $filled = (int) round(($current / $total) * $width);

        return str_repeat('█', $filled).str_repeat('░', $width - $filled);
    }

    private function calculateSizeImpact(array $meta): string
    {
        $total = (int) ($meta['additions'] ?? 0) + (int) ($meta['deletions'] ?? 0);

        return match (true) {
            $total < 100 => 'Small',
            $total < 500 => 'Medium',
            $total < 2000 => 'Large',
            default => 'XL'
        };
    }

    private function getSizeColor(array $meta): string
    {
        $total = (int) ($meta['additions'] ?? 0) + (int) ($meta['deletions'] ?? 0);

        return match (true) {
            $total < 100 => 'green',
            $total < 500 => 'yellow',
            default => 'red'
        };
    }

    private function getScoreColor(float $score): string
    {
        return match (true) {
            $score >= 8 => 'green',
            $score >= 6 => 'yellow',
            default => 'red'
        };
    }

    private function getRiskColor(string $risk): string
    {
        return match ($risk) {
            'Low' => 'green',
            'Medium' => 'yellow',
            'High' => 'red',
            default => 'gray'
        };
    }

    private function getImpactColor(string $impact): string
    {
        return match ($impact) {
            'Positive' => 'green',
            'Neutral' => 'yellow',
            'Negative' => 'red',
            default => 'gray'
        };
    }

    private function getReviewIcon(string $state): string
    {
        return match ($state) {
            'APPROVED' => '✅',
            'CHANGES_REQUESTED' => '🔴',
            'COMMENTED' => '💬',
            default => '⏳'
        };
    }

    private function generateMergeRecommendation(array $mergeability): string
    {
        $score = $mergeability['confidence_score'];

        return match (true) {
            $score >= 9 => '🎉 READY TO MERGE - Excellent quality, all checks pass',
            $score >= 7 => '✅ MERGE WITH CONFIDENCE - Minor issues, safe to proceed',
            $score >= 5 => '⚠️ MERGE WITH CAUTION - Address key issues first',
            default => '🚫 DO NOT MERGE - Critical issues must be resolved'
        };
    }

    private function detectRepository(): ?string
    {
        // Implement git remote detection logic
        $remoteUrl = trim(shell_exec('git remote get-url origin 2>/dev/null') ?? '');

        if (preg_match('/github\.com[\/:]([^\/]+)\/(.+?)(?:\.git)?$/', $remoteUrl, $matches)) {
            return "{$matches[1]}/{$matches[2]}";
        }

        return null;
    }

    private function outputJson(array $analysis): int
    {
        $this->line(json_encode($analysis, JSON_PRETTY_PRINT));

        return 0;
    }

    private function outputSummary(array $analysis): int
    {
        $pr = $analysis['pr'];
        $meta = $analysis['metadata'];
        $mergeability = $analysis['mergeability'];

        $this->line("PR #{$pr['number']}: {$pr['title']}");
        $this->line("Score: {$mergeability['confidence_score']}/10 ({$mergeability['status']})");
        $this->line("Size: +{$meta['additions']} -{$meta['deletions']} in {$meta['changed_files']} files");
        $this->line("Reviews: {$meta['review_comments']} comments, {$meta['discussion_count']} discussions");

        return 0;
    }
}
