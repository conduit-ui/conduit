<?php

namespace App\Commands\GitHub;

use App\Services\GithubAuthService;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\select;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

class AuthCommand extends Command
{
    protected $signature = 'github:auth 
                            {--status : Show current authentication status}
                            {--setup : Guide through authentication setup}
                            {--test : Test current authentication}';

    protected $description = 'Manage GitHub authentication';

    public function handle(GithubAuthService $githubAuth): int
    {
        if ($this->option('status')) {
            return $this->showStatus($githubAuth);
        }

        if ($this->option('setup')) {
            return $this->guideSetup($githubAuth);
        }

        if ($this->option('test')) {
            return $this->testAuth($githubAuth);
        }

        // Interactive mode - show menu
        return $this->showMenu($githubAuth);
    }

    private function showMenu(GithubAuthService $githubAuth): int
    {
        $status = $githubAuth->getAuthStatus();
        
        info('🐙 GitHub Authentication');
        $this->newLine();
        
        if ($status['authenticated']) {
            $this->line("✅ <fg=green>Authenticated via {$status['method']}</>");
        } else {
            $this->line("❌ <fg=red>Not authenticated</>");
        }
        
        $this->newLine();

        $options = [
            'status' => '📊 Show detailed status',
            'setup' => '🔧 Setup authentication',
            'test' => '🧪 Test current authentication',
            'exit' => '🚪 Exit'
        ];

        $action = select(
            label: 'What would you like to do?',
            options: $options,
            default: $status['authenticated'] ? 'status' : 'setup'
        );

        switch ($action) {
            case 'status':
                return $this->showStatus($githubAuth);
            case 'setup':
                return $this->guideSetup($githubAuth);
            case 'test':
                return $this->testAuth($githubAuth);
            case 'exit':
                return 0;
        }

        return 0;
    }

    private function showStatus(GithubAuthService $githubAuth): int
    {
        $status = $githubAuth->getAuthStatus();
        
        info('📊 GitHub Authentication Status');
        $this->newLine();
        
        $this->line("🔐 <fg=cyan>Overall Status:</> " . ($status['authenticated'] ? '<fg=green>Authenticated</>' : '<fg=red>Not Authenticated</>'));
        $this->line("🎯 <fg=cyan>Active Method:</> {$status['method']}");
        $this->newLine();
        
        $this->line("📋 <fg=cyan>Available Methods:</>");
        $this->line("   🌍 Environment Variable: " . ($status['env_token'] ? '<fg=green>✓ Set</>' : '<fg=yellow>✗ Not set</>'));
        $this->line("   🔧 GitHub CLI: " . ($status['gh_cli'] ? '<fg=green>✓ Authenticated</>' : '<fg=yellow>✗ Not authenticated</>'));
        $this->newLine();
        
        if ($status['authenticated']) {
            $this->line("💡 <fg=green>You're ready to use GitHub commands!</>");
        } else {
            $this->line("💡 <fg=yellow>Run 'conduit github:auth --setup' to get started</>");
        }

        return 0;
    }

    private function guideSetup(GithubAuthService $githubAuth): int
    {
        info('🔧 GitHub Authentication Setup');
        $this->newLine();
        
        $this->line("🎯 <fg=cyan>Choose your preferred authentication method:</>");
        $this->newLine();

        $methods = [
            'gh' => '🔧 GitHub CLI (Recommended) - Uses official gh auth',
            'token' => '🔑 Personal Access Token - Set GITHUB_TOKEN env var',
            'help' => '❓ Help - Show more information'
        ];

        $method = select(
            label: 'Authentication method:',
            options: $methods,
            default: 'gh'
        );

        switch ($method) {
            case 'gh':
                return $this->setupGitHubCli();
            case 'token':
                return $this->setupPersonalToken();
            case 'help':
                return $this->showSetupHelp();
        }

        return 0;
    }

    private function setupGitHubCli(): int
    {
        info('🔧 Setting up GitHub CLI Authentication');
        $this->newLine();
        
        // Check if gh is installed
        if (!$this->isGhInstalled()) {
            error('GitHub CLI is not installed');
            $this->newLine();
            $this->line("📦 <fg=yellow>Install GitHub CLI first:</>");
            $this->line("   🍎 macOS: brew install gh");
            $this->line("   🐧 Linux: https://github.com/cli/cli#installation");
            $this->line("   🪟 Windows: winget install GitHub.cli");
            return 1;
        }

        $this->line("✅ <fg=green>GitHub CLI is installed</>");
        $this->newLine();

        // Check current auth status
        $process = new \Symfony\Component\Process\Process(['gh', 'auth', 'status']);
        $process->run();

        if ($process->isSuccessful()) {
            info('✅ Already authenticated with GitHub CLI!');
            return 0;
        }

        $this->line("🔐 <fg=yellow>Starting GitHub CLI authentication...</>");
        $this->newLine();

        if (confirm('Start GitHub CLI login process?', true)) {
            // Launch gh auth login
            $this->line("🚀 <fg=cyan>Launching: gh auth login</>");
            $this->newLine();
            
            $loginProcess = new \Symfony\Component\Process\Process(['gh', 'auth', 'login']);
            $loginProcess->setTty(true);
            $loginProcess->run();

            if ($loginProcess->isSuccessful()) {
                info('✅ GitHub CLI authentication successful!');
                $this->newLine();
                $this->line("💡 <fg=green>You can now use all GitHub commands in Conduit</>");
                return 0;
            } else {
                error('GitHub CLI authentication failed');
                return 1;
            }
        }

        return 0;
    }

    private function setupPersonalToken(): int
    {
        info('🔑 Setting up Personal Access Token');
        $this->newLine();
        
        $this->line("📋 <fg=cyan>Steps to create a Personal Access Token:</>");
        $this->line("   1. Go to: https://github.com/settings/tokens");
        $this->line("   2. Click 'Generate new token (classic)'");
        $this->line("   3. Select scopes: repo, read:org, read:user");
        $this->line("   4. Copy the generated token");
        $this->newLine();
        
        $this->line("🔧 <fg=cyan>Then set it as an environment variable:</>");
        $this->line("   export GITHUB_TOKEN=your_token_here");
        $this->line("   # Or add to your .bashrc/.zshrc");
        $this->newLine();

        if (confirm('Open GitHub token settings in browser?', true)) {
            $this->openInBrowser('https://github.com/settings/tokens');
        }

        return 0;
    }

    private function showSetupHelp(): int
    {
        info('❓ GitHub Authentication Help');
        $this->newLine();
        
        $this->line("🎯 <fg=cyan>Why authenticate?</>");
        $this->line("   • Access your private repositories");
        $this->line("   • Higher API rate limits (5000 vs 60 requests/hour)");
        $this->line("   • Create issues, PRs, and manage repositories");
        $this->newLine();
        
        $this->line("🔧 <fg=cyan>GitHub CLI (Recommended):</>");
        $this->line("   • Official GitHub authentication");
        $this->line("   • Handles token refresh automatically");
        $this->line("   • Works with SSO and 2FA");
        $this->line("   • Same auth as 'gh' command");
        $this->newLine();
        
        $this->line("🔑 <fg=cyan>Personal Access Token:</>");
        $this->line("   • Direct token authentication");
        $this->line("   • Manual token management");
        $this->line("   • Good for CI/CD environments");
        $this->newLine();

        if (confirm('Ready to set up authentication?', true)) {
            return $this->guideSetup(app(GithubAuthService::class));
        }

        return 0;
    }

    private function testAuth(GithubAuthService $githubAuth): int
    {
        info('🧪 Testing GitHub Authentication');
        $this->newLine();

        $result = spin(
            callback: function () use ($githubAuth) {
                // Test authentication by making a simple API call
                try {
                    $token = $githubAuth->getToken();
                    if (!$token) {
                        return ['success' => false, 'error' => 'No token available'];
                    }

                    // Test with a simple user API call
                    $process = new \Symfony\Component\Process\Process([
                        'curl', '-s', '-H', "Authorization: token {$token}",
                        'https://api.github.com/user'
                    ]);
                    $process->run();

                    if ($process->isSuccessful()) {
                        $userData = json_decode($process->getOutput(), true);
                        return [
                            'success' => true,
                            'user' => $userData['login'] ?? 'unknown',
                            'rate_limit' => $this->getRateLimit($token)
                        ];
                    } else {
                        return ['success' => false, 'error' => 'API call failed'];
                    }
                } catch (\Exception $e) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
            },
            message: 'Testing API connection...'
        );

        if ($result['success']) {
            info("✅ Authentication successful!");
            $this->line("👤 <fg=cyan>User:</> {$result['user']}");
            if (isset($result['rate_limit'])) {
                $this->line("📊 <fg=cyan>Rate Limit:</> {$result['rate_limit']['remaining']}/{$result['rate_limit']['limit']} remaining");
            }
        } else {
            error("❌ Authentication failed: {$result['error']}");
            $this->newLine();
            $this->line("💡 <fg=yellow>Try running: conduit github:auth --setup</>");
        }

        return $result['success'] ? 0 : 1;
    }

    private function getRateLimit(string $token): ?array
    {
        try {
            $process = new \Symfony\Component\Process\Process([
                'curl', '-s', '-H', "Authorization: token {$token}",
                'https://api.github.com/rate_limit'
            ]);
            $process->run();

            if ($process->isSuccessful()) {
                $data = json_decode($process->getOutput(), true);
                return $data['rate']['core'] ?? null;
            }
        } catch (\Exception $e) {
            // Ignore rate limit check errors
        }

        return null;
    }

    private function isGhInstalled(): bool
    {
        $process = new \Symfony\Component\Process\Process(['which', 'gh']);
        $process->run();
        return $process->isSuccessful();
    }

    private function openInBrowser(string $url): void
    {
        $os = PHP_OS_FAMILY;
        try {
            switch ($os) {
                case 'Darwin':
                    shell_exec("open '{$url}' > /dev/null 2>&1");
                    break;
                case 'Windows':
                    shell_exec("start '{$url}' > /dev/null 2>&1");
                    break;
                case 'Linux':
                    shell_exec("xdg-open '{$url}' > /dev/null 2>&1");
                    break;
            }
            info("🌐 Opened in browser");
        } catch (\Exception $e) {
            error("Failed to open browser");
        }
    }
}