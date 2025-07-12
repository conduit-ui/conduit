# Conduit User Guide

**Your Personal Developer Operating System**

---

## Table of Contents

- [What is Conduit?](#what-is-conduit)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Essential Commands](#essential-commands)
- [Component System](#component-system)
- [Context-Aware Features](#context-aware-features)
- [Interactive Mode](#interactive-mode)
- [Common Workflows](#common-workflows)
- [Advanced Usage](#advanced-usage)
- [Troubleshooting](#troubleshooting)

---

## What is Conduit?

Conduit transforms your terminal into a **Personal Developer Operating System** that eliminates tool friction and optimizes your workflow through AI-powered orchestration.

### The Problem Conduit Solves

Instead of juggling multiple tools and browser tabs:
- ❌ Switching between terminal, IDE, browser, and Slack
- ❌ Hunting for GitHub issues across repositories
- ❌ Remembering which tools to use for which tasks
- ❌ Context switching that breaks your flow

### Conduit's Solution

- ✅ **Single Interface**: Everything through your terminal
- ✅ **Context Awareness**: Tools adapt to your current project
- ✅ **AI Orchestration**: Tools work together intelligently
- ✅ **Component System**: Extensible and customizable

---

## Quick Start

### Installation

#### Method 1: Direct Download (Recommended)
```bash
# Download and install Conduit
curl -L https://github.com/jordanpartridge/conduit/releases/latest/download/conduit -o conduit
chmod +x conduit
sudo mv conduit /usr/local/bin/
```

#### Method 2: From Source
```bash
# Clone and build from source
git clone https://github.com/jordanpartridge/conduit.git
cd conduit
composer install
php -d phar.readonly=off vendor/bin/box compile
```

### First Run Setup

```bash
# Initialize Conduit storage
conduit storage:init

# Verify installation
conduit

# Discover available components
conduit components discover

# Install essential components
conduit components install github-zero
```

### Your First Commands

```bash
# Check current project context
conduit context

# List available commands
conduit list

# Get help for any command
conduit help components
```

---

## Core Concepts

### 1. Microkernel Architecture

Conduit's core is **intentionally minimal**. All functionality comes through **components** that you install based on your needs.

```
Conduit Core (Minimal)
├── Component Management System
├── Context Detection Engine
├── Command Registration
└── SQLite Storage

Components (Extensible)
├── github-zero (GitHub integration)
├── laravel-tools (Laravel workflows)
├── spotify-control (Music management)
└── [Your custom components]
```

### 2. Context Awareness

Conduit automatically detects your environment and activates relevant components:

```bash
# In a Laravel project
conduit context
📍 Laravel project • Git repo • GitHub integration

# Available commands adapt
conduit list
# Shows Laravel-specific commands like:
# - conduit laravel:test
# - conduit laravel:migrate
# - conduit artisan
```

### 3. Component Lifecycle

Components are **Composer packages** with special metadata:

```json
{
    "name": "your-name/conduit-spotify",
    "keywords": ["conduit-component"],
    "extra": {
        "conduit": {
            "activation": {
                "activation_events": ["context:development"]
            }
        }
    }
}
```

---

## Essential Commands

### Component Management

```bash
# Discover new components
conduit components discover

# List all available components
conduit components list

# Install a component
conduit components install github-zero

# Uninstall a component
conduit components uninstall component-name

# Show installation status
conduit components status
```

### Context & Environment

```bash
# Show current directory context
conduit context

# Show context as JSON (for scripts)
conduit context --json

# Show components with activation status
conduit components list --show-context
```

### Interactive Mode

```bash
# Enable interactive mode (default)
conduit interactive enable

# Disable interactive mode
conduit interactive disable

# Check current status
conduit interactive status
```

### GitHub Integration (with github-zero component)

```bash
# List your repositories
conduit ghz:repos

# Clone with interactive selection
conduit ghz:clone

# Repository shortcuts
conduit repos           # Alias for ghz:repos
conduit clone           # Alias for ghz:clone
```

---

## Component System

### Understanding Components

Components are **self-contained packages** that extend Conduit's capabilities:

#### Core Components (Maintained by Conduit team)
- **github-zero**: GitHub repository management
- **laravel-tools**: Laravel development workflows
- **composer-manager**: Smart package management

#### Community Components
- **spotify-control**: Music and environment control
- **docker-tools**: Container management
- **task-manager**: Project task tracking

### Installing Components

#### From Discovery
```bash
# Find components automatically
conduit components discover

# Results show:
# ✓ github-zero (core)
# ✓ laravel-tools (certified)
# ○ spotify-control (community)

# Install interactively
conduit components install
# Shows list for selection

# Install specific component
conduit components install github-zero
```

#### From URL
```bash
# Install from GitHub URL
conduit components install https://github.com/user/conduit-component

# Install from Packagist
conduit components install vendor/package-name
```

### Component Configuration

Each component can be configured through:

#### 1. Environment Variables
```bash
# Set in .env or shell profile
export GITHUB_TOKEN=your_token
export SPOTIFY_CLIENT_ID=your_id
```

#### 2. Conduit Settings
```bash
# Set component-specific settings
conduit config set github-zero.default_org "your-org"
conduit config set interactive_mode true
```

#### 3. Context-Based Activation
Components automatically activate based on your environment:

```yaml
# Component activates in Laravel projects
activation_events:
  - context:laravel
  - framework:laravel
```

---

## Context-Aware Features

### Automatic Context Detection

Conduit analyzes your current directory to provide relevant tools:

```bash
# In a Laravel project
conduit context
Working Directory: /Users/you/projects/my-app
Git Repository: ✓ (main branch)
GitHub Integration: ✓ (your-org/my-app)
Project Type: Laravel
Languages: PHP, JavaScript
Package Managers: Composer, npm
CI/CD: GitHub Actions
```

### Context-Driven Commands

Available commands change based on context:

#### In a Git Repository
```bash
conduit list
# Shows git-related commands:
# - conduit ghz:repos
# - conduit ghz:clone
# - git workflow shortcuts
```

#### In a Laravel Project
```bash
conduit list
# Shows Laravel commands:
# - conduit laravel:test
# - conduit laravel:migrate
# - conduit artisan [command]
```

#### In a Node.js Project
```bash
conduit list
# Shows Node.js commands:
# - conduit npm:audit
# - conduit yarn:workspace
# - conduit node:version
```

### Context Commands

```bash
# Show detailed context
conduit context

# Context as JSON for scripting
conduit context --json

# Show which components are active
conduit components --show-context
```

---

## Interactive Mode

Interactive mode provides **enhanced user experience** with prompts, confirmations, and guided workflows.

### Enabling Interactive Mode

```bash
# Enable (default)
conduit interactive enable

# Disable for scripts
conduit interactive disable

# Check status
conduit
# Shows: 🎛️ Interactive Mode: ENABLED
```

### Interactive Features

#### 1. Component Installation
```bash
conduit components install
# Shows interactive selection:
# ┌─ Available Components ─┐
# │ ○ github-zero (core)   │
# │ ○ laravel-tools        │
# │ ○ spotify-control      │
# └────────────────────────┘
```

#### 2. Repository Selection
```bash
conduit ghz:clone
# Interactive repository browser:
# ┌─ Your Repositories ────┐
# │ ○ jordanpartridge/conduit │
# │ ○ company/secret-project  │
# │ ○ personal/side-project   │
# └───────────────────────────┘
```

#### 3. Confirmation Prompts
```bash
conduit components uninstall github-zero
# Prompt: Remove github-zero component? [y/N]
```

### Non-Interactive Mode

Perfect for **scripts and automation**:

```bash
# Disable prompts
conduit interactive disable

# Or use --no-interaction flag
conduit components install github-zero --no-interaction

# Scripting example
#!/bin/bash
conduit interactive disable
conduit components install github-zero
conduit ghz:clone jordanpartridge/conduit
```

---

## Common Workflows

### Daily Development Routine

#### Morning Startup
```bash
# Check project status
conduit context

# See GitHub repositories
conduit repos

# Check for updates
conduit components discover
```

#### During Development
```bash
# Quick repository switching
conduit clone

# Check current project context
conduit context

# Access project-specific tools
conduit laravel:test    # If in Laravel project
conduit npm:audit       # If in Node.js project
```

#### End of Day
```bash
# Review today's work
conduit ghz:repos --recent

# Check component status
conduit components status
```

### Component Management Workflow

#### Initial Setup
```bash
# 1. Discover available components
conduit components discover

# 2. Install essential components
conduit components install github-zero

# 3. Verify installation
conduit components list
```

#### Regular Maintenance
```bash
# Check for new components
conduit components discover --update

# Review installed components
conduit components list --show-context

# Remove unused components
conduit components uninstall unused-component
```

### Project Onboarding

#### New Project Setup
```bash
# 1. Navigate to project
cd /path/to/new-project

# 2. Check project context
conduit context

# 3. Install relevant components
conduit components install laravel-tools  # If Laravel
conduit components install docker-tools   # If using Docker

# 4. Verify setup
conduit list
```

#### Team Onboarding
```bash
# Share component configuration
conduit config export > conduit-config.json

# Team member imports
conduit config import conduit-config.json
```

---

## Advanced Usage

### Custom Component Development

#### 1. Create Component Package
```bash
# Create new Composer package
composer init

# Add conduit-component topic
# Add conduit metadata to composer.json
{
    "keywords": ["conduit-component"],
    "extra": {
        "conduit": {
            "activation": {
                "activation_events": ["context:laravel"]
            }
        }
    }
}
```

#### 2. Implement Component Interface
```php
<?php
namespace YourNamespace\ConduitSpotify;

use ConduitIo\Core\Contracts\ComponentInterface;

class SpotifyComponent implements ComponentInterface
{
    public function getName(): string
    {
        return 'spotify-control';
    }

    public function getCommands(): array
    {
        return [
            'spotify:play',
            'spotify:pause',
            'spotify:next'
        ];
    }
}
```

#### 3. Register Service Provider
```php
<?php
namespace YourNamespace\ConduitSpotify;

use Illuminate\Support\ServiceProvider;

class SpotifyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            Commands\PlayCommand::class,
            Commands\PauseCommand::class,
        ]);
    }
}
```

### Environment Configuration

#### System-Wide Settings
```bash
# Set global Conduit settings
export CONDUIT_INTERACTIVE=true
export CONDUIT_CONFIG_PATH=~/.conduit

# Add to shell profile
echo 'export CONDUIT_INTERACTIVE=true' >> ~/.zshrc
```

#### Project-Specific Settings
```bash
# Create .conduit file in project root
echo '{"interactive_mode": false}' > .conduit

# Project-specific component activation
echo '{"components": ["laravel-tools", "docker-tools"]}' > .conduit
```

### Scripting with Conduit

#### Bash Integration
```bash
#!/bin/bash
# deployment-script.sh

# Disable interactive mode for scripts
conduit interactive disable

# Use Conduit commands in scripts
conduit context --json | jq '.project_type'

if conduit context --json | jq -r '.project_type' == "laravel"; then
    conduit laravel:test
    conduit laravel:deploy
fi
```

#### Alias Creation
```bash
# Add to shell profile
alias cctx='conduit context'
alias crepos='conduit ghz:repos'
alias cclone='conduit ghz:clone'
alias ccomps='conduit components list'
```

---

## Troubleshooting

### Common Issues

#### 1. Command Not Found
```bash
# Symptom: "command not found: conduit"

# Solutions:
# Check installation
which conduit

# Add to PATH
export PATH="/usr/local/bin:$PATH"

# Reinstall if necessary
curl -L https://github.com/jordanpartridge/conduit/releases/latest/download/conduit -o conduit
chmod +x conduit
sudo mv conduit /usr/local/bin/
```

#### 2. Component Not Loading
```bash
# Symptom: Expected component commands not available

# Check component status
conduit components list --show-context

# Verify context requirements
conduit context

# Reinstall component
conduit components uninstall component-name
conduit components install component-name
```

#### 3. Storage Issues
```bash
# Symptom: "Database not found" or storage errors

# Reinitialize storage
conduit storage:init

# Check permissions
ls -la ~/.conduit/

# Reset storage (warning: loses settings)
rm -rf ~/.conduit/
conduit storage:init
```

#### 4. GitHub Integration Issues
```bash
# Symptom: GitHub commands fail or show authentication errors

# Check GitHub token
echo $GITHUB_TOKEN

# Set GitHub token
export GITHUB_TOKEN=your_token_here

# Test GitHub connection
conduit github-client test-auth
```

### Debug Mode

#### Enable Verbose Output
```bash
# Debug single command
conduit components list -vvv

# Debug with environment
CONDUIT_DEBUG=true conduit context

# Check logs
tail -f ~/.conduit/logs/conduit.log
```

#### Common Debug Commands
```bash
# Show all environment variables
conduit config:env

# Test component loading
conduit components test component-name

# Validate configuration
conduit config:validate

# Show system information
conduit system:info
```

### Getting Help

#### Built-in Help
```bash
# General help
conduit help

# Command-specific help
conduit help components

# List all available commands
conduit list

# Show examples for a command
conduit components --help
```

#### Community Resources
- **GitHub Issues**: [Report bugs or request features](https://github.com/jordanpartridge/conduit/issues)
- **Discussions**: [Community Q&A and ideas](https://github.com/jordanpartridge/conduit/discussions)
- **Documentation**: [Complete documentation](https://docs.conduit.dev)

#### Professional Support
- **Enterprise Support**: Available for teams and organizations
- **Custom Component Development**: Professional component development services
- **Training & Onboarding**: Team training and best practices workshops

---

## What's Next?

### Immediate Next Steps
1. **Explore Components**: Try `conduit components discover` to find tools for your workflow
2. **Customize Context**: Set up project-specific component activation
3. **Join Community**: Share your experience and contribute to the ecosystem

### Advanced Features to Explore
- **Custom Component Development**: Build tools specific to your team's needs
- **Workflow Automation**: Create scripts that leverage Conduit's context awareness
- **Team Configuration**: Set up shared component configurations for your team

### Stay Updated
- **Follow Releases**: Star the repository for updates
- **Join Discord**: Real-time community discussion and support
- **Subscribe to Newsletter**: Monthly updates and best practices

---

**Conduit: Where AI amplifies human creativity, one command at a time.**

*Need help? Run `conduit help` or visit [github.com/jordanpartridge/conduit](https://github.com/jordanpartridge/conduit)*
