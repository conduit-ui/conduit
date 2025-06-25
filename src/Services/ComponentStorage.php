<?php

declare(strict_types=1);

namespace ConduitIo\Core\Services;

use ConduitIo\Core\Models\Component;
use ConduitIo\Core\Contracts\ComponentInterface;
use Illuminate\Support\Collection;

class ComponentStorage
{
    public function store(ComponentInterface $component): Component
    {
        return Component::updateOrCreate(
            ['name' => $component->getName()],
            [
                'package_name' => $component->getName(),
                'version' => $component->getVersion(),
                'description' => $component->getDescription(),
                'metadata' => $component->getMetadata(),
                'status' => 'installed',
                'installed_at' => now()
            ]
        );
    }

    public function remove(string $name): bool
    {
        return Component::where('name', $name)->delete() > 0;
    }

    public function get(string $name): ?Component
    {
        return Component::where('name', $name)->first();
    }

    public function all(): Collection
    {
        return Component::all();
    }

    public function isInstalled(string $name): bool
    {
        return Component::where('name', $name)
            ->where('status', 'installed')
            ->exists();
    }
}