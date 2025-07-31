# Tests Directory - CLAUDE.md

## Overview
Test suite for the Conduit CLI application using Pest PHP testing framework.

## Current State (2025-07-31)
- **Framework**: Pest PHP for expressive testing
- **Status**: Tests need updating after major architecture simplification
- **Key Issue**: Many tests reference deleted services (ComponentManager, ComponentStorage)
- **Coverage**: Tests exist but may be outdated after 6,266+ lines of code removal

## Knowledge Management
Track testing insights and patterns:
```bash
# Testing patterns
conduit know:add "Pest test pattern X works well for testing Y functionality" --tags=testing,pest

# Test failures
conduit know:add "Test suite fails with error X after change Y" --tags=testing,bugs --todo

# Coverage insights
conduit know:add "Added tests for edge case X after production bug" --tags=testing,coverage
```

## Test Structure

### Unit Tests (`tests/Unit/`)
- **Purpose**: Test individual classes and methods in isolation
- **Status**: ⚠️ Needs cleanup - many reference deleted services
- **Pattern**: One test file per service/class

### Feature Tests (`tests/Feature/`)
- **Purpose**: Test complete user workflows and command interactions
- **Status**: ⚠️ May need updates for new component architecture
- **Pattern**: Test user-facing functionality end-to-end

### Test Configuration
- `Pest.php` - Test configuration and shared setup
- `TestCase.php` - Base test case with common functionality

## Current Issues

### 🐛 Obsolete Test References
**Problem**: Tests reference deleted services (ComponentStorage, ComponentManager)
**Impact**: Test suite may fail to run
**Solution**: Update or remove obsolete tests
**Knowledge**: `conduit know:search "obsolete tests" --tags=testing,cleanup`

### 🔄 Architecture Migration Testing
**Challenge**: New trait-based service architecture needs new test patterns
**Opportunity**: Simpler architecture = simpler tests
**Priority**: High - ensure new ComponentService works correctly

## Testing Patterns

### Pest PHP Best Practices
```php
// Use descriptive test names
it('installs components via global composer', function () {
    // Test implementation
});

// Use datasets for multiple scenarios
it('handles various component names', function ($componentName, $expected) {
    // Test with different inputs
})->with([
    ['knowledge', 'jordanpartridge/conduit-knowledge'],
    ['spotify', 'jordanpartridge/conduit-spotify'],
]);

// Test command output
it('shows helpful error message when component not found', function () {
    $this->artisan('install invalid-component')
         ->expectsOutput('Component not found')
         ->assertExitCode(1);
});
```

### Testing Component Installation
```php
// Mock composer processes
Process::fake([
    'composer global require *' => Process::result(output: 'Success'),
]);

// Test successful installation
it('installs components globally', function () {
    $service = new ComponentService();
    $result = $service->install('knowledge');
    
    expect($result->isSuccessful())->toBeTrue();
});
```

### Testing Command Interactions
```php
// Test command flow
it('migrates from know to knowledge command', function () {
    $this->artisan('know add "test"')
         ->expectsOutput('commands have been removed')
         ->expectsOutput('conduit-knowledge component')
         ->assertExitCode(1);
});
```

## Running Tests

### Full Test Suite
```bash
# Run all tests
./vendor/bin/pest

# Run with coverage (if configured)
./vendor/bin/pest --coverage

# Document test results
conduit know:add "Test suite status: X passing, Y failing, Z skipped" --tags=testing,status
```

### Specific Test Categories
```bash
# Unit tests only
./vendor/bin/pest tests/Unit/

# Feature tests only  
./vendor/bin/pest tests/Feature/

# Specific test file
./vendor/bin/pest tests/Unit/Services/ComponentServiceTest.php
```

### Debugging Tests
```bash
# Verbose output
./vendor/bin/pest -v

# Stop on first failure
./vendor/bin/pest --stop-on-failure

# Filter by test name
./vendor/bin/pest --filter="component installation"
```

## Test Maintenance Tasks

### Immediate Priorities
1. **Remove obsolete tests** for deleted services
2. **Update service tests** to use new ComponentService
3. **Add tests** for new trait-based architecture
4. **Verify command tests** still work after command changes

### Test Categories Needing Updates
- Component management tests
- Service integration tests  
- Command execution tests
- Global component discovery tests

## Mock Patterns

### External Process Mocking
```php
// Mock composer commands
Process::fake([
    'composer global show *' => Process::result(
        output: json_encode(['installed' => [/* mock data */]])
    ),
]);
```

### File System Mocking
```php
// Mock file operations
Storage::fake('local');
File::shouldReceive('exists')->andReturn(true);
```

### GitHub API Mocking
```php
// Mock GitHub client responses
Http::fake([
    'api.github.com/*' => Http::response(['data' => 'mock'], 200)
]);
```

## Performance Testing
```bash
# Time test execution
time ./vendor/bin/pest

# Profile memory usage
./vendor/bin/pest --memory-limit=128M

# Document performance insights
conduit know:add "Test suite runs in X seconds, Y memory usage" --tags=testing,performance
```

## Documentation Maintenance
**Update Frequency**: After significant code changes or test updates
**Last Updated**: 2025-07-31  
**Update Command**:
```bash
conduit know:add "Updated tests/CLAUDE.md - documented test changes for feature X" --tags=documentation,testing
```

## Related Knowledge Entries
```bash
conduit know:search "testing" --tags=pest,php
conduit know:search "mocking" --tags=testing,patterns
conduit know:search "test coverage" --tags=testing,quality
conduit know:search "component testing" --tags=testing,architecture
```