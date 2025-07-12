# Conduit API Reference

**Developer documentation for Conduit's internal APIs and interfaces**

---

## Table of Contents

- [Core Architecture](#core-architecture)
- [Component Interface](#component-interface)
- [Service APIs](#service-apis)
- [Storage System](#storage-system)
- [Context Detection](#context-detection)
- [Command Registration](#command-registration)
- [Events & Hooks](#events--hooks)

---

## Core Architecture

### Application Structure

```php
namespace App;

// Core Services
├── Services/
│   ├── ComponentManager           # Component lifecycle management
│   ├── ComponentDiscoveryService  # GitHub-based component discovery  
│   ├── ComponentInstallationService # Secure package installation
│   ├── ComponentStorage          # SQLite-based persistence
│   ├── ContextDetectionService   # Environment context analysis
│   └── SecurePackageInstaller    # Validated Composer operations
│
// Contracts & Interfaces
├── Contracts/
│   ├── ComponentInterface        # Standard component contract
│   ├── ComponentManagerInterface # Component management contract
│   ├── ComponentStorageInterface # Storage abstraction
│   └── PackageInstallerInterface # Installation abstraction
│
// Value Objects
├── ValueObjects/
│   ├── Component                 # Component data structure
│   └── ComponentActivation       # Activation configuration
│
// Commands (Laravel Zero)
└── Commands/
    ├── ComponentsCommand         # Component management CLI
    ├── ContextCommand           # Context display CLI
    ├── InteractiveCommand       # Interactive mode management
    └── SummaryCommand           # Enhanced command listing
```

---

## Component Interface

### ComponentInterface Contract

All Conduit components must implement the `ComponentInterface`:

```php
<?php

namespace App\Contracts;

interface ComponentInterface
{
    /**
     * Get the component's unique identifier
     */
    public function getName(): string;
    
    /**
     * Get the component's human-readable description
     */
    public function getDescription(): string;
    
    /**
     * Get the component's version
     */
    public function getVersion(): string;
    
    /**
     * Get the commands provided by this component
     * 
     * @return array<string> Array of command signatures
     */
    public function getCommands(): array;
    
    /**
     * Get the component's activation configuration
     * 
     * @return array{
     *     activation_events?: string[],
     *     exclude_events?: string[],
     *     always_active?: bool
     * }
     */
    public function getActivationConfig(): array;
    
    /**
     * Check if the component is active in the current context
     */
    public function isActive(array $context): bool;
    
    /**
     * Validate the component's configuration and dependencies
     */
    public function validate(): bool;
    
    /**
     * Get the component's required environment variables
     * 
     * @return array<string> Array of required env var names
     */
    public function getRequiredEnvVars(): array;
}
```

### Component Implementation Example

```php
<?php

namespace JordanPartridge\SpotifyZero;

use App\Contracts\ComponentInterface;

class SpotifyZeroComponent implements ComponentInterface
{
    public function getName(): string
    {
        return 'spotify-zero';
    }
    
    public function getDescription(): string
    {
        return 'Spotify integration for music control during development';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getCommands(): array
    {
        return [
            'spotify:play',
            'spotify:pause',
            'spotify:next',
            'spotify:previous',
            'spotify:playlist',
            'spotify:status'
        ];
    }
    
    public function getActivationConfig(): array
    {
        return [
            'activation_events' => ['context:development'],
            'always_active' => false,
            'exclude_events' => []
        ];
    }
    
    public function isActive(array $context): bool
    {
        return app()->environment('local', 'development');
    }
    
    public function validate(): bool
    {
        return !empty(env('SPOTIFY_CLIENT_ID')) && 
               !empty(env('SPOTIFY_CLIENT_SECRET'));
    }
    
    public function getRequiredEnvVars(): array
    {
        return [
            'SPOTIFY_CLIENT_ID',
            'SPOTIFY_CLIENT_SECRET',
            'SPOTIFY_REDIRECT_URI'
        ];
    }
}
```

---

## Service APIs

### ComponentManager

Central service for component lifecycle management:

```php
<?php

namespace App\Services;

class ComponentManager implements ComponentManagerInterface
{
    /**
     * Register a new component
     * 
     * @param string $name Component identifier
     * @param array{
     *     package: string,
     *     description?: string,
     *     version?: string,
     *     activation?: array
     * } $config Component configuration
     */
    public function register(string $name, array $config): bool;
    
    /**
     * Unregister a component
     */
    public function unregister(string $name): bool;
    
    /**
     * Get all registered components
     * 
     * @return array<string, Component>
     */
    public function getInstalled(): array;
    
    /**
     * Check if a component is installed
     */
    public function isInstalled(string $name): bool;
    
    /**
     * Check if a component is active in current context
     */
    public function isActive(string $name): bool;
    
    /**
     * Get active components for current context
     * 
     * @return array<string, Component>
     */
    public function getActive(): array;
    
    /**
     * Get global settings
     */
    public function getGlobalSetting(string $key, mixed $default = null): mixed;
    
    /**
     * Set global settings
     */
    public function setGlobalSetting(string $key, mixed $value): void;
    
    /**
     * Validate component configuration
     */
    public function validateComponent(string $name): array;
}
```

### ComponentDiscoveryService

GitHub-based component discovery:

```php
<?php

namespace App\Services;

class ComponentDiscoveryService
{
    /**
     * Discover components via GitHub search
     * 
     * @param array{
     *     topic?: string,
     *     organization?: string,
     *     user?: string,
     *     certified_only?: bool
     * } $filters Search filters
     * 
     * @return array{
     *     core: Component[],
     *     certified: Component[],
     *     community: Component[]
     * }
     */
    public function discover(array $filters = []): array;
    
    /**
     * Get component metadata from repository
     */
    public function getComponentMetadata(string $repository): ?array;
    
    /**
     * Check if repository is a valid Conduit component
     */
    public function isValidComponent(string $repository): bool;
    
    /**
     * Get component's composer.json configuration
     */
    public function getComposerConfig(string $repository): ?array;
    
    /**
     * Get component's README content
     */
    public function getReadme(string $repository): ?string;
}
```

### ContextDetectionService

Environment context analysis:

```php
<?php

namespace App\Services;

class ContextDetectionService
{
    /**
     * Get current directory context
     * 
     * @return array{
     *     working_directory: string,
     *     is_git_repo: bool,
     *     git?: array{
     *         current_branch: string,
     *         remote_url: string,
     *         is_github: bool,
     *         github_owner?: string,
     *         github_repo?: string,
     *         has_uncommitted_changes: bool
     *     },
     *     project_type?: string,
     *     languages: string[],
     *     frameworks: string[],
     *     package_managers: string[],
     *     ci_cd: string[],
     *     containers: string[]
     * }
     */
    public function getContext(): array;
    
    /**
     * Detect project type (laravel, wordpress, node, etc.)
     */
    public function detectProjectType(): ?string;
    
    /**
     * Detect programming languages in use
     * 
     * @return string[]
     */
    public function detectLanguages(): array;
    
    /**
     * Detect frameworks in use
     * 
     * @return string[]
     */
    public function detectFrameworks(): array;
    
    /**
     * Detect package managers
     * 
     * @return string[]
     */
    public function detectPackageManagers(): array;
    
    /**
     * Check if directory is a Git repository
     */
    public function isGitRepository(): bool;
    
    /**
     * Get Git repository information
     */
    public function getGitInfo(): ?array;
    
    /**
     * Check if Git repository is hosted on GitHub
     */
    public function isGitHubRepository(): bool;
}
```

---

## Storage System

### ComponentStorage

SQLite-based component persistence:

```php
<?php

namespace App\Services;

class ComponentStorage implements ComponentStorageInterface
{
    /**
     * Initialize storage database
     */
    public function initialize(): void;
    
    /**
     * Store component metadata
     */
    public function store(Component $component): bool;
    
    /**
     * Retrieve component by name
     */
    public function retrieve(string $name): ?Component;
    
    /**
     * Get all stored components
     * 
     * @return Component[]
     */
    public function all(): array;
    
    /**
     * Delete component from storage
     */
    public function delete(string $name): bool;
    
    /**
     * Check if component exists in storage
     */
    public function exists(string $name): bool;
    
    /**
     * Update component metadata
     */
    public function update(string $name, array $data): bool;
    
    /**
     * Store global settings
     */
    public function storeGlobalSetting(string $key, mixed $value): void;
    
    /**
     * Retrieve global settings
     */
    public function retrieveGlobalSetting(string $key, mixed $default = null): mixed;
}
```

### Database Schema

```sql
-- Component metadata storage
CREATE TABLE components (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    package VARCHAR(255) NOT NULL,
    version VARCHAR(50),
    description TEXT,
    activation_config JSON,
    installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Global settings storage
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key VARCHAR(255) UNIQUE NOT NULL,
    value JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Component activation events
CREATE TABLE activation_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    component_name VARCHAR(255),
    event_type VARCHAR(100),
    event_value VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (component_name) REFERENCES components(name)
);
```

---

## Context Detection

### Context Structure

```php
<?php

// Context array structure returned by ContextDetectionService::getContext()
return [
    'working_directory' => '/Users/developer/projects/my-app',
    'is_git_repo' => true,
    'git' => [
        'current_branch' => 'feature/spotify-integration',
        'remote_url' => 'https://github.com/jordanpartridge/conduit.git',
        'is_github' => true,
        'github_owner' => 'jordanpartridge',
        'github_repo' => 'conduit',
        'has_uncommitted_changes' => true
    ],
    'project_type' => 'laravel',
    'languages' => ['php', 'javascript'],
    'frameworks' => ['laravel', 'vue'],
    'package_managers' => ['composer', 'npm'],
    'ci_cd' => ['github_actions'],
    'containers' => ['docker']
];
```

### Activation Events

Components can declare activation events:

```php
// Component activation configuration
'activation' => [
    'activation_events' => [
        'context:git',          // Activate in Git repositories
        'context:github',       // Activate in GitHub repositories  
        'context:laravel',      // Activate in Laravel projects
        'language:php',         // Activate when PHP files present
        'framework:laravel',    // Activate when Laravel detected
        'package:composer',     // Activate when Composer is used
        'ci:github_actions'     // Activate when GitHub Actions detected
    ],
    'exclude_events' => [
        'context:wordpress'     // Don't activate in WordPress projects
    ],
    'always_active' => false    // Always active regardless of context
]
```

---

## Command Registration

### Laravel Zero Command Structure

Components register commands through service providers:

```php
<?php

namespace JordanPartridge\SpotifyZero;

use Illuminate\Support\ServiceProvider;

class SpotifyZeroServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register services
        $this->app->singleton(SpotifyClient::class, function ($app) {
            return new SpotifyClient(
                config('spotify.client_id'),
                config('spotify.client_secret')
            );
        });
    }
    
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\PlayCommand::class,
                Commands\PauseCommand::class,
                Commands\NextCommand::class,
                Commands\PreviousCommand::class,
                Commands\PlaylistCommand::class,
                Commands\StatusCommand::class,
            ]);
        }
        
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/spotify.php' => config_path('spotify.php'),
        ], 'config');
        
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/spotify.php', 'spotify'
        );
    }
}
```

### Command Implementation

```php
<?php

namespace JordanPartridge\SpotifyZero\Commands;

use LaravelZero\Framework\Commands\Command;
use JordanPartridge\SpotifyClient\SpotifyClient;

class PlayCommand extends Command
{
    protected $signature = 'spotify:play 
                            {track? : Track name to search and play}
                            {--playlist= : Playlist to play from}
                            {--device= : Spotify device to play on}';
    
    protected $description = 'Play music on Spotify';
    
    public function handle(SpotifyClient $spotify): int
    {
        $track = $this->argument('track');
        $playlist = $this->option('playlist');
        $device = $this->option('device');
        
        try {
            if ($track) {
                $result = $spotify->searchAndPlay($track, $device);
                $this->info("🎵 Playing: {$result['track']} by {$result['artist']}");
            } elseif ($playlist) {
                $result = $spotify->playPlaylist($playlist, $device);
                $this->info("🎶 Playing playlist: {$result['name']}");
            } else {
                $result = $spotify->resume($device);
                $this->info("▶️ Resumed playback");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Spotify error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
```

---

## Events & Hooks

### Component Lifecycle Events

```php
<?php

// Events dispatched during component lifecycle

namespace App\Events;

// Component installation
class ComponentInstalling extends Event
{
    public function __construct(
        public string $name,
        public string $package
    ) {}
}

class ComponentInstalled extends Event
{
    public function __construct(
        public string $name,
        public Component $component
    ) {}
}

// Component activation
class ComponentActivating extends Event
{
    public function __construct(
        public string $name,
        public array $context
    ) {}
}

class ComponentActivated extends Event
{
    public function __construct(
        public string $name,
        public Component $component
    ) {}
}

// Component uninstallation
class ComponentUninstalling extends Event
{
    public function __construct(
        public string $name
    ) {}
}

class ComponentUninstalled extends Event
{
    public function __construct(
        public string $name
    ) {}
}
```

### Event Listeners

Components can listen to system events:

```php
<?php

namespace JordanPartridge\SpotifyZero\Listeners;

use App\Events\ComponentActivated;

class SpotifyActivatedListener
{
    public function handle(ComponentActivated $event): void
    {
        if ($event->name === 'spotify-zero') {
            // Initialize Spotify client
            // Check authentication status
            // Setup default playlists
        }
    }
}
```

### Service Provider Event Registration

```php
<?php

namespace JordanPartridge\SpotifyZero;

use Illuminate\Support\ServiceProvider;
use App\Events\ComponentActivated;
use JordanPartridge\SpotifyZero\Listeners\SpotifyActivatedListener;

class SpotifyZeroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register event listeners
        $this->app['events']->listen(
            ComponentActivated::class,
            SpotifyActivatedListener::class
        );
    }
}
```

---

## Testing APIs

### Component Testing

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ComponentManager;
use JordanPartridge\SpotifyZero\SpotifyZeroComponent;

class SpotifyComponentTest extends TestCase
{
    public function test_component_implements_interface(): void
    {
        $component = new SpotifyZeroComponent();
        
        $this->assertInstanceOf(ComponentInterface::class, $component);
        $this->assertEquals('spotify-zero', $component->getName());
        $this->assertIsArray($component->getCommands());
        $this->assertContains('spotify:play', $component->getCommands());
    }
    
    public function test_component_activation(): void
    {
        $component = new SpotifyZeroComponent();
        $context = ['project_type' => 'laravel'];
        
        $this->assertTrue($component->isActive($context));
    }
    
    public function test_component_validation(): void
    {
        config(['spotify.client_id' => 'test_id']);
        config(['spotify.client_secret' => 'test_secret']);
        
        $component = new SpotifyZeroComponent();
        $this->assertTrue($component->validate());
    }
}
```

### Service Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ComponentManager;

class ComponentManagerTest extends TestCase
{
    public function test_component_registration(): void
    {
        $manager = app(ComponentManager::class);
        
        $result = $manager->register('test-component', [
            'package' => 'vendor/test-component',
            'description' => 'Test component'
        ]);
        
        $this->assertTrue($result);
        $this->assertTrue($manager->isInstalled('test-component'));
    }
    
    public function test_component_activation(): void
    {
        $manager = app(ComponentManager::class);
        
        // Register component with activation rules
        $manager->register('context-component', [
            'package' => 'vendor/context-component',
            'activation' => [
                'activation_events' => ['context:laravel']
            ]
        ]);
        
        // Mock Laravel context
        $this->mockContext(['project_type' => 'laravel']);
        
        $active = $manager->getActive();
        $this->assertArrayHasKey('context-component', $active);
    }
}
```

---

This API reference provides the technical foundation for building Conduit components and extending the platform's capabilities through its well-defined interfaces and services.