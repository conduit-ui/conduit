<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class KnowledgeCaptured
{
    use Dispatchable;

    public function __construct(
        public int $entryId,
        public string $content,
        public ?string $commitSha,
        public ?string $repo,
        public ?string $branch,
        public array $tags = [],
        public string $captureType = 'manual' // 'manual', 'auto-commit', 'auto-failure'
    ) {}

    /**
     * Get the full GitHub commit URL if applicable
     */
    public function getCommitUrl(): ?string
    {
        if ($this->repo && $this->commitSha) {
            return "https://github.com/{$this->repo}/commit/{$this->commitSha}";
        }
        
        return null;
    }

    /**
     * Check if this was an auto-captured event
     */
    public function isAutoCapture(): bool
    {
        return str_starts_with($this->captureType, 'auto-');
    }
}