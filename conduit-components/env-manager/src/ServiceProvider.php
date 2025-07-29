<?php

declare(strict_types=1);

namespace Jordanpartridge\ConduitEnvmanager;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Jordanpartridge\ConduitEnvmanager\Commands\EnvBackupCommand;
use Jordanpartridge\ConduitEnvmanager\Commands\EnvGetCommand;
use Jordanpartridge\ConduitEnvmanager\Commands\EnvinitCommand;
use Jordanpartridge\ConduitEnvmanager\Commands\EnvSetCommand;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnvinitCommand::class,
                EnvSetCommand::class,
                EnvGetCommand::class,
                EnvBackupCommand::class,
            ]);
        }
    }
}
