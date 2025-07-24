<?php

namespace App\Commands\Know;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class AutoEnhanceCommand extends Command
{
    protected $signature = 'know:auto-enhance 
                            {--branch= : Branch that was pushed}
                            {--remote= : Remote that was pushed to}';
    
    protected $description = 'Auto-enhance knowledge entries after git push (internal use)';
    
    public function handle(): int
    {
        $branch = $this->option('branch') ?: $this->getCurrentBranch();
        $remote = $this->option('remote') ?: 'origin';
        
        if (!$branch) {
            return 0; // Silently exit if we can't determine branch
        }
        
        // Get remote URL to determine repo
        $remoteUrl = $this->getRemoteUrl($remote);
        if (!$remoteUrl || !preg_match('#github\.com[/:]([^/]+/[^/]+?)(?:\.git)?/?$#', $remoteUrl, $matches)) {
            return 0;
        }
        
        $repo = $matches[1];
        
        // Find entries on this branch that don't have GitHub URLs yet
        $query = DB::table('knowledge_entries as ke')
            ->where('ke.branch', $branch)
            ->where('ke.repo', $repo)
            ->whereNotNull('ke.commit_sha')
            ->whereRaw("ke.content NOT LIKE '%github.com%'");
            
        // If v2 schema, join with tags
        if (Schema::hasTable('knowledge_entry_tags')) {
            $query->leftJoin('knowledge_entry_tags as ket', 'ke.id', '=', 'ket.entry_id')
                  ->leftJoin('knowledge_tags as kt', 'ket.tag_id', '=', 'kt.id')
                  ->where('kt.name', 'auto-capture');
        }
        
        $entries = $query->select('ke.id', 'ke.content', 'ke.commit_sha')
                        ->distinct()
                        ->get();
        
        if ($entries->isEmpty()) {
            return 0;
        }
        
        $enhanced = 0;
        foreach ($entries as $entry) {
            $url = "https://github.com/{$repo}/commit/{$entry->commit_sha}";
            $enhancedContent = $entry->content . "\n\n🔗 View commit: {$url}";
            
            DB::table('knowledge_entries')
                ->where('id', $entry->id)
                ->update(['content' => $enhancedContent]);
            
            $enhanced++;
        }
        
        // Only show output if running interactively
        if (!$this->option('quiet') && $enhanced > 0) {
            $this->info("✨ Enhanced {$enhanced} entries with GitHub URLs after push");
        }
        
        return 0;
    }
    
    private function getCurrentBranch(): ?string
    {
        $process = new Process(['git', 'branch', '--show-current']);
        $process->run();
        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }
    
    private function getRemoteUrl(string $remote): ?string
    {
        $process = new Process(['git', 'remote', 'get-url', $remote]);
        $process->run();
        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }
}