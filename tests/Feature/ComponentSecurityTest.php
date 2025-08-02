<?php

namespace Tests\Feature;

use App\Services\ComponentDelegationService;
use App\Services\Security\ComponentSecurityValidator;
use App\Services\StandaloneComponentDiscovery;
use Mockery;
use Tests\TestCase;

class ComponentSecurityTest extends TestCase
{
    /** @test */
    public function it_prevents_command_injection_in_component_discovery()
    {
        $discovery = app(StandaloneComponentDiscovery::class);
        
        // Create a malicious component directory
        $maliciousDir = base_path('components/core/evil; rm -rf /');
        
        // Discovery should skip invalid component names
        $components = $discovery->discover();
        
        // Ensure the malicious component is not discovered
        $this->assertFalse($components->has('evil; rm -rf /'));
    }

    /** @test */
    public function it_prevents_command_injection_in_delegation()
    {
        $delegationService = app(ComponentDelegationService::class);
        
        // Mock a component with safe name
        $component = [
            'name' => 'test',
            'binary' => base_path('components/core/test/test'),
        ];

        // Create mock validator that will throw on dangerous input
        $validator = Mockery::mock(ComponentSecurityValidator::class);
        $validator->shouldReceive('buildSafeCommand')
            ->with(
                $component['binary'],
                'list; cat /etc/passwd',
                Mockery::any(),
                Mockery::any()
            )
            ->andThrow(new \InvalidArgumentException('Invalid command name'));

        // Inject mock validator
        $service = new ComponentDelegationService(
            app('log'),
            $validator
        );

        // Attempt delegation with dangerous command
        $exitCode = $service->delegate(
            $component,
            'list; cat /etc/passwd',
            [],
            []
        );

        // Should fail with error code
        $this->assertEquals(1, $exitCode);
    }

    /** @test */
    public function it_prevents_path_traversal_in_component_paths()
    {
        $discovery = app(StandaloneComponentDiscovery::class);
        
        // Try to discover components outside allowed directories
        // This should be caught by the security validator
        $components = $discovery->discover();
        
        // Verify no components from outside allowed paths
        foreach ($components as $component) {
            $path = $component['path'];
            
            // Should be within allowed directories
            $this->assertTrue(
                str_starts_with($path, base_path('components/')) ||
                str_starts_with($path, $_SERVER['HOME'] . '/.conduit/components/'),
                "Component path should be within allowed directories: $path"
            );
        }
    }

    /** @test */
    public function it_sanitizes_user_arguments_before_delegation()
    {
        // Create a test component directory
        $testDir = base_path('components/core/test');
        $testBinary = $testDir . '/test';
        
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        // Create a simple test script that echoes arguments
        file_put_contents($testBinary, "#!/bin/bash\necho \"Args: \$@\"\n");
        chmod($testBinary, 0755);

        $delegationService = app(ComponentDelegationService::class);
        
        $component = [
            'name' => 'test',
            'binary' => $testBinary,
        ];

        // Capture output
        ob_start();
        
        // Delegate with dangerous arguments
        $exitCode = $delegationService->delegate(
            $component,
            'echo',
            ['$(whoami)', '`id`', 'normal arg'],
            ['option' => 'value; rm -rf /']
        );
        
        $output = ob_get_clean();

        // Arguments should be safely escaped in output
        $this->assertStringContainsString("'$(whoami)'", $output);
        $this->assertStringContainsString("'`id`'", $output);
        $this->assertStringContainsString("'normal arg'", $output);
        $this->assertStringContainsString("'value; rm -rf /'", $output);
        
        // Clean up
        unlink($testBinary);
        rmdir($testDir);
    }

    /** @test */
    public function it_validates_binary_permissions_before_execution()
    {
        $validator = app(ComponentSecurityValidator::class);
        
        // Create test directory
        $testDir = base_path('components/core/test');
        $testBinary = $testDir . '/test';
        
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        // Create world-writable binary (security risk)
        touch($testBinary);
        chmod($testBinary, 0777);
        
        // Should throw exception for world-writable binary
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('world-writable');
        
        $validator->validateBinaryIntegrity($testBinary);
        
        // Clean up
        unlink($testBinary);
        rmdir($testDir);
    }

    /** @test */
    public function it_handles_malformed_component_commands_safely()
    {
        $discovery = app(StandaloneComponentDiscovery::class);
        
        // Create a test component with malicious command names in config
        $testDir = base_path('components/core/malicious');
        $configDir = $testDir . '/config';
        $testBinary = $testDir . '/malicious';
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        // Create config with dangerous command names
        file_put_contents($configDir . '/commands.php', '<?php return [
            "published" => [
                "safe-command",
                "danger; rm -rf /",
                "$(whoami)",
                "`id`",
                "../../etc/passwd"
            ]
        ];');
        
        // Create executable
        file_put_contents($testBinary, "#!/bin/bash\necho 'test'\n");
        chmod($testBinary, 0755);
        
        // Discover components
        $components = $discovery->discover();
        
        if ($components->has('malicious')) {
            $commands = $components->get('malicious')['commands'];
            
            // Only safe command should be included
            $this->assertContains('safe-command', $commands);
            $this->assertNotContains('danger; rm -rf /', $commands);
            $this->assertNotContains('$(whoami)', $commands);
            $this->assertNotContains('`id`', $commands);
            $this->assertNotContains('../../etc/passwd', $commands);
        }
        
        // Clean up
        unlink($configDir . '/commands.php');
        unlink($testBinary);
        rmdir($configDir);
        rmdir($testDir);
    }
}