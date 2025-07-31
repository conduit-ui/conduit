<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\DisplaysUpdateStatus;
use App\Services\ComponentUpdateChecker;
use App\Services\ComponentUpdateService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\multiselect;

class UpdateCommand extends Command
{
    use DisplaysUpdateStatus;

    protected $signature = 'update 
                            {component? : Specific component to update}
                            {--all : Update all components}
                            {--check : Only check for updates, don\'t install}
                            {--force : Force update even if breaking changes}';

    protected $description = 'Update Conduit components';

    public function handle(
        ComponentUpdateChecker $checker,
        ComponentUpdateService $updater
    ): int {
        // Check for updates
        if ($this->option('check')) {
            return $this->checkUpdates($checker);
        }

        $component = $this->argument('component');

        // Update specific component
        if ($component) {
            return $this->updateComponent($component, $updater);
        }

        // Update all components
        if ($this->option('all')) {
            return $this->updateAllComponents($updater);
        }

        // Interactive update selection
        return $this->interactiveUpdate($checker, $updater);
    }

    private function checkUpdates(ComponentUpdateChecker $checker): int
    {
        $this->info('🔍 Checking for component updates...');
        $updates = $checker->quickCheck();

        if ($updates->isEmpty()) {
            $this->info('✅ All components are up to date!');

            return Command::SUCCESS;
        }

        $this->displayUpdatesTable($updates);

        $this->newLine();
        $this->info('💡 Run "conduit update" to install updates interactively');
        $this->info('💡 Run "conduit update --all" to update all components');

        return Command::SUCCESS;
    }

    private function updateComponent(string $name, ComponentUpdateService $updater): int
    {
        $this->info("🔄 Updating {$name}...");

        try {
            $success = $updater->updateComponent($name);

            if ($success) {
                $this->info("✅ Successfully updated {$name}");

                return Command::SUCCESS;
            } else {
                $this->error("❌ Failed to update {$name}");

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error updating {$name}: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function updateAllComponents(ComponentUpdateService $updater): int
    {
        $this->info('🔄 Updating all components...');

        $results = $updater->updateAll(! $this->option('force'));

        foreach ($results as $component => $result) {
            match ($result) {
                'updated' => $this->info("✅ Updated {$component}"),
                'skipped_breaking' => $this->warn("⚠️  Skipped {$component} (breaking changes - use --force)"),
                'failed' => $this->error("❌ Failed to update {$component}"),
                default => $this->error("❌ {$component}: {$result}")
            };
        }

        $updated = collect($results)->filter(fn ($r) => $r === 'updated')->count();
        $total = count($results);

        $this->newLine();
        $this->info("🎉 Updated {$updated}/{$total} components");

        return Command::SUCCESS;
    }

    private function interactiveUpdate(
        ComponentUpdateChecker $checker,
        ComponentUpdateService $updater
    ): int {
        $updates = $checker->quickCheck();

        if ($updates->isEmpty()) {
            $this->info('✅ All components are up to date!');

            return Command::SUCCESS;
        }

        $this->info('📦 Available updates:');
        $this->newLine();

        $options = $updates->mapWithKeys(function ($update) {
            $priority = $update['priority'] === 'security' ? ' (security)' : '';
            $breaking = $update['priority'] === 'breaking' ? ' (breaking changes)' : '';

            $label = "{$update['name']} {$update['current']} → {$update['latest']}{$priority}{$breaking}";

            return [$update['name'] => $label];
        })->toArray();

        $selected = multiselect(
            label: 'Select components to update:',
            options: $options,
            default: $updates->where('priority', '!=', 'breaking')->pluck('name')->toArray()
        );

        if (empty($selected)) {
            $this->info('No components selected for update');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('🔄 Updating selected components...');

        foreach ($selected as $component) {
            try {
                $this->line("Updating {$component}...");
                $success = $updater->updateComponent($component);

                if ($success) {
                    $this->info("✅ {$component} updated successfully");
                } else {
                    $this->error("❌ Failed to update {$component}");
                }
            } catch (\Exception $e) {
                $this->error("❌ Error updating {$component}: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
