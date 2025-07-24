# 🚀 Conduit v2.0.0

> Your personal developer API & MCP integration engine - AI-ready GitHub CLI and beyond

[![Latest Version](https://img.shields.io/packagist/v/conduit-ui/conduit.svg?style=flat-square)](https://packagist.org/packages/conduit-ui/conduit)
[![Total Downloads](https://img.shields.io/packagist/dt/conduit-ui/conduit.svg?style=flat-square)](https://packagist.org/packages/conduit-ui/conduit)
[![License](https://img.shields.io/packagist/l/conduit-ui/conduit.svg?style=flat-square)](https://packagist.org/packages/conduit-ui/conduit)

Conduit is a **modular, extensible CLI platform** built with Laravel Zero that transforms your development workflow. Starting with powerful GitHub integration, it features a revolutionary component system that makes adding new tools as simple as running `conduit install:service`.

## ✨ What's New in v2.0.0

### 🧠 **Knowledge System v2 - Graph Architecture**
- **Normalized database**: Proper relationships with tags, metadata, and entries
- **Advanced search**: Content, tags, repository context, and semantic relationships
- **Knowledge graph**: Entries can relate to each other with typed relationships
- **Intelligent optimization**: Duplicate detection with Jaccard/Levenshtein similarity
- **Auto-migration**: Seamless upgrade from v1 to v2 schema
- **Enhanced TODO management**: Rich metadata with priority, status, and context

### 🎵 **Spotify Integration**
- **Playlist generation**: 13 intelligent playlists based on your listening habits
- **Smart device control**: Auto-device selection and authentication
- **Duplicate analysis**: Intelligent duplicate detection with auto-cleanup

### 🧩 **Component System**
- **Modular architecture**: Discoverable, installable components
- **Component registry**: Curated and community components
- **Self-validation**: Components can test their own health

### 🚀 **Developer Experience**
- **Simplified commands**: Clean naming convention (know:add vs know:addCommand)
- **CI/CD pipeline**: Automated testing with GitHub Actions
- **Code quality**: Laravel Pint, Pest, and security scanning

## 🚀 Installation

### Via Composer (Recommended)
```bash
composer global require conduit-ui/conduit
```

### Via GitHub Releases
```bash
# Download latest PHAR
curl -L https://github.com/jordanpartridge/conduit/releases/latest/download/conduit.phar -o conduit
chmod +x conduit
sudo mv conduit /usr/local/bin/conduit
```

### Development Setup
```bash
git clone https://github.com/jordanpartridge/conduit.git
cd conduit
composer install
```

## 🎯 Quick Start

```bash
# Initialize your knowledge database
conduit storage:init

# Capture development insights with v2 commands
conduit know:add "Redis better than Memcached for our use case" --tags="architecture,performance"

# Search your knowledge base
conduit know:search "auth" --limit=5

# Track TODOs with rich metadata
conduit know:add "Implement OAuth refresh tokens" --tags="todo,auth" --priority=high --status=open

# List all knowledge entries
conduit know:list --limit=10

# List only TODOs
conduit know:list --todo

# Show specific entry with full details
conduit know:show 42

# Context-aware search (prioritizes current repo)
conduit know:context

# Optimize and clean duplicates
conduit know:optimize

# Migrate from v1 to v2 (automatic)
conduit know:migrate

# Set up Spotify integration
# Option 1: Guided setup (recommended) - Beautiful prompts & tasks
conduit spotify:setup

# Option 2: Manual setup (add to .env file)
# Create a Spotify app at https://developer.spotify.com/dashboard
# SPOTIFY_CLIENT_ID=your_client_id
# SPOTIFY_CLIENT_SECRET=your_client_secret
# SPOTIFY_REDIRECT_URI=http://127.0.0.1:9876/callback
# SPOTIFY_CALLBACK_PORT=9876  # Optional: customize callback port

# Generate intelligent playlists
conduit spotify:generate-playlists

# Control playback
conduit spotify:focus --device=Desktop

# Manage components
conduit components discover
conduit components install github
```

## 🧩 Component Architecture

Conduit's component system provides modular functionality:

```bash
# Discover available components
conduit components discover

# Install components
conduit components install github
conduit components install spotify

# List installed components
conduit components list

# Component management
conduit components activate github
conduit components deactivate spotify
```

### Available Components
- **🎵 Spotify**: Music control, playlist generation, and analytics
- **🐙 GitHub** *(planned)*: Repository management and automation
- **🐳 Docker** *(planned)*: Container management and orchestration
- **☁️ AWS Toolkit** *(planned)*: Cloud infrastructure helpers
- **🗄️ Database Tools** *(planned)*: Migration and seeding utilities

## 📊 Knowledge System v2 Features

### Graph Database Architecture
```bash
# Core entities with relationships
├── Entries (content, git context)
├── Tags (normalized, reusable)
├── Metadata (priority, status, custom fields)
└── Relationships (depends_on, relates_to, conflicts_with)
```

### Advanced Search & Discovery
```bash
# Multi-dimensional search
conduit know:search "redis" --tags="performance" --repo="myproject" --recent

# Related entries (via tags and relationships)
conduit know:show 42  # Shows related entries automatically

# Repository-specific knowledge
conduit know:context --limit=5
```

### Intelligent Optimization
```bash
# Find and merge duplicates
conduit know:optimize

# Similarity metrics: Jaccard, Levenshtein, semantic analysis
# Auto-suggests consolidation of similar entries
```

## 🤖 AI-Ready Architecture

Conduit is built from the ground up for AI integration:
- **Knowledge graph**: Rich relationships between concepts and solutions
- **Structured commands**: Perfect for AI tool integration
- **Rich metadata**: Commands expose detailed help and options  
- **Context-aware**: Smart defaults based on project detection
- **MCP Protocol ready**: Foundation for Model Context Protocol servers
- **Shared intelligence**: Personal knowledge base across installations
- **Semantic search**: Advanced similarity detection for knowledge discovery

## 📚 Complete Command Reference

### Knowledge Management
```bash
# Core commands
conduit know:add "knowledge content" --tags="tag1,tag2" --priority=high
conduit know:search "query" --tags="performance" --limit=10
conduit know:list --todo --recent --repo=myproject
conduit know:show 42
conduit know:forget 42
conduit know:context  # Repository-aware search

# Advanced operations
conduit know:optimize  # Find and merge duplicates
conduit know:migrate   # Upgrade v1 to v2 schema
conduit know:setup     # Configure git auto-capture hooks
```

### Spotify Integration
```bash
# Setup and authentication
conduit spotify:setup
conduit spotify:login
conduit spotify:logout

# Playback control
conduit spotify:play "artist - song"
conduit spotify:pause
conduit spotify:skip
conduit spotify:volume 50

# Device and playlist management
conduit spotify:devices
conduit spotify:current
conduit spotify:playlists
conduit spotify:queue

# Analytics and insights
conduit spotify:analytics
conduit spotify:focus --device=Desktop
```

### Component System
```bash
# Discovery and management
conduit components discover
conduit components list
conduit components install github
conduit components activate spotify
conduit components deactivate github
```

### System Management
```bash
# Storage and configuration
conduit storage:init
conduit summary
conduit interactive  # Interactive mode
```

## 🧪 Testing & Quality

### Running Tests
```bash
# Run test suite
./vendor/bin/pest

# With coverage
./vendor/bin/pest --coverage --min=80

# Code formatting
./vendor/bin/pint

# Security audit
composer audit
```

### CI/CD Pipeline
- **Multi-PHP testing**: PHP 8.2 and 8.3
- **Code quality**: Laravel Pint formatting validation
- **Security scanning**: Composer audit and vulnerability checks
- **Coverage reporting**: Codecov integration

## Development

This project is built with Laravel Zero and uses the `jordanpartridge/github-client` package for GitHub operations.

### Architecture
- **Microkernel design**: Core framework with modular components
- **Component system**: Discoverable, installable functionality modules
- **Knowledge graph**: Advanced relationship modeling for insights
- **AI-ready**: Built for Model Context Protocol integration

## License

MIT
# Auto-capture test
