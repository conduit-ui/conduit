<?php

namespace JordanPartridge\ConduitSpotify\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use JordanPartridge\ConduitSpotify\Contracts\SpotifyAuthInterface;

class SpotifyAuthService implements SpotifyAuthInterface
{
    private Client $httpClient;

    private ?string $clientId;

    private ?string $clientSecret;

    private string $redirectUri;

    private array $defaultScopes;

    public function __construct()
    {
        $this->httpClient = new Client([
            'base_uri' => 'https://accounts.spotify.com/',
            'timeout' => 30,
        ]);

        // Try stored credentials first, fallback to config
        $fileCache = Cache::store('file');
        $this->clientId = $fileCache->get('spotify_client_id') ?: config('spotify.client_id');
        $this->clientSecret = $fileCache->get('spotify_client_secret') ?: config('spotify.client_secret');
        $this->redirectUri = config('spotify.redirect_uri', 'http://localhost:8888/callback');
        $this->defaultScopes = config('spotify.scopes', [
            'user-read-playback-state',
            'user-modify-playback-state',
            'user-read-currently-playing',
            'playlist-read-private',
        ]);
    }

    public function getAuthorizationUrl(array $scopes = []): string
    {
        $scopes = empty($scopes) ? $this->defaultScopes : $scopes;
        $state = bin2hex(random_bytes(16));

        // Store state for validation
        Cache::put("spotify_auth_state_{$state}", true, now()->addMinutes(10));

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ];

        return 'https://accounts.spotify.com/authorize?'.http_build_query($params);
    }

    public function exchangeCodeForToken(string $code): array
    {
        try {
            $response = $this->httpClient->post('api/token', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            // Store tokens securely
            $this->storeTokens($data);

            return $data;
        } catch (RequestException $e) {
            throw new \Exception('Failed to exchange code for token: '.$e->getMessage());
        }
    }

    /**
     * Start OAuth flow with temporary local server.
     */
    public function authenticateWithLocalServer(): array
    {
        // Use configured redirect URI (extract port from it)
        $port = $this->extractPortFromRedirectUri();

        // Check if port is available, suggest alternatives if not
        if (! $this->isPortAvailable($port)) {
            throw new \Exception("Port {$port} is not available. Please ensure nothing else is running on this port, or update your Spotify app settings to use a different redirect URI.");
        }

        // Start temporary server on the configured port
        $serverProcess = $this->startTemporaryServer($port);

        try {
            // Generate auth URL and open browser
            $authUrl = $this->getAuthorizationUrl();
            $this->openBrowser($authUrl);

            // Wait for callback with timeout
            $code = $this->waitForCallback($port, 120); // 2 minute timeout

            if (! $code) {
                throw new \Exception('Authentication timed out or was cancelled');
            }

            // Exchange code for token
            return $this->exchangeCodeForToken($code);

        } finally {
            // Always stop the server
            $this->stopServer($serverProcess);
        }
    }

    private function extractPortFromRedirectUri(): int
    {
        $parsedUrl = parse_url($this->redirectUri);

        if (! isset($parsedUrl['port'])) {
            // Default ports based on scheme
            return $parsedUrl['scheme'] === 'https' ? 443 : 80;
        }

        return (int) $parsedUrl['port'];
    }

    private function isPortAvailable(int $port): bool
    {
        $socket = @fsockopen('localhost', $port, $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);

            return false; // Port is in use
        }

        return true; // Port is available
    }

    private function startTemporaryServer(int $port): array
    {
        $serverScript = $this->createServerScript($port);
        $scriptPath = sys_get_temp_dir().'/spotify_oauth_server.php';
        file_put_contents($scriptPath, $serverScript);

        // Start PHP built-in server on 127.0.0.1 to match redirect URI
        $command = "php -S 127.0.0.1:{$port} {$scriptPath} > /dev/null 2>&1 & echo $!";
        $pid = (int) trim(shell_exec($command));

        return ['pid' => $pid, 'script' => $scriptPath];
    }

    private function createServerScript(int $port): string
    {
        return <<<'PHP'
<?php
// Minimal OAuth callback server
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

if (strpos($requestUri, '/callback') === 0) {
    $code = $_GET['code'] ?? null;
    $error = $_GET['error'] ?? null;
    
    if ($error) {
        http_response_code(400);
        echo "<h1>Authentication Failed</h1><p>Error: {$error}</p>";
        // Write error to temp file
        file_put_contents(sys_get_temp_dir() . '/spotify_oauth_result', "ERROR:{$error}");
    } elseif ($code) {
        http_response_code(200);
        echo "<h1>Authentication Successful!</h1><p>You can close this window.</p>";
        // Write success code to temp file
        file_put_contents(sys_get_temp_dir() . '/spotify_oauth_result', "SUCCESS:{$code}");
    } else {
        http_response_code(400);
        echo "<h1>Authentication Failed</h1><p>No authorization code received.</p>";
        file_put_contents(sys_get_temp_dir() . '/spotify_oauth_result', "ERROR:no_code");
    }
    
    // Shutdown signal
    file_put_contents(sys_get_temp_dir() . '/spotify_oauth_shutdown', '1');
} else {
    http_response_code(404);
    echo "<h1>Not Found</h1>";
}
PHP;
    }

    private function waitForCallback(int $port, int $timeoutSeconds): ?string
    {
        $resultFile = sys_get_temp_dir().'/spotify_oauth_result';
        $shutdownFile = sys_get_temp_dir().'/spotify_oauth_shutdown';

        // Clean up any existing files
        @unlink($resultFile);
        @unlink($shutdownFile);

        $startTime = time();

        while (time() - $startTime < $timeoutSeconds) {
            if (file_exists($shutdownFile)) {
                @unlink($shutdownFile);

                if (file_exists($resultFile)) {
                    $result = file_get_contents($resultFile);
                    @unlink($resultFile);

                    if (strpos($result, 'SUCCESS:') === 0) {
                        return substr($result, 8); // Extract code
                    } elseif (strpos($result, 'ERROR:') === 0) {
                        $error = substr($result, 6);
                        throw new \Exception("OAuth error: {$error}");
                    }
                }
                break;
            }

            usleep(500000); // 0.5 second
        }

        return null;
    }

    private function stopServer(array $serverProcess): void
    {
        if (isset($serverProcess['pid'])) {
            // Kill the server process
            shell_exec("kill {$serverProcess['pid']} 2>/dev/null");
        }

        if (isset($serverProcess['script'])) {
            // Clean up script file
            @unlink($serverProcess['script']);
        }

        // Clean up any remaining temp files
        @unlink(sys_get_temp_dir().'/spotify_oauth_result');
        @unlink(sys_get_temp_dir().'/spotify_oauth_shutdown');
    }

    private function openBrowser(string $url): void
    {
        $os = PHP_OS_FAMILY;

        try {
            switch ($os) {
                case 'Darwin': // macOS
                    shell_exec("open '{$url}' > /dev/null 2>&1");
                    break;
                case 'Windows':
                    shell_exec("start '{$url}' > /dev/null 2>&1");
                    break;
                case 'Linux':
                    shell_exec("xdg-open '{$url}' > /dev/null 2>&1");
                    break;
            }
        } catch (\Exception $e) {
            // Silently fail if we can't open browser
        }
    }

    public function refreshToken(string $refreshToken): array
    {
        try {
            $response = $this->httpClient->post('api/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            // Update stored tokens
            $this->storeTokens($data, $refreshToken);

            return $data;
        } catch (RequestException $e) {
            throw new \Exception('Failed to refresh token: '.$e->getMessage());
        }
    }

    public function getAccessToken(): ?string
    {
        $fileCache = Cache::store('file');
        $token = $fileCache->get('spotify_access_token');

        if (! $token) {
            return null;
        }

        // Check if token is expired and refresh if needed
        $expiresAt = $fileCache->get('spotify_token_expires_at');
        if ($expiresAt && now()->isAfter($expiresAt)) {
            $refreshToken = $fileCache->get('spotify_refresh_token');
            if ($refreshToken) {
                try {
                    $this->refreshToken($refreshToken);

                    return $fileCache->get('spotify_access_token');
                } catch (\Exception $e) {
                    // Refresh failed, clear tokens
                    $this->clearTokens();

                    return null;
                }
            }
        }

        return $token;
    }

    public function isAuthenticated(): bool
    {
        return ! empty($this->getAccessToken());
    }

    public function revoke(): bool
    {
        $this->clearTokens();

        return true;
    }

    /**
     * Store tokens securely in cache.
     */
    private function storeTokens(array $tokenData, ?string $existingRefreshToken = null): void
    {
        $fileCache = Cache::store('file');
        $accessToken = $tokenData['access_token'];
        $expiresIn = $tokenData['expires_in'] ?? 3600;
        $refreshToken = $tokenData['refresh_token'] ?? $existingRefreshToken;

        $fileCache->put('spotify_access_token', $accessToken, now()->addSeconds($expiresIn - 60));
        $fileCache->put('spotify_token_expires_at', now()->addSeconds($expiresIn), now()->addHours(24));

        if ($refreshToken) {
            $fileCache->put('spotify_refresh_token', $refreshToken, now()->addDays(30));
        }
    }

    /**
     * Clear stored tokens.
     */
    private function clearTokens(): void
    {
        $fileCache = Cache::store('file');
        $fileCache->forget('spotify_access_token');
        $fileCache->forget('spotify_refresh_token');
        $fileCache->forget('spotify_token_expires_at');
    }
}
