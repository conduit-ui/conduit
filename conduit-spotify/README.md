# Conduit Spotify Component

🎵 **Spotify integration for Conduit** - Control your music during development workflows

## Features

### 🎮 Player Control
- `spotify:play` - Start/resume playback with optional URI
- `spotify:pause` - Pause current playback  
- `spotify:skip` - Skip to next/previous track
- `spotify:volume` - Control volume (0-100) or adjust up/down
- `spotify:current` - Show currently playing track info

### 🎯 Focus Workflows
- `spotify:focus` - Smart focus music for different coding scenarios
  - `coding` - Deep focus programming music
  - `break` - Relaxing break music
  - `deploy` - Celebration music for successful deployments
  - `debug` - Calm debugging music
  - `testing` - Focused testing music

### 📋 Playlist Management  
- `spotify:playlists list` - Show your playlists
- `spotify:playlists play "name"` - Play playlist by name
- `spotify:playlists search "query"` - Search public playlists

### 🔐 Authentication
- `spotify:auth` - Authenticate with Spotify OAuth
- `spotify:auth --status` - Check authentication status
- `spotify:auth --logout` - Logout from Spotify

## Installation

```bash
# Install as Conduit component
conduit components install spotify

# Or manually add to your project
composer require jordanpartridge/conduit-spotify
```

## Setup

1. **Create Spotify App**
   - Go to [Spotify Developer Dashboard](https://developer.spotify.com/dashboard/applications)
   - Create new app or select existing one
   - Set redirect URI to: `http://localhost:8888/callback`

2. **Configure Environment**
   ```bash
   # Add to your .env file
   SPOTIFY_CLIENT_ID=your_client_id_here
   SPOTIFY_CLIENT_SECRET=your_client_secret_here
   SPOTIFY_REDIRECT_URI=http://localhost:8888/callback
   ```

3. **Authenticate**
   ```bash
   php conduit spotify:auth
   ```

## Usage Examples

```bash
# Basic playback control
php conduit spotify:play                          # Resume playback
php conduit spotify:play spotify:playlist:abc123  # Play specific playlist
php conduit spotify:pause                         # Pause
php conduit spotify:skip                          # Next track
php conduit spotify:skip --previous               # Previous track

# Volume control
php conduit spotify:volume 70                     # Set to 70%
php conduit spotify:volume --up                   # Increase by 10
php conduit spotify:volume --down                 # Decrease by 10

# Focus workflows
php conduit spotify:focus coding                  # Start coding music
php conduit spotify:focus break --volume=50       # Break music at 50%
php conduit spotify:focus deploy                  # Celebration music!

# Current track info
php conduit spotify:current                       # Full display
php conduit spotify:current --compact             # One line
php conduit spotify:current --json                # JSON output

# Playlist management
php conduit spotify:playlists list                # Your playlists
php conduit spotify:playlists play "Coding Focus" # Play by name
php conduit spotify:playlists search "chill"      # Search playlists
```

## Preset Shortcuts

Play preset playlists with shortcuts:

```bash
php conduit spotify:play coding   # Deep Focus playlist
php conduit spotify:play break    # Chill Hits playlist  
php conduit spotify:play deploy   # Upbeat celebration music
php conduit spotify:play debug    # Peaceful Piano
php conduit spotify:play testing  # Concentration music
```

## Configuration

Customize in `config/spotify.php`:

```php
'presets' => [
    'coding' => 'spotify:playlist:37i9dQZF1DX0XUsuxWHRQd',
    'break' => 'spotify:playlist:37i9dQZF1DX3rxVfibe1L0',
    // Add your own presets...
],

'auto_play' => [
    'on_coding_start' => false,
    'on_deploy_success' => true,
    'volume' => 70,
],
```

## Context-Aware Activation

The component activates automatically during:
- Coding sessions (`coding.start`)
- Git operations (`git.working`)
- Work hours (`time.work_hours`)
- Laravel/PHP projects

Excludes during:
- Active meetings (`meeting.active`)
- Phone calls (`call.active`)

## Integration with Other Components

### GitHub Integration
```bash
# Celebration music on successful PR merge
git merge main && php conduit spotify:focus deploy

# Focus music when starting feature work
git checkout -b feature/new-feature && php conduit spotify:focus coding
```

### Laravel Integration  
```bash
# Celebration music after successful deployment
php artisan deploy:production && php conduit spotify:focus deploy

# Testing music during test runs
php conduit spotify:focus testing && php artisan test
```

## Requirements

- PHP 8.1+
- Spotify Premium account (required for playback control)
- Active Spotify device (app open on phone/computer/web)

## Error Handling

Common issues and solutions:

- **"No active device"** → Open Spotify app and start playing something
- **"Authentication failed"** → Run `php conduit spotify:auth` again
- **"Rate limit exceeded"** → Wait a moment and try again
- **"Token expired"** → Run `php conduit spotify:auth` to refresh

## Development

This component follows Conduit's architecture:

- **Service Layer**: `SpotifyAuthService`, `SpotifyApiService`
- **Commands**: Feature-focused command classes
- **Configuration**: Environment-based with sensible defaults
- **Error Handling**: Graceful degradation with helpful messages

Perfect for enhancing your development workflow with music! 🎵✨