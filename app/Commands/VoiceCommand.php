<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ClaudeNarrationService;
use App\Services\VoiceNarrationService;
use App\ValueObjects\NarrationContent;
use App\ValueObjects\SpeechConfiguration;
use Illuminate\Console\Command;
use JordanPartridge\GithubClient\Github;

class VoiceCommand extends Command
{
    protected $signature = 'voice 
                           {type : Content type (issue, pr, repo, commit)}
                           {target : Target identifier (issue number, PR number, etc.)}
                           {--repo= : Repository (owner/repo)}
                           {--claude= : Custom Claude prompt for narration}
                           {--voice=default : Pre-built voice style}
                           {--speed=normal : Speaking speed}
                           {--include-comments : Include comments/reviews}
                           {--include-stats : Include statistics}
                           {--preview : Show text instead of speaking}';

    protected $description = 'Universal voice interface for GitHub content - powered by Claude AI';

    public function __construct(
        private readonly Github $github,
        private readonly VoiceNarrationService $voiceService,
        private readonly ClaudeNarrationService $claudeService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->argument('type');
        $target = $this->argument('target');
        $claudePrompt = $this->option('claude');

        if (! $this->validateType($type)) {
            return 1;
        }

        try {
            $content = $this->fetchContent($type, $target);
            $config = SpeechConfiguration::fromOptions($this->options());

            $narration = $claudePrompt
                ? $this->generateClaudeNarration($content, $claudePrompt)
                : $this->generateTraditionalNarration($content, $config);

            if ($this->option('preview')) {
                $this->displayPreview($narration);
            } else {
                $this->voiceService->speak($narration, $config);
                $this->info('✅ Voice briefing complete!');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ {$e->getMessage()}");

            return 1;
        }
    }

    private function validateType(string $type): bool
    {
        $validTypes = ['issue', 'pr', 'repo', 'commit'];

        if (! in_array($type, $validTypes)) {
            $this->error("❌ Invalid type '{$type}'. Valid types: ".implode(', ', $validTypes));
            $this->line('💡 Examples:');
            $this->line("  voice issue 123 --claude='Explain like a pirate'");
            $this->line("  voice pr 48 --claude='Channel Gordon Ramsay'");
            $this->line("  voice commit abc123 --claude='David Attenborough narration'");

            return false;
        }

        return true;
    }

    private function fetchContent(string $type, string $target): NarrationContent
    {
        $repo = $this->option('repo') ?? $this->detectRepository();
        if (! $repo) {
            throw new \Exception('Repository required. Use --repo=owner/repo');
        }

        [$owner, $repoName] = explode('/', $repo);

        return match ($type) {
            'issue' => $this->fetchIssueContent($owner, $repoName, (int) $target),
            'pr' => $this->fetchPrContent($owner, $repoName, (int) $target),
            'repo' => $this->fetchRepoContent($owner, $repoName),
            'commit' => $this->fetchCommitContent($owner, $repoName, $target),
        };
    }

    private function fetchIssueContent(string $owner, string $repo, int $number): NarrationContent
    {
        $issue = $this->fetchViaGH("repos/{$owner}/{$repo}/issues/{$number}");

        $comments = null;
        if ($this->option('include-comments')) {
            $commentsData = $this->fetchViaGH("repos/{$owner}/{$repo}/issues/{$number}/comments");
            $comments = collect($commentsData);
        }

        return NarrationContent::fromIssue($issue, $comments);
    }

    private function fetchPrContent(string $owner, string $repo, int $number): NarrationContent
    {
        $pr = $this->fetchViaGH("repos/{$owner}/{$repo}/pulls/{$number}");

        $comments = null;
        $reviews = null;

        if ($this->option('include-comments')) {
            $commentsData = $this->fetchViaGH("repos/{$owner}/{$repo}/issues/{$number}/comments");
            $reviewsData = $this->fetchViaGH("repos/{$owner}/{$repo}/pulls/{$number}/reviews");
            $comments = collect($commentsData);
            $reviews = collect($reviewsData);
        }

        return NarrationContent::fromPullRequest($pr, $comments, $reviews);
    }

    private function fetchRepoContent(string $owner, string $repo): NarrationContent
    {
        $repoData = $this->fetchViaGH("repos/{$owner}/{$repo}");

        return new NarrationContent(
            type: 'repository',
            number: 0,
            title: $repoData['name'],
            description: $repoData['description'] ?? 'No description',
            state: $repoData['archived'] ? 'archived' : 'active',
            author: $repoData['owner']['login'],
            metadata: [
                'stars' => $repoData['stargazers_count'] ?? 0,
                'forks' => $repoData['forks_count'] ?? 0,
                'language' => $repoData['language'] ?? 'Unknown',
                'size' => $repoData['size'] ?? 0,
                'open_issues' => $repoData['open_issues_count'] ?? 0,
                'created_at' => $repoData['created_at'] ?? null,
                'updated_at' => $repoData['updated_at'] ?? null,
            ]
        );
    }

    private function fetchCommitContent(string $owner, string $repo, string $sha): NarrationContent
    {
        $commit = $this->fetchViaGH("repos/{$owner}/{$repo}/commits/{$sha}");

        return new NarrationContent(
            type: 'commit',
            number: 0,
            title: $commit['commit']['message'] ?? 'No message',
            description: $commit['commit']['message'] ?? 'No description',
            state: 'committed',
            author: $commit['commit']['author']['name'] ?? 'Unknown',
            metadata: [
                'sha' => $commit['sha'],
                'additions' => $commit['stats']['additions'] ?? 0,
                'deletions' => $commit['stats']['deletions'] ?? 0,
                'total' => $commit['stats']['total'] ?? 0,
                'files_changed' => count($commit['files'] ?? []),
                'date' => $commit['commit']['author']['date'] ?? null,
            ]
        );
    }

    private function fetchViaGH(string $endpoint): array
    {
        $command = "gh api {$endpoint} 2>/dev/null";
        $output = shell_exec($command);

        if (! $output) {
            throw new \Exception("Could not fetch data from GitHub API: {$endpoint}");
        }

        $data = json_decode($output, true);
        if (! $data) {
            throw new \Exception('Invalid JSON response from GitHub API');
        }

        return $data;
    }

    private function generateClaudeNarration(NarrationContent $content, string $prompt): string
    {
        $this->line('🤖 Claude is crafting your custom narration...');

        return $this->claudeService->generateNarration($content, $prompt);
    }

    private function generateTraditionalNarration(NarrationContent $content, SpeechConfiguration $config): string
    {
        // Use traditional voice narrators for pre-built styles
        return $this->voiceService->generateNarration($content, $config);
    }

    private function displayPreview(string $narration): void
    {
        $this->line('');
        $this->line('🎭 VOICE PREVIEW:');
        $this->line('═══════════════════════════════════');
        $this->line($narration);
        $this->line('═══════════════════════════════════');
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
