<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class CommentThread
{
    public function __construct(
        public readonly string $id,
        public readonly string $type, // 'review', 'general', 'suggestion', 'issue'
        public readonly ?string $filePath,
        public readonly ?int $lineNumber,
        public readonly string $status, // 'open', 'resolved', 'outdated'
        public readonly Collection $comments,
        public readonly ?string $resolvedBy = null,
        public readonly ?Carbon $resolvedAt = null,
        public readonly Carbon $createdAt = new Carbon,
        public readonly Carbon $updatedAt = new Carbon,
        public readonly array $metadata = []
    ) {}

    public static function fromComments(Collection $comments, array $options = []): self
    {
        $firstComment = $comments->first();
        $lastComment = $comments->last();

        return new self(
            id: $options['id'] ?? self::generateThreadId($comments),
            type: $options['type'] ?? self::detectThreadType($comments),
            filePath: $options['file_path'] ?? self::extractFilePath($comments),
            lineNumber: $options['line_number'] ?? self::extractLineNumber($comments),
            status: $options['status'] ?? 'open',
            comments: $comments->sortBy('created_at'),
            resolvedBy: $options['resolved_by'] ?? null,
            resolvedAt: isset($options['resolved_at']) ? Carbon::parse($options['resolved_at']) : null,
            createdAt: $firstComment ? Carbon::parse($firstComment['created_at']) : now(),
            updatedAt: $lastComment ? Carbon::parse($lastComment['updated_at']) : now(),
            metadata: $options['metadata'] ?? []
        );
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isOutdated(): bool
    {
        return $this->status === 'outdated';
    }

    public function getParticipants(): Collection
    {
        return $this->comments
            ->pluck('user.login')
            ->unique()
            ->values();
    }

    public function getLastActivity(): Carbon
    {
        return $this->comments
            ->map(fn ($comment) => Carbon::parse($comment['updated_at']))
            ->max() ?? $this->updatedAt;
    }

    public function getSummary(): string
    {
        $participantCount = $this->getParticipants()->count();
        $commentCount = $this->comments->count();

        $summary = "{$commentCount} comment".($commentCount !== 1 ? 's' : '');
        $summary .= " from {$participantCount} participant".($participantCount !== 1 ? 's' : '');

        if ($this->filePath) {
            $summary .= ' in '.basename($this->filePath);
            if ($this->lineNumber) {
                $summary .= ":{$this->lineNumber}";
            }
        }

        return $summary;
    }

    public function getStatusIcon(): string
    {
        return match ($this->status) {
            'resolved' => '✅',
            'outdated' => '⏸️',
            default => match ($this->type) {
                'review' => '🔧',
                'suggestion' => '💡',
                'issue' => '🐛',
                default => '💬'
            }
        };
    }

    private static function generateThreadId(Collection $comments): string
    {
        $firstComment = $comments->first();
        $hash = md5(json_encode([
            $firstComment['id'] ?? 'unknown',
            $firstComment['path'] ?? null,
            $firstComment['line'] ?? null,
        ]));

        return substr($hash, 0, 8);
    }

    private static function detectThreadType(Collection $comments): string
    {
        $firstComment = $comments->first();

        // PR review comments have path and line
        if (isset($firstComment['path']) && isset($firstComment['line'])) {
            return 'review';
        }

        // Check for suggestion keywords
        $body = strtolower($firstComment['body'] ?? '');
        if (str_contains($body, 'suggest') || str_contains($body, 'consider') || str_contains($body, 'recommendation')) {
            return 'suggestion';
        }

        // Check for issue keywords
        if (str_contains($body, 'bug') || str_contains($body, 'error') || str_contains($body, 'problem')) {
            return 'issue';
        }

        return 'general';
    }

    private static function extractFilePath(Collection $comments): ?string
    {
        return $comments->first()['path'] ?? null;
    }

    private static function extractLineNumber(Collection $comments): ?int
    {
        return $comments->first()['line'] ?? null;
    }
}
