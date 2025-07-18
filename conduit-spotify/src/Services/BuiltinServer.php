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
        $this->callbackFile = $this->createCallbackScript();
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

        // Clean up the temporary callback script
        if (file_exists($this->callbackFile)) {
            @unlink($this->callbackFile);
        }
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

    /**
     * Create a temporary callback script for the OAuth flow.
     */
    private function createCallbackScript(): string
    {
        $callbackScript = <<<'PHP'
<?php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

if (strpos($requestUri, '/callback') === 0) {
    $code = $_GET['code'] ?? null;
    $error = $_GET['error'] ?? null;
    $state = $_GET['state'] ?? null;
    
    if ($error) {
        file_put_contents('/tmp/spotify_auth_error', $error);
        echo "<h1>❌ Authorization Failed</h1><p>Error: {$error}</p>";
        echo "<p>You can close this window and try again in your terminal.</p>";
    } elseif ($code) {
        file_put_contents('/tmp/spotify_auth_code', json_encode(['code' => $code, 'state' => $state]));
        echo "<h1>🎉 Authorization Successful!</h1>";
        echo "<p>You can close this window and return to your terminal.</p>";
        echo "<script>setTimeout(() => { window.close(); }, 3000);</script>";
    } else {
        file_put_contents('/tmp/spotify_auth_error', 'no_code');
        echo "<h1>❌ Authorization Failed</h1><p>No authorization code received.</p>";
        echo "<p>You can close this window and try again in your terminal.</p>";
    }
} else {
    echo "<h1>🎵 Spotify OAuth Server</h1>";
    echo "<p>Waiting for authorization callback...</p>";
    echo "<p>Complete the authorization in your other browser tab.</p>";
}
PHP;

        $scriptPath = sys_get_temp_dir().'/spotify_oauth_callback_'.uniqid().'.php';
        file_put_contents($scriptPath, $callbackScript);

        return $scriptPath;
    }
}
