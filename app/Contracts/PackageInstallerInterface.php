<?php

namespace App\Contracts;

use App\Services\ProcessResult;
use App\ValueObjects\Component;

interface PackageInstallerInterface
{
    /**
     * Install a package with full security validation
     */
    public function install(Component $component): ProcessResult;

    /**
     * Remove a package safely
     */
    public function remove(string $packageName): ProcessResult;
}