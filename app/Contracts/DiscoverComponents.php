<?php

namespace App\Contracts;

/**
 * Interface for component discovery capabilities
 */
interface DiscoverComponents
{
    /**
     * Discover available components
     */
    public function discover(): array;

    /**
     * Search for components by name or description
     */
    public function search(string $query): array;
}