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

// Copy default config files if they don't exist
$configFiles = [
    'app.php',
    'database.php',
    'cache.php',
    'filesystems.php',
    'spotify.php',
];

foreach ($configFiles as $configFile) {
    $sourceConfig = $packageRoot.'/config/'.$configFile;
    $targetConfig = $conduitHome.'/config/'.$configFile;

    if (file_exists($sourceConfig) && ! file_exists($targetConfig)) {
        copy($sourceConfig, $targetConfig);
    }
}

// Create the Laravel Zero application with global paths
$app = Application::configure(basePath: $packageRoot)->create();

// Override storage and config paths for global installation
$app->useStoragePath($conduitHome.'/storage');
$app->useConfigPath($conduitHome.'/config');

return $app;
