<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Component metadata loaded from conduit.json manifest
 */
class ComponentMetadata
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $name,
        public readonly string $description,
        public readonly string $version,
        public readonly array $commands,
        public readonly string $minConduitVersion,
        public readonly array $dependencies = [],
        public readonly array $tags = [],
        public readonly ?array $author = null,
        public readonly ?string $homepage = null,
        public readonly ?string $repository = null,
        public readonly ?string $license = null
    ) {
        $this->validateNamespace($namespace);
        $this->validateCommands($commands);
    }

    /**
     * Create ComponentMetadata from conduit.json file
     */
    public static function fromManifestFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Manifest file not found: {$path}");
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid JSON in manifest: " . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    /**
     * Create ComponentMetadata from array data
     */
    public static function fromArray(array $data): self
    {
        $required = ['namespace', 'name', 'description', 'version', 'commands'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        return new self(
            namespace: $data['namespace'],
            name: $data['name'],
            description: $data['description'],
            version: $data['version'],
            commands: $data['commands'],
            minConduitVersion: $data['min_conduit_version'] ?? '1.0.0',
            dependencies: $data['dependencies'] ?? [],
            tags: $data['tags'] ?? [],
            author: $data['author'] ?? null,
            homepage: $data['homepage'] ?? null,
            repository: $data['repository'] ?? null,
            license: $data['license'] ?? null
        );
    }

    /**
     * Get full command names with namespace prefix
     */
    public function getFullCommands(): array
    {
        return array_map(
            fn(string $command) => "{$this->namespace}:{$command}",
            $this->commands
        );
    }

    /**
     * Get commands as a collection for easier manipulation
     */
    public function getCommandsCollection(): Collection
    {
        return collect($this->getFullCommands());
    }

    /**
     * Check if this component provides a specific command
     */
    public function hasCommand(string $command): bool
    {
        // Support both namespaced and non-namespaced lookup
        $normalizedCommand = str_starts_with($command, $this->namespace . ':') 
            ? $command 
            : "{$this->namespace}:{$command}";

        return in_array($normalizedCommand, $this->getFullCommands());
    }

    /**
     * Check if component is compatible with given Conduit version
     */
    public function isCompatibleWith(string $conduitVersion): bool
    {
        return version_compare($conduitVersion, $this->minConduitVersion, '>=');
    }

    /**
     * Get component signature for registry purposes
     */
    public function getSignature(): string
    {
        return hash('sha256', json_encode([
            'namespace' => $this->namespace,
            'commands' => $this->commands,
            'version' => $this->version
        ]));
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'namespace' => $this->namespace,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'commands' => $this->commands,
            'min_conduit_version' => $this->minConduitVersion,
            'dependencies' => $this->dependencies,
            'tags' => $this->tags,
            'author' => $this->author,
            'homepage' => $this->homepage,
            'repository' => $this->repository,
            'license' => $this->license,
        ];
    }

    /**
     * Validate namespace format
     */
    private function validateNamespace(string $namespace): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $namespace)) {
            throw new InvalidArgumentException(
                "Invalid namespace format: {$namespace}. Must be lowercase with hyphens."
            );
        }

        if (strlen($namespace) < 2 || strlen($namespace) > 20) {
            throw new InvalidArgumentException(
                "Namespace must be between 2-20 characters: {$namespace}"
            );
        }
    }

    /**
     * Validate command format
     */
    private function validateCommands(array $commands): void
    {
        if (empty($commands)) {
            throw new InvalidArgumentException("Component must provide at least one command");
        }

        foreach ($commands as $command) {
            if (!preg_match('/^[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*$/', $command)) {
                throw new InvalidArgumentException(
                    "Invalid command format: {$command}. Must be lowercase with hyphens and optional colons."
                );
            }
        }
    }
}