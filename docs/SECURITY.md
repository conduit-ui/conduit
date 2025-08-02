# Conduit Security Documentation

## Component System Security

The Conduit component system implements multiple layers of security to prevent code injection, path traversal, and other vulnerabilities.

### Security Features

#### 1. Path Validation and Sandboxing
- Components are restricted to specific directories:
  - `{app}/components/core` - Core components
  - `{app}/components/dev` - Development components  
  - `~/.conduit/components` - User-installed components
- Path traversal attempts are detected and blocked
- All paths are canonicalized to prevent `../` attacks

#### 2. Input Validation
- **Component Names**: Must match `/^[a-zA-Z0-9\-_]+$/` (alphanumeric, hyphens, underscores)
- **Command Names**: Must match `/^[a-zA-Z0-9\-_:]+$/` (includes colons for namespacing)
- **Length Limits**: Component names (50 chars), command names (100 chars)

#### 3. Command Injection Prevention
- All shell arguments are escaped using `escapeshellarg()`
- Process execution uses array syntax, not string concatenation
- No direct `shell_exec()` or `exec()` calls with user input
- Timeouts enforced on all component executions (60 seconds default)

#### 4. Binary Integrity Checks
- Binaries must exist and be executable
- World-writable binaries are rejected (security risk)
- Binary name must match component directory name

#### 5. Sanitization Pipeline
```php
// All user input goes through this pipeline:
1. Validate component name format
2. Validate path is within allowed directories  
3. Validate binary exists and has secure permissions
4. Validate command name format
5. Sanitize all arguments with escapeshellarg()
6. Build safe command array for Process execution
```

### Security Best Practices for Component Authors

1. **Never trust user input** - Always validate and sanitize
2. **Use the delegated command pattern** - Let Conduit handle security
3. **Avoid shell execution** - Use PHP functions or Process class
4. **Validate file paths** - Ensure they're within expected directories
5. **Set proper permissions** - Binaries should be 755, not 777

### Threat Model

The component system defends against:

- **Command Injection**: Malicious input like `; rm -rf /`
- **Path Traversal**: Attempts to access `../../etc/passwd`
- **Binary Hijacking**: Replacing legitimate binaries
- **Argument Injection**: Special characters in arguments
- **Permission Escalation**: World-writable binaries

### Security Testing

Run security tests with:
```bash
./vendor/bin/pest tests/Unit/Services/Security/ComponentSecurityValidatorTest.php
./vendor/bin/pest tests/Feature/ComponentSecurityTest.php
```

### Reporting Security Issues

If you discover a security vulnerability, please email security@conduit.dev instead of using the issue tracker.

## Implementation Details

### ComponentSecurityValidator

The `ComponentSecurityValidator` class provides centralized security validation:

```php
// Validate paths
$validator->validateComponentPath($path);
$validator->validateBinaryPath($binaryPath);

// Validate names
$validator->validateComponentName($name);
$validator->validateCommandName($command);

// Sanitize input
$safeArgs = $validator->sanitizeArguments($arguments);
$safeOpts = $validator->sanitizeOptions($options);

// Build safe command
$commandArray = $validator->buildSafeCommand(
    $binary,
    $command, 
    $arguments,
    $options
);
```

### Integration Points

1. **StandaloneComponentDiscovery**: Validates all discovered components
2. **ComponentDelegationService**: Sanitizes all delegation requests
3. **DynamicDelegationCommand**: Validates command routing

### Logging

Security violations are logged but not exposed to users:
- Invalid component names during discovery
- Failed validation during delegation
- Permission issues with binaries

This prevents information leakage while maintaining audit trails.