<?php

namespace App\Providers;

use App\Commands\GitHub\AuthCommand;
use App\Commands\GitHub\IssueAssignCommand;
use App\Commands\GitHub\IssueCloseCommand;
use App\Commands\GitHub\IssueCreateCommand;
use App\Commands\GitHub\IssueEditCommand;
use App\Commands\GitHub\IssueViewCommand;
use App\Commands\GitHub\PrAnalysisCommand;
use App\Commands\GitHub\PrCommentsCommand;
use App\Commands\GitHub\PrCreateCommand;
use App\Commands\GitHub\PrStatusCommand;
use App\Commands\GitHub\PrThreadsCommand;
use App\Commands\PrsCommand;
use App\Commands\StatusCommand;
use App\Contracts\GitHub\PrCreateInterface;
use App\Services\ComponentService;
use App\Services\GitHub\CommentThreadService;
use App\Services\GitHub\PrAnalysisService;
use App\Services\GitHub\PrCreateService;
use App\Services\GithubAuthService;
use App\Services\VoiceNarrationService;
use Illuminate\Support\Collection;
// GitHub client imports - only used if package is installed
use Illuminate\Support\ServiceProvider;

// GitHub client connector imports disabled during refactoring
// use JordanPartridge\GithubClient\Contracts\GithubConnectorInterface;
// use JordanPartridge\GithubClient\GithubConnector;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load globally installed components
        $this->loadGlobalComponents();

        // Register dynamic component delegation
        $this->registerComponentDelegation();

        // Register core commands
        $this->commands([
            StatusCommand::class,
            AuthCommand::class,
            IssueViewCommand::class,
            IssueCreateCommand::class,
            IssueEditCommand::class,
            IssueCloseCommand::class,
            IssueAssignCommand::class,
            PrCreateCommand::class,
            PrAnalysisCommand::class,
            PrStatusCommand::class,
            PrCommentsCommand::class,
            PrThreadsCommand::class,
            \App\Commands\PrAnalyzeCommand::class,
            \App\Commands\GitHubClientGapAnalysisCommand::class,
            \App\Commands\CodeRabbitStatusCommand::class,
            \App\Commands\IssuesSpeakCommand::class,
            \App\Commands\PrsSpeakCommand::class,
            \App\Commands\CodeRabbitSpeakCommand::class,
            \App\Commands\VoiceCommand::class,
            \App\Commands\ComponentConfigCommand::class,
            // \App\Commands\UpdateCommand::class, // Disabled - needs refactoring for new architecture
            // \App\Commands\System\CleanupCommand::class, // Disabled - uses old ComponentManager
            // \App\Commands\System\SyncComponentsCommand::class, // Disabled - uses old ComponentManager
            PrsCommand::class,
        ]);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // GitHub services - register GitHub client services
        $this->app->singleton(GithubAuthService::class);

        // Set a default GitHub token to prevent errors during service provider registration
        if (empty(config('github-client.token'))) {
            $authService = new GithubAuthService;
            $token = $authService->getToken();

            // Set the token in config so other providers don't throw errors
            config(['github-client.token' => $token ?: 'dummy_token_for_startup']);
        }

        // Override GitHub client with optional auth after registration
        // Disabled during GitHub client refactoring
        // $this->app->extend(GithubConnectorInterface::class, function ($connector, $app) {
        //     $authService = $app->make(GithubAuthService::class);
        //     $token = $authService->getToken();
        //
        //     // Return new connector with actual token or null
        //     return new GithubConnector($token);
        // });

        // Bind interfaces to implementations
        $this->app->singleton(PrCreateInterface::class, PrCreateService::class);

        // Register core services
        $this->app->singleton(ComponentService::class);
        $this->app->singleton(PrAnalysisService::class);
        $this->app->singleton(CommentThreadService::class);

        // Register global component discovery
        $this->app->singleton(\App\Services\GlobalComponentDiscovery::class);

        // Register standalone component discovery
        $this->app->singleton(\App\Services\StandaloneComponentDiscovery::class);

        // Register security validator
        $this->app->singleton(\App\Services\Security\ComponentSecurityValidator::class);

        // Register component delegation service
        $this->app->singleton(\App\Services\ComponentDelegationService::class);

        // Register voice narration system
        $this->registerVoiceNarrationSystem();
    }

    /**
     * Register the voice narration system with dependency injection
     */
    private function registerVoiceNarrationSystem(): void
    {
        // Register narrator collection factory
        $this->app->singleton('voice.narrators', function ($app) {
            $narrators = collect();

            // Register available narrators
            if (class_exists('App\Narrators\DefaultNarrator')) {
                $narrators->put('default', $app->make('App\Narrators\DefaultNarrator'));
            }

            if (class_exists('App\Narrators\ClaudeNarrator')) {
                $narrators->put('claude', $app->make('App\Narrators\ClaudeNarrator'));
            }

            // Add more narrators as they're implemented
            // $narrators->put('dramatic', $app->make('App\Narrators\DramaticNarrator'));
            // $narrators->put('sarcastic', $app->make('App\Narrators\SarcasticNarrator'));

            return $narrators;
        });

        // Register VoiceNarrationService with narrator collection
        $this->app->singleton(VoiceNarrationService::class, function ($app) {
            return new VoiceNarrationService(
                $app->make('voice.narrators')
            );
        });
    }

    /**
     * Register optional components from local JSON registry
     */
    private function registerOptionalComponents(): void
    {
        // Try multiple possible component file locations
        $possiblePaths = [
            config_path('components.json'),  // Development
            $_SERVER['HOME'].'/.conduit/config/components.json',  // Global installation
            base_path('config/components.json'),  // Fallback
        ];

        $componentsFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $componentsFile = $path;
                break;
            }
        }

        if (! $componentsFile) {
            return;
        }

        try {
            $components = json_decode(file_get_contents($componentsFile), true);

            foreach ($components['registry'] ?? [] as $name => $config) {
                if (($config['status'] ?? 'inactive') === 'active' &&
                    isset($config['service_provider']) &&
                    class_exists($config['service_provider'])) {

                    $this->app->register($config['service_provider']);
                }
            }
        } catch (\Exception $e) {
            // Silently fail - optional components shouldn't break the app
        }
    }

    /**
     * Check for component updates on startup
     */
    private function checkForComponentUpdates(): void
    {
        // Only check during commands, not tests
        if ($this->app->runningUnitTests()) {
            return;
        }

        try {
            // ComponentUpdateChecker disabled during architecture refactoring
            // $checker = $this->app->make(ComponentUpdateChecker::class);
            // $checker->displayUpdateStatus();
        } catch (\Exception $e) {
            // Fail gracefully - never break commands
        }
    }

    /**
     * Register component delegation commands dynamically
     */
    private function registerComponentDelegation(): void
    {
        try {
            $discovery = $this->app->make(\App\Services\StandaloneComponentDiscovery::class);
            $components = $discovery->discover();

            foreach ($components as $componentName => $component) {
                foreach ($component['commands'] as $commandName) {
                    $this->registerDelegatedCommand($componentName, $commandName, $component);
                }
            }
        } catch (\Exception $e) {
            // Fail gracefully - components are optional
        }
    }

    private function registerDelegatedCommand(string $componentName, string $commandName, array $component): void
    {
        $signature = "{$componentName}:{$commandName}";

        // Create a dynamic command class
        $commandClass = new class($signature, $component, $commandName) extends \LaravelZero\Framework\Commands\Command
        {
            protected $signature;

            protected $description;

            private array $component;

            private string $commandName;

            public function __construct(string $signature, array $component, string $commandName)
            {
                $this->signature = $signature.' {args?*}';  // Accept any arguments
                $this->description = "Execute {$commandName} command via {$component['name']} component";
                $this->component = $component;
                $this->commandName = $commandName;

                parent::__construct();
            }

            public function handle(): int
            {
                // Get all arguments passed to the command
                $arguments = [];
                $options = [];

                // Add positional arguments
                $args = $this->argument('args') ?? [];
                foreach ($args as $arg) {
                    if ($arg !== null && $arg !== '') {
                        $arguments[] = $arg;
                    }
                }

                // Get all options
                foreach ($this->options() as $key => $value) {
                    if ($value !== false && $value !== null && $value !== '') {
                        $options[$key] = $value;
                    }
                }

                $this->line("🔗 Delegating to {$this->component['name']}: {$this->commandName}");

                $delegationService = app(\App\Services\ComponentDelegationService::class);

                return $delegationService->delegate($this->component, $this->commandName, $arguments, $options);
            }
        };

        $this->commands([$commandClass]);
    }

    /**
     * Load globally installed Composer components
     */
    private function loadGlobalComponents(): void
    {

        $isVerbose = in_array('-v', $_SERVER['argv'] ?? []) || in_array('--verbose', $_SERVER['argv'] ?? []);

        try {
            $discovery = $this->app->make(\App\Services\GlobalComponentDiscovery::class);
            $components = $discovery->discover();

            foreach ($components as $component) {
                $discovery->loadComponent($component);
            }

            if ($isVerbose && $components->count() > 0) {
                $this->app->make('log')->info("Discovered {$components->count()} global components");
            }
        } catch (\Exception $e) {
            // Fail gracefully - global components are optional
            if ($isVerbose) {
                $this->app->make('log')->warning('Failed to load global components: '.$e->getMessage());
            }
        }
    }
}
