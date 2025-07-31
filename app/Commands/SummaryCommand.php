<?php

namespace App\Commands;

use App\Services\ComponentService;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

/**
 * Enhanced summary command showing interactive mode status and contextual guidance
 *
 * Replaces the default Laravel Zero summary to provide better user experience
 * with prominent interactive mode status and actionable next steps.
 */
class SummaryCommand extends Command
{
    protected $signature = 'list {namespace? : The namespace name}
                            {--raw : To output raw command list}
                            {--format=txt : The output format (txt, xml, json, or md)}
                            {--short : To skip describing commands\' arguments}';

    protected $description = 'List commands with enhanced status information';

    public function handle(ComponentService $componentService): int
    {
        // Show standard command list first
        $this->showCommandList();

        // Add enhanced status section
        $this->showEnhancedStatus($componentService);

        return Command::SUCCESS;
    }

    protected function showCommandList(): void
    {
        $helper = new DescriptorHelper;
        $helper->describe(
            $this->output,
            $this->getApplication(),
            [
                'format' => $this->option('format'),
                'raw_text' => $this->option('raw'),
                'namespace' => $this->argument('namespace'),
                'short' => $this->option('short'),
            ]
        );
    }

    protected function showEnhancedStatus(ComponentService $componentService): void
    {
        $installed = $componentService->listInstalled();

        // Component Status
        if (empty($installed)) {
            warning('No components installed');
            note('Get started:');
            note('• Discover: conduit discover');
            note('• Install: conduit install <name>');
        } else {
            $componentCount = count($installed);
            $componentNames = implode(', ', array_map(fn ($c) => $c['name'], $installed));

            info("Components: {$componentCount} installed ({$componentNames})");
            note('Available actions:');
            note('• List: conduit list');
            note('• Discover: conduit discover');
        }

        // Quick Tips
        note('Tips:');
        note('• Run any command with --help for detailed usage');
        note('• Components are now managed via global Composer packages');
    }
}
