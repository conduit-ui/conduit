# Laravel Say Logger Integration Guide

**Audio feedback logging for enhanced development experience**

---

## Overview

Laravel Say Logger provides **text-to-speech feedback** for log messages using macOS `say` command, creating immediate audible notifications during development. This integration transforms silent log entries into spoken alerts, making debugging more interactive and responsive.

### Key Features

- 🔊 **Audio Log Feedback** - Spoken log messages via macOS text-to-speech
- 🎭 **Voice Mapping** - Different voices for each log level (error, warning, info, etc.)
- ⚡ **Non-blocking** - Asynchronous processing doesn't impact performance
- 🎛️ **Configurable** - Environment-based enable/disable controls
- 🛡️ **Safe Integration** - Graceful fallbacks and input sanitization

---

## Installation Status

**Current Configuration:**
- **Package**: `jordanpartridge/laravel-say-logger`
- **Version**: `dev-feature/laravel-zero-support`
- **Status**: ⚠️ Configured but needs setup completion

**Service Provider Registration:**
```php
// config/app.php:6
'JordanPartridge\LaravelSayLogger\LaravelSayLoggerServiceProvider'
```

**Log Channel Configuration:**
```php
// config/logging.php:130-134
'say' => [
    'driver' => 'say',
    'level' => env('LOG_LEVEL', 'debug'),
],
```

---

## Setup & Configuration

### 1. Publish Configuration

The package configuration needs to be published to work properly:

```bash
php artisan vendor:publish --tag=say-logger-config
```

This creates `config/say-logger.php` with voice mappings and settings.

### 2. Environment Configuration

Add to your `.env` file:

```env
# Enable/disable say logger
SAY_LOGGER_ENABLED=true

# Set default log level
LOG_LEVEL=debug

# Optional: Set specific log channel to use say logger
LOG_CHANNEL=say
```

### 3. Voice Configuration

Default voice mapping (can be customized):

```php
// config/say-logger.php
'voices' => [
    'debug' => 'Alex',        // Default system voice
    'info' => 'Victoria',     // Friendly female voice
    'notice' => 'Fred',       // Casual male voice
    'warning' => 'Kathy',     // Alert female voice
    'error' => 'Veena',       // Urgent female voice
    'critical' => 'Moira',    // Serious female voice
    'alert' => 'Tessa',       // Sharp female voice
    'emergency' => 'Kyoko',   // Distinctive voice
],
```

---

## Usage Examples

### Basic Logging with Audio Feedback

```php
use Illuminate\Support\Facades\Log;

// These will be spoken aloud during development
Log::info('Component installation completed');
Log::warning('Deprecated method usage detected');
Log::error('Database connection failed');
```

### Contextual Logging (Current Implementation)

```php
// app/Commands/SummaryCommand.php:30-33
Log::info('Running summary command', [
    'interactive_mode' => $manager->getGlobalSetting('interactive_mode', true),
    'context' => $contextService->getContext(),
]);
```

### Conditional Audio Logging

```php
// Only log with audio in development
if (app()->environment('local')) {
    Log::channel('say')->info('Development notification');
}

// Standard logging for production
Log::info('Production log entry');
```

---

## Architecture & Implementation

### Component Structure

```
MacOSSayHandler (Monolog Handler)
├── Voice Selection Logic
├── macOS `say` Command Execution
├── Async Process Management
└── Message Sanitization
```

### Processing Flow

1. **Log Message Received** → Handler processes log record
2. **Voice Selection** → Maps log level to specific voice
3. **Message Cleaning** → Strips HTML/formatting for speech
4. **Async Execution** → Spawns `say` command without blocking
5. **Graceful Fallback** → Uses default voice if configured voice unavailable

### Voice Validation

The handler validates voice availability:

```php
protected function isVoiceAvailable(string $voice): bool
{
    $process = new Process(['say', '-v', $voice, '']);
    $process->run();
    return $process->isSuccessful();
}
```

---

## Platform Requirements

### macOS Compatibility
- **Required**: macOS with `say` command
- **Voices**: System voices must be available
- **Permissions**: Terminal/IDE must have accessibility permissions

### Development Environment
- **PHP**: 8.2+
- **Laravel**: 11.x (Laravel Zero support)
- **Dependencies**: `symfony/process` for command execution

---

## Troubleshooting

### Common Issues

#### 1. "Log [say] is not defined" Error

**Symptom**: `InvalidArgumentException: Log [say] is not defined`

**Cause**: Missing published configuration file

**Solution**:
```bash
php artisan vendor:publish --tag=say-logger-config
php artisan config:cache
```

#### 2. No Audio Output

**Checks**:
- Verify macOS system: `which say`
- Test voice availability: `say -v Victoria "test"`
- Check environment: `SAY_LOGGER_ENABLED=true`
- Verify volume settings and audio output

#### 3. Voice Not Available

**Symptom**: Falls back to "Alex" voice

**Solution**:
```bash
# List available voices
say -v ?

# Test specific voice
say -v Veena "Error message test"
```

#### 4. Performance Impact

**Symptoms**: Slow log processing

**Checks**:
- Async processing enabled (default)
- Not running in production environment
- System resources available

### Debug Commands

```bash
# Test say logger directly
php conduit tinker
>>> Log::channel('say')->info('Test message');

# Verify configuration
php artisan config:show say-logger

# Check voice availability
say -v ? | grep -i veena
```

---

## Development Integration

### Current Usage in Conduit

1. **SummaryCommand**: Contextual logging with interactive mode status
2. **Component Operations**: Future audio feedback for installations/discoveries
3. **Error Handling**: Immediate audio alerts for critical issues

### Best Practices

#### Development Environment
```php
// Use say logger for immediate feedback
if (app()->environment('local')) {
    Log::channel('say')->error('Component installation failed');
}
```

#### Production Safety
```php
// Ensure say logger disabled in production
if (config('say-logger.enabled') && app()->environment('local')) {
    Log::channel('say')->info('Development notification');
}
```

#### Voice Customization
```php
// Custom voice per component type
Log::channel('say')->info('GitHub component installed', [
    'voice' => 'Victoria'  // Override default voice
]);
```

---

## Performance Considerations

### Async Processing
- ✅ **Non-blocking**: Uses `Symfony\Component\Process` async execution
- ✅ **Resource efficient**: Minimal memory footprint
- ✅ **Fast fallback**: Quick voice validation with timeout

### Production Deployment
- ⚠️ **Disable in production**: Set `SAY_LOGGER_ENABLED=false`
- ✅ **Zero overhead**: No processing when disabled
- ✅ **Graceful degradation**: Falls back to standard logging

---

## Security Analysis

### Safe Practices
- ✅ **Input sanitization**: Messages cleaned before speech
- ✅ **Process isolation**: Commands executed in isolated process
- ✅ **No network access**: Local system command only
- ✅ **Permission controlled**: Respects macOS accessibility settings

### Considerations
- 🔒 **Voice privacy**: Log content spoken aloud (development only)
- 🔒 **System access**: Uses macOS `say` command (local system)
- 🔒 **Audio output**: Consider when working in shared spaces

---

## Future Enhancements

### Planned Features
- **Cross-platform support**: Windows SAPI and Linux espeak compatibility
- **Custom sound effects**: Non-speech audio alerts for specific events
- **Voice themes**: Preset voice configurations for different development modes
- **Volume control**: Per-level volume adjustment
- **Time-based rules**: Quiet hours and scheduling

### Integration Opportunities
- **Component lifecycle**: Audio feedback for install/uninstall operations
- **GitHub operations**: Voice confirmations for Git operations
- **Context awareness**: Different voices for different project types
- **Interactive mode**: Enhanced audio feedback for interactive commands

---

## Resources

### Documentation
- [Laravel Logging Documentation](https://laravel.com/docs/logging)
- [Monolog Handler Documentation](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md)
- [macOS say Command Manual](https://ss64.com/osx/say.html)

### Voice Management
```bash
# List all available voices
say -v ?

# Download additional voices (System Preferences > Accessibility > Spoken Content)
# Test voice pronunciation
say -v Moira "Critical system error detected"
```

---

*This guide reflects the current integration status and provides setup instructions for Laravel Say Logger in the Conduit CLI application.*