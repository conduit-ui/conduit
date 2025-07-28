<?php

namespace App\Commands;

use App\Services\AutoServiceProviderRegistrar;
use App\Services\ServiceProviderDetector;
use LaravelZero\Framework\Commands\Command;

class TestAutoRegistrationCommand extends Command
{
    protected $signature = 'test:auto-registration {package=jordanpartridge/conduit-env-manager}';

    protected $description = 'Test automatic service provider registration';

    public function handle(ServiceProviderDetector $detector, AutoServiceProviderRegistrar $registrar): int
    {
        $packageName = $this->argument('package');

        $this->info("Testing auto-registration for package: {$packageName}");

        // Step 1: Detect service providers
        $serviceProviders = $detector->detectServiceProviders($packageName);

        if (empty($serviceProviders)) {
            $this->warn('No service providers detected');

            return self::FAILURE;
        }

        $this->info('Detected service providers:');
        foreach ($serviceProviders as $provider) {
            $this->line("  - {$provider}");
        }

        // Step 2: Check if already registered
        $this->info('Current registration status:');
        foreach ($serviceProviders as $provider) {
            $status = $registrar->isRegistered($provider) ? 'REGISTERED' : 'NOT REGISTERED';
            $this->line("  - {$provider}: {$status}");
        }

        // Step 3: Register service providers
        $this->info('Registering service providers...');
        $success = $registrar->registerServiceProviders($serviceProviders);

        if ($success) {
            $this->info('✅ Service providers registered successfully');
        } else {
            $this->error('❌ Failed to register service providers');

            return self::FAILURE;
        }

        // Step 4: Verify registration
        $this->info('Verification:');
        foreach ($serviceProviders as $provider) {
            $status = $registrar->isRegistered($provider) ? '✅ REGISTERED' : '❌ NOT REGISTERED';
            $this->line("  - {$provider}: {$status}");
        }

        // Step 5: Detect commands
        $commands = $detector->detectCommands($serviceProviders);

        if (! empty($commands)) {
            $this->info('Detected commands:');
            foreach ($commands as $command) {
                $this->line("  - {$command}");
            }
        }

        return self::SUCCESS;
    }
}
