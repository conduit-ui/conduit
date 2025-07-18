<?php

namespace App\Commands;

use App\Services\DatabaseSchemaManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class KnowledgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'know 
                            {content? : Knowledge content to capture}
                            {--search= : Search for knowledge entries}
                            {--context : Show context-relevant knowledge}
                            {--tags= : Add tags (comma-separated)}
                            {--json : Output as JSON}
                            {--limit=10 : Limit number of results}
                            {--todo : Mark as TODO item}
                            {--todos : List all TODO items}
                            {--priority=medium : Set priority (low/medium/high)}
                            {--status=open : Set status (open/in-progress/completed/blocked)}';

    /**
     * The console command description.
     */
    protected $description = 'Capture and search development knowledge with automatic git context';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Ensure database is initialized
        if (! $this->isDatabaseReady()) {
            $this->info('🗄️ Initializing knowledge database...');
            $this->initializeDatabase();
        }

        // Search mode
        if ($this->option('search')) {
            return $this->searchKnowledge($this->option('search'));
        }

        // Context mode
        if ($this->option('context')) {
            return $this->showContext();
        }

        // Todos mode
        if ($this->option('todos')) {
            return $this->listTodos();
        }

        // Capture mode
        $content = $this->argument('content');
        if (! $content) {
            $this->error('💭 Please provide knowledge content to capture');
            $this->info('💡 Usage: conduit know "Redis better than Memcached for our use case"');
            $this->info('🔍 Search: conduit know --search="auth"');
            $this->info('📍 Context: conduit know --context');

            return 1;
        }

        return $this->captureKnowledge($content);
    }

    /**
     * Capture knowledge with automatic git context
     */
    private function captureKnowledge(string $content): int
    {
        try {
            $gitContext = $this->getGitContext();
            $tags = $this->option('tags') ? explode(',', $this->option('tags')) : [];

            // Handle TODO flag
            if ($this->option('todo')) {
                $tags[] = 'todo';
            }

            $id = DB::table('knowledge_entries')->insertGetId([
                'content' => $content,
                'repo' => $gitContext['repo'],
                'branch' => $gitContext['branch'],
                'commit_sha' => $gitContext['commit_sha'],
                'author' => $gitContext['author'],
                'project_type' => $gitContext['project_type'],
                'tags' => ! empty($tags) ? json_encode(array_map('trim', $tags)) : null,
                'priority' => $this->option('priority'),
                'status' => $this->option('status'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("✅ Knowledge captured (ID: {$id})");

            if ($gitContext['repo']) {
                $this->line("📍 Context: {$gitContext['repo']} • {$gitContext['branch']} • {$gitContext['commit_sha']}");
            }

            if (! empty($tags)) {
                $this->line('🏷️  Tags: '.implode(', ', $tags));
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error capturing knowledge: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Search knowledge entries
     */
    private function searchKnowledge(string $query): int
    {
        try {
            $entries = DB::table('knowledge_entries')
                ->where('content', 'LIKE', "%{$query}%")
                ->orWhere('tags', 'LIKE', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->limit((int) $this->option('limit'))
                ->get();

            if ($entries->isEmpty()) {
                $this->info("🔍 No knowledge found for: {$query}");

                return 0;
            }

            if ($this->option('json')) {
                $this->line(json_encode($entries, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->info("🔍 Found {$entries->count()} entries for: {$query}");
            $this->newLine();

            foreach ($entries as $entry) {
                $this->displayEntry($entry);
                $this->newLine();
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error searching knowledge: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Show context-relevant knowledge
     */
    private function showContext(): int
    {
        try {
            $gitContext = $this->getGitContext();

            $query = DB::table('knowledge_entries');

            if ($gitContext['repo']) {
                $query->where('repo', $gitContext['repo']);
            }

            $entries = $query->orderBy('created_at', 'desc')
                ->limit((int) $this->option('limit'))
                ->get();

            if ($entries->isEmpty()) {
                $this->info('📍 No knowledge found for current context');
                if ($gitContext['repo']) {
                    $this->line("   Repository: {$gitContext['repo']}");
                }

                return 0;
            }

            $this->info("📍 Knowledge for current context ({$entries->count()} entries)");
            if ($gitContext['repo']) {
                $this->line("   Repository: {$gitContext['repo']} • Branch: {$gitContext['branch']}");
            }
            $this->newLine();

            foreach ($entries as $entry) {
                $this->displayEntry($entry);
                $this->newLine();
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error showing context: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Get current git context
     */
    private function getGitContext(): array
    {
        $context = [
            'repo' => null,
            'branch' => null,
            'commit_sha' => null,
            'author' => null,
            'project_type' => null,
        ];

        try {
            // Get repo name
            $remoteUrl = $this->runGitCommand(['git', 'remote', 'get-url', 'origin']);
            if ($remoteUrl) {
                if (preg_match('#github\.com[/:]([^/]+/[^/]+?)(?:\.git)?/?$#', $remoteUrl, $matches)) {
                    $context['repo'] = $matches[1];
                }
            }

            // Get current branch
            $context['branch'] = $this->runGitCommand(['git', 'branch', '--show-current']);

            // Get current commit SHA
            $context['commit_sha'] = substr($this->runGitCommand(['git', 'rev-parse', 'HEAD']) ?: '', 0, 7);

            // Get git author
            $context['author'] = $this->runGitCommand(['git', 'config', 'user.name']);

            // Detect project type
            $context['project_type'] = $this->detectProjectType();

        } catch (\Exception $e) {
            // Git context is optional, continue without it
        }

        return $context;
    }

    /**
     * Run a git command safely
     */
    private function runGitCommand(array $command): ?string
    {
        try {
            $process = new Process($command);
            $process->run();

            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Detect project type
     */
    private function detectProjectType(): ?string
    {
        if (file_exists('composer.json')) {
            $composer = json_decode(file_get_contents('composer.json'), true);

            if (isset($composer['require']['laravel-zero/framework'])) {
                return 'laravel-zero';
            }

            if (isset($composer['require']['laravel/framework'])) {
                return 'laravel';
            }

            return 'php';
        }

        if (file_exists('package.json')) {
            return 'node';
        }

        return null;
    }

    /**
     * Display a knowledge entry
     */
    private function displayEntry($entry): void
    {
        $this->line("💡 <options=bold>{$entry->content}</>");

        $details = [];
        if ($entry->repo && $entry->branch) {
            $details[] = "📂 {$entry->repo} • {$entry->branch}";
        }

        if ($entry->created_at) {
            $details[] = '📅 '.\Carbon\Carbon::parse($entry->created_at)->diffForHumans();
        }

        if ($entry->tags) {
            $tags = json_decode($entry->tags, true);
            if ($tags) {
                $details[] = '🏷️  '.implode(', ', $tags);
            }
        }

        if (! empty($details)) {
            $this->line('   '.implode(' | ', $details));
        }
    }

    /**
     * List all TODO items
     */
    private function listTodos(): int
    {
        try {
            $query = DB::table('knowledge_entries')
                ->where('tags', 'LIKE', '%todo%')
                ->orWhere('status', '!=', 'completed')
                ->whereIn('status', ['open', 'in-progress', 'blocked'])
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit((int) $this->option('limit'));

            $entries = $query->get();

            if ($entries->isEmpty()) {
                $this->info('📋 No TODO items found');

                return 0;
            }

            $this->info("📋 TODO Items ({$entries->count()} total)");
            $this->newLine();

            foreach ($entries as $entry) {
                $this->displayTodoEntry($entry);
                $this->newLine();
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error listing TODOs: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Display a TODO entry with status indicators
     */
    private function displayTodoEntry($entry): void
    {
        $statusIcon = match ($entry->status ?? 'open') {
            'open' => '⭕',
            'in-progress' => '🔄',
            'completed' => '✅',
            'blocked' => '🚫',
            default => '📝'
        };

        $priorityIcon = match ($entry->priority ?? 'medium') {
            'high' => '🔴',
            'medium' => '🟡',
            'low' => '🟢',
            default => '⚪'
        };

        $this->line("{$statusIcon} {$priorityIcon} <options=bold>{$entry->content}</>");

        $details = [];
        if ($entry->repo && $entry->branch) {
            $details[] = "📂 {$entry->repo} • {$entry->branch}";
        }

        if ($entry->status) {
            $details[] = "Status: {$entry->status}";
        }

        if ($entry->priority) {
            $details[] = "Priority: {$entry->priority}";
        }

        if ($entry->created_at) {
            $details[] = '📅 '.\Carbon\Carbon::parse($entry->created_at)->diffForHumans();
        }

        if (! empty($details)) {
            $this->line('   '.implode(' | ', $details));
        }
    }

    /**
     * Check if the knowledge database is ready
     */
    private function isDatabaseReady(): bool
    {
        try {
            return Schema::hasTable('knowledge_entries');
        } catch (\Exception $e) {
            return false;
        }
    }

    private function initializeDatabase(): void
    {
        try {
            $schemaManager = new DatabaseSchemaManager;

            // For global installations, initialize the database schema
            if ($this->isGlobalInstallation()) {
                $schemaManager->initializeGlobalDatabase();
                $this->info('🗄️ Initialized Conduit database storage...');
                $this->line('🔧 Configured SQLite database: '.$schemaManager->getDatabasePath());
            } else {
                // For local installations, try to run migrations
                $this->info('📦 Running database migrations...');
                $exitCode = $this->call('migrate', ['--force' => true]);

                if ($exitCode !== 0) {
                    // Fallback to schema manager if migrations fail
                    $schemaManager->ensureSchemaExists();
                    $this->info('🔧 Created database schema programmatically');
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to initialize database: '.$e->getMessage());
            throw $e;
        }
    }

    private function isGlobalInstallation(): bool
    {
        // Check if we're running from a global composer installation
        return strpos(__DIR__, '/.composer/') !== false ||
               strpos(__DIR__, '/vendor/conduit-ui/conduit') !== false;
    }
}
