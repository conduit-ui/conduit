<?php

namespace JordanPartridge\ConduitSpotify\Commands;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;

class SpotifyCurrentCommand extends Command
{
    protected $signature = 'spotify:current 
                           {--json : Output as JSON}
                           {--compact : Show compact view}';

    protected $description = 'Show currently playing track information';

    public function handle(SpotifyAuthInterface $auth, SpotifyApiInterface $api): int
    {
        if (!$auth->isAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:auth');
            return 1;
        }

        try {
            $current = $api->getCurrentPlayback();

            if (!$current || !isset($current['item'])) {
                $this->info('🔇 Nothing currently playing');
                return 0;
            }

            if ($this->option('json')) {
                $this->line(json_encode($current, JSON_PRETTY_PRINT));
                return 0;
            }

            $track = $current['item'];
            $isPlaying = $current['is_playing'] ?? false;
            $device = $current['device'] ?? null;
            $progressMs = $current['progress_ms'] ?? 0;
            $durationMs = $track['duration_ms'] ?? 0;

            // Format track info
            $artist = collect($track['artists'])->pluck('name')->join(', ');
            $album = $track['album']['name'] ?? 'Unknown Album';

            // Format time
            $progress = $this->formatTime($progressMs);
            $duration = $this->formatTime($durationMs);
            $progressPercent = $durationMs > 0 ? round(($progressMs / $durationMs) * 100) : 0;

            if ($this->option('compact')) {
                $status = $isPlaying ? '▶️' : '⏸️';
                $this->line("{$status} <info>{$track['name']}</info> by <comment>{$artist}</comment> [{$progress}/{$duration}]");
                return 0;
            }

            // Full display
            $this->newLine();
            $this->line("🎵 <options=bold>Now Playing</>");
            $this->newLine();

            $this->line("  <info>Track:</info>   {$track['name']}");
            $this->line("  <info>Artist:</info>  {$artist}");
            $this->line("  <info>Album:</info>   {$album}");
            
            if ($device) {
                $this->line("  <info>Device:</info>  {$device['name']} ({$device['type']})");
                if (isset($device['volume_percent'])) {
                    $this->line("  <info>Volume:</info>  {$device['volume_percent']}%");
                }
            }

            $this->newLine();

            // Progress bar
            $barLength = 40;
            $filledLength = (int) (($progressPercent / 100) * $barLength);
            $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
            
            $status = $isPlaying ? '▶️' : '⏸️';
            $this->line("  {$status} [{$bar}] {$progressPercent}%");
            $this->line("     {$progress} / {$duration}");

            if (isset($current['shuffle_state'])) {
                $shuffle = $current['shuffle_state'] ? '🔀 Shuffle ON' : '🔀 Shuffle OFF';
                $repeat = match($current['repeat_state'] ?? 'off') {
                    'track' => '🔂 Repeat Track',
                    'context' => '🔁 Repeat All',
                    default => '🔁 Repeat OFF'
                };
                $this->newLine();
                $this->line("  {$shuffle}  |  {$repeat}");
            }

            $this->newLine();

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function formatTime(int $milliseconds): string
    {
        $seconds = (int) ($milliseconds / 1000);
        $minutes = (int) ($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}