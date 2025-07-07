<?php

namespace App\ValueObjects;

readonly class Component
{
    public function __construct(
        public string $name,
        public string $fullName,
        public string $description,
        public string $url,
        public array $topics = [],
        public int $stars = 0,
        public string $language = 'Unknown',
        public string $license = 'No license',
        public string $status = 'active',
        public array $requiredCapabilities = [],
        public ?ComponentActivation $activation = null
    ) {}

    /**
     * Create Component from array (for backward compatibility)
     */
    public static function fromArray(array $data): self
    {
        $activation = null;
        if (isset($data['activation']) || isset($data['activation_events'])) {
            $activationData = $data['activation'] ?? ['activation_events' => $data['activation_events'] ?? []];
            $activation = ComponentActivation::fromArray($activationData);
        }

        return new self(
            name: $data['name'],
            fullName: $data['full_name'],
            description: $data['description'],
            url: $data['url'],
            topics: $data['topics'] ?? [],
            stars: $data['stars'] ?? 0,
            language: $data['language'] ?? 'Unknown',
            license: $data['license'] ?? 'No license',
            status: $data['status'] ?? 'active',
            requiredCapabilities: $data['required_capabilities'] ?? [],
            activation: $activation
        );
    }

    /**
     * Convert to array (for backward compatibility)
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'full_name' => $this->fullName,
            'description' => $this->description,
            'url' => $this->url,
            'topics' => $this->topics,
            'stars' => $this->stars,
            'language' => $this->language,
            'license' => $this->license,
            'status' => $this->status,
            'required_capabilities' => $this->requiredCapabilities,
        ];

        if ($this->activation) {
            $data['activation'] = [
                'activation_events' => $this->activation->events,
                'always_active' => $this->activation->alwaysActive,
                'exclude_events' => $this->activation->excludeEvents,
            ];
        }

        return $data;
    }
}
