<?php

namespace Conduit\Spotify\Services;

class SpotifyTempServer
{
    private $socket;

    private int $port;

    private ?string $authCode = null;

    private bool $isRunning = false;

    public function __construct(int $port = 9876)
    {
        $this->port = $port;
    }

    /**
     * Start the temporary server and wait for callback.
     */
    public function startAndWaitForCallback(int $timeoutSeconds = 300): ?string
    {
        if (! $this->start()) {
            throw new \Exception("Failed to start server on port {$this->port}");
        }

        try {
            return $this->waitForCallback($timeoutSeconds);
        } finally {
            $this->stop();
        }
    }

    /**
     * Start the HTTP server.
     */
    private function start(): bool
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (! $this->socket) {
            return false;
        }

        // Allow socket reuse
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (! socket_bind($this->socket, '127.0.0.1', $this->port)) {
            socket_close($this->socket);

            return false;
        }

        if (! socket_listen($this->socket, 1)) {
            socket_close($this->socket);

            return false;
        }

        $this->isRunning = true;

        return true;
    }

    /**
     * Wait for callback with timeout.
     */
    private function waitForCallback(int $timeoutSeconds): ?string
    {
        $startTime = time();

        while ($this->isRunning && (time() - $startTime) < $timeoutSeconds) {
            // Set socket timeout to 1 second for non-blocking behavior
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);

            $clientSocket = @socket_accept($this->socket);

            if ($clientSocket === false) {
                continue; // Timeout, try again
            }

            try {
                $request = socket_read($clientSocket, 2048);

                if ($request) {
                    $this->handleRequest($request, $clientSocket);

                    if ($this->authCode) {
                        return $this->authCode;
                    }
                }
            } finally {
                socket_close($clientSocket);
            }
        }

        return null; // Timeout
    }

    /**
     * Handle HTTP request and extract auth code.
     */
    private function handleRequest(string $request, $clientSocket): void
    {
        // Parse the HTTP request
        $lines = explode("\r\n", $request);
        $requestLine = $lines[0] ?? '';

        // Extract path from "GET /callback?code=... HTTP/1.1"
        if (preg_match('/^GET ([^\s]+)/', $requestLine, $matches)) {
            $path = $matches[1];

            // Parse query parameters
            $urlParts = parse_url($path);
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $params);

                if (isset($params['code'])) {
                    $this->authCode = $params['code'];
                }
            }
        }

        // Send response
        $this->sendResponse($clientSocket);
    }

    /**
     * Send HTTP response to browser.
     */
    private function sendResponse($clientSocket): void
    {
        $html = $this->getSuccessPage();

        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: text/html\r\n";
        $response .= 'Content-Length: '.strlen($html)."\r\n";
        $response .= "Connection: close\r\n";
        $response .= "\r\n";
        $response .= $html;

        socket_write($clientSocket, $response);
    }

    /**
     * Get success page HTML.
     */
    private function getSuccessPage(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>Spotify Authorization Complete</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #1db954; color: white; }
        .container { max-width: 500px; margin: 0 auto; }
        h1 { font-size: 2em; margin-bottom: 20px; }
        p { font-size: 1.2em; margin-bottom: 20px; }
        .emoji { font-size: 3em; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">🎵</div>
        <h1>Authorization Complete!</h1>
        <p>Spotify has been successfully connected to Conduit.</p>
        <p>You can close this window and return to your terminal.</p>
        <div class="emoji">✅</div>
    </div>
    <script>
        // Auto-close after 3 seconds
        setTimeout(() => {
            window.close();
        }, 3000);
    </script>
</body>
</html>';
    }

    /**
     * Stop the server.
     */
    private function stop(): void
    {
        $this->isRunning = false;

        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Check if port is available.
     */
    public function isPortAvailable(): bool
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

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
}
