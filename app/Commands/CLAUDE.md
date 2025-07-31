# Commands Directory - CLAUDE.md

## Overview
This directory contains Laravel Zero command classes for the Conduit CLI application.

## Current State (2025-07-31)
- **Architecture**: Simplified component system in progress
- **Migration Status**: Legacy commands being cleaned up, new component-based architecture being implemented
- **Key Issue**: GlobalComponentDiscovery service providers not registering properly for knowledge commands

## Knowledge Management
Use the knowledge system to track command-related insights:
```bash
# Document command patterns
conduit know:add "Laravel Zero commands should extend Command and use Laravel Prompts for user interaction" --tags=commands,patterns

# Track bugs
conduit know:add "Command XYZ fails with error ABC" --tags=commands,bugs --todo

# Document solutions
conduit know:add "Fixed command loading by updating service provider registration" --tags=commands,solutions
```

## Directory Structure

### Core Commands
- `InstallCommand.php` - Install components via global composer
- `UninstallCommand.php` - Remove components via global composer  
- `SummaryCommand.php` - Enhanced list command with component status
- `KnowFallbackCommand.php` - Migration handler for legacy know commands

### Component Commands
- `Component/CertifyCommand.php` - Component certification testing
- `Component/ConfigCommand.php` - Component scaffolding configuration

### Migration Commands  
- `MigrateKnowledgeCommand.php` - Migrate from built-in to component-based knowledge

### Legacy/Deprecated
Commands using old ComponentManager/ComponentStorage architecture are being phased out.

## Current Issues

### 🐛 Component Command Loading
**Problem**: Global components install successfully but their commands don't appear
**Status**: Investigating GlobalComponentDiscovery service provider registration
**Knowledge Entry**: `conduit know:search "GlobalComponentDiscovery" --tags=bugs`

### 🔄 Architecture Migration
**Problem**: Mixed old/new service architecture causing conflicts
**Solution**: Systematic cleanup of old service references
**Progress**: ComponentStorage references removed, ComponentManager cleanup in progress

## Development Patterns

### Adding New Commands
1. Extend `LaravelZero\Framework\Commands\Command`
2. Use Laravel Prompts for user interaction (`use function Laravel\Prompts\info;`)
3. Document with knowledge system: `conduit know:add "New command pattern X works well for Y" --tags=commands,patterns`

### Service Injection
- Use new `ComponentService` instead of old `ComponentManager`
- Avoid direct database dependencies - use traits for composable functionality

### Error Handling
- Fail gracefully with helpful error messages
- Use Laravel Prompts for consistent error display
- Document errors: `conduit know:add "Command error pattern and solution" --tags=commands,errors`

## Testing Commands
```bash
# Test command registration
./conduit list | grep "your-command"

# Test command execution
./conduit your-command --help
./conduit your-command

# Document test results
conduit know:add "Command tests pass/fail with details" --tags=commands,testing
```

## Documentation Maintenance
**Update Frequency**: Weekly or when significant changes occur
**Last Updated**: 2025-07-31
**Update Command**: 
```bash
conduit know:add "Updated Commands/CLAUDE.md - document changes made" --tags=documentation,commands
```

## Related Knowledge Entries
Search for related documentation:
```bash
conduit know:search "commands" --tags=documentation
conduit know:search "Laravel Zero" --tags=patterns
conduit know:search "component loading" --tags=bugs
```