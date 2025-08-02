<?php

namespace Tests\Unit\Services\Security;

use App\Services\Security\ComponentSecurityValidator;
use Tests\TestCase;

class ComponentSecurityValidatorTest extends TestCase
{
    private ComponentSecurityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ComponentSecurityValidator;
    }

    /** @test */
    public function it_validates_safe_component_names()
    {
        $validNames = [
            'github',
            'github-zero',
            'my_component',
            'component123',
            'test-component_123',
        ];

        foreach ($validNames as $name) {
            $result = $this->validator->validateComponentName($name);
            $this->assertEquals($name, $result);
        }
    }

    /** @test */
    public function it_rejects_dangerous_component_names()
    {
        $dangerousNames = [
            'github; rm -rf /',
            'component && echo "hacked"',
            'test|cat /etc/passwd',
            'comp`whoami`',
            'test$(id)',
            '../../../etc/passwd',
            'component\\..\\..\\windows\\system32',
            'test<script>alert(1)</script>',
            'component"test',
            "component'test",
            'very_long_component_name_that_exceeds_fifty_characters_limit',
        ];

        foreach ($dangerousNames as $name) {
            $this->expectException(\InvalidArgumentException::class);
            $this->validator->validateComponentName($name);
        }
    }

    /** @test */
    public function it_validates_safe_command_names()
    {
        $validCommands = [
            'list',
            'github:pr-create',
            'component:install',
            'test-command',
            'command_with_underscore',
        ];

        foreach ($validCommands as $command) {
            $result = $this->validator->validateCommandName($command);
            $this->assertEquals($command, $result);
        }
    }

    /** @test */
    public function it_rejects_dangerous_command_names()
    {
        $dangerousCommands = [
            'list; rm -rf /',
            'command && cat /etc/passwd',
            'test|whoami',
            'cmd`id`',
            'test$(whoami)',
            'command"test',
            "command'test",
            'command<script>',
        ];

        foreach ($dangerousCommands as $command) {
            $this->expectException(\InvalidArgumentException::class);
            $this->validator->validateCommandName($command);
        }
    }

    /** @test */
    public function it_sanitizes_arguments_properly()
    {
        // Test that dangerous characters are properly escaped
        $dangerousInputs = [
            'simple' => 'simple',  // No quotes needed
            'with space' => "'with space'",  // Quoted
            'with;semicolon' => "'with;semicolon'",  // Quoted
            'with|pipe' => "'with|pipe'",  // Quoted
            'with&ampersand' => "'with&ampersand'",  // Quoted
            'with$dollar' => "'with\$dollar'",  // Quoted with escaped $
            'rm -rf /' => "'rm -rf /'",  // Quoted
            '$(whoami)' => "'$(whoami)'",  // Quoted but safe inside quotes
            '`id`' => "'`id`'",  // Quoted but safe inside quotes
        ];

        foreach ($dangerousInputs as $input => $expected) {
            $result = $this->validator->sanitizeArgument($input);

            // For simple comparison, just ensure it's the same as PHP's escapeshellarg
            $phpExpected = escapeshellarg($input);
            $this->assertEquals($phpExpected, $result, "Failed for input: $input");

            // Additional safety check: ensure the result is safe to use in shell
            // by checking it's properly quoted when needed
            if (preg_match('/[^a-zA-Z0-9_\-.]/', $input)) {
                $this->assertTrue(
                    (str_starts_with($result, "'") && str_ends_with($result, "'")) ||
                    (str_starts_with($result, '"') && str_ends_with($result, '"')),
                    "Result should be quoted for input: $input"
                );
            }
        }
    }

    /** @test */
    public function it_validates_component_paths_within_allowed_directories()
    {
        $basePath = base_path('components/core');
        $validPath = $basePath.'/github';

        $result = $this->validator->validateComponentPath($validPath);
        $this->assertStringStartsWith($basePath, $result);
    }

    /** @test */
    public function it_rejects_paths_outside_allowed_directories()
    {
        $dangerousPaths = [
            '/etc/passwd',
            '/usr/bin/rm',
            base_path('../../../etc/passwd'),
            '/tmp/malicious/component',
        ];

        foreach ($dangerousPaths as $path) {
            $this->expectException(\InvalidArgumentException::class);
            $this->validator->validateComponentPath($path);
        }
    }

    /** @test */
    public function it_detects_path_traversal_attempts()
    {
        $pathTraversalAttempts = [
            base_path('components/core/../../../etc/passwd'),
            base_path('components/core/../../config/app.php'),
            base_path('components/core/./../../storage'),
        ];

        foreach ($pathTraversalAttempts as $path) {
            $this->expectException(\InvalidArgumentException::class);
            $this->validator->validateComponentPath($path);
        }
    }

    /** @test */
    public function it_builds_safe_command_arrays()
    {
        // Create a test directory structure
        $testComponentDir = base_path('components/core/test-component');
        $testBinary = $testComponentDir.'/test-component';

        // Add test path to allowed paths
        $this->validator->addAllowedPath($testComponentDir);

        // Mock the binary existence check
        if (! is_dir($testComponentDir)) {
            mkdir($testComponentDir, 0755, true);
        }
        touch($testBinary);
        chmod($testBinary, 0755);

        $result = $this->validator->buildSafeCommand(
            $testBinary,
            'test:command',
            ['arg1', 'arg with space', 'arg;with;semicolon'],
            ['option1' => 'value1', 'flag' => true, 'dangerous' => 'val;ue']
        );

        // Check the command array is properly sanitized
        $this->assertEquals($testBinary, $result[0]);
        $this->assertEquals('delegated', $result[1]);
        $this->assertEquals('test:command', $result[2]);
        $this->assertEquals("'arg1'", $result[3]);
        $this->assertEquals("'arg with space'", $result[4]);
        $this->assertEquals("'arg;with;semicolon'", $result[5]);
        $this->assertEquals('--option1', $result[6]);
        $this->assertEquals("'value1'", $result[7]);
        $this->assertEquals('--flag', $result[8]);
        $this->assertEquals('--dangerous', $result[9]);
        $this->assertEquals("'val;ue'", $result[10]);

        // Clean up
        unlink($testBinary);
        rmdir($testComponentDir);
    }

    /** @test */
    public function it_validates_binary_integrity()
    {
        $testComponentDir = base_path('components/core/test-component');
        $testBinary = $testComponentDir.'/test-component';

        // Add test path to allowed paths
        $this->validator->addAllowedPath($testComponentDir);

        if (! is_dir($testComponentDir)) {
            mkdir($testComponentDir, 0755, true);
        }

        // Test non-existent binary
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Binary does not exist');
        $this->validator->validateBinaryIntegrity($testBinary);

        // Create binary
        touch($testBinary);

        // Test non-executable binary
        chmod($testBinary, 0644);
        try {
            $this->validator->validateBinaryIntegrity($testBinary);
            $this->fail('Should have thrown exception for non-executable binary');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('not executable', $e->getMessage());
        }

        // Test world-writable binary
        chmod($testBinary, 0777);
        try {
            $this->validator->validateBinaryIntegrity($testBinary);
            $this->fail('Should have thrown exception for world-writable binary');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('world-writable', $e->getMessage());
        }

        // Test valid binary
        chmod($testBinary, 0755);
        $this->validator->validateBinaryIntegrity($testBinary); // Should not throw

        // Clean up
        unlink($testBinary);
        rmdir($testComponentDir);
    }
}
