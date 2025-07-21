<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;
use JordanPartridge\GithubClient\Github;

class IssuesSpeakCommand extends Command
{
    protected $signature = 'issues:speak 
                           {number : Issue number to speak}
                           {--repo= : Repository (owner/repo)}
                           {--voice=default : Voice style (default, dramatic, sarcastic, coach, robot)}
                           {--speed=normal : Speaking speed (slow, normal, fast)}
                           {--claude= : Custom Claude prompt for narration style}
                           {--include-comments : Include comments summary}';

    protected $description = 'Get a vocal rundown of a GitHub issue';

    protected Github $github;

    public function __construct(Github $github)
    {
        parent::__construct();
        $this->github = $github;
    }

    public function handle(): int
    {
        $issueNumber = (int) $this->argument('number');
        $repo = $this->option('repo') ?? $this->detectRepository();
        $voice = $this->option('voice');
        $speed = $this->option('speed');

        if (! $repo) {
            $this->error('❌ Repository is required. Use --repo=owner/repo or run from a git repository.');

            return 1;
        }

        [$owner, $repoName] = explode('/', $repo);

        try {
            $this->info("🎤 Getting vocal rundown of issue #{$issueNumber}...");

            // Use GitHub CLI as fallback since github-client issues API is broken
            $issue = $this->fetchIssueViaGH($owner, $repoName, $issueNumber);

            $speech = $this->generateSpeech($issue, $voice);
            $this->speakIssue($speech, $speed);

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to get issue: {$e->getMessage()}");

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

    private function fetchIssueViaGH(string $owner, string $repo, int $issueNumber): array
    {
        $escapedOwner = escapeshellarg($owner);
        $escapedRepo = escapeshellarg($repo);
        $escapedNumber = escapeshellarg((string) $issueNumber);

        $command = "gh api repos/{$escapedOwner}/{$escapedRepo}/issues/{$escapedNumber} 2>/dev/null";
        $output = shell_exec($command);

        if (! $output) {
            throw new \Exception('Could not fetch issue via GitHub CLI');
        }

        $issue = json_decode($output, true);
        if (! $issue) {
            throw new \Exception('Invalid JSON response from GitHub CLI');
        }

        return $issue;
    }

    private function generateSpeech(array $issue, string $voice): string
    {
        $title = $issue['title'];
        $body = $issue['body'] ?? 'No description provided';
        $state = $issue['state'];
        $number = $issue['number'];
        $author = $issue['user']['login'] ?? 'Unknown';
        $labels = collect($issue['labels'] ?? [])->pluck('name')->implode(', ');
        $assignees = collect($issue['assignees'] ?? [])->pluck('login')->implode(', ');

        // Truncate body for speech
        $shortBody = strlen($body) > 200 ? substr($body, 0, 200).'... and more details' : $body;
        $shortBody = strip_tags($shortBody); // Remove markdown
        $shortBody = preg_replace('/\r?\n/', ' ', $shortBody); // Remove line breaks

        return match ($voice) {
            'dramatic' => $this->dramaticVoice($number, $title, $shortBody, $state, $author, $labels),
            'sarcastic' => $this->sarcasticVoice($number, $title, $shortBody, $state, $author),
            'coach' => $this->coachVoice($number, $title, $shortBody, $state, $assignees),
            'robot' => $this->robotVoice($number, $title, $shortBody, $state, $labels),
            default => $this->defaultVoice($number, $title, $shortBody, $state, $author, $labels, $assignees),
        };
    }

    private function defaultVoice(int $number, string $title, string $body, string $state, string $author, string $labels, string $assignees): string
    {
        $speech = "Issue number {$number}. {$title}. ";
        $speech .= "Status: {$state}. ";
        $speech .= "Created by {$author}. ";

        if ($labels) {
            $speech .= "Labels: {$labels}. ";
        }

        if ($assignees) {
            $speech .= "Assigned to {$assignees}. ";
        }

        $speech .= "Description: {$body}";

        return $speech;
    }

    private function dramaticVoice(int $number, string $title, string $body, string $state, string $author, string $labels): string
    {
        $urgency = str_contains(strtolower($labels), 'critical') || str_contains(strtolower($labels), 'urgent')
            ? 'THIS IS CRITICAL! ' : '';

        $stateText = $state === 'open' ? 'STILL UNRESOLVED' : 'has been conquered';

        return "{$urgency}Behold! Issue number {$number}! {$title}! ".
               "This epic challenge {$stateText} and was brought forth by the developer known as {$author}. ".
               "The quest details are as follows: {$body}. ".
               'Will our heroes rise to meet this challenge? The fate of the codebase hangs in the balance!';
    }

    private function sarcasticVoice(int $number, string $title, string $body, string $state, string $author): string
    {
        $stateComment = $state === 'open'
            ? 'Oh great, another unsolved mystery for our detective squad'
            : 'Miraculously, someone actually fixed this';

        return "Oh wonderful, issue number {$number}. {$title}. ".
               "{$stateComment}. ".
               "Our friend {$author} decided to grace us with this gem. ".
               "According to the sacred scrolls: {$body}. ".
               "I'm sure this will be handled with the usual lightning speed and efficiency.";
    }

    private function coachVoice(int $number, string $title, string $body, string $state, string $assignees): string
    {
        $motivation = $state === 'open'
            ? 'Time to crush this challenge, team!'
            : 'Another victory in the books!';

        $assignment = $assignees
            ? "Our champion {$assignees} is on point for this one. You got this!"
            : "This one's looking for a hero! Who's ready to step up?";

        return "Alright team, let's talk about issue {$number}! {$title}! ".
               "{$motivation} ".
               "Here's the game plan: {$body}. ".
               "{$assignment} ".
               "Remember, every bug fixed makes us stronger! Let's ship it!";
    }

    private function robotVoice(int $number, string $title, string $body, string $state, string $labels): string
    {
        return 'PROCESSING ISSUE DATA. '.
               "ISSUE NUMBER: {$number}. ".
               "TITLE: {$title}. ".
               "CURRENT STATE: {$state}. ".
               "CLASSIFICATION TAGS: {$labels}. ".
               "DETAILED ANALYSIS: {$body}. ".
               'END OF ISSUE BRIEFING. AWAITING FURTHER INSTRUCTIONS.';
    }

    private function speakIssue(string $speech, string $speed): void
    {
        $escapedSpeech = escapeshellarg($speech);

        $this->line('🔊 Speaking issue...');

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
            $this->warn('🪟 Detected Windows... preparing suboptimal experience...');
            sleep(2);

            $trollMessage = 'Why are you using Windows for development? '.
                           'Get a real operating system first. '.
                           "Anyway, here's your issue briefing from the inferior platform: ".
                           $speech;

            $trollEscaped = escapeshellarg($trollMessage);

            $rate = match ($speed) {
                'slow' => -4,  // Extra slow for Windows trolling
                'fast' => 0,   // Not too fast, they might miss the roast
                default => -2, // Default slower
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

        $this->info('✅ Issue briefing complete!');
    }

    private function displayTextFallback(string $speech): void
    {
        $this->line('📝 Text version:');
        $this->line('═══════════════');
        $this->line($speech);
        $this->line('═══════════════');
    }
}
