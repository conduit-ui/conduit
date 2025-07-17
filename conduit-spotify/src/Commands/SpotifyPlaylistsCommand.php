<?php

namespace JordanPartridge\ConduitSpotify\Commands;

use Illuminate\Console\Command;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyApiInterface;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;

class SpotifyPlaylistsCommand extends Command
{
    protected $signature = 'spotify:playlists 
                           {action? : Action (list, play, search)}
                           {query? : Playlist name or search query}
                           {--limit=20 : Number of playlists to show}
                           {--json : Output as JSON}';

    protected $description = 'Manage and play Spotify playlists';

    public function handle(SpotifyAuthInterface $auth, SpotifyApiInterface $api): int
    {
        if (!$auth->isAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: php conduit spotify:auth');
            return 1;
        }

        $action = $this->argument('action') ?? 'list';
        
        return match($action) {
            'list' => $this->listPlaylists($api),
            'play' => $this->playPlaylist($api),
            'search' => $this->searchPlaylists($api),
            default => $this->handleInvalidAction($action)
        };
    }

    private function listPlaylists(SpotifyApiInterface $api): int
    {
        try {
            $limit = (int) $this->option('limit');
            $playlists = $api->getUserPlaylists($limit);

            if (empty($playlists)) {
                $this->info('📭 No playlists found');
                return 0;
            }

            if ($this->option('json')) {
                $this->line(json_encode($playlists, JSON_PRETTY_PRINT));
                return 0;
            }

            $this->info("🎵 Your Spotify Playlists ({$limit} shown):");
            $this->newLine();

            foreach ($playlists as $index => $playlist) {
                $number = $index + 1;
                $name = $playlist['name'];
                $trackCount = $playlist['tracks']['total'] ?? 0;
                $owner = $playlist['owner']['display_name'] ?? 'Unknown';
                
                $this->line("  <info>{$number}.</info> <comment>{$name}</comment>");
                $this->line("      {$trackCount} tracks • by {$owner}");
                
                if ($index < count($playlists) - 1) {
                    $this->newLine();
                }
            }

            $this->newLine();
            $this->line('💡 To play a playlist: php conduit spotify:playlists play "playlist name"');
            $this->line('   Or use: php conduit spotify:play spotify:playlist:PLAYLIST_ID');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function playPlaylist(SpotifyApiInterface $api): int
    {
        $query = $this->argument('query');
        
        if (!$query) {
            $this->error('❌ Please specify a playlist name');
            $this->line('💡 Usage: php conduit spotify:playlists play "My Playlist"');
            return 1;
        }

        try {
            // First, try to find the playlist by name
            $playlists = $api->getUserPlaylists(50); // Get more for better search
            $matchedPlaylist = null;

            foreach ($playlists as $playlist) {
                if (stripos($playlist['name'], $query) !== false) {
                    $matchedPlaylist = $playlist;
                    break;
                }
            }

            if (!$matchedPlaylist) {
                $this->error("❌ Playlist not found: {$query}");
                $this->line('💡 Try: php conduit spotify:playlists list');
                return 1;
            }

            $playlistUri = $matchedPlaylist['uri'];
            $success = $api->play($playlistUri);

            if ($success) {
                $name = $matchedPlaylist['name'];
                $trackCount = $matchedPlaylist['tracks']['total'] ?? 0;
                
                $this->info("▶️  Playing playlist: {$name}");
                $this->line("🎵 {$trackCount} tracks");

                // Show current track after a moment
                sleep(1);
                $current = $api->getCurrentTrack();
                if ($current && isset($current['item'])) {
                    $track = $current['item'];
                    $artist = collect($track['artists'])->pluck('name')->join(', ');
                    $this->line("   <info>{$track['name']}</info> by <comment>{$artist}</comment>");
                }

                return 0;
            } else {
                $this->error('❌ Failed to play playlist');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function searchPlaylists(SpotifyApiInterface $api): int
    {
        $query = $this->argument('query');
        
        if (!$query) {
            $this->error('❌ Please specify a search query');
            $this->line('💡 Usage: php conduit spotify:playlists search "chill"');
            return 1;
        }

        try {
            $results = $api->search($query, ['playlist'], 20);

            if (!isset($results['playlists']['items']) || empty($results['playlists']['items'])) {
                $this->info("🔍 No playlists found for: {$query}");
                return 0;
            }

            $playlists = $results['playlists']['items'];

            if ($this->option('json')) {
                $this->line(json_encode($playlists, JSON_PRETTY_PRINT));
                return 0;
            }

            $this->info("🔍 Search results for: {$query}");
            $this->newLine();

            foreach ($playlists as $index => $playlist) {
                $number = $index + 1;
                $name = $playlist['name'];
                $trackCount = $playlist['tracks']['total'] ?? 0;
                $owner = $playlist['owner']['display_name'] ?? 'Unknown';
                $description = $playlist['description'] ?? '';
                
                $this->line("  <info>{$number}.</info> <comment>{$name}</comment>");
                $this->line("      {$trackCount} tracks • by {$owner}");
                
                if ($description) {
                    $shortDesc = strlen($description) > 60 ? substr($description, 0, 60) . '...' : $description;
                    $this->line("      {$shortDesc}");
                }
                
                if ($index < count($playlists) - 1) {
                    $this->newLine();
                }
            }

            $this->newLine();
            $this->line('💡 To play: php conduit spotify:play ' . $playlists[0]['uri']);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function handleInvalidAction(string $action): int
    {
        $this->error("❌ Invalid action: {$action}");
        $this->line('💡 Available actions: list, play, search');
        $this->line('   Examples:');
        $this->line('     php conduit spotify:playlists list');
        $this->line('     php conduit spotify:playlists play "My Coding Playlist"');
        $this->line('     php conduit spotify:playlists search "chill"');
        return 1;
    }
}