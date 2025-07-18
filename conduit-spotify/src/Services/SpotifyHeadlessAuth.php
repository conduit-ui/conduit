<?php

namespace Conduit\Spotify\Services;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Chrome\ChromeProcess;
use Laravel\Dusk\DuskServiceProvider;
use Illuminate\Support\Facades\Cache;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

class SpotifyHeadlessAuth
{
    private string $clientId;
    private string $redirectUri;
    private array $scopes;

    public function __construct()
    {
        $fileCache = Cache::store('file');
        $this->clientId = $fileCache->get('spotify_client_id') ?: config('spotify.client_id');
        $this->redirectUri = config('spotify.redirect_uri', 'http://127.0.0.1:9876/callback');
        $this->scopes = config('spotify.scopes', [
            'user-read-playback-state',
            'user-modify-playback-state',
            'user-read-currently-playing',
            'playlist-read-private',
        ]);
    }

    /**
     * Authenticate using headless browser automation.
     */
    public function authenticateHeadless(string $username, string $password): string
    {
        $authUrl = $this->buildAuthUrl();

        // Create headless Chrome driver
        $driver = $this->createHeadlessDriver();

        try {
            $browser = new Browser($driver);

            // Navigate to Spotify auth
            $browser->visit($authUrl);

            // Handle login flow
            $this->performLogin($browser, $username, $password);

            // Wait for redirect and extract code
            $callbackUrl = $this->waitForCallback($browser);
            
            return $this->extractCodeFromUrl($callbackUrl);

        } finally {
            $driver->quit();
        }
    }

    private function buildAuthUrl(): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'scope' => implode(' ', $this->scopes),
            'redirect_uri' => $this->redirectUri,
            'state' => bin2hex(random_bytes(16)),
        ];

        return 'https://accounts.spotify.com/authorize?' . http_build_query($params);
    }

    private function createHeadlessDriver(): RemoteWebDriver
    {
        $options = (new ChromeOptions())->addArguments([
            '--disable-gpu',
            '--headless',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-web-security',
            '--window-size=1920,1080',
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        return RemoteWebDriver::create(
            'http://localhost:9515',
            $capabilities,
            5000,
            5000
        );
    }

    private function performLogin(Browser $browser, string $username, string $password): void
    {
        // Check if already logged in
        if ($browser->driver->getCurrentURL() !== 'https://accounts.spotify.com/login') {
            return;
        }

        // Fill login form
        $browser->type('#login-username', $username)
                ->type('#login-password', $password)
                ->press('#login-button');

        // Wait for login to complete
        $browser->waitUntil('window.location.href.includes("authorize") || window.location.href.includes("callback")', 10);
    }

    private function waitForCallback(Browser $browser): string
    {
        // Wait for authorization page or direct callback
        $browser->waitUntil('window.location.href.includes("callback") || document.querySelector("[data-testid=auth-accept]")', 15);

        $currentUrl = $browser->driver->getCurrentURL();

        // If we're on the auth page, click accept
        if (str_contains($currentUrl, 'authorize') && !str_contains($currentUrl, 'callback')) {
            $browser->press('[data-testid=auth-accept]');
            $browser->waitUntil('window.location.href.includes("callback")', 10);
        }

        return $browser->driver->getCurrentURL();
    }

    private function extractCodeFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        
        if (!isset($parsed['query'])) {
            throw new \Exception('No query parameters found in callback URL');
        }

        parse_str($parsed['query'], $params);

        if (!isset($params['code'])) {
            throw new \Exception('No authorization code found in callback URL');
        }

        return $params['code'];
    }

    /**
     * Check if ChromeDriver is running, start it if needed.
     */
    public function ensureChromeDriverRunning(): void
    {
        $process = new ChromeProcess();
        
        if (!$process->isRunning()) {
            $process->start();
            sleep(2); // Give it time to start
        }
    }
}