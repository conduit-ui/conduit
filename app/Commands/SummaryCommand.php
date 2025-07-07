<?php

namespace App\Commands;

use App\Services\ComponentManager;
use App\Services\ContextDetectionService;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;

/**
 * Enhanced summary command showing interactive mode status and contextual guidance
 *
 * Replaces the default Laravel Zero summary to provide better user experience
 * with prominent interactive mode status and actionable next steps.
 */
class SummaryCommand extends Command
{
    protected $signature = 'list {namespace? : The namespace name}
                            {--raw : To output raw command list}
                            {--format=txt : The output format (txt, xml, json, or md)}
                            {--short : To skip describing commands\' arguments}';

    protected $description = 'List commands with enhanced status information';

    protected $hidden = true; // Hide from command list since it's the default

    public function handle(ComponentManager $manager, ContextDetectionService $contextService): int
    {
        // Show standard command list first
        $this->showCommandList();

        // Add context-aware enhanced status section
        $this->showContextAwareStatus($manager, $contextService);

        return Command::SUCCESS;
    }

    protected function showCommandList(): void
    {
        $helper = new DescriptorHelper;
        $helper->describe(
            $this->output,
            $this->getApplication(),
            [
                'format' => $this->option('format'),
                'raw_text' => $this->option('raw'),
                'namespace' => $this->argument('namespace'),
                'short' => $this->option('short'),
            ]
        );
    }

    protected function showContextAwareStatus(ComponentManager $manager, ContextDetectionService $contextService): void
    {
        $context = $contextService->getContext();
        $interactiveMode = $manager->getGlobalSetting('interactive_mode', true);
        $installed = $manager->getInstalled();

        $this->newLine();
        $this->line('─────────────────────────────────────────────────────');

        // Show context if relevant
        if ($context['is_git_repo'] || $context['project_type']) {
            $this->showMinimalContext($context);
            $this->newLine();
        }

        // Interactive Mode Status
        if ($interactiveMode) {
            $this->line('🎛️  <fg=green;options=bold>Interactive Mode: ENABLED</> <fg=gray>(conduit interactive disable to change)</>');
        } else {
            $this->line('🤖 <fg=yellow;options=bold>Interactive Mode: DISABLED</> <fg=gray>(conduit interactive enable to change)</>');
        }

        $this->newLine();

        // Context-aware suggestions
        $this->showContextualSuggestions($context, $installed);

        // Component Status
        if (empty($installed)) {
            $this->line('📦 <fg=cyan;options=bold>Components:</> None installed');
            $this->line('   🔍 <fg=white>Discover:</> conduit components discover');
        } else {
            $componentCount = count($installed);
            $componentNames = implode(', ', array_keys($installed));
            $this->line("📦 <fg=green;options=bold>Components:</> {$componentCount} installed <fg=gray>({$componentNames})</>");
            $this->line('   🎛️  <fg=white>Manage:</> conduit components');
        }

        // Quick Tips
        $this->newLine();
        $this->line('<fg=gray>💡 Tip:</> conduit context <fg=gray>shows detailed project information</>');
    }

    private function showMinimalContext(array $context): void
    {
        $parts = [];

        if ($context['is_git_repo']) {
            $git = $context['git'];
            if ($git['is_github']) {
                $parts[] = "<fg=cyan>{$git['github_owner']}/{$git['github_repo']}</>";
            } else {
                $parts[] = '<fg=cyan>Git repo</>';
            }

            if ($git['current_branch']) {
                $parts[] = "on <fg=yellow>{$git['current_branch']}</>";
            }
        }

        if ($context['project_type']) {
            $parts[] = '<fg=green>'.ucfirst($context['project_type']).' project</>';
        }

        if (! empty($parts)) {
            $this->line('📍 '.implode(' • ', $parts));
        }
    }

    private function showContextualSuggestions(array $context, array $installed): void
    {
        $suggestions = [];

        // Git/GitHub suggestions
        if (isset($installed['github-zero'])) {
            if ($context['is_git_repo'] && $context['git']['is_github']) {
                // In a GitHub repo - show repo-specific commands
                $suggestions[] = '   📂 <fg=white>Repos:</> conduit repos';
                // Future: issues, prs when available
                // Don't show clone when already in a repo
            } else {
                // Not in a GitHub repo - show general commands
                $suggestions[] = '   📂 <fg=white>Repos:</> conduit repos';
                $suggestions[] = '   📥 <fg=white>Clone:</> conduit clone';
            }
        } elseif ($context['is_git_repo'] && $context['git']['is_github']) {
            $suggestions[] = '   💡 <fg=yellow>Install github-zero for GitHub integration</>';
        }

        // Laravel suggestions
        if ($context['project_type'] === 'laravel') {
            // Future: Add Laravel-specific suggestions when components are available
        }

        if (! empty($suggestions)) {
            $this->line('<fg=cyan;options=bold>Contextual Commands:</>');
            foreach ($suggestions as $suggestion) {
                $this->line($suggestion);
            }
            $this->newLine();
        }
    }
}
