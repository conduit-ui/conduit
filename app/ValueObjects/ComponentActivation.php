<?php

namespace App\ValueObjects;

/**
 * Value object representing component activation configuration
 */
class ComponentActivation
{
    public function __construct(
        public readonly array $events = [],
        public readonly bool $alwaysActive = false,
        public readonly array $excludeEvents = []
    ) {}

    /**
     * Check if component should be active based on current events
     */
    public function shouldActivate(array $currentEvents): bool
    {
        // Always active components
        if ($this->alwaysActive) {
            return true;
        }

        // Check exclusions first
        foreach ($this->excludeEvents as $excludeEvent) {
            if (in_array($excludeEvent, $currentEvents)) {
                return false;
            }
        }

        // If no activation events specified, component is always active
        if (empty($this->events)) {
            return true;
        }

        // Check if any activation event matches
        foreach ($this->events as $event) {
            if (in_array($event, $currentEvents)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create from array configuration
     */
    public static function fromArray(array $config): self
    {
        return new self(
            events: $config['activation_events'] ?? [],
            alwaysActive: $config['always_active'] ?? false,
            excludeEvents: $config['exclude_events'] ?? []
        );
    }
}
