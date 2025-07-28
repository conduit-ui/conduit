<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class ComponentConfigCommand extends Command
{
    protected $signature = 'component:config';

    protected $description = 'Configure component scaffolding preferences (GitHub username, PHP namespace, etc.)';

    public function handle(): int
    {
        $this->info('🔧 Component Scaffolding Configuration');
        $this->newLine();

        // Get current settings (ensure strings)
        $currentGithubUsername = config('components.github_username') ?? '';
        $currentNamespace = config('components.php_namespace') ?? '';
        $currentEmail = config('components.author_email') ?? '';

        // GitHub Username
        $githubUsername = text(
            label: 'GitHub Username',
            placeholder: 'e.g., jordanpartridge',
            default: $currentGithubUsername,
            required: true,
            hint: 'Your GitHub username (lowercase, for package names)'
        );

        // PHP Namespace
        $phpNamespace = text(
            label: 'PHP Namespace',
            placeholder: 'e.g., JordanPartridge',
            default: $currentNamespace,
            required: true,
            hint: 'Your preferred PHP namespace prefix (PascalCase)'
        );

        // Author Email
        $authorEmail = text(
            label: 'Author Email',
            placeholder: 'e.g., jordan@example.com',
            default: $currentEmail,
            required: true,
            hint: 'Email for composer.json author field'
        );

        // Show preview
        $this->newLine();
        $this->info('📋 Configuration Preview:');
        $this->line("   GitHub Username: {$githubUsername}");
        $this->line("   PHP Namespace: {$phpNamespace}");
        $this->line("   Author Email: {$authorEmail}");
        $this->newLine();

        $exampleComponent = 'docker';
        $this->line('📦 Example for component "'.$exampleComponent.'":');
        $this->line("   Package: {$githubUsername}/conduit-{$exampleComponent}");
        $this->line("   Namespace: {$phpNamespace}\\Conduit".ucfirst($exampleComponent));
        $this->newLine();

        if (! confirm('Save these settings?', default: true)) {
            $this->info('Configuration cancelled.');

            return self::SUCCESS;
        }

        // Save to config
        $this->saveConfig([
            'github_username' => $githubUsername,
            'php_namespace' => $phpNamespace,
            'author_email' => $authorEmail,
        ]);

        $this->info('✅ Component configuration saved!');
        $this->line('   Use "component:config" anytime to update these settings.');

        return self::SUCCESS;
    }

    private function saveConfig(array $settings): void
    {
        $configPath = config_path('components.php');
        $config = file_exists($configPath) ? include $configPath : [];

        // Merge new settings
        $config = array_merge($config, $settings);

        // Generate new config file content
        $content = "<?php\n\nreturn ".var_export($config, true).";\n";

        file_put_contents($configPath, $content);
    }
}
