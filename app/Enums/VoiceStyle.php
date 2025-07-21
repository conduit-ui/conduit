<?php

declare(strict_types=1);

namespace App\Enums;

enum VoiceStyle: string
{
    case Default = 'default';
    case Dramatic = 'dramatic';
    case Sarcastic = 'sarcastic';
    case Coach = 'coach';
    case Robot = 'robot';
    case Reviewer = 'reviewer';
    case Executive = 'executive';
    case Zen = 'zen';
    case Pirate = 'pirate';
    case Documentary = 'documentary';
}
