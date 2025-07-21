<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;
use JordanPartridge\GithubClient\Github;

class PrsSpeakCommand extends Command
{
    protected $signature = 'prs:speak 
                           {number : PR number to speak}
                           {--repo= : Repository (owner/repo)}
                           {--voice=default : Voice style (default, dramatic, sarcastic, coach, robot, reviewer)}
                           {--speed=normal : Speaking speed (slow, normal, fast)}
                           {--include-stats : Include PR statistics}';

    protected $description = 'Get a vocal rundown of a GitHub Pull Request';

    protected Github $github;

    public function __construct(Github $github)
    {
        parent::__construct();
        $this->github = $github;
    }

    public function handle(): int
    {
        $prNumber = (int) $this->argument('number');
        $repo = $this->option('repo') ?? $this->detectRepository();
        $voice = $this->option('voice');
        $speed = $this->option('speed');
        $includeStats = $this->option('include-stats');

        if (! $repo) {
            $this->error('❌ Repository is required. Use --repo=owner/repo or run from a git repository.');

            return 1;
        }

        [$owner, $repoName] = explode('/', $repo);

        try {
            $this->info("🎤 Getting vocal rundown of PR #{$prNumber}...");

            $pr = $this->fetchPrViaGH($owner, $repoName, $prNumber);

            $speech = $this->generatePrSpeech($pr, $voice, $includeStats);
            $this->speakPr($speech, $speed);

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to get PR: {$e->getMessage()}");

            return 1;
        }
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

    private function fetchPrViaGH(string $owner, string $repo, int $prNumber): array
    {
        $escapedOwner = escapeshellarg($owner);
        $escapedRepo = escapeshellarg($repo);
        $escapedNumber = escapeshellarg((string) $prNumber);

        $command = "gh api repos/{$escapedOwner}/{$escapedRepo}/pulls/{$escapedNumber} 2>/dev/null";
        $output = shell_exec($command);

        if (! $output) {
            throw new \Exception('Could not fetch PR via GitHub CLI');
        }

        $pr = json_decode($output, true);
        if (! $pr) {
            throw new \Exception('Invalid JSON response from GitHub CLI');
        }

        return $pr;
    }

    private function generatePrSpeech(array $pr, string $voice, bool $includeStats): string
    {
        $title = $pr['title'];
        $body = $pr['body'] ?? 'No description provided';
        $state = $pr['state'];
        $number = $pr['number'];
        $author = $pr['user']['login'] ?? 'Unknown';
        $draft = $pr['draft'] ?? false;
        $mergeable = $pr['mergeable'] ?? null;
        $additions = $pr['additions'] ?? 0;
        $deletions = $pr['deletions'] ?? 0;
        $changedFiles = $pr['changed_files'] ?? 0;
        $commits = $pr['commits'] ?? 0;
        $comments = $pr['comments'] ?? 0;
        $reviewComments = $pr['review_comments'] ?? 0;

        // Truncate body for speech
        $shortBody = strlen($body) > 200 ? substr($body, 0, 200).'... and more details' : $body;
        $shortBody = strip_tags($shortBody); // Remove markdown
        $shortBody = preg_replace('/\r?\n/', ' ', $shortBody); // Remove line breaks

        return match ($voice) {
            'dramatic' => $this->dramaticPrVoice($number, $title, $shortBody, $state, $author, $draft, $mergeable, $additions, $deletions),
            'sarcastic' => $this->sarcasticPrVoice($number, $title, $shortBody, $state, $author, $draft, $changedFiles, $commits),
            'coach' => $this->coachPrVoice($number, $title, $shortBody, $state, $author, $additions, $deletions, $changedFiles),
            'robot' => $this->robotPrVoice($number, $title, $shortBody, $state, $additions, $deletions, $changedFiles, $commits),
            'reviewer' => $this->reviewerPrVoice($number, $title, $shortBody, $state, $mergeable, $comments, $reviewComments, $changedFiles),
            default => $this->defaultPrVoice($number, $title, $shortBody, $state, $author, $includeStats ? [$additions, $deletions, $changedFiles, $commits] : null),
        };
    }

    private function defaultPrVoice(int $number, string $title, string $body, string $state, string $author, ?array $stats): string
    {
        $speech = "Pull request number {$number}. {$title}. ";
        $speech .= "Status: {$state}. ";
        $speech .= "Created by {$author}. ";

        if ($stats) {
            [$additions, $deletions, $changedFiles, $commits] = $stats;
            $speech .= "Statistics: {$additions} additions, {$deletions} deletions, {$changedFiles} files changed, {$commits} commits. ";
        }

        $speech .= "Description: {$body}";

        return $speech;
    }

    private function dramaticPrVoice(int $number, string $title, string $body, string $state, string $author, bool $draft, ?bool $mergeable, int $additions, int $deletions): string
    {
        $urgency = $draft ? 'BEWARE! This is but a draft! ' : '';
        $mergeStatus = match ($mergeable) {
            true => 'The path to merge is clear!',
            false => 'DANGER! Merge conflicts block the way!',
            default => 'The merge-ability remains a mystery!'
        };

        $impact = $additions + $deletions > 1000 ? 'MASSIVE CHANGES DETECTED! ' : '';
        $stateText = $state === 'open' ? 'awaits judgment' : 'has been decided';

        return "{$urgency}{$impact}Behold! Pull request number {$number}! {$title}! ".
               "This epic contribution {$stateText} and was forged by the developer {$author}. ".
               "{$mergeStatus} ".
               "The scope of changes: {$additions} lines added, {$deletions} lines removed! ".
               "The quest details: {$body}. ".
               'Will this code be worthy of the main branch? The reviewers must decide!';
    }

    private function sarcasticPrVoice(int $number, string $title, string $body, string $state, string $author, bool $draft, int $changedFiles, int $commits): string
    {
        $draftComment = $draft ? "Oh, and it's still a draft. How... responsible." : '';
        $sizeComment = $changedFiles > 20 ? "Because touching {$changedFiles} files at once is totally a great idea." : '';
        $commitComment = $commits > 10 ? "With a whopping {$commits} commits. Someone clearly believes in atomic changes." : '';

        $stateComment = $state === 'open'
            ? 'Still sitting there, waiting for someone to care'
            : 'Somehow this actually got merged';

        return "Oh fantastic, pull request number {$number}. {$title}. ".
               "{$stateComment}. ".
               "Our productive friend {$author} has blessed us with this contribution. {$draftComment} ".
               "{$sizeComment} {$commitComment} ".
               "The profound description reads: {$body}. ".
               "I'm sure the reviewers are just dying to look at this masterpiece.";
    }

    private function coachPrVoice(int $number, string $title, string $body, string $state, string $author, int $additions, int $deletions, int $changedFiles): string
    {
        $motivation = $state === 'open'
            ? 'Time to review this beast and ship it!'
            : 'Another successful deployment in the books!';

        $sizeEncouragement = $additions + $deletions > 500
            ? 'Big changes, big impact! That takes courage!'
            : 'Clean, focused changes - I love it!';

        return "Alright team, let's dive into pull request {$number}! {$title}! ".
               "{$motivation} ".
               "Our champion {$author} stepped up with some solid work here. ".
               "{$sizeEncouragement} We're looking at {$changedFiles} files touched. ".
               "Here's what they're bringing to the table: {$body}. ".
               "Remember, every review makes the codebase stronger! Let's get this shipped!";
    }

    private function robotPrVoice(int $number, string $title, string $body, string $state, int $additions, int $deletions, int $changedFiles, int $commits): string
    {
        return 'ANALYZING PULL REQUEST DATA. '.
               "PULL REQUEST NUMBER: {$number}. ".
               "TITLE: {$title}. ".
               "CURRENT STATE: {$state}. ".
               "STATISTICAL ANALYSIS: {$additions} LINES ADDED, {$deletions} LINES REMOVED, {$changedFiles} FILES MODIFIED, {$commits} COMMITS DETECTED. ".
               "DESCRIPTION ANALYSIS: {$body}. ".
               'END OF PULL REQUEST BRIEFING. AWAITING REVIEWER INPUT.';
    }

    private function reviewerPrVoice(int $number, string $title, string $body, string $state, ?bool $mergeable, int $comments, int $reviewComments, int $changedFiles): string
    {
        $mergeCheck = match ($mergeable) {
            true => 'Looking good for merge.',
            false => "Hold up - we've got conflicts to resolve.",
            default => 'Merge status is unclear - needs investigation.'
        };

        $reviewActivity = ($comments + $reviewComments) > 5
            ? "Lots of discussion happening - {$comments} general comments, {$reviewComments} code review comments."
            : 'Quiet so far - minimal review activity.';

        return "Pull request {$number} review time. {$title}. ".
               "Status check: {$state}. {$mergeCheck} ".
               "{$reviewActivity} ".
               "We're looking at {$changedFiles} files in this change. ".
               "Author's description: {$body}. ".
               "Time to dive into the code and see what we're working with.";
    }

    private function speakPr(string $speech, string $speed): void
    {
        $escapedSpeech = escapeshellarg($speech);

        $this->line('🔊 Speaking PR...');

        if (PHP_OS_FAMILY === 'Darwin') {
            // macOS - use say command (for the superior beings)
            $rate = match ($speed) {
                'slow' => 100,
                'fast' => 200,
                default => 140, // Slower default for better comprehension
            };
            $command = "say -r {$rate} {$escapedSpeech}";
            shell_exec($command);

        } elseif (PHP_OS_FAMILY === 'Windows') {
            // Windows - troll the peasants
            $this->warn('🪟 Detected Windows... preparing suboptimal PR experience...');
            sleep(2);

            $trollMessage = 'Why are you reviewing PRs on Windows? Real developers use real operating systems. '.
                           "Anyway, here's your PR briefing from the inferior platform: ".
                           $speech;

            $trollEscaped = escapeshellarg($trollMessage);

            $rate = match ($speed) {
                'slow' => -4,
                'fast' => 0,
                default => -2,
            };

            $psCommand = 'Add-Type -AssemblyName System.Speech; '.
                        "\\$speak = New-Object System.Speech.Synthesis.SpeechSynthesizer; ".
                        "\\$speak.Rate = {$rate}; ".
                        "\\$speak.Speak({$trollEscaped}); ".
                        "\\$speak.Dispose()";

            shell_exec("powershell -Command \"{$psCommand}\"");

        } elseif (PHP_OS_FAMILY === 'Linux') {
            // Linux - try espeak, festival, or spd-say
            if (shell_exec('which espeak 2>/dev/null')) {
                $speedFlag = match ($speed) {
                    'slow' => '-s 120',
                    'fast' => '-s 200',
                    default => '-s 160',
                };
                shell_exec("espeak {$speedFlag} {$escapedSpeech}");

            } elseif (shell_exec('which spd-say 2>/dev/null')) {
                $speedFlag = match ($speed) {
                    'slow' => '-r -50',
                    'fast' => '-r +50',
                    default => '',
                };
                shell_exec("spd-say {$speedFlag} {$escapedSpeech}");

            } elseif (shell_exec('which festival 2>/dev/null')) {
                shell_exec("echo {$escapedSpeech} | festival --tts");

            } else {
                $this->warn('⚠️  No text-to-speech engine found on Linux.');
                $this->line('💡 Install: sudo apt-get install espeak');
                $this->displayTextFallback($speech);

                return;
            }

        } else {
            $this->warn('⚠️  Text-to-speech not supported on this platform.');
            $this->displayTextFallback($speech);

            return;
        }

        $this->info('✅ PR briefing complete!');
    }

    private function displayTextFallback(string $speech): void
    {
        $this->line('📝 Text version:');
        $this->line('═══════════════');
        $this->line($speech);
        $this->line('═══════════════');
    }
}
