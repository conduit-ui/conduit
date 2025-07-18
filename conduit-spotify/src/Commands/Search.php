<?php

namespace Conduit\Spotify\Commands;

use Illuminate\Console\Command;
use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;

class Search extends Command
{
    protected $signature = 'spotify:search 
                           {query : Search query}
                           {--type=track : Type of content (track, album, playlist, artist)}
                           {--limit=10 : Number of results to show}
                           {--play : Play the first result}
                           {--urls : Show Spotify URLs for results}';

    protected $description = 'Search Spotify for tracks, albums, playlists, or artists';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (!$auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: conduit spotify:login');
            return 1;
        }

        try {
            $query = $this->argument('query');
            $type = $this->option('type');
            $limit = (int) $this->option('limit');
            $shouldPlay = $this->option('play');
            $showUrls = $this->option('urls');

            $this->info("🔍 Searching for {$type}s: \"{$query}\"");
            $this->newLine();

            $results = $api->search($query, [$type], $limit);
            
            if (empty($results) || empty($results[$type . 's']['items'] ?? [])) {
                $this->warn('❌ No results found');
                return 0;
            }

            $items = $results[$type . 's']['items'];
            
            foreach ($items as $index => $item) {
                $number = $index + 1;
                
                $url = $item['external_urls']['spotify'] ?? null;
                
                switch ($type) {
                    case 'track':
                        $artist = collect($item['artists'])->pluck('name')->join(', ');
                        $duration = $this->formatDuration($item['duration_ms']);
                        $this->line("{$number}. <info>{$item['name']}</info> by <comment>{$artist}</comment> ({$duration})");
                        break;
                        
                    case 'album':
                        $artist = collect($item['artists'])->pluck('name')->join(', ');
                        $year = date('Y', strtotime($item['release_date']));
                        $this->line("{$number}. <info>{$item['name']}</info> by <comment>{$artist}</comment> ({$year})");
                        break;
                        
                    case 'playlist':
                        $owner = $item['owner']['display_name'] ?? 'Unknown';
                        $tracks = $item['tracks']['total'] ?? 0;
                        $this->line("{$number}. <info>{$item['name']}</info> by <comment>{$owner}</comment> ({$tracks} tracks)");
                        break;
                        
                    case 'artist':
                        $followers = number_format($item['followers']['total'] ?? 0);
                        $this->line("{$number}. <info>{$item['name']}</info> ({$followers} followers)");
                        break;
                }
                
                if ($showUrls && $url) {
                    $this->line("    <comment>{$url}</comment>");
                }
            }

            if ($shouldPlay && !empty($items)) {
                $firstItem = $items[0];
                $uri = $firstItem['uri'];
                
                $this->newLine();
                $this->info("▶️  Playing first result...");
                
                if ($api->play($uri)) {
                    $this->info("🎵 Now playing: {$firstItem['name']}");
                } else {
                    $this->error("❌ Failed to play {$firstItem['name']}");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Search failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function formatDuration(int $milliseconds): string
    {
        $seconds = intval($milliseconds / 1000);
        $minutes = intval($seconds / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }
}