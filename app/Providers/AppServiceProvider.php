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
use JordanPartridge\GithubClient\Contracts\GithubConnectorInterface;
use JordanPartridge\GithubClient\GithubConnector;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load globally installed components
        $this->loadGlobalComponents();

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
            \App\Commands\UpdateCommand::class,
            \App\Commands\System\CleanupCommand::class,
            \App\Commands\System\SyncComponentsCommand::class,
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
        $this->app->extend(GithubConnectorInterface::class, function ($connector, $app) {
            $authService = $app->make(GithubAuthService::class);
            $token = $authService->getToken();

            // Return new connector with actual token or null
            return new GithubConnector($token);
        });

        // Bind interfaces to implementations
        $this->app->singleton(PrCreateInterface::class, PrCreateService::class);

        // Register core services
        $this->app->singleton(ComponentService::class);
        $this->app->singleton(PrAnalysisService::class);
        $this->app->singleton(CommentThreadService::class);

        // Register global component discovery
        $this->app->singleton(\App\Services\GlobalComponentDiscovery::class);

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
            $checker = $this->app->make(ComponentUpdateChecker::class);
            $checker->displayUpdateStatus();
        } catch (\Exception $e) {
            // Fail gracefully - never break commands
        }
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
