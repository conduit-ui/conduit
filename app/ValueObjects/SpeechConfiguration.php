<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\VoiceStyle;
use App\Enums\SpeechSpeed;

class SpeechConfiguration
{
    public function __construct(
        public readonly VoiceStyle $voice,
        public readonly SpeechSpeed $speed,
        public readonly bool $includeStats = false,
        public readonly bool $includeComments = false,
        public readonly ?string $claudePrompt = null,
    ) {}

    public static function fromOptions(array $options): self
    {
        return new self(
            voice: VoiceStyle::tryFrom($options['voice'] ?? 'default') ?? VoiceStyle::Default,
            speed: SpeechSpeed::tryFrom($options['speed'] ?? 'normal') ?? SpeechSpeed::Normal,
            includeStats: $options['include-stats'] ?? false,
            includeComments: $options['include-comments'] ?? false,
            claudePrompt: $options['claude'] ?? null,
        );
    }

    public function isClaudePowered(): bool
    {
        return !is_null($this->claudePrompt);
    }
}