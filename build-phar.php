<?php

if (Phar::canWrite()) {
    if (! is_dir(__DIR__ . '/builds')) {
        mkdir(__DIR__ . '/builds', 0777, true);
    }
    $phar = new Phar('builds/conduit.phar');
    $phar->buildFromDirectory(__DIR__, '/\.(php|json)$/');
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
