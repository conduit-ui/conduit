# 🚀 Conduit v1.11.0

> Your personal developer API & MCP integration engine - AI-ready GitHub CLI and beyond

[![Latest Version](https://img.shields.io/packagist/v/jordanpartridge/conduit.svg?style=flat-square)](https://packagist.org/packages/jordanpartridge/conduit)
[![Total Downloads](https://img.shields.io/packagist/dt/jordanpartridge/conduit.svg?style=flat-square)](https://packagist.org/packages/jordanpartridge/conduit)
[![License](https://img.shields.io/packagist/l/jordanpartridge/conduit.svg?style=flat-square)](https://packagist.org/packages/jordanpartridge/conduit)

Conduit is a **modular, extensible CLI platform** built with Laravel Zero that transforms your development workflow. Starting with powerful GitHub integration, it features a revolutionary component system that makes adding new tools as simple as running `conduit install:service`.

## ✨ What's New in v1.11.0

### 🗄️ **Shared Knowledge Database**
- **Personal knowledge base**: Capture and search development insights with git context
- **Shared storage**: Knowledge persists across local and global installations
- **TODO management**: Track tasks with priority and status
- **Smart search**: Find knowledge by content, tags, or repository context

### 🎵 **Spotify Integration**
- **Playlist generation**: 13 intelligent playlists based on your listening habits
- **Smart device control**: Auto-device selection and authentication
- **Duplicate analysis**: Intelligent duplicate detection with auto-cleanup

### 🧩 **Component System**
- **Modular architecture**: Discoverable, installable components
- **Component registry**: Curated and community components
- **Self-validation**: Components can test their own health

## 🚀 Installation

### Via Composer (Recommended)
```bash
composer global require jordanpartridge/conduit
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

# Capture development insights
conduit know "Redis better than Memcached for our use case" --tags="architecture,performance"

# Search your knowledge base
conduit know --search="auth" --limit=5

# Track TODOs with priority
conduit know "Implement OAuth refresh tokens" --todo --priority=high

# List all TODOs
conduit know --todos

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

## 🤖 AI-Ready Architecture

Conduit is built from the ground up for AI integration:
- **Knowledge capture**: Automatic git context for all insights
- **Structured commands**: Perfect for AI tool integration
- **Rich metadata**: Commands expose detailed help and options  
- **Context-aware**: Smart defaults based on project detection
- **MCP Protocol ready**: Foundation for Model Context Protocol servers
- **Shared intelligence**: Personal knowledge base across installations

## Development

This project is built with Laravel Zero and uses the `jordanpartridge/github-client` package for GitHub operations.

## License

MIT
