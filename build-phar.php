<?php

if (Phar::canWrite()) {
    $phar = new Phar('builds/conduit.phar');

    // Add all files (including resources, configs, etc.)
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
    $phar->buildFromIterator($iterator, __DIR__);

    // Create the stub that will execute the conduit binary
    $stub = "#!/usr/bin/env php\n<?php\n";
    $stub .= "Phar::mapPhar('conduit.phar');\n";
    $stub .= "require 'phar://conduit.phar/conduit';\n";
    $stub .= "__HALT_COMPILER();\n";

    $phar->setStub($stub);
    $phar->compressFiles(Phar::GZ);

    echo "PHAR created successfully at builds/conduit.phar\n";
} else {
    echo "Error: PHAR creation is disabled. Run with: php -d phar.readonly=off build-phar.php\n";
}
