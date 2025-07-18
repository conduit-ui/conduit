<?php

namespace Conduit\Spotify\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class SpotifyClipboardAuth
{
    /**
     * Generate ASCII QR code for terminal display.
     */
    public function generateQRCode(string $url): string
    {
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_STRING_TEXT,
            'eccLevel' => QRCode::ECC_L, // Lower error correction for longer URLs
            'textOptions' => [
                'textDark' => '██',
                'textLight' => '  ',
            ],
        ]);

        return (new QRCode($options))->render($url);
    }

    /**
     * Monitor clipboard for callback URL with timeout.
     */
    public function monitorClipboardForCallback(int $timeoutSeconds = 300): ?string
    {
        $startTime = time();
        $lastClipboard = '';

        // Check current clipboard immediately
        $currentClipboard = $this->getClipboard();
        if ($this->isCallbackUrl($currentClipboard)) {
            return $currentClipboard;
        }

        $lastClipboard = $currentClipboard;

        while ((time() - $startTime) < $timeoutSeconds) {
            $currentClipboard = $this->getClipboard();

            // Check if clipboard changed
            if ($currentClipboard !== $lastClipboard) {
                $lastClipboard = $currentClipboard;

                // Check if it looks like a callback URL
                if ($this->isCallbackUrl($currentClipboard)) {
                    return $currentClipboard;
                }
            }

            // Check every 500ms to avoid excessive CPU usage
            usleep(500000);
        }

        return null; // Timeout
    }

    /**
     * Extract authorization code from callback URL.
     */
    public function extractCodeFromUrl(string $url): ?string
    {
        $parsedUrl = parse_url($url);

        if (! isset($parsedUrl['query'])) {
            return null;
        }

        parse_str($parsedUrl['query'], $params);

        return $params['code'] ?? null;
    }

    /**
     * Get clipboard contents (cross-platform).
     */
    private function getClipboard(): string
    {
        $os = PHP_OS_FAMILY;

        try {
            switch ($os) {
                case 'Darwin': // macOS
                    return trim(shell_exec('pbpaste') ?? '');

                case 'Linux':
                    // Try different clipboard tools
                    $result = shell_exec('xclip -selection clipboard -o 2>/dev/null')
                           ?? shell_exec('xsel --clipboard --output 2>/dev/null')
                           ?? '';

                    return trim($result);

                case 'Windows':
                    return trim(shell_exec('powershell -command "Get-Clipboard"') ?? '');

                default:
                    return '';
            }
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Check if URL looks like a Spotify callback.
     */
    private function isCallbackUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Check for common callback patterns
        $callbackPatterns = [
            'callback',
            'code=',
            'state=',
            'spotify',
            '127.0.0.1',
            'localhost',
        ];

        $lowerUrl = strtolower($url);

        foreach ($callbackPatterns as $pattern) {
            if (str_contains($lowerUrl, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get universal redirect URI that works from any device.
     */
    public function getUniversalRedirectUri(): string
    {
        // Use a public URL that will work from any device
        return 'https://jordanpartridge.github.io/conduit-spotify-callback/';
    }

    /**
     * Check if clipboard monitoring is supported on this platform.
     */
    public function isClipboardMonitoringSupported(): bool
    {
        $os = PHP_OS_FAMILY;

        switch ($os) {
            case 'Darwin': // macOS
                return $this->command_exists('pbpaste');

            case 'Linux':
                return $this->command_exists('xclip') || $this->command_exists('xsel');

            case 'Windows':
                return true; // PowerShell is usually available

            default:
                return false;
        }
    }

    /**
     * Helper function to check if command exists.
     */
    private function command_exists($command): bool
    {
        $which = shell_exec("which $command 2>/dev/null");

        return ! empty($which);
    }
}
