<?php

namespace App\Commands;

use App\Services\GitHubClientGapTracker;
use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;

class GitHubClientGapAnalysisCommand extends Command
{
    protected $signature = 'github-client:analyze-gaps 
                           {--repo= : Repository to test (owner/repo)}
                           {--pr= : PR number to analyze}
                           {--submit-issues : Automatically submit issues to github-client repo}
                           {--format=visual : Output format (visual, json)}';

    protected $description = 'Analyze github-client capabilities and identify gaps for PR analysis features';

    public function handle(): int
    {
        $repoSpec = $this->option('repo') ?? 'conduit-ui/conduit';
        $prNumber = $this->option('pr') ?? 47;

        [$owner, $repo] = explode('/', $repoSpec);

        info("🔍 Analyzing github-client capabilities for PR #{$prNumber} in {$repoSpec}");
        $this->newLine();

        $tracker = new GitHubClientGapTracker();
        
        try {
            $analysis = $tracker->analyzePrCapabilities($owner, $repo, (int) $prNumber);
            
            if ($this->option('format') === 'json') {
                $this->line(json_encode($analysis, JSON_PRETTY_PRINT));
                return 0;
            }

            $this->displayGapAnalysis($analysis);
            
            // Offer to submit issues
            if ($this->option('submit-issues') || confirm('Submit discovered gaps as issues to github-client repository?')) {
                $this->submitIssues($tracker, $analysis['recommended_issues']);
            }

            return 0;

        } catch (\Exception $e) {
            error("Gap analysis failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function displayGapAnalysis(array $analysis): void
    {
        $this->line('<bg=red;fg=white>                                                              </bg=red;fg=white>');
        $this->line('<bg=red;fg=white>  🔍 GITHUB-CLIENT GAP ANALYSIS REPORT                      </bg=red;fg=white>');
        $this->line('<bg=red;fg=white>                                                              </bg=red;fg=white>');
        $this->newLine();

        // Summary
        $totalGaps = $this->countTotalGaps($analysis['gaps_found']);
        $missingEndpoints = count($analysis['missing_endpoints']);
        $incompleteData = count($analysis['incomplete_data']);

        $this->line('<options=bold>📊 SUMMARY</options>');
        $this->line("• Total gaps identified: <fg=red>{$totalGaps}</fg>");
        $this->line("• Missing endpoints: <fg=red>{$missingEndpoints}</fg>");
        $this->line("• Incomplete data mappings: <fg=yellow>{$incompleteData}</fg>");
        $this->line("• Recommended issues: <fg=blue>" . count($analysis['recommended_issues']) . "</fg>");
        $this->newLine();

        // Detailed gap analysis
        $this->displayDetailedGaps($analysis['gaps_found']);
        
        // Missing endpoints
        if (!empty($analysis['missing_endpoints'])) {
            $this->displayMissingEndpoints($analysis['missing_endpoints']);
        }
        
        // Recommended issues
        if (!empty($analysis['recommended_issues'])) {
            $this->displayRecommendedIssues($analysis['recommended_issues']);
        }
    }

    private function displayDetailedGaps(array $gaps): void
    {
        $this->line('<options=bold>🔍 DETAILED GAP ANALYSIS</options>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach ($gaps as $category => $categoryGaps) {
            if (empty($categoryGaps)) continue;

            $this->line('');
            $this->line("<fg=cyan>📋 " . strtoupper(str_replace('_', ' ', $category)) . ":</fg>");
            
            foreach ($categoryGaps as $gapType => $gapData) {
                if (is_array($gapData)) {
                    $this->line("  🔴 {$gapType}:");
                    
                    if (isset($gapData['missing_fields'])) {
                        foreach ($gapData['missing_fields'] as $field) {
                            $this->line("    • <fg=red>{$field['field']}</fg>: {$field['purpose']}");
                        }
                    } elseif (isset($gapData['error'])) {
                        $this->line("    <fg=red>Error:</fg> {$gapData['error']}");
                        if (isset($gapData['needed_endpoint'])) {
                            $this->line("    <fg=yellow>Needed:</fg> {$gapData['needed_endpoint']}");
                        }
                    } else {
                        $this->line("    " . json_encode($gapData, JSON_PRETTY_PRINT));
                    }
                } else {
                    $this->line("  🔴 {$gapType}: {$gapData}");
                }
            }
        }
        $this->newLine();
    }

    private function displayMissingEndpoints(array $endpoints): void
    {
        $this->line('<options=bold>🚫 MISSING ENDPOINTS</options>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        foreach ($endpoints as $endpoint) {
            $priority = $endpoint['priority'];
            $color = $priority === 'HIGH' ? 'red' : ($priority === 'MEDIUM' ? 'yellow' : 'green');
            
            $this->line("<fg={$color}>🔥 {$priority} PRIORITY</fg>");
            $this->line("   Endpoint: <fg=cyan>{$endpoint['endpoint']}</fg>");
            $this->line("   Purpose: {$endpoint['purpose']}");
            $this->line('');
        }
    }

    private function displayRecommendedIssues(array $issues): void
    {
        $this->line('<options=bold>📝 RECOMMENDED ISSUES</options>');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        foreach ($issues as $index => $issue) {
            $priority = $issue['priority'];
            $color = $priority === 'HIGH' ? 'red' : ($priority === 'MEDIUM' ? 'yellow' : 'green');
            
            $number = $index + 1;
            $this->line("<fg={$color}>#{$number} [{$priority}]</fg> {$issue['title']}");
            $this->line("   {$issue['description']}");
            
            if (isset($issue['endpoint_needed'])) {
                $this->line("   <fg=cyan>Endpoint:</fg> {$issue['endpoint_needed']}");
            }
            
            if (isset($issue['labels'])) {
                $labels = implode(', ', $issue['labels']);
                $this->line("   <fg=gray>Labels:</fg> {$labels}");
            }
            $this->line('');
        }
    }

    private function submitIssues(GitHubClientGapTracker $tracker, array $issues): void
    {
        info('🚀 Submitting issues to github-client repository...');
        $this->newLine();

        $results = $tracker->submitDiscoveredIssues($issues);
        
        $successful = 0;
        $failed = 0;
        
        foreach ($results as $result) {
            if (isset($result['url'])) {
                $this->line("<fg=green>✅</fg> {$result['title']}");
                $this->line("   <fg=cyan>{$result['url']}</fg>");
                $successful++;
            } else {
                $this->line("<fg=red>❌</fg> {$result['title']}");
                $this->line("   <fg=red>Error:</fg> {$result['error']}");
                $failed++;
            }
            $this->line('');
        }

        info("📊 Results: {$successful} submitted, {$failed} failed");
        
        if ($successful > 0) {
            info('🎉 Issues submitted successfully! Track progress at: https://github.com/jordanpartridge/github-client/issues');
        }
    }

    private function countTotalGaps(array $gaps): int
    {
        $total = 0;
        foreach ($gaps as $categoryGaps) {
            if (is_array($categoryGaps)) {
                $total += count($categoryGaps);
            }
        }
        return $total;
    }
}