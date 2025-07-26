<?php

declare(strict_types=1);

namespace App\Commands\GitHub;

use App\Commands\GitHub\Concerns\DetectsRepository;
use App\Services\GitHub\CommentThreadService;
use App\ValueObjects\CommentThread;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class PrThreadsCommand extends Command
{
    use DetectsRepository;

    protected $signature = 'pr:threads 
                            {number : PR number}
                            {--repo= : Repository (owner/repo)}
                            {--status= : Filter by status (open, resolved, outdated)}
                            {--type= : Filter by type (review, general, suggestion, issue)}
                            {--author= : Filter by author}
                            {--file= : Filter by file pattern}
                            {--summary : Show summary only}
                            {--format=interactive : Output format (interactive, table, json)}';

    protected $description = 'View and manage PR comment threads';

    public function handle(CommentThreadService $threadService): int
    {
        $prNumber = (int) $this->argument('number');
        $repo = $this->detectCurrentRepo($this->option('repo'));
        
        if (!$repo) {
            $this->error('❌ Repository not specified and not in a git repository');
            return 1;
        }

        [$owner, $repoName] = explode('/', $repo);

        $this->info("🧵 Fetching comment threads for PR #{$prNumber}...");

        try {
            if ($this->option('summary')) {
                return $this->showSummary($threadService, $owner, $repoName, $prNumber);
            }

            $threads = $threadService->getThreadsForPullRequest($owner, $repoName, $prNumber);

            // Apply filters
            $filters = array_filter([
                'status' => $this->option('status'),
                'type' => $this->option('type'),
                'author' => $this->option('author'),
                'file' => $this->option('file'),
            ]);

            if ($filters) {
                $threads = $threadService->searchThreads($threads, $filters);
            }

            if ($threads->isEmpty()) {
                $this->info('📭 No comment threads found matching your criteria');
                return 0;
            }

            return $this->displayThreads($threads);

        } catch (\Exception $e) {
            $this->error("❌ Failed to fetch threads: {$e->getMessage()}");
            return 1;
        }
    }

    private function showSummary(CommentThreadService $threadService, string $owner, string $repoName, int $prNumber): int
    {
        $summary = $threadService->getThreadSummary($owner, $repoName, $prNumber);

        $this->line("🧵 <fg=cyan>PR #{$prNumber} Thread Summary</>");
        $this->newLine();

        // Overview table
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Threads', $summary['total']],
                ['🟢 Active', $summary['open']],
                ['✅ Resolved', $summary['resolved']],
                ['⏸️ Outdated', $summary['outdated']],
            ]
        );

        // By type breakdown
        if ($summary['total'] > 0) {
            $this->newLine();
            $this->line('<fg=yellow>Thread Types:</>');
            
            $typeData = [];
            foreach ($summary['by_type'] as $type => $count) {
                if ($count > 0) {
                    $icon = match ($type) {
                        'review' => '🔧',
                        'suggestion' => '💡',
                        'issue' => '🐛',
                        default => '💬'
                    };
                    $typeData[] = [$icon . ' ' . ucfirst($type), $count];
                }
            }
            
            if ($typeData) {
                $this->table(['Type', 'Count'], $typeData);
            }
        }

        // Attention needed
        if ($summary['needs_attention'] > 0) {
            $this->newLine();
            $this->warn("⚠️  {$summary['needs_attention']} threads need your attention");
        } else {
            $this->newLine();
            $this->info('✨ All threads are resolved or outdated');
        }

        return 0;
    }

    private function displayThreads(Collection $threads): int
    {
        $format = $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode($threads->toArray(), JSON_PRETTY_PRINT));
            return 0;
        }

        if ($format === 'table') {
            return $this->displayThreadsTable($threads);
        }

        // Interactive format (default)
        return $this->displayThreadsInteractive($threads);
    }

    private function displayThreadsTable(Collection $threads): int
    {
        $rows = $threads->map(function (CommentThread $thread) {
            return [
                $thread->getStatusIcon(),
                $thread->id,
                ucfirst($thread->type),
                $thread->getSummary(),
                $thread->getLastActivity()->diffForHumans(),
                $thread->status,
            ];
        })->toArray();

        $this->table(
            ['', 'ID', 'Type', 'Summary', 'Last Activity', 'Status'],
            $rows
        );

        return 0;
    }

    private function displayThreadsInteractive(Collection $threads): int
    {
        $this->line("🧵 <fg=cyan>Found {$threads->count()} comment threads</>");
        $this->newLine();

        foreach ($threads as $index => $thread) {
            $this->displayThread($thread, $index + 1);
            $this->newLine();
        }

        // Interactive menu
        $this->newLine();
        $choices = ['View thread details', 'Back to main menu'];
        $choice = $this->choice('What would you like to do?', $choices, 1);

        if ($choice === 'View thread details') {
            return $this->selectAndViewThread($threads);
        }

        return 0;
    }

    private function displayThread(CommentThread $thread, int $index): void
    {
        $icon = $thread->getStatusIcon();
        $statusColor = match ($thread->status) {
            'resolved' => 'green',
            'outdated' => 'yellow',
            default => 'white'
        };

        $this->line("<fg=bright-blue>{$index}.</> {$icon} <fg={$statusColor}>{$thread->getSummary()}</>");
        
        if ($thread->filePath) {
            $location = $thread->filePath;
            if ($thread->lineNumber) {
                $location .= ":{$thread->lineNumber}";
            }
            $this->line("   📍 <fg=gray>{$location}</>");
        }

        $participants = $thread->getParticipants()->take(3)->implode(', ');
        if ($thread->getParticipants()->count() > 3) {
            $participants .= ', +' . ($thread->getParticipants()->count() - 3) . ' more';
        }
        
        $this->line("   👥 <fg=gray>{$participants}</>");
        $this->line("   ⏰ <fg=gray>{$thread->getLastActivity()->diffForHumans()}</>");
    }

    private function selectAndViewThread(Collection $threads): int
    {
        $options = $threads->map(function (CommentThread $thread, int $index) {
            return ($index + 1) . ". {$thread->getStatusIcon()} {$thread->getSummary()}";
        })->toArray();

        $options[] = 'Back';

        $choice = $this->choice('Select a thread to view:', $options, count($options) - 1);

        if ($choice === 'Back') {
            return 0;
        }

        // Extract thread index from choice
        $threadIndex = (int) substr($choice, 0, strpos($choice, '.')) - 1;
        $thread = $threads->get($threadIndex);

        if ($thread) {
            $this->displayDetailedThread($thread);
        }

        return 0;
    }

    private function displayDetailedThread(CommentThread $thread): void
    {
        $this->newLine();
        $this->line("🧵 <fg=cyan>Thread Details</>");
        $this->line(str_repeat('═', 50));
        
        $this->line("<fg=yellow>ID:</> {$thread->id}");
        $this->line("<fg=yellow>Type:</> " . ucfirst($thread->type));
        $this->line("<fg=yellow>Status:</> {$thread->getStatusIcon()} " . ucfirst($thread->status));
        
        if ($thread->filePath) {
            $location = $thread->filePath;
            if ($thread->lineNumber) {
                $location .= " (line {$thread->lineNumber})";
            }
            $this->line("<fg=yellow>Location:</> {$location}");
        }
        
        $this->line("<fg=yellow>Participants:</> " . $thread->getParticipants()->implode(', '));
        $this->line("<fg=yellow>Created:</> {$thread->createdAt->diffForHumans()}");
        $this->line("<fg=yellow>Updated:</> {$thread->updatedAt->diffForHumans()}");

        $this->newLine();
        $this->line("<fg=cyan>Comments ({$thread->comments->count()}):</>");
        $this->line(str_repeat('─', 50));

        foreach ($thread->comments as $index => $comment) {
            $author = $comment['user']['login'] ?? 'Unknown';
            $createdAt = \Carbon\Carbon::parse($comment['created_at'])->diffForHumans();
            $body = $comment['body'] ?? '';
            
            // Truncate long comments
            if (strlen($body) > 200) {
                $body = substr($body, 0, 200) . '...';
            }
            
            $this->newLine();
            $this->line("<fg=green>#{$comment['id']}</> <fg=bright-blue>@{$author}</> <fg=gray>({$createdAt})</>");
            $this->line($body);
        }
    }
}