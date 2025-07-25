<?php

use App\Enums\VoiceStyle;

it('has correct voice style values', function () {
    expect(VoiceStyle::Default->value)->toBe('default');
    expect(VoiceStyle::Dramatic->value)->toBe('dramatic');
    expect(VoiceStyle::Sarcastic->value)->toBe('sarcastic');
    expect(VoiceStyle::Coach->value)->toBe('coach');
    expect(VoiceStyle::Robot->value)->toBe('robot');
    expect(VoiceStyle::Reviewer->value)->toBe('reviewer');
    expect(VoiceStyle::Executive->value)->toBe('executive');
    expect(VoiceStyle::Zen->value)->toBe('zen');
    expect(VoiceStyle::Pirate->value)->toBe('pirate');
    expect(VoiceStyle::Documentary->value)->toBe('documentary');
});

it('can list all cases', function () {
    $cases = VoiceStyle::cases();
    expect($cases)->toHaveCount(10);
});