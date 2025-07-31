# Services Directory - CLAUDE.md

## Overview
This directory contains service classes that provide business logic and external integrations for Conduit.

## Current State (2025-07-31)
- **Architecture**: Major simplification in progress - moving from complex multi-service to trait-based composition
- **Migration Status**: Old services being removed, new ComponentService with traits implemented
- **Key Achievement**: Eliminated 6,266+ lines of code while maintaining functionality

## Knowledge Management
Document service patterns and issues:
```bash
# Service patterns
conduit know:add "Trait composition works better than inheritance for service architecture" --tags=services,architecture

# Integration issues  
conduit know:add "Service X integration fails when Y condition" --tags=services,bugs --todo

# Performance insights
conduit know:add "Service caching pattern improved response time by 50%" --tags=services,performance
```

## Current Services

### ✅ Active Services
- `ComponentService.php` - **NEW**: Composite service using traits for component management
- `GithubAuthService.php` - GitHub authentication management
- `PrAnalysisService.php` - Pull request analysis logic
- `CommentThreadService.php` - PR comment thread handling
- `VoiceNarrationService.php` - Voice interface for GitHub content

### 🗑️ Removed Services (Architecture Simplification)
- `ComponentManager.php` - **DELETED**: Complex component management
- `ComponentStorage.php` - **DELETED**: Database-based component storage  
- `JsonComponentRegistrar.php` - **DELETED**: JSON-based component registry
- `SecurePackageInstaller.php` - **DELETED**: Complex package installation
- `ComponentUpdateChecker.php` - **DELETED**: Update checking system

### 🔄 New Architecture: Trait-Based Services

#### ComponentService Traits
Located in `Services/Traits/`:
- `ManagesPackages.php` - Common package management operations
- `InstallsComponents.php` - Component installation logic
- `UninstallsComponents.php` - Component removal logic  
- `ListsComponents.php` - Component listing and discovery
- `DiscoverComponents.php` - GitHub-based component discovery

**Benefits**:
- Single responsibility principle
- Composable functionality
- Easier testing
- Reduced complexity

## Current Issues

### 🐛 GlobalComponentDiscovery Service Provider Registration
**Problem**: Service providers from global components not registering properly
**Impact**: Global component commands don't appear in local conduit
**Status**: Under investigation
**Knowledge**: `conduit know:search "GlobalComponentDiscovery" --tags=services,bugs`

### 🔄 Service Container Cleanup
**Problem**: Some commands still trying to inject deleted services
**Solution**: Systematic cleanup of constructor dependencies
**Progress**: ComponentStorage references removed, others in progress

## Development Patterns

### Creating New Services
1. Consider trait composition over large monolithic classes
2. Implement relevant interfaces for type safety
3. Use dependency injection for external dependencies
4. Document with knowledge system:
```bash
conduit know:add "New service pattern works well for specific use case" --tags=services,patterns
```

### Service Testing
```bash
# Test service integration
./vendor/bin/pest tests/Unit/Services/

# Document test insights
conduit know:add "Service testing pattern catches edge case X" --tags=services,testing
```

### Integration Patterns
- Use Laravel's service container for dependency injection
- Prefer constructor injection over service location
- Implement graceful failure for optional external services
- Cache expensive operations where appropriate

## Performance Considerations
- **Global Component Loading**: Added once per request, cached in memory
- **GitHub API**: Rate limited, implement retry logic with backoff
- **Database Operations**: Use query builder for performance
- **File Operations**: Validate paths to prevent directory traversal

## Security Notes
- All file paths validated in GlobalComponentDiscovery
- GitHub tokens handled through secure authentication service
- Component installation restricted to allowed directories
- Class name validation prevents code injection

## Documentation Maintenance
**Update Frequency**: When services are added/modified/removed
**Last Updated**: 2025-07-31
**Update Command**:
```bash
conduit know:add "Updated Services/CLAUDE.md - documented changes to service X" --tags=documentation,services
```

## Related Knowledge Entries
```bash
conduit know:search "services" --tags=architecture
conduit know:search "component management" --tags=services
conduit know:search "trait composition" --tags=patterns
conduit know:search "dependency injection" --tags=services
```