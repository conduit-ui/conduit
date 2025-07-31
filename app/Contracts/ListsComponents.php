<?php

namespace App\Contracts;

/**
 * Interface for component listing capabilities
 */
interface ListsComponents
{
    /**
     * List all installed components
     */
    public function listInstalled(): array;

    /**
     * List components with detailed information
     */
    public function listWithDetails(): array;
}
