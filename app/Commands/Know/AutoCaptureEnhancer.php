<?php

namespace App\Commands\Know;

use Illuminate\Support\Facades\DB;
use LaravelZero\Framework\Commands\Command;

class AutoCaptureEnhancer extends Command
{
    protected $signature = 'know:enhance-auto-captures 
                            {--dry-run : Show what would be updated without making changes}';
    
    protected $description = 'Add GitHub commit URLs to auto-captured entries';
    
    public function handle(): int
    {
        $this->info('🔗 Enhancing auto-captured entries with GitHub URLs...');
        
        // Find all auto-captured entries with commit SHAs
        $entries = DB::table('knowledge_entries as ke')
            ->leftJoin('knowledge_entry_tags as ket', 'ke.id', '=', 'ket.entry_id')
            ->leftJoin('knowledge_tags as kt', 'ket.tag_id', '=', 'kt.id')
            ->where('kt.name', 'auto-capture')
            ->whereNotNull('ke.commit_sha')
            ->whereNotNull('ke.repo')
            ->select('ke.id', 'ke.content', 'ke.repo', 'ke.commit_sha')
            ->get();
        
        if ($entries->isEmpty()) {
            $this->info('No auto-captured entries found with commit information.');
            return 0;
        }
        
        $this->info("Found {$entries->count()} entries to enhance");
        $this->newLine();
        
        foreach ($entries as $entry) {
            // GitHub commit URLs work with just the SHA, no branch needed
            $url = "https://github.com/{$entry->repo}/commit/{$entry->commit_sha}";
            $shortSha = substr($entry->commit_sha, 0, 7);
            
            // Check if URL already in content
            if (str_contains($entry->content, $url) || str_contains($entry->content, 'github.com')) {
                $this->line("⏭️  Entry #{$entry->id} already has URL");
                continue;
            }
            
            // Create enhanced content with URL
            $enhancedContent = $entry->content . "\n\n🔗 View commit: {$url}";
            
            $this->line("📝 Entry #{$entry->id}: {$entry->content}");
            $this->line("   → Adding URL: {$url}");
            
            if (!$this->option('dry-run')) {
                DB::table('knowledge_entries')
                    ->where('id', $entry->id)
                    ->update(['content' => $enhancedContent]);
                
                $this->info("   ✅ Updated!");
            } else {
                $this->warn("   🔍 Dry run - no changes made");
            }
            $this->newLine();
        }
        
        $this->info('✨ Enhancement complete!');
        return 0;
    }
}