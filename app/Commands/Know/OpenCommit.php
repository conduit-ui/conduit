<?php

namespace App\Commands\Know;

use Illuminate\Support\Facades\DB;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class OpenCommit extends Command
{
    protected $signature = 'know:open-commit 
                            {id : Knowledge entry ID to open commit for}
                            {--copy : Copy URL to clipboard instead of opening}';
    
    protected $description = 'Open the GitHub commit URL for a knowledge entry';
    
    public function handle(): int
    {
        $id = $this->argument('id');
        
        // Get the entry
        $entry = DB::table('knowledge_entries')->find($id);
        
        if (!$entry) {
            $this->error("Knowledge entry #{$id} not found");
            return 1;
        }
        
        if (!$entry->commit_sha || !$entry->repo) {
            $this->error("Entry #{$id} doesn't have commit information");
            return 1;
        }
        
        $url = "https://github.com/{$entry->repo}/commit/{$entry->commit_sha}";
        
        $this->info("📝 {$entry->content}");
        $this->line("🔗 {$url}");
        
        if ($this->option('copy')) {
            // Copy to clipboard (macOS)
            $process = new Process(['pbcopy']);
            $process->setInput($url);
            $process->run();
            
            if ($process->isSuccessful()) {
                $this->info("✅ URL copied to clipboard!");
            } else {
                $this->warn("Could not copy to clipboard");
            }
        } else {
            // Open in browser
            $this->info("🌐 Opening in browser...");
            
            // Detect OS and use appropriate command
            if (PHP_OS_FAMILY === 'Darwin') {
                $process = new Process(['open', $url]);
            } elseif (PHP_OS_FAMILY === 'Linux') {
                $process = new Process(['xdg-open', $url]);
            } elseif (PHP_OS_FAMILY === 'Windows') {
                $process = new Process(['start', $url], null, null, null, null);
            } else {
                $this->error("Unsupported OS for opening URLs");
                return 1;
            }
            
            $process->run();
            
            if (!$process->isSuccessful()) {
                $this->warn("Could not open browser. You can manually visit:");
                $this->line($url);
            }
        }
        
        return 0;
    }
}