<?php

namespace Conduit\Spotify\Commands;

use Illuminate\Console\Command;
use Conduit\Spotify\Contracts\ApiInterface;
use Conduit\Spotify\Contracts\AuthInterface;

class Queue extends Command
{
    protected $signature = 'spotify:queue 
                           {query : Search query or Spotify URI to add to queue}
                           {--device= : Device ID to add to queue on}';

    protected $description = 'Add a track to your Spotify queue';

    public function handle(AuthInterface $auth, ApiInterface $api): int
    {
        if (!$auth->ensureAuthenticated()) {
            $this->error('❌ Not authenticated with Spotify');
            $this->info('💡 Run: conduit spotify:login');
            return 1;
        }

        try {
            $query = $this->argument('query');
            $deviceId = $this->option('device');
            
            // Handle search vs URI
            if (!str_starts_with($query, 'spotify:')) {
                $this->info("🔍 Searching for: \"{$query}\"");
                $searchResults = $api->search($query, ['track'], 1);
                
                if (empty($searchResults['tracks']['items'])) {
                    $this->error("❌ No tracks found for: \"{$query}\"");
                    return 1;
                }
                
                $track = $searchResults['tracks']['items'][0];
                $uri = $track['uri'];
                $artist = collect($track['artists'])->pluck('name')->join(', ');
                $this->info("🎵 Found: {$track['name']} by {$artist}");
            } else {
                $uri = $query;
            }

            // Add to queue
            if ($api->addToQueue($uri, $deviceId)) {
                $this->info("✅ Added to queue!");
                return 0;
            } else {
                $this->error("❌ Failed to add to queue");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }
}