<?php

namespace App\Services;

/**
 * Result object for component operations
 */
class ComponentResult
{
    public function __construct(
        private bool $success,
        private string $message,
        private array $data = [],
        private ?string $errorOutput = null
    ) {}

    public static function success(string $message, array $data = []): self
    {
        return new self(true, $message, $data);
    }

    public static function failure(string $message, ?string $errorOutput = null): self
    {
        return new self(false, $message, [], $errorOutput);
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getErrorOutput(): ?string
    {
        return $this->errorOutput;
    }

    public function getCommands(): array
    {
        return $this->data['commands'] ?? [];
    }
}
