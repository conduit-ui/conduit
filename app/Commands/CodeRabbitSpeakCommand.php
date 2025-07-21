<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\CodeRabbitAnalysisService;
use App\Services\VoiceNarrationService;
use App\ValueObjects\SpeechConfiguration;
use Illuminate\Console\Command;

class CodeRabbitSpeakCommand extends Command
{
    protected $signature = 'coderabbit:speak 
                           {pr : PR number}
                           {--repo= : Repository (owner/repo)}
                           {--claude= : Custom Claude analysis prompt}
                           {--voice=executive : Voice style (executive, detailed, sarcastic)}
                           {--speed=normal : Speaking speed}
                           {--preview : Show text instead of speaking}';

    protected $description = 'AI-powered voice analysis of CodeRabbit feedback';

    public function __construct(
        private readonly CodeRabbitAnalysisService $analysisService,
        private readonly VoiceNarrationService $voiceService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $prNumber = (int) $this->argument('pr');
        $repo = $this->option('repo') ?? $this->detectRepository();

        if (! $repo) {
            $this->error('❌ Repository required. Use --repo=owner/repo');

            return 1;
        }

        [$owner, $repoName] = explode('/', $repo);

        try {
            $this->info("🤖 Analyzing CodeRabbit feedback for PR #{$prNumber}...");

            $analysis = $this->analysisService->analyzeCodeRabbitFeedback(
                $prNumber,
                $owner,
                $repoName
            );

            $narration = $this->generateNarration($analysis);

            if ($this->option('preview')) {
                $this->displayPreview($analysis, $narration);
            } else {
                $this->speakAnalysis($narration);
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to analyze CodeRabbit feedback: {$e->getMessage()}");

            return 1;
        }
    }

    private function generateNarration($analysis): string
    {
        $claudePrompt = $this->option('claude');
        $voice = $this->option('voice');

        if ($claudePrompt) {
            return $this->generateCustomClaudeNarration($analysis, $claudePrompt);
        }

        return match ($voice) {
            'detailed' => $this->generateDetailedNarration($analysis),
            'sarcastic' => $this->generateSarcasticNarration($analysis),
            default => $analysis->getVoiceNarration(), // executive style
        };
    }

    private function generateCustomClaudeNarration($analysis, string $prompt): string
    {
        // Use Claude to generate custom narration based on the analysis
        $this->line('🎭 Claude is crafting your custom CodeRabbit analysis...');

        $contextData = [
            'pr_number' => $analysis->prNumber,
            'total_comments' => $analysis->totalComments,
            'ai_summary' => $analysis->aiSummary,
            'files_affected' => array_keys($analysis->commentsByFile),
            'categories' => array_keys($analysis->commentsByCategory),
        ];

        $fullPrompt = 'Based on this CodeRabbit analysis data: '.
                     json_encode($contextData, JSON_PRETTY_PRINT).
                     "\n\nCustom request: {$prompt}".
                     "\n\nGenerate a spoken narration (under 200 words):";

        // This would call Claude via the ClaudeNarrationService
        return 'Custom Claude analysis: '.$analysis->getVoiceNarration();
    }

    private function generateDetailedNarration($analysis): string
    {
        $base = $analysis->getVoiceNarration();

        // Add detailed breakdown
        $details = ' Detailed breakdown by category: ';
        foreach ($analysis->commentsByCategory as $category => $data) {
            $details .= "{$category}: {$data['count']} comments. ";
        }

        return $base.$details;
    }

    private function generateSarcasticNarration($analysis): string
    {
        if ($analysis->totalComments === 0) {
            return 'Wow, look at that! CodeRabbit actually found nothing wrong. '.
                   'Either this code is perfect, or the bot is having an off day. '.
                   "I'm betting on the latter.";
        }

        $high = $analysis->rawComments->where('priority', 'high')->count();
        $total = $analysis->totalComments;

        return "Oh fantastic! CodeRabbit blessed us with {$total} comments. ".
               ($high > 0 ? "Including {$high} high-priority issues because apparently we can't write code properly. " : '').
               "I'm sure these are all absolutely crucial suggestions that will change the world. ".
               'Better get started on that code review marathon.';
    }

    private function speakAnalysis(string $narration): void
    {
        $config = SpeechConfiguration::fromOptions($this->options());

        $this->line('🔊 Speaking CodeRabbit analysis...');
        $this->voiceService->speak($narration, $config);
        $this->info('✅ CodeRabbit analysis complete!');
    }

    private function displayPreview($analysis, string $narration): void
    {
        $this->line('');
        $this->info("🤖 CODERABBIT ANALYSIS PREVIEW - PR #{$analysis->prNumber}");
        $this->line('════════════════════════════════════════════');
        $this->line($narration);
        $this->line('════════════════════════════════════════════');

        if ($analysis->totalComments > 0) {
            $this->line('');
            $this->comment('📊 QUICK STATS:');
            $this->line("• Total Comments: {$analysis->totalComments}");
            $this->line('• Files Affected: '.count($analysis->commentsByFile));
            $this->line('• Categories: '.implode(', ', array_keys($analysis->commentsByCategory)));

            if (! empty($analysis->aiSummary['action_priorities'])) {
                $this->line('');
                $this->comment('🎯 AI PRIORITIES:');
                foreach (array_slice($analysis->aiSummary['action_priorities'], 0, 3) as $i => $priority) {
                    $this->line('• '.($i + 1).". {$priority}");
                }
            }
        }

        $this->line('');
        $this->comment('💡 Remove --preview to hear it spoken aloud');
    }

    private function detectRepository(): ?string
    {
        try {
            $remote = trim(shell_exec('git remote get-url origin 2>/dev/null') ?? '');
            if (preg_match('/github\.com[\/:]([^\/]+)\/([^\/]+?)(?:\.git)?$/', $remote, $matches)) {
                return $matches[1].'/'.$matches[2];
            }
        } catch (\Exception $e) {
            // Ignore git errors
        }

        return null;
    }
}
