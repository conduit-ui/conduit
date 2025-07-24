<?php

namespace App\Commands\Know;

use App\Events\KnowledgeCaptured;
use App\Services\DatabaseSchemaManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class AutoCaptureCommand extends Command
{
    protected $signature = 'know:auto-capture 
                            {type=commit : Type of auto-capture (commit/failure)}
                            {--command= : Failed command to capture}
                            {--exit-code= : Exit code of failed command}';

    protected $description = 'Auto-capture knowledge from git commits and command failures (internal use)';

    public function handle(): int
    {
        if (! $this->isDatabaseReady()) {
            $this->initializeDatabase();
        }

        $type = $this->argument('type');

        return match ($type) {
            'commit' => $this->captureCommit(),
            'failure' => $this->captureFailure(),
            default => $this->error("❌ Unknown auto-capture type: {$type}")
        };
    }

    private function captureCommit(): int
    {
        try {
            $gitContext = $this->getGitContext();

            if (! $gitContext['commit_sha']) {
                $this->line('🔍 No recent commit found for auto-capture');
                return 0;
            }

            // Get commit message and diff stats
            $commitMessage = $this->runGitCommand(['git', 'log', '-1', '--pretty=format:%s']);
            $diffStats = $this->runGitCommand(['git', 'diff', '--stat', 'HEAD~1', 'HEAD']);

            if (! $commitMessage) {
                return 0;
            }

            // Skip certain types of commits
            if ($this->shouldSkipCommit($commitMessage)) {
                return 0;
            }

            // Extract meaningful knowledge from commit
            $knowledge = $this->extractKnowledgeFromCommit($commitMessage, $diffStats);

            if (! $knowledge) {
                return 0;
            }

            $id = DB::table('knowledge_entries')->insertGetId([
                'content' => $knowledge,
                'repo' => $gitContext['repo'],
                'branch' => $gitContext['branch'],
                'commit_sha' => $gitContext['commit_sha'],
                'author' => $gitContext['author'],
                'project_type' => $gitContext['project_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Handle tags for v2 schema
            $this->addTagsToEntry($id, ['auto-capture', 'git-commit']);
            
            // Handle metadata for v2 schema
            $this->addMetadataToEntry($id, 'priority', 'low');
            $this->addMetadataToEntry($id, 'status', 'open');

            $this->info("📝 Auto-captured commit knowledge (ID: {$id})");
            $this->line("   {$knowledge}");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error auto-capturing commit: {$e->getMessage()}");

            return 1;
        }
    }

    private function captureFailure(): int
    {
        try {
            $command = $this->option('command');
            $exitCode = $this->option('exit-code');

            if (! $command) {
                return 0;
            }

            $gitContext = $this->getGitContext();

            // Create knowledge entry for the failure
            $knowledge = "Command failed: {$command} (exit code: {$exitCode})";

            $id = DB::table('knowledge_entries')->insertGetId([
                'content' => $knowledge,
                'repo' => $gitContext['repo'],
                'branch' => $gitContext['branch'],
                'commit_sha' => $gitContext['commit_sha'],
                'author' => $gitContext['author'],
                'project_type' => $gitContext['project_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Handle tags for v2 schema
            $this->addTagsToEntry($id, ['auto-capture', 'command-failure', 'debugging']);
            
            // Handle metadata for v2 schema
            $this->addMetadataToEntry($id, 'priority', 'medium');
            $this->addMetadataToEntry($id, 'status', 'open');

            $this->info("🚨 Auto-captured command failure (ID: {$id})");
            $this->line("   {$knowledge}");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error auto-capturing failure: {$e->getMessage()}");

            return 1;
        }
    }

    private function shouldSkipCommit(string $commitMessage): bool
    {
        $skipPatterns = [
            '/^Merge /',
            '/^wip/i',
            '/^temp/i',
            '/^test commit/i',
            '/^\d+\.\d+\.\d+$/', // Version tags
            '/^v\d+\.\d+\.\d+$/', // Version tags
            '/^fix typo/i',
            '/^formatting/i',
            '/^lint/i',
            '/whitespace/i',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $commitMessage)) {
                return true;
            }
        }

        return false;
    }

    private function extractKnowledgeFromCommit(string $commitMessage, ?string $diffStats): string
    {
        // Extract meaningful insights from commit message
        $insights = [];

        // Detect patterns in commit messages
        if (preg_match('/fix[ed]?[:\s]+(.*)/i', $commitMessage, $matches)) {
            $insights[] = 'Fixed issue: '.trim($matches[1]);
        } elseif (preg_match('/add[ed]?[:\s]+(.*)/i', $commitMessage, $matches)) {
            $insights[] = 'Added feature: '.trim($matches[1]);
        } elseif (preg_match('/refactor[ed]?[:\s]+(.*)/i', $commitMessage, $matches)) {
            $insights[] = 'Refactored: '.trim($matches[1]);
        } elseif (preg_match('/improv[ed]?[:\s]+(.*)/i', $commitMessage, $matches)) {
            $insights[] = 'Improved: '.trim($matches[1]);
        } else {
            // Just use the commit message as-is if no patterns match
            $insights[] = $commitMessage;
        }

        // Add context from diff stats if available
        if ($diffStats && preg_match('/(\d+) files? changed/', $diffStats, $matches)) {
            $fileCount = (int) $matches[1];
            if ($fileCount > 5) {
                $insights[] = "(Large change affecting {$fileCount} files)";
            }
        }

        return implode(' ', $insights);
    }

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
            if ($remoteUrl && preg_match('#github\.com[/:]([^/]+/[^/]+?)(?:\.git)?/?$#', $remoteUrl, $matches)) {
                $context['repo'] = $matches[1];
            }

            $context['branch'] = $this->runGitCommand(['git', 'branch', '--show-current']);
            $context['commit_sha'] = $this->runGitCommand(['git', 'rev-parse', 'HEAD']);
            $context['author'] = $this->runGitCommand(['git', 'config', 'user.name']);
            $context['project_type'] = $this->detectProjectType();

        } catch (\Exception $e) {
            // Git context is optional
        }

        return $context;
    }

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
            $schemaManager->initializeGlobalDatabase();
        } catch (\Exception $e) {
            // Silently fail if we can't initialize
        }
    }

    private function addTagsToEntry(int $entryId, array $tagNames): void
    {
        try {
            // Check if we're using v2 schema
            if (! Schema::hasTable('knowledge_tags')) {
                // Fallback for v1 schema
                DB::table('knowledge_entries')
                    ->where('id', $entryId)
                    ->update(['tags' => json_encode($tagNames)]);
                return;
            }

            foreach ($tagNames as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) {
                    continue;
                }

                // Get or create tag with race condition handling
                $tag = DB::table('knowledge_tags')->where('name', $tagName)->first();
                
                if (!$tag) {
                    try {
                        $tagId = DB::table('knowledge_tags')->insertGetId([
                            'name' => $tagName,
                            'usage_count' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } catch (\Exception $e) {
                        // Another process may have created it, try to get it again
                        $tag = DB::table('knowledge_tags')->where('name', $tagName)->first();
                        $tagId = $tag ? $tag->id : null;
                    }
                } else {
                    $tagId = $tag->id;
                }
                
                if ($tagId) {
                    // Increment usage count
                    DB::table('knowledge_tags')->where('id', $tagId)->increment('usage_count');
                    
                    // Link entry to tag (prevent duplicates)
                    DB::table('knowledge_entry_tags')->insertOrIgnore([
                        'entry_id' => $entryId,
                        'tag_id' => $tagId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Silently fail if tags can't be added
        }
    }

    private function addMetadataToEntry(int $entryId, string $key, string $value): void
    {
        try {
            // Check if we're using v2 schema
            if (! Schema::hasTable('knowledge_metadata')) {
                // Fallback for v1 schema - update column directly
                if (in_array($key, ['priority', 'status'])) {
                    DB::table('knowledge_entries')
                        ->where('id', $entryId)
                        ->update([$key => $value]);
                }
                return;
            }

            // Use updateOrInsert to prevent duplicates
            DB::table('knowledge_metadata')->updateOrInsert(
                [
                    'entry_id' => $entryId,
                    'key' => $key,
                ],
                [
                    'value' => $value,
                    'type' => 'string', // Currently all our metadata is string-based
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            // Silently fail if metadata can't be added
        }
    }
}
