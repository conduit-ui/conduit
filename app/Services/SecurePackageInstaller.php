<?php

namespace App\Services;

use App\Contracts\PackageInstallerInterface;
use App\ValueObjects\Component;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Secure package installation service with comprehensive validation
 */
class SecurePackageInstaller implements PackageInstallerInterface
{
    private Client $httpClient;

    private int $timeout;

    public function __construct()
    {
        $this->httpClient = new Client(['timeout' => 10]);
        $this->timeout = 300; // 5 minutes
    }

    /**
     * Install a package with full security validation
     */
    public function install(Component $component): ProcessResult
    {
        $this->validatePackageName($component->fullName);
        $this->verifyPackageEligibility($component);

        return $this->executeComposerInstall($component->fullName);
    }

    /**
     * Remove a package safely
     */
    public function remove(string $packageName): ProcessResult
    {
        $this->validatePackageName($packageName);

        $result = Process::timeout($this->timeout)
            ->path(base_path())
            ->run([
                'composer',
                'remove',
                $packageName,
                '--no-interaction',
            ]);

        return new ProcessResult(
            $result->successful(),
            $result->output(),
            $result->errorOutput()
        );
    }

    /**
     * Validate package name format for security
     */
    private function validatePackageName(string $packageName): void
    {
        // Composer package naming conventions
        if (! preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/', $packageName)) {
            throw new InvalidArgumentException(
                "Invalid package name format: {$packageName}. ".
                'Must follow vendor/package naming convention.'
            );
        }

        if (strlen($packageName) > 100) {
            throw new InvalidArgumentException("Package name too long: {$packageName}");
        }
    }

    /**
     * Verify package exists on Packagist and has required topic
     */
    private function verifyPackageEligibility(Component $component): void
    {
        try {
            $response = $this->httpClient->get("https://packagist.org/packages/{$component->fullName}.json");

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException("Package '{$component->fullName}' not found on Packagist");
            }

            $packageData = json_decode($response->getBody()->getContents(), true);

            // Verify package has required topic
            if (! $this->validateComponentTopic($packageData, $component->fullName)) {
                $requiredTopic = config('components.discovery.github_topic', 'conduit-component');
                throw new RuntimeException(
                    "Package '{$component->fullName}' does not have required topic '{$requiredTopic}'. ".
                    'Only verified Conduit components can be installed.'
                );
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new RuntimeException("Package '{$component->fullName}' not found on Packagist");
            }
            throw new RuntimeException('Failed to verify package: '.$e->getMessage());
        } catch (\Exception $e) {
            throw new RuntimeException('Package verification failed: '.$e->getMessage());
        }
    }

    /**
     * Execute secure composer install
     */
    private function executeComposerInstall(string $packageName): ProcessResult
    {
        $result = Process::timeout($this->timeout)
            ->path(base_path())
            ->run([
                'composer',
                'require',
                $packageName,
                '--no-interaction',
                '--no-progress',
                '--prefer-dist',
            ]);

        return new ProcessResult(
            $result->successful(),
            $result->output(),
            $result->errorOutput()
        );
    }

    /**
     * Validate component topic in both Packagist keywords and GitHub topics
     */
    private function validateComponentTopic(array $packageData, string $packageName): bool
    {
        $requiredTopic = config('components.discovery.github_topic', 'conduit-component');
        
        // First check Packagist keywords
        $keywords = $packageData['package']['keywords'] ?? [];
        if (in_array($requiredTopic, $keywords)) {
            return true;
        }
        
        // Fallback: Check GitHub topics if repository URL is available
        $repositoryUrl = $packageData['package']['repository'] ?? null;
        if ($repositoryUrl && $this->checkGitHubTopics($repositoryUrl, $requiredTopic)) {
            return true;
        }
        
        return false;
    }

    /**
     * Check GitHub repository topics via API
     */
    private function checkGitHubTopics(string $repositoryUrl, string $requiredTopic): bool
    {
        // Extract owner/repo from GitHub URL
        if (! preg_match('#github\.com[/:]([^/]+)/([^/]+?)(?:\.git)?/?$#', $repositoryUrl, $matches)) {
            return false;
        }
        
        $owner = $matches[1];
        $repo = $matches[2];
        
        try {
            $response = $this->httpClient->get("https://api.github.com/repos/{$owner}/{$repo}/topics", [
                'headers' => [
                    'Accept' => 'application/vnd.github.mercy-preview+json',
                    'User-Agent' => 'Conduit/1.0',
                ],
            ]);
            
            if ($response->getStatusCode() === 200) {
                $topicsData = json_decode($response->getBody()->getContents(), true);
                $topics = $topicsData['names'] ?? [];
                
                return in_array($requiredTopic, $topics);
            }
        } catch (\Exception $e) {
            // If GitHub API fails, don't block installation
            error_log("Failed to check GitHub topics for {$owner}/{$repo}: " . $e->getMessage());
        }
        
        return false;
    }
}
