<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\table;

/**
 * Simple command to list globally installed Conduit components
 *
 * Replaces the complex component discovery system with direct
 * composer global show operations.
 */
class ListComponentsCommand extends Command
{
    protected $signature = 'list:components 
                            {--all : Show all global packages, not just Conduit components}';

    protected $description = 'List globally installed Conduit components';

    public function handle(): int
    {
        $showAll = $this->option('all');

        $this->info('Checking globally installed packages...');

        // Get all globally installed packages
        $process = new Process(['composer', 'global', 'show', '--format=json']);
        $process->run();

        if ($process->getExitCode() !== 0) {
            $this->error('Failed to retrieve global packages.');
            $this->line('Error: '.$process->getErrorOutput());

            return Command::FAILURE;
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        if (! isset($data['installed'])) {
            $this->warn('No global packages found.');

            return Command::SUCCESS;
        }

        $packages = $data['installed'];

        // Filter for Conduit components unless --all is specified
        if (! $showAll) {
            $packages = array_filter($packages, function ($package) {
                return str_starts_with($package['name'], 'jordanpartridge/conduit-');
            });
        }

        if (empty($packages)) {
            if ($showAll) {
                $this->info('No global packages are installed.');
            } else {
                $this->info('No Conduit components are installed globally.');
                $this->newLine();
                $this->line('💡 Install components with: conduit install <component>');
                $this->line('   Available components: knowledge, spotify, env-manager, github');
            }

            return Command::SUCCESS;
        }

        // Prepare table data
        $tableData = [];
        foreach ($packages as $package) {
            $componentName = $this->extractComponentName($package['name']);

            $tableData[] = [
                'Component' => $componentName,
                'Package' => $package['name'],
                'Version' => $package['version'] ?? 'N/A',
                'Description' => $package['description'] ?? 'N/A',
            ];
        }

        // Sort by component name
        usort($tableData, fn ($a, $b) => strcmp($a['Component'], $b['Component']));

        $this->newLine();
        $title = $showAll ? 'All Global Packages' : 'Installed Conduit Components';
        $this->line("<fg=green>{$title}</>");
        $this->newLine();

        table(['Component', 'Package', 'Version', 'Description'], $tableData);

        $this->newLine();
        $this->line('💡 Manage components:');
        $this->line('   • conduit install <component>   - Install a component');
        $this->line('   • conduit uninstall <component> - Remove a component');

        return Command::SUCCESS;
    }

    /**
     * Extract component name from package name
     */
    private function extractComponentName(string $packageName): string
    {
        // Handle Conduit components
        if (str_starts_with($packageName, 'jordanpartridge/conduit-')) {
            return substr($packageName, 24); // Remove 'jordanpartridge/conduit-' prefix
        }

        // For non-Conduit packages when --all is used
        return $packageName;
    }
}
