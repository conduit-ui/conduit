<?php

namespace Conduit\Spotify\Commands;

use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;
use Illuminate\Console\Command;

class Focus extends Command
{
    protected $signature = 'spotify:focus 
                           {mode? : Focus mode (coding, break, deploy, debug, testing)}
                           {--volume= : Set volume (0-100)}
                           {--shuffle : Enable shuffle}
                           {--list : List available focus modes}';

    protected $description = 'Start focus music for coding workflows';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (! $auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:login');

            return 1;
        }

        if ($this->option('list')) {
            return $this->listFocusModes();
        }

        try {
            $mode = $this->argument('mode') ?? 'coding';
            $volume = $this->option('volume');
            $shuffle = $this->option('shuffle');

            $presets = config('spotify.presets', []);

            if (! isset($presets[$mode])) {
                $this->error("❌ Unknown focus mode: {$mode}");
                $this->line('💡 Available modes: '.implode(', ', array_keys($presets)));
                $this->line('   Or run: php conduit spotify:focus --list');

                return 1;
            }

            $playlistUri = $presets[$mode];

            // Set volume if specified or use default
            $targetVolume = $volume ?? config('spotify.auto_play.volume', 70);
            if ($targetVolume) {
                $api->setVolume((int) $targetVolume);
                $this->line("🔊 Volume set to {$targetVolume}%");
            }

            // Enable shuffle if requested
            if ($shuffle) {
                $api->setShuffle(true);
                $this->line('🔀 Shuffle enabled');
            }

            // Start focus playlist
            $success = $api->play($playlistUri);

            if ($success) {
                $emoji = $this->getFocusEmoji($mode);
                $description = $this->getFocusDescription($mode);

                $this->info("{$emoji} {$description}");
                $this->line("🎵 Playing: {$mode} focus playlist");

                // Show current track after a moment
                sleep(1);
                $current = $api->getCurrentTrack();
                if ($current && isset($current['item'])) {
                    $track = $current['item'];
                    $artist = collect($track['artists'])->pluck('name')->join(', ');
                    $this->line("   <info>{$track['name']}</info> by <comment>{$artist}</comment>");
                }

                // Show productivity tip
                $this->newLine();
                $this->line($this->getProductivityTip($mode));

                return 0;
            } else {
                $this->error('❌ Failed to start focus music');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function listFocusModes(): int
    {
        $presets = config('spotify.presets', []);

        $this->info('🎵 Available Focus Modes:');
        $this->newLine();

        foreach ($presets as $mode => $uri) {
            $emoji = $this->getFocusEmoji($mode);
            $description = $this->getFocusDescription($mode);
            $this->line("  {$emoji} <info>{$mode}</info> - {$description}");
        }

        $this->newLine();
        $this->line('💡 Usage: php conduit spotify:focus [mode]');
        $this->line('   Example: php conduit spotify:focus coding --volume=60 --shuffle');

        return 0;
    }

    private function getFocusEmoji(string $mode): string
    {
        return match ($mode) {
            'coding' => '💻',
            'break' => '☕',
            'deploy' => '🚀',
            'debug' => '🐛',
            'testing' => '🧪',
            default => '🎵'
        };
    }

    private function getFocusDescription(string $mode): string
    {
        return match ($mode) {
            'coding' => 'Deep focus coding music activated',
            'break' => 'Relaxing break music started',
            'deploy' => 'Celebration music for successful deployments',
            'debug' => 'Calm debugging music to help concentration',
            'testing' => 'Focused testing music for quality assurance',
            default => 'Focus music activated'
        };
    }

    private function getProductivityTip(string $mode): string
    {
        $tips = [
            'coding' => '💡 Tip: Try the Pomodoro technique - 25 min coding, 5 min break',
            'break' => '💡 Tip: Step away from the screen, stretch, or take a short walk',
            'deploy' => '💡 Tip: Time to celebrate! Your hard work paid off 🎉',
            'debug' => '💡 Tip: Take it slow, read the error messages carefully',
            'testing' => '💡 Tip: Think about edge cases and user scenarios',
        ];

        return $tips[$mode] ?? '💡 Tip: Stay focused and productive!';
    }
}
