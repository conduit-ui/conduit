# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Conduit is a Laravel Zero CLI application that serves as a personal developer API gateway. It provides a microkernel architecture for integrating multiple developer tools through a discoverable component system.

## Development Commands (Conduit-First Approach)

```bash
# Install dependencies
composer install                             # Auto-syncs components via post-install hook

# Use Conduit for development workflow
php conduit components list                  # See installed development tools
php conduit components discover             # Find new development components  
php conduit install github                  # Install GitHub component for repo management

# Core development tasks
./vendor/bin/pest                           # Run tests
./vendor/bin/pint                          # Code formatting

# Component management for clean releases
php conduit system:cleanup --components     # Remove all components before commit
php conduit system:sync-components          # Re-sync components after pull/install

# Build and distribution
php -d phar.readonly=off vendor/bin/box compile  # Build PHAR executable (always clean)
php conduit [command]                       # Run application locally
```

## Conduit-Powered Development Workflow

### Component Management
- **Components**: Modular functionality via `conduit components`
- **Discovery**: Find new tools via GitHub topics and Packagist
- **Installation**: Automated setup with `conduit install`
- **Registry**: Curated vs community components

### Future Development Commands
Once component ecosystem is built, development will use:
```bash
# Version control (via github-zero component)
conduit github repos                        # List repositories
conduit github clone <repo>                 # Clone repositories
conduit github pr create                    # Create pull requests

# Package management (via conduit-composer component)  
conduit composer require <package>          # Smart package installation
conduit composer audit                      # Security and dependency analysis

# Testing and quality (via conduit-laravel component)
conduit laravel test                        # Run Laravel tests
conduit laravel migrate                     # Database migrations
```

## Architecture

### Current Architecture (Laravel Zero Foundation)
- **Core Application**: Laravel Zero CLI framework with standard command structure
- **Commands**: Extend `LaravelZero\Framework\Commands\Command` 
- **Services**: Trait-based composition with dependency injection
- **Components**: External component system via `conduit-components/` directory

### Actual Component Structure
Components in `conduit-components/` use:
- **AbstractGitHubComponent**: Base class for GitHub integrations
- **Service Providers**: Laravel service provider registration
- **Standalone Architecture**: Can work independently or within Conduit

### Current Command Patterns
- Commands extend Laravel Zero's `Command` class
- Service layer with trait composition (e.g., `ManagesBranches`, `ManagesReviewers`)
- Interface-driven design with proper separation of concerns
- Dependency injection via constructor or service container

### Development Philosophy
- **Conduit builds Conduit**: Use Conduit itself for development tasks
- **Component Discovery**: Automated finding and installation of functionality
- **Extensible**: Component-based architecture for unlimited functionality  
- **Laravel Zero Foundation**: Built on proven CLI framework patterns

### Planned Future Architecture
- **Component-first Evolution**: Migrate toward discoverable component system
- **Microkernel Vision**: Minimal core with modular functionality
- **Enhanced Component Ecosystem**: Rich marketplace of developer tools

## Testing Strategy
- **Unit Tests**: Individual component testing
- **Integration Tests**: Component installation and interaction
- **End-to-end**: Full workflow testing with multiple components
- **Self-validation**: Components can test their own health

## Key Dependencies
- `laravel-zero/framework`: CLI framework foundation
- `jordanpartridge/conduit-component`: Base component interface (planned)
- `jordanpartridge/packagist-client`: Component discovery
- `jordanpartridge/github-client`: GitHub integration foundation

## Component Lifecycle Management

### The Problem
Components are runtime dependencies that get added to `composer.json` when installed. This creates challenges:
- Developers install components locally for testing
- `composer.json` gets committed with component dependencies  
- Other developers pull the repo but components aren't properly registered
- PHAR builds include unnecessary component code

### The Solution
Conduit implements automatic component lifecycle management:

#### Post-Install Hook
```bash
# Runs automatically after composer install/update
php conduit system:sync-components --silent
```
- Reads `config/components.json` registry
- Re-registers any components with installed packages
- Ensures service providers are properly loaded
- Maintains component state across team members

#### Pre-Commit Cleanup
```bash  
# Run before committing for clean releases
php conduit system:cleanup --components
```
- Removes all installed components from `composer.json`
- Clears component registry
- Ensures PHAR builds are minimal
- Prevents component dependencies in releases

#### Development Workflow
1. **Install components**: `conduit install spotify`
2. **Develop with components**: All commands available
3. **Before commit**: `conduit system:cleanup --components` 
4. **Commit clean code**: No component dependencies
5. **After pull**: `composer install` auto-syncs components

This ensures clean releases while preserving development flexibility.