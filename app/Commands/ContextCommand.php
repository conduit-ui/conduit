<?php

namespace App\Commands;

use App\Services\ContextDetectionService;
use LaravelZero\Framework\Commands\Command;

class ContextCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'context {--json : Output as JSON}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display context information about the current directory';

    /**
     * Execute the console command.
     */
    public function handle(ContextDetectionService $contextService): int
    {
        $context = $contextService->getContext();

        if ($this->option('json')) {
            $this->line(json_encode($context, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->displayContext($context, $contextService);

        return self::SUCCESS;
    }

    /**
     * Display context in a human-readable format
     */
    private function displayContext(array $context, ContextDetectionService $contextService): void
    {
        $this->info('Current Directory Context');
        $this->line('');

        // Working directory
        $this->line('<comment>Working Directory:</comment> '.$context['working_directory']);
        $this->line('');

        // Git information
        if ($context['is_git_repo']) {
            $this->line('<comment>Version Control:</comment> Git Repository');

            if ($context['git']) {
                $git = $context['git'];
                $this->line('  Branch: '.($git['current_branch'] ?? 'unknown'));

                if ($git['is_github']) {
                    $this->line('  GitHub: '.$git['github_owner'].'/'.$git['github_repo']);
                }

                if ($git['has_uncommitted_changes']) {
                    $this->line('  <fg=yellow>⚠ Uncommitted changes detected</>');
                }
            }
            $this->line('');
        }

        // Project type
        if ($context['project_type']) {
            $this->line('<comment>Project Type:</comment> '.ucfirst($context['project_type']));
            $this->line('');
        }

        // Languages
        if (! empty($context['languages'])) {
            $this->line('<comment>Languages:</comment> '.implode(', ', array_map('ucfirst', $context['languages'])));
            $this->line('');
        }

        // Frameworks
        if (! empty($context['frameworks'])) {
            $this->line('<comment>Frameworks:</comment> '.implode(', ', array_map('ucfirst', $context['frameworks'])));
            $this->line('');
        }

        // Package managers
        if (! empty($context['package_managers'])) {
            $this->line('<comment>Package Managers:</comment> '.implode(', ', array_map('ucfirst', $context['package_managers'])));
            $this->line('');
        }

        // CI/CD
        if (! empty($context['ci_cd'])) {
            $this->line('<comment>CI/CD:</comment> '.implode(', ', array_map(function ($tool) {
                return ucfirst(str_replace('_', ' ', $tool));
            }, $context['ci_cd'])));
            $this->line('');
        }

        // Containers
        if (! empty($context['containers'])) {
            $this->line('<comment>Containerization:</comment> '.implode(', ', array_map('ucfirst', $context['containers'])));
            $this->line('');
        }

        // Activation events
        $events = $contextService->getActivationEvents();
        if (! empty($events)) {
            $this->line('<comment>Activation Events:</comment>');
            foreach ($events as $event) {
                $this->line('  - '.$event);
            }
        }
    }
}
