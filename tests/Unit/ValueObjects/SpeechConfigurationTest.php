<?php

use App\ValueObjects\SpeechConfiguration;
use App\Enums\VoiceStyle;
use App\Enums\SpeechSpeed;

it('creates from options array', function () {
    $config = SpeechConfiguration::fromOptions([
        'voice' => 'dramatic',
        'speed' => 'fast',
        'include-stats' => true,
        'include-comments' => true,
        'claude' => 'custom prompt'
    ]);
    
    expect($config->voice)->toBe(VoiceStyle::Dramatic);
    expect($config->speed)->toBe(SpeechSpeed::Fast);
    expect($config->includeStats)->toBeTrue();
    expect($config->includeComments)->toBeTrue();
    expect($config->claudePrompt)->toBe('custom prompt');
});

it('uses default values when options are empty', function () {
    $config = SpeechConfiguration::fromOptions([]);
    
    expect($config->voice)->toBe(VoiceStyle::Default);
    expect($config->speed)->toBe(SpeechSpeed::Normal);
    expect($config->includeStats)->toBeFalse();
    expect($config->includeComments)->toBeFalse();
    expect($config->claudePrompt)->toBeNull();
});

it('identifies claude powered based on prompt presence', function () {
    $withClaude = SpeechConfiguration::fromOptions(['claude' => 'some prompt']);
    expect($withClaude->isClaudePowered())->toBeTrue();
    
    $withoutClaude = SpeechConfiguration::fromOptions([]);
    expect($withoutClaude->isClaudePowered())->toBeFalse();
});