<?php

declare(strict_types=1);

namespace App\Narrators;

use App\Contracts\VoiceNarratorInterface;
use App\Services\ClaudeNarrationService;
use App\ValueObjects\NarrationContent;
use App\ValueObjects\SpeechConfiguration;

class ClaudeNarrator implements VoiceNarratorInterface
{
    public function __construct(
        private readonly ClaudeNarrationService $claudeService
    ) {}

    public function generate(NarrationContent $content, SpeechConfiguration $config): string
    {
        if (!$config->claudePrompt) {
            throw new \InvalidArgumentException('Claude prompt is required for Claude narrator');
        }

        return $this->claudeService->generateNarration($content, $config->claudePrompt);
    }

    public function supports(string $contentType): bool
    {
        return in_array($contentType, ['issue', 'pull_request']);
    }
}