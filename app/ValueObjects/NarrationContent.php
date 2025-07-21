<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Collection;

class NarrationContent
{
    public function __construct(
        public readonly string $type,
        public readonly int $number,
        public readonly string $title,
        public readonly string $description,
        public readonly string $state,
        public readonly string $author,
        public readonly array $metadata = [],
        public readonly ?Collection $comments = null,
        public readonly ?Collection $reviews = null,
    ) {}

    public static function fromIssue(array $issue, ?Collection $comments = null): self
    {
        return new self(
            type: 'issue',
            number: $issue['number'],
            title: $issue['title'],
            description: self::sanitizeText($issue['body'] ?? ''),
            state: $issue['state'],
            author: $issue['user']['login'] ?? 'Unknown',
            metadata: [
                'labels' => collect($issue['labels'] ?? [])->pluck('name')->toArray(),
                'assignees' => collect($issue['assignees'] ?? [])->pluck('login')->toArray(),
                'created_at' => $issue['created_at'] ?? null,
                'updated_at' => $issue['updated_at'] ?? null,
            ],
            comments: $comments,
        );
    }

    public static function fromPullRequest(array $pr, ?Collection $comments = null, ?Collection $reviews = null): self
    {
        return new self(
            type: 'pull_request',
            number: $pr['number'],
            title: $pr['title'],
            description: self::sanitizeText($pr['body'] ?? ''),
            state: $pr['state'],
            author: $pr['user']['login'] ?? 'Unknown',
            metadata: [
                'draft' => $pr['draft'] ?? false,
                'mergeable' => $pr['mergeable'] ?? null,
                'additions' => $pr['additions'] ?? 0,
                'deletions' => $pr['deletions'] ?? 0,
                'changed_files' => $pr['changed_files'] ?? 0,
                'commits' => $pr['commits'] ?? 0,
                'base_branch' => $pr['base']['ref'] ?? 'main',
                'head_branch' => $pr['head']['ref'] ?? 'feature',
                'created_at' => $pr['created_at'] ?? null,
                'updated_at' => $pr['updated_at'] ?? null,
            ],
            comments: $comments,
            reviews: $reviews,
        );
    }

    private static function sanitizeText(string $text): string
    {
        // Remove markdown formatting
        $text = strip_tags($text);

        // Remove excessive whitespace and line breaks
        $text = preg_replace('/\s+/', ' ', $text);

        // Truncate for speech
        if (strlen($text) > 300) {
            $text = substr($text, 0, 300).'... and more details';
        }

        return trim($text);
    }

    public function getStatsSummary(): string
    {
        if ($this->type === 'pull_request') {
            $additions = $this->metadata['additions'] ?? 0;
            $deletions = $this->metadata['deletions'] ?? 0;
            $files = $this->metadata['changed_files'] ?? 0;
            $commits = $this->metadata['commits'] ?? 0;

            return "Statistics: {$additions} additions, {$deletions} deletions, {$files} files changed, {$commits} commits";
        }

        return '';
    }

    public function getCommentsSummary(): string
    {
        if (! $this->comments || $this->comments->isEmpty()) {
            return 'No comments yet.';
        }

        $total = $this->comments->count();
        $recentComments = $this->comments->sortByDesc('created_at')->take(3);

        $summary = "There are {$total} comments. ";

        if ($total <= 3) {
            $summary .= 'Recent discussion includes: ';
            $summary .= $recentComments->map(function ($comment) {
                $author = $comment['user']['login'] ?? 'Someone';
                $snippet = substr(strip_tags($comment['body'] ?? ''), 0, 50);

                return "{$author} said: {$snippet}";
            })->join('. ');
        } else {
            $summary .= 'Most recent comments from: ';
            $summary .= $recentComments->pluck('user.login')->unique()->join(', ');
        }

        return $summary;
    }

    public function getReviewsSummary(): string
    {
        if (! $this->reviews || $this->reviews->isEmpty()) {
            return 'No reviews yet.';
        }

        $approved = $this->reviews->where('state', 'APPROVED')->count();
        $requestedChanges = $this->reviews->where('state', 'CHANGES_REQUESTED')->count();
        $commented = $this->reviews->where('state', 'COMMENTED')->count();

        $parts = [];
        if ($approved > 0) {
            $parts[] = "{$approved} approvals";
        }
        if ($requestedChanges > 0) {
            $parts[] = "{$requestedChanges} change requests";
        }
        if ($commented > 0) {
            $parts[] = "{$commented} review comments";
        }

        return 'Review status: '.implode(', ', $parts);
    }
}
