<?php

declare(strict_types=1);

namespace ConduitIo\Core\Contracts;

interface ComponentInterface
{
    public function getName(): string;
    
    public function getVersion(): string;
    
    public function getDescription(): string;
    
    public function getCommands(): array;
    
    public function install(): bool;
    
    public function uninstall(): bool;
    
    public function isInstalled(): bool;
    
    public function validate(): bool;
    
    public function getMetadata(): array;
}