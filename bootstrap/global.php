<?php

use LaravelZero\Framework\Application;

/*
|--------------------------------------------------------------------------
| Global Installation Bootstrap
|--------------------------------------------------------------------------
|
| This file handles bootstrapping Conduit when installed as a global
| Composer package. It sets up proper paths and storage locations
| for the global installation context.
|
*/

// Determine the package root directory
$packageRoot = dirname(__DIR__);

// For global installations, we need to use user-specific paths
$userHome = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? sys_get_temp_dir();
$conduitHome = $userHome.'/.conduit';

// Ensure the .conduit directory exists
if (! is_dir($conduitHome)) {
    mkdir($conduitHome, 0755, true);
}

// Set up environment variables for global installation
putenv('CONDUIT_HOME='.$conduitHome);
putenv('CONDUIT_STORAGE_PATH='.$conduitHome.'/storage');
putenv('CONDUIT_CONFIG_PATH='.$conduitHome.'/config');

// Create necessary directories
$directories = [
    $conduitHome.'/storage',
    $conduitHome.'/storage/app',
    $conduitHome.'/storage/framework',
    $conduitHome.'/storage/framework/cache',
    $conduitHome.'/storage/framework/sessions',
    $conduitHome.'/storage/framework/views',
    $conduitHome.'/storage/logs',
    $conduitHome.'/config',
];

foreach ($directories as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

// Copy all config files from source to user directory
$sourceConfigDir = $packageRoot.'/config';
$targetConfigDir = $conduitHome.'/config';

if (is_dir($sourceConfigDir)) {
    $configFiles = glob($sourceConfigDir.'/*.php');
    foreach ($configFiles as $sourceConfig) {
        $filename = basename($sourceConfig);
        $targetConfig = $targetConfigDir.'/'.$filename;

        // Always copy to ensure we have the latest config (especially app.php with version)
        copy($sourceConfig, $targetConfig);
    }
}

// Create the Laravel Zero application with global paths
try {
    $app = Application::configure(basePath: $packageRoot)->create();

    // Override storage and config paths for global installation
    $app->useStoragePath($conduitHome.'/storage');
    $app->useConfigPath($conduitHome.'/config');

    // Configure database for global installation
    $app->singleton('db.schema', function () {
        return new \App\Services\DatabaseSchemaManager;
    });

    // Set up global database configuration
    $app->afterResolving('db', function ($db) {
        $schemaManager = new \App\Services\DatabaseSchemaManager;

        // Update database configuration for global installation
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => $schemaManager->getGlobalDatabaseConfig(),
        ]);

        // Initialize database schema
        $schemaManager->initializeGlobalDatabase();
    });

    return $app;
} catch (\Exception $e) {
    echo 'Error creating Laravel Zero application: '.$e->getMessage()."\n";
    echo 'Package root: '.$packageRoot."\n";
    echo 'Conduit home: '.$conduitHome."\n";
    echo 'Stack trace: '.$e->getTraceAsString()."\n";
    exit(1);
}
