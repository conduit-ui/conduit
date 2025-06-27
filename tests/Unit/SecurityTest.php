<?php

namespace Tests\Unit;

use App\Services\SecurePackageInstaller;
use App\ValueObjects\Component;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SecurityTest extends TestCase
{
    #[DataProvider('maliciousInputProvider')]
    public function test_package_name_validation_prevents_command_injection($maliciousInput)
    {
        $installer = new SecurePackageInstaller;
        $reflection = new ReflectionClass($installer);
        $method = $reflection->getMethod('validatePackageName');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($installer, $maliciousInput);
    }

    public static function maliciousInputProvider()
    {
        return [
            'command injection with semicolon' => ['evil; rm -rf /'],
            'command injection with and operator' => ['package && echo pwned'],
            'command injection with semicolon and file read' => ['vendor/package; cat /etc/passwd'],
            'command injection with pipe' => ['valid/package | malicious-command'],
            'command injection with backticks' => ['package`whoami`'],
            'command injection with command substitution' => ['package$(id)'],
        ];
    }

    #[DataProvider('validInputProvider')]
    public function test_package_name_validation_accepts_valid_names($validInput)
    {
        $installer = new SecurePackageInstaller;
        $reflection = new ReflectionClass($installer);
        $method = $reflection->getMethod('validatePackageName');
        $method->setAccessible(true);

        // Should not throw exception
        $result = $method->invoke($installer, $validInput);
        $this->assertTrue(true); // Assertion to confirm no exception
    }

    public static function validInputProvider()
    {
        return [
            'basic vendor/package format' => ['vendor/package'],
            'real world example' => ['jordanpartridge/github-zero'],
            'laravel framework example' => ['laravel-zero/framework'],
            'hyphenated package name' => ['company/my-package'],
            'package with dot' => ['dev/package.name'],
            'package with underscore' => ['org/package_name'],
        ];
    }

    #[DataProvider('invalidInputProvider')]
    public function test_package_name_validation_rejects_invalid_formats($invalidInput)
    {
        $installer = new SecurePackageInstaller;
        $reflection = new ReflectionClass($installer);
        $method = $reflection->getMethod('validatePackageName');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($installer, $invalidInput);
    }

    public static function invalidInputProvider()
    {
        return [
            'missing vendor' => ['just-package-name'],
            'missing package name' => ['vendor/'],
            'missing vendor prefix' => ['/package'],
            'uppercase vendor' => ['VENDOR/package'],
            'uppercase package' => ['vendor/Package'],
            'double slash' => ['vendor//package'],
            'empty string' => [''],
            'too long' => [str_repeat('a', 101)],
        ];
    }

    public function test_secure_process_execution_prevents_injection()
    {
        $installer = new SecurePackageInstaller;

        // Test that the installer uses secure process execution
        // We can't easily test the actual process execution without side effects,
        // but we can verify the validation happens before execution

        $maliciousComponent = new Component(
            name: 'malicious',
            fullName: 'malicious; rm -rf /',
            description: 'Test malicious component',
            url: 'https://example.com'
        );

        $this->expectException(\InvalidArgumentException::class);
        $installer->install($maliciousComponent);
    }

    public function test_service_layer_separation()
    {
        // Test that our services are properly separated
        $installer = new SecurePackageInstaller;
        $this->assertInstanceOf(SecurePackageInstaller::class, $installer);

        // Verify key security methods exist
        $reflection = new ReflectionClass($installer);
        $this->assertTrue($reflection->hasMethod('validatePackageName'));
        $this->assertTrue($reflection->hasMethod('verifyPackageEligibility'));
    }
}
