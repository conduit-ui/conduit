<?php

use App\Enums\SpeechSpeed;

it('has correct speech speed values', function () {
    expect(SpeechSpeed::Slow->value)->toBe('slow');
    expect(SpeechSpeed::Normal->value)->toBe('normal');
    expect(SpeechSpeed::Fast->value)->toBe('fast');
    expect(SpeechSpeed::Blazing->value)->toBe('blazing');
});

it('can list all cases', function () {
    $cases = SpeechSpeed::cases();
    expect($cases)->toHaveCount(4);
    expect(array_map(fn ($case) => $case->value, $cases))->toBe([
        'slow',
        'normal',
        'fast',
        'blazing',
    ]);
});
