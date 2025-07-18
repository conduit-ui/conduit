<?php

namespace Conduit\Spotify\Services;

use Symfony\Component\Process\Process;

class BuiltinServer
{
    private ?Process $serverProcess = null;

    private int $port;

    private string $callbackFile;

    public function __construct(int $port = 9876)
    {
        $this->port = $port;
        $this->callbackFile = '/Users/jordanpartridge/packages/conduit/storage/spotify-callback.php';
    }

    /**
     * Start the PHP built-in server and wait for auth callback.
     */
    public function startAndWaitForAuth(int $timeoutSeconds = 300): ?string
    {
        // Clean up any previous auth files
        $this->cleanupTempFiles();

        if (! $this->startServer()) {
            throw new \Exception("Failed to start server on port {$this->port}");
        }

        try {
            return $this->waitForAuth($timeoutSeconds);
        } finally {
            $this->stopServer();
            $this->cleanupTempFiles();
        }
    }

    /**
     * Start the PHP built-in server.
     */
    public function startServer(): bool
    {
        if (! $this->isPortAvailable()) {
            throw new \Exception("Port {$this->port} is not available");
        }

        $command = [
            'php',
            '-S',
            "127.0.0.1:{$this->port}",
            $this->callbackFile,
        ];

        $this->serverProcess = new Process($command);
        $this->serverProcess->start();

        // Give the server a moment to start
        sleep(1);

        if (! $this->serverProcess->isRunning()) {
            $error = $this->serverProcess->getErrorOutput();
            throw new \Exception('Server failed to start: '.($error ?: 'Unknown error'));
        }

        return true;
    }

    /**
     * Stop the server.
     */
    public function stopServer(): void
    {
        if ($this->serverProcess && $this->serverProcess->isRunning()) {
            $this->serverProcess->stop();
        }
        $this->serverProcess = null;
    }

    /**
     * Wait for auth callback with timeout.
     */
    private function waitForAuth(int $timeoutSeconds): ?string
    {
        $startTime = time();
        $authCodeFile = '/tmp/spotify_auth_code';
        $authErrorFile = '/tmp/spotify_auth_error';

        while ((time() - $startTime) < $timeoutSeconds) {
            // Check for auth code
            if (file_exists($authCodeFile)) {
                $authData = json_decode(file_get_contents($authCodeFile), true);

                return $authData['code'] ?? null;
            }

            // Check for auth error
            if (file_exists($authErrorFile)) {
                $error = file_get_contents($authErrorFile);
                throw new \Exception("Spotify auth error: {$error}");
            }

            // Check if server is still running
            if (! $this->serverProcess->isRunning()) {
                throw new \Exception('Auth server stopped unexpectedly');
            }

            usleep(500000); // Check every 500ms
        }

        throw new \Exception("Auth timeout after {$timeoutSeconds} seconds");
    }

    /**
     * Check if the port is available.
     */
    public function isPortAvailable(): bool
    {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! $socket) {
            return false;
        }

        $result = @socket_bind($socket, '127.0.0.1', $this->port);
        socket_close($socket);

        return $result !== false;
    }

    /**
     * Get the callback URL for this server.
     */
    public function getCallbackUrl(): string
    {
        return "http://127.0.0.1:{$this->port}/callback";
    }

    /**
     * Clean up temporary auth files.
     */
    private function cleanupTempFiles(): void
    {
        @unlink('/tmp/spotify_auth_code');
        @unlink('/tmp/spotify_auth_error');
    }

    /**
     * Get server status.
     */
    public function isRunning(): bool
    {
        return $this->serverProcess && $this->serverProcess->isRunning();
    }
}
