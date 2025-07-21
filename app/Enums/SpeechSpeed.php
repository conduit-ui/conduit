<?php

declare(strict_types=1);

namespace App\Enums;

enum SpeechSpeed: string
{
    case Slow = 'slow';
    case Normal = 'normal';
    case Fast = 'fast';
    case Blazing = 'blazing';
}
