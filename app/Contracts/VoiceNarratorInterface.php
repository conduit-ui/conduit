<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\NarrationContent;
use App\ValueObjects\SpeechConfiguration;

interface VoiceNarratorInterface
{
    public function generate(NarrationContent $content, SpeechConfiguration $config): string;

    public function supports(string $contentType): bool;
}
