<?php

namespace App\Services\Security;

use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Path;

/**
 * Security validator for component system
 * Prevents path traversal, command injection, and other security vulnerabilities
 */
class ComponentSecurityValidator
{
    /**
     * Allowed component directories (sandboxed paths)
     */
    private array $allowedPaths;

    /**
     * Dangerous shell characters that must be escaped
     */
    private const DANGEROUS_CHARS = ['&', '|', ';', '$', '`', '\\', '!', '<', '>', '(', ')', '{', '}', '[', ']', '"', "'", "\n", "\r", "\0"];

    /**
     * Allowed characters in component names
     */
    private const COMPONENT_NAME_PATTERN = '/^[a-zA-Z0-9\-_]+$/';

    /**
     * Allowed characters in command names
     */
    private const COMMAND_NAME_PATTERN = '/^[a-zA-Z0-9\-_:]+$/';

    public function __construct()
    {
        $this->allowedPaths = [
            Path::canonicalize(base_path('components/core')),
            Path::canonicalize(base_path('components/dev')),
            Path::canonicalize($this->getHomeDirectory().'/.conduit/components'),
        ];
    }

    /**
     * Validate and sanitize a component path
     *
     * @throws \InvalidArgumentException if path is invalid or outside allowed directories
     */
    public function validateComponentPath(string $path): string
    {
        // Canonicalize the path to resolve .. and .
        $canonicalPath = Path::canonicalize($path);

        // Check if path is within allowed directories
        $isAllowed = false;
        foreach ($this->allowedPaths as $allowedPath) {
            if (str_starts_with($canonicalPath, $allowedPath)) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            throw new \InvalidArgumentException(
                'Component path is outside allowed directories: '.$path
            );
        }

        // Additional checks for path traversal attempts
        if (Str::contains($path, ['../', '..\\', '../', '.\\'])) {
            throw new \InvalidArgumentException(
                'Path traversal attempt detected: '.$path
            );
        }

        return $canonicalPath;
    }

    /**
     * Validate a binary path and ensure it's within a valid component directory
     *
     * @throws \InvalidArgumentException if binary path is invalid
     */
    public function validateBinaryPath(string $binaryPath): string
    {
        $canonicalPath = Path::canonicalize($binaryPath);

        // Binary must be within component directory
        $componentDir = dirname($canonicalPath);
        $this->validateComponentPath($componentDir);

        // Binary name must match component directory name
        $componentName = basename($componentDir);
        $binaryName = basename($canonicalPath);

        if ($componentName !== $binaryName) {
            throw new \InvalidArgumentException(
                'Binary name must match component directory name'
            );
        }

        // Validate component name format
        $this->validateComponentName($componentName);

        return $canonicalPath;
    }

    /**
     * Validate a component name
     *
     * @throws \InvalidArgumentException if component name is invalid
     */
    public function validateComponentName(string $name): string
    {
        if (! preg_match(self::COMPONENT_NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException(
                'Invalid component name. Only alphanumeric characters, hyphens, and underscores are allowed: '.$name
            );
        }

        // Additional length check
        if (strlen($name) > 50) {
            throw new \InvalidArgumentException(
                'Component name too long (max 50 characters): '.$name
            );
        }

        return $name;
    }

    /**
     * Validate a command name
     *
     * @throws \InvalidArgumentException if command name is invalid
     */
    public function validateCommandName(string $command): string
    {
        if (! preg_match(self::COMMAND_NAME_PATTERN, $command)) {
            throw new \InvalidArgumentException(
                'Invalid command name. Only alphanumeric characters, hyphens, underscores, and colons are allowed: '.$command
            );
        }

        // Additional length check
        if (strlen($command) > 100) {
            throw new \InvalidArgumentException(
                'Command name too long (max 100 characters): '.$command
            );
        }

        return $command;
    }

    /**
     * Sanitize command arguments to prevent injection
     */
    public function sanitizeArgument(string $argument): string
    {
        // Escape shell metacharacters
        return escapeshellarg($argument);
    }

    /**
     * Sanitize an array of arguments
     */
    public function sanitizeArguments(array $arguments): array
    {
        return array_map([$this, 'sanitizeArgument'], $arguments);
    }

    /**
     * Validate and sanitize options
     */
    public function sanitizeOptions(array $options): array
    {
        $sanitized = [];

        foreach ($options as $key => $value) {
            // Validate option key (no special chars)
            if (! preg_match('/^[a-zA-Z0-9\-_]+$/', $key)) {
                throw new \InvalidArgumentException(
                    'Invalid option key: '.$key
                );
            }

            // Sanitize option value
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeArgument($value);
            } elseif (is_bool($value) || is_null($value)) {
                $sanitized[$key] = $value;
            } else {
                // Convert to string and sanitize
                $sanitized[$key] = $this->sanitizeArgument((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Check if a binary has valid permissions and ownership
     */
    public function validateBinaryIntegrity(string $binaryPath): void
    {
        if (! file_exists($binaryPath)) {
            throw new \InvalidArgumentException(
                'Binary does not exist: '.$binaryPath
            );
        }

        if (! is_executable($binaryPath)) {
            throw new \InvalidArgumentException(
                'Binary is not executable: '.$binaryPath
            );
        }

        // Check file permissions (should not be world-writable)
        $perms = fileperms($binaryPath);
        if ($perms & 0002) {
            throw new \InvalidArgumentException(
                'Binary is world-writable, which is a security risk: '.$binaryPath
            );
        }

        // Optionally check file ownership (uncomment if needed)
        // $owner = fileowner($binaryPath);
        // if ($owner !== getmyuid()) {
        //     throw new \InvalidArgumentException(
        //         'Binary is not owned by current user: ' . $binaryPath
        //     );
        // }
    }

    /**
     * Escape a command for safe execution
     */
    public function escapeCommand(string $command): string
    {
        // Use escapeshellcmd for the command itself
        return escapeshellcmd($command);
    }

    /**
     * Build a safe command array for Process
     */
    public function buildSafeCommand(string $binary, string $command, array $arguments = [], array $options = []): array
    {
        // Validate binary
        $safeBinary = $this->validateBinaryPath($binary);
        $this->validateBinaryIntegrity($safeBinary);

        // Validate command
        $safeCommand = $this->validateCommandName($command);

        // Start building command array
        $commandArray = [$safeBinary, 'delegated', $safeCommand];

        // Add sanitized arguments
        foreach ($arguments as $arg) {
            if ($arg !== null && $arg !== '') {
                $commandArray[] = $this->sanitizeArgument((string) $arg);
            }
        }

        // Add sanitized options
        $sanitizedOptions = $this->sanitizeOptions($options);
        foreach ($sanitizedOptions as $key => $value) {
            if ($value === true) {
                $commandArray[] = '--'.$key;
            } elseif ($value !== false && $value !== null && $value !== '') {
                $commandArray[] = '--'.$key;
                $commandArray[] = $value; // Already sanitized
            }
        }

        return $commandArray;
    }

    /**
     * Get the user's home directory
     */
    private function getHomeDirectory(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        if (! $home && PHP_OS_FAMILY === 'Windows') {
            $homeDrive = getenv('HOMEDRIVE');
            $homePath = getenv('HOMEPATH');
            if ($homeDrive && $homePath) {
                $home = $homeDrive.$homePath;
            }
        }

        return $home ?: sys_get_temp_dir();
    }

    /**
     * Add an allowed path (for testing)
     */
    public function addAllowedPath(string $path): void
    {
        $this->allowedPaths[] = Path::canonicalize($path);
    }
}
