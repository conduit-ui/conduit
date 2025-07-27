<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\ValueObjects\CommentThread;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use JordanPartridge\GithubClient\GitHub;

class CommentThreadService
{
    public function __construct(
        private readonly GitHub $github
    ) {}

    public function getThreadsForPullRequest(string $owner, string $repo, int $prNumber): Collection
    {
        // Get PR discussion comments (where replies happen) using issues API
        $discussionComments = collect($this->github->issues()->comments($owner, $repo, $prNumber));

        // Get file-specific review comments using PR API
        $reviewComments = collect($this->github->pullRequests()->comments($owner, $repo, $prNumber));

        // Get review-level comments
        $reviews = $this->github->pullRequests()->reviews($owner, $repo, $prNumber);

        $threads = collect();

        // Process discussion comments (PR-level, with reply support)
        if ($discussionComments->isNotEmpty()) {
            $discussionThreads = $this->groupGeneralComments($discussionComments);
            $threads = $threads->merge($discussionThreads);
        }

        // Process file-specific review comments
        if ($reviewComments->isNotEmpty()) {
            $fileReviewThreads = $this->groupFileReviewComments($reviewComments);
            $threads = $threads->merge($fileReviewThreads);
        }

        // Process review-level comments (overall PR feedback)
        if ($reviews->isNotEmpty()) {
            $reviewLevelThreads = $this->groupReviewComments($reviews);
            $threads = $threads->merge($reviewLevelThreads);
        }

        return $threads->sortByDesc(fn (CommentThread $thread) => $thread->getLastActivity());
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
        if (! $thread) {
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

    private function groupGeneralComments(Collection $generalComments): Collection
    {
        // Group comments by GitHub's native reply relationships
        $commentsArray = $generalComments->map(function ($comment) {
            return is_array($comment) ? $comment : $this->convertCommentToArray($comment);
        });

        // Build thread groups using GitHub's in_reply_to_id
        $threadGroups = collect();
        $processedComments = collect();

        foreach ($commentsArray as $comment) {
            if ($processedComments->contains('id', $comment['id'])) {
                continue;
            }

            // If this is a top-level comment (no in_reply_to_id), start a new thread
            if (! isset($comment['in_reply_to_id']) || $comment['in_reply_to_id'] === null) {
                $threadComments = $this->findRepliesRecursively($comment, $commentsArray, $processedComments);

                $threadGroups->push(CommentThread::fromComments($threadComments, [
                    'type' => 'general',
                ]));
            }
        }

        return $threadGroups;
    }

    private function groupFileReviewComments(Collection $reviewComments): Collection
    {
        // Group file-specific review comments by file path and line number
        $commentsArray = $reviewComments->map(function ($comment) {
            return is_array($comment) ? $comment : $this->convertCommentToArray($comment);
        });

        // Group by file path and line number for file-specific discussions
        $grouped = $commentsArray->groupBy(function ($comment) {
            $filePath = $comment['path'] ?? 'unknown';
            $lineNumber = $comment['line'] ?? $comment['original_line'] ?? 0;

            return $filePath.':'.$lineNumber;
        });

        return $grouped->map(function (Collection $comments, string $key) {
            [$filePath, $lineNumber] = explode(':', $key);

            return CommentThread::fromComments($comments->sortBy('created_at'), [
                'type' => 'review',
                'file_path' => $filePath !== 'unknown' ? $filePath : null,
                'line_number' => (int) $lineNumber ?: null,
            ]);
        })->values();
    }

    private function findRepliesRecursively(array $rootComment, Collection $allComments, Collection $processedComments): Collection
    {
        $threadComments = collect([$rootComment]);
        $processedComments->push(['id' => $rootComment['id']]);

        // Find all direct replies to this comment
        $replies = $allComments->filter(function ($comment) use ($rootComment) {
            return isset($comment['in_reply_to_id']) &&
                   $comment['in_reply_to_id'] == $rootComment['id'];
        });

        // Recursively find replies to replies
        foreach ($replies as $reply) {
            if (! $processedComments->contains('id', $reply['id'])) {
                $replyThread = $this->findRepliesRecursively($reply, $allComments, $processedComments);
                $threadComments = $threadComments->merge($replyThread);
            }
        }

        return $threadComments->sortBy('created_at');
    }

    private function findRelatedComments(array $rootComment, Collection $allComments, Collection $processedComments): Collection
    {
        $threadComments = collect([$rootComment]);
        $rootAuthor = $rootComment['user']['login'];
        $rootTime = Carbon::parse($rootComment['created_at']);

        // Find comments that are likely part of the same thread
        foreach ($allComments as $comment) {
            if ($comment['id'] === $rootComment['id'] ||
                $processedComments->contains('id', $comment['id'])) {
                continue;
            }

            $commentTime = Carbon::parse($comment['created_at']);
            $commentAuthor = $comment['user']['login'];
            $commentBody = strtolower($comment['body'] ?? '');

            // Thread detection logic:

            // 1. Direct reply relationship (if available in API)
            if (isset($comment['in_reply_to']) && $comment['in_reply_to'] == $rootComment['id']) {
                $threadComments->push($comment);

                continue;
            }

            // 2. @mention of the root comment author
            if (str_contains($commentBody, '@'.strtolower($rootAuthor))) {
                $threadComments->push($comment);

                continue;
            }

            // 3. Same author continuing conversation (within reasonable time)
            if ($commentAuthor === $rootAuthor &&
                $commentTime->diffInHours($rootTime) <= 6) {
                $threadComments->push($comment);

                continue;
            }

            // 4. Back-and-forth conversation pattern (alternating authors within timeframe)
            if ($threadComments->count() > 1) {
                $lastComment = $threadComments->last();
                $lastAuthor = $lastComment['user']['login'];
                $lastTime = Carbon::parse($lastComment['created_at']);

                if ($commentAuthor !== $lastAuthor &&
                    $commentTime->diffInHours($lastTime) <= 2 &&
                    ($commentAuthor === $rootAuthor || $lastAuthor === $rootAuthor)) {
                    $threadComments->push($comment);

                    continue;
                }
            }
        }

        return $threadComments->sortBy('created_at');
    }

    private function groupReviewComments(Collection $reviews): Collection
    {
        $threads = collect();

        // Get all individual review comments (file-specific comments)
        $allReviewComments = collect();

        foreach ($reviews as $review) {
            // Process review-level comments (general review feedback)
            if (! empty($review->body)) {
                $reviewComment = [
                    'id' => $review->id ?? 'review-'.uniqid(),
                    'body' => $review->body ?? '',
                    'user' => [
                        'login' => $review->user->login ?? 'Unknown',
                    ],
                    'created_at' => $review->submittedAt ?? now()->toISOString(),
                    'updated_at' => $review->submittedAt ?? now()->toISOString(),
                    'path' => null, // Reviews are not file-specific
                    'line' => null,
                ];

                $threads->push(CommentThread::fromComments(collect([$reviewComment]), [
                    'type' => 'review',
                ]));
            }
        }

        return $threads;
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
                'login' => $comment->user->login ?? ($comment->author ?? 'Unknown'),
            ],
            'created_at' => $comment->createdAt ?? ($comment->created_at ?? now()->toISOString()),
            'updated_at' => $comment->updatedAt ?? ($comment->updated_at ?? now()->toISOString()),
            'path' => $comment->path ?? null,
            'line' => $comment->line ?? null,
            'in_reply_to_id' => $comment->in_reply_to_id ?? ($comment->inReplyToId ?? null),
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
                if (! $thread->filePath) {
                    return false;
                }

                return fnmatch($pattern, $thread->filePath);
            });
        }

        return $filtered;
    }
}
