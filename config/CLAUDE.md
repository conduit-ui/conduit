# Config Directory - CLAUDE.md

## Overview
Configuration files for the Conduit Laravel Zero application, including command registration, database settings, and application configuration.

## Current State (2025-07-31)
- **Architecture**: Migrating from complex JSON-based component storage to simple global Composer management
- **Status**: Commands cleaned up, obsolete storage commands removed
- **Key Change**: Simplified component system eliminates need for most configuration complexity

## Knowledge Management
Track configuration insights and issues:
```bash
# Configuration patterns
conduit know:add "Laravel Zero config pattern X works well for Y use case" --tags=config,patterns

# Configuration bugs
conduit know:add "Config file Z causes issue when setting A=B" --tags=config,bugs --todo

# Environment differences
conduit know:add "Development vs production config differences for feature X" --tags=config,environments
```

## Key Configuration Files

### `commands.php` - Command Registration
**Purpose**: Explicit command registration and hiding
**Recent Changes**: 
- Removed `StorageInitCommand` reference (deleted service)
- Disabled broken commands that use old ComponentManager
- Added `KnowFallbackCommand` for legacy command migration

**Current Issues**:
- Some commands still commented out due to old service dependencies
- Need to re-enable after service migration complete

**Commands Hidden**:
- Laravel Zero development commands (BuildCommand, RenameCommand, etc.)
- Database commands (migrations, seeds) - dangerous for CLI app
- Internal/auto-handled commands

### `app.php` - Application Configuration  
**Key Settings**:
- Environment detection
- Service provider registration
- Component loading configuration

### `database.php` - Database Configuration
**Status**: Minimal configuration for optional database features
**Migration Note**: Moving away from database storage toward file-based/global solutions

### Component Configuration (Legacy)
**Deprecated**: `components.json` and related database-backed component storage
**Replacement**: Global Composer package management
**Migration**: Components now installed via `composer global require`

## Current Issues

### 🐛 Command Registration Dependencies
**Problem**: Some commands disabled due to old service dependencies
**Affected Commands**: UpdateCommand, CleanupCommand, SyncComponentsCommand
**Solution**: Update to use new ComponentService instead of ComponentManager
**Knowledge**: `conduit know:search "command registration" --tags=config,bugs`

### 🔄 Configuration Simplification
**Goal**: Eliminate complex configuration as architecture simplifies
**Progress**: Component storage config removed, command config cleaned
**Next**: Re-enable cleaned commands with new services

## Development Patterns

### Adding Commands to Registration
```php
// In config/commands.php 'add' array:
App\Commands\YourNewCommand::class,
```

### Hiding Commands from Help
```php  
// In config/commands.php 'hidden' array:
App\Commands\InternalCommand::class,
```

### Environment-Specific Configuration
- Use `config('app.env')` for environment detection
- Prefer environment variables over hardcoded values
- Document environment differences in knowledge system

## Testing Configuration
```bash
# Test command registration
./conduit list | grep "your-command"

# Test hidden commands (should not appear in help)
./conduit list | grep "hidden-command" # Should return nothing

# Validate configuration
php artisan config:cache # If using config caching

# Document test results
conduit know:add "Configuration test X passes/fails with details" --tags=config,testing
```

## Security Considerations
- No sensitive data in configuration files
- Use environment variables for secrets
- Validate file paths in component discovery
- Restrict command access in production environments

## Performance Notes
- Command registration happens once per request
- Configuration caching available but not typically needed for CLI
- Component discovery runs on each command execution

## Documentation Maintenance
**Update Frequency**: When configuration files are modified
**Last Updated**: 2025-07-31
**Update Command**:
```bash
conduit know:add "Updated config/CLAUDE.md - documented configuration change X" --tags=documentation,config
```

## Migration Guide: Old → New Architecture

### Before (Complex)
- Database-backed component storage
- JSON configuration files
- Multiple service dependencies
- Complex package installation

### After (Simple)
- Global Composer package management
- Minimal configuration
- Trait-based service composition
- Standard Composer commands

### Migration Steps
1. Remove old component storage config
2. Update command dependencies to new services
3. Test command registration
4. Document changes in knowledge system

## Related Knowledge Entries
```bash
conduit know:search "configuration" --tags=laravel-zero
conduit know:search "command registration" --tags=config
conduit know:search "environment" --tags=config
conduit know:search "component storage" --tags=deprecated
```