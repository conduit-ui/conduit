<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\ValueObjects\CommentThread;
use Illuminate\Support\Collection;
use JordanPartridge\GithubClient\GitHub;

class CommentThreadService
{
    public function __construct(
        private readonly GitHub $github
    ) {}

    public function getThreadsForPullRequest(string $owner, string $repo, int $prNumber): Collection
    {
        // Get comments using the existing comments command logic  
        // For now, we'll create simple threads from general comments only
        $generalComments = collect($this->github->pullRequests()->comments($owner, $repo, $prNumber));
        
        // For the initial implementation, create one thread per general comment
        // In the future, we can add more sophisticated grouping
        $threads = $this->groupGeneralComments($generalComments);
        
        return $threads->sortByDesc(fn(CommentThread $thread) => $thread->getLastActivity());
    }

    public function getThreadsForIssue(string $owner, string $repo, int $issueNumber): Collection
    {
        $comments = collect($this->github->issues()->comments($owner, $repo, $issueNumber));
        
        // For issues, group comments by topic/reply chains
        return $this->groupGeneralComments($comments);
    }

    public function getThread(string $owner, string $repo, int $number, string $threadId): ?CommentThread
    {
        $threads = $this->getThreadsForPullRequest($owner, $repo, $number);
        
        return $threads->firstWhere('id', $threadId);
    }

    public function resolveThread(string $owner, string $repo, int $number, string $threadId, string $resolvedBy): bool
    {
        // In a real implementation, this would update thread status in a database
        // For now, we'll simulate by adding a resolution comment
        
        $thread = $this->getThread($owner, $repo, $number, $threadId);
        if (!$thread) {
            return false;
        }
        
        // Add a resolution marker comment
        $resolutionMessage = "🔒 Thread resolved by @{$resolvedBy}";
        
        try {
            $this->github->pullRequests()->createComment($owner, $repo, $number, $resolutionMessage);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateComment(string $owner, string $repo, int $commentId, string $newBody): bool
    {
        try {
            // Try as PR comment first
            $this->github->pullRequests()->updateComment($owner, $repo, $commentId, $newBody);
            return true;
        } catch (\Exception $e) {
            try {
                // Fall back to issue comment
                $this->github->issues()->updateComment($owner, $repo, $commentId, $newBody);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function deleteComment(string $owner, string $repo, int $commentId): bool
    {
        try {
            // Try as PR comment first
            $this->github->pullRequests()->deleteComment($owner, $repo, $commentId);
            return true;
        } catch (\Exception $e) {
            try {
                // Fall back to issue comment
                $this->github->issues()->deleteComment($owner, $repo, $commentId);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function getThreadSummary(string $owner, string $repo, int $number): array
    {
        $threads = $this->getThreadsForPullRequest($owner, $repo, $number);
        
        $summary = [
            'total' => $threads->count(),
            'open' => $threads->where('status', 'open')->count(),
            'resolved' => $threads->where('status', 'resolved')->count(),
            'outdated' => $threads->where('status', 'outdated')->count(),
            'by_type' => [
                'review' => $threads->where('type', 'review')->count(),
                'general' => $threads->where('type', 'general')->count(),
                'suggestion' => $threads->where('type', 'suggestion')->count(),
                'issue' => $threads->where('type', 'issue')->count(),
            ],
            'needs_attention' => $threads->where('status', 'open')->count(),
        ];
        
        return $summary;
    }

    private function groupReviewComments(Collection $reviewComments): Collection
    {
        // Group by file path and line number
        $grouped = $reviewComments->groupBy(function ($comment) {
            return ($comment['path'] ?? 'unknown') . ':' . ($comment['line'] ?? 0);
        });
        
        return $grouped->map(function (Collection $comments, string $key) {
            [$filePath, $lineNumber] = explode(':', $key);
            
            return CommentThread::fromComments($comments, [
                'type' => 'review',
                'file_path' => $filePath !== 'unknown' ? $filePath : null,
                'line_number' => (int)$lineNumber ?: null,
            ]);
        })->values();
    }

    private function groupGeneralComments(Collection $generalComments): Collection
    {
        // For now, treat each comment as its own thread
        // In future, we could add reply detection based on @mentions or timing
        
        return $generalComments->map(function ($comment) {
            // Convert the comment to array format if it's a DTO
            $commentArray = is_array($comment) ? $comment : $this->convertCommentToArray($comment);
            
            return CommentThread::fromComments(collect([$commentArray]), [
                'type' => 'general',
            ]);
        });
    }

    private function convertCommentToArray($comment): array
    {
        // Handle both DTO objects and arrays
        if (is_array($comment)) {
            return $comment;
        }
        
        // Convert DTO to array format expected by CommentThread
        return [
            'id' => $comment->id ?? uniqid(),
            'body' => $comment->body ?? '',
            'user' => [
                'login' => $comment->user->login ?? ($comment->author ?? 'Unknown')
            ],
            'created_at' => $comment->createdAt ?? ($comment->created_at ?? now()->toISOString()),
            'updated_at' => $comment->updatedAt ?? ($comment->updated_at ?? now()->toISOString()),
            'path' => $comment->path ?? null,
            'line' => $comment->line ?? null,
        ];
    }

    public function searchThreads(Collection $threads, array $filters = []): Collection
    {
        $filtered = $threads;
        
        if (isset($filters['status'])) {
            $filtered = $filtered->where('status', $filters['status']);
        }
        
        if (isset($filters['type'])) {
            $filtered = $filtered->where('type', $filters['type']);
        }
        
        if (isset($filters['author'])) {
            $filtered = $filtered->filter(function (CommentThread $thread) use ($filters) {
                return $thread->getParticipants()->contains($filters['author']);
            });
        }
        
        if (isset($filters['file'])) {
            $pattern = $filters['file'];
            $filtered = $filtered->filter(function (CommentThread $thread) use ($pattern) {
                if (!$thread->filePath) {
                    return false;
                }
                
                return fnmatch($pattern, $thread->filePath);
            });
        }
        
        return $filtered;
    }
}