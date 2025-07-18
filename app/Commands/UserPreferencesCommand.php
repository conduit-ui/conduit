<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UserPreferencesCommand extends Command
{
    protected $signature = 'preferences 
                           {--set=* : Set preference key=value}
                           {--get= : Get preference value}
                           {--list : List all preferences}
                           {--reset : Reset all preferences}
                           {--export : Export preferences to file}
                           {--import= : Import preferences from file}';

    protected $description = 'Manage user preferences for Conduit commands';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listPreferences();
        }

        if ($this->option('reset')) {
            return $this->resetPreferences();
        }

        if ($this->option('export')) {
            return $this->exportPreferences();
        }

        if ($this->option('import')) {
            return $this->importPreferences($this->option('import'));
        }

        if ($this->option('get')) {
            return $this->getPreference($this->option('get'));
        }

        if ($this->option('set')) {
            return $this->setPreferences($this->option('set'));
        }

        $this->info('🔧 Conduit User Preferences');
        $this->newLine();
        $this->line('Available options:');
        $this->line('  --list              List all preferences');
        $this->line('  --set key=value     Set preference');
        $this->line('  --get key           Get preference value');
        $this->line('  --reset             Reset all preferences');
        $this->line('  --export            Export to file');
        $this->line('  --import file       Import from file');

        return 0;
    }

    private function listPreferences(): int
    {
        $preferences = Cache::get('user_preferences', []);

        if (empty($preferences)) {
            $this->info('📝 No user preferences set');
            return 0;
        }

        $this->info('📋 User Preferences:');
        $this->newLine();

        foreach ($preferences as $key => $value) {
            $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
            $this->line("  <info>{$key}</info> = <comment>{$displayValue}</comment>");
        }

        return 0;
    }

    private function setPreferences(array $settings): int
    {
        $preferences = Cache::get('user_preferences', []);

        foreach ($settings as $setting) {
            if (!str_contains($setting, '=')) {
                $this->error("❌ Invalid format: {$setting}. Use key=value");
                continue;
            }

            [$key, $value] = explode('=', $setting, 2);
            
            // Handle special value types
            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            } elseif (is_numeric($value)) {
                $value = (int) $value;
            } elseif (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                $value = json_decode($value, true) ?? $value;
            }

            $preferences[$key] = $value;
            $this->info("✅ Set {$key} = {$value}");
        }

        Cache::forever('user_preferences', $preferences);

        return 0;
    }

    private function getPreference(string $key): int
    {
        $preferences = Cache::get('user_preferences', []);

        if (!isset($preferences[$key])) {
            $this->error("❌ Preference '{$key}' not found");
            return 1;
        }

        $value = $preferences[$key];
        $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
        
        $this->line($displayValue);

        return 0;
    }

    private function resetPreferences(): int
    {
        if (!$this->confirm('Are you sure you want to reset all preferences?')) {
            $this->info('Operation cancelled');
            return 0;
        }

        Cache::forget('user_preferences');
        $this->info('✅ All preferences reset');

        return 0;
    }

    private function exportPreferences(): int
    {
        $preferences = Cache::get('user_preferences', []);
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
        $filePath = $homeDir . '/.conduit/preferences.json';

        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, json_encode($preferences, JSON_PRETTY_PRINT));
        
        $this->info("✅ Preferences exported to {$filePath}");

        return 0;
    }

    private function importPreferences(string $filePath): int
    {
        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return 1;
        }

        $content = file_get_contents($filePath);
        $preferences = json_decode($content, true);

        if ($preferences === null) {
            $this->error("❌ Invalid JSON in file: {$filePath}");
            return 1;
        }

        Cache::forever('user_preferences', $preferences);
        $this->info("✅ Preferences imported from {$filePath}");

        return 0;
    }
}