# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Conduit is a Laravel Zero CLI application that serves as a personal developer API gateway and MCP (Model Context Protocol) integration engine. It provides a microkernel architecture for integrating multiple developer tools through a component system.

## Development Commands (Conduit-First Approach)

```bash
# Install dependencies
composer install

# Use Conduit for development workflow
php conduit components list                    # See installed development tools
php conduit components discover               # Find new development components  
php conduit components install github        # Install GitHub component for repo management

# Core development tasks
./vendor/bin/pest                            # Run tests
./vendor/bin/pint                           # Code formatting

# Build and distribution
php -d phar.readonly=off vendor/bin/box compile  # Build PHAR executable
php conduit [command]                        # Run application locally
```

## Conduit-Powered Development Workflow

### Component Management
- **Components**: Modular functionality via `conduit components`
- **Discovery**: Find new tools via GitHub topics and Packagist
- **Installation**: Automated setup with `conduit components install`
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
- **AI-ready**: MCP integration for AI tool collaboration  
- **Laravel Zero Foundation**: Built on proven CLI framework patterns

### Planned Future Architecture
- **Component-first Evolution**: Migrate toward discoverable component system
- **Microkernel Vision**: Minimal core with modular functionality
- **Enhanced MCP Integration**: Deeper AI agent collaboration features

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