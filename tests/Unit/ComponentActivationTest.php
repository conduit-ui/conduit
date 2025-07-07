<?php

use App\ValueObjects\ComponentActivation;

test('component activates with matching events', function () {
    $activation = new ComponentActivation(
        events: ['context:git', 'language:php'],
        alwaysActive: false,
        excludeEvents: []
    );
    
    $currentEvents = ['context:git', 'language:php', 'framework:laravel'];
    
    expect($activation->shouldActivate($currentEvents))->toBeTrue();
});

test('component does not activate without matching events', function () {
    $activation = new ComponentActivation(
        events: ['context:docker'],
        alwaysActive: false,
        excludeEvents: []
    );
    
    $currentEvents = ['context:git', 'language:php'];
    
    expect($activation->shouldActivate($currentEvents))->toBeFalse();
});

test('always active components activate regardless of events', function () {
    $activation = new ComponentActivation(
        events: [],
        alwaysActive: true,
        excludeEvents: []
    );
    
    $currentEvents = [];
    
    expect($activation->shouldActivate($currentEvents))->toBeTrue();
});

test('exclude events prevent activation', function () {
    $activation = new ComponentActivation(
        events: ['language:php'],
        alwaysActive: false,
        excludeEvents: ['context:wordpress']
    );
    
    $currentEvents = ['language:php', 'context:wordpress'];
    
    expect($activation->shouldActivate($currentEvents))->toBeFalse();
});

test('no activation events means always active', function () {
    $activation = new ComponentActivation(
        events: [],
        alwaysActive: false,
        excludeEvents: []
    );
    
    $currentEvents = ['context:git'];
    
    expect($activation->shouldActivate($currentEvents))->toBeTrue();
});

test('creates from array configuration', function () {
    $config = [
        'activation_events' => ['context:laravel'],
        'always_active' => false,
        'exclude_events' => ['context:wordpress']
    ];
    
    $activation = ComponentActivation::fromArray($config);
    
    expect($activation->events)->toBe(['context:laravel']);
    expect($activation->alwaysActive)->toBeFalse();
    expect($activation->excludeEvents)->toBe(['context:wordpress']);
});