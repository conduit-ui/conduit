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
        public array $requiredCapabilities = []
    ) {}

    /**
     * Create Component from array (for backward compatibility)
     */
    public static function fromArray(array $data): self
    {
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
            requiredCapabilities: $data['required_capabilities'] ?? []
        );
    }

    /**
     * Convert to array (for backward compatibility)
     */
    public function toArray(): array
    {
        return [
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
    }
}