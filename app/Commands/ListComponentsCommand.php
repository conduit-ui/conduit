<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

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

        // Get all globally installed packages
        $result = spin(function () {
            $process = new Process(['composer', 'global', 'show', '--format=json']);
            $process->setEnv(['HOME' => getenv('HOME')]);
            $process->run();

            return [
                'success' => $process->getExitCode() === 0,
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
            ];
        }, 'Checking globally installed packages...');

        if (! $result['success']) {
            error('Failed to retrieve global packages.');
            error($result['error']);

            return Command::FAILURE;
        }

        $data = json_decode($result['output'], true);

        if (! isset($data['installed'])) {
            warning('No global packages found.');

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
                info('No global packages are installed.');
            } else {
                info('No Conduit components are installed globally.');
                info('💡 Install components with: conduit install <component>');
                info('   Available components: knowledge, spotify, env-manager, github');
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
        info($title);

        table(['Component', 'Package', 'Version', 'Description'], $tableData);

        $this->newLine();
        info('💡 Manage components:');
        info('   • conduit install <component>   - Install a component');
        info('   • conduit uninstall <component> - Remove a component');

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
