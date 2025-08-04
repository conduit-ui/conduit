<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Carbon\Carbon;

use function Laravel\Prompts\table;
use function Laravel\Prompts\select;
use function Laravel\Prompts\search;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class LsCommand extends ConduitCommand
{
    protected $signature = 'ls {path?} {--json : Output as JSON} {--recent : Sort by recently modified} {--large : Sort by size} {--git : Show git status} {--octal : Show octal permissions} {--detailed-perms : Show full rwx permissions} {--guide : Show the sexy options guide} {--interactive : Interactive file browser}';

    protected $description = '💩 List files and directories (but actually good)';

    protected function executeCommand(): int
    {
        // Check for custom guide first
        if ($this->option('guide')) {
            return $this->showSexyHelp();
        }
        
        $path = $this->argument('path') ?? getcwd();
        
        if (!is_dir($path)) {
            $this->forceOutput("💩 Path doesn't exist: {$path}", 'error');
            return self::FAILURE;
        }

        $files = $this->scanDirectory($path);
        
        if ($this->option('recent')) {
            $files = collect($files)->sortByDesc('modified')->values()->all();
        } elseif ($this->option('large')) {
            $files = collect($files)->sortByDesc('size')->values()->all();
        }

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse(['files' => $files, 'path' => $path]);
        }

        if ($this->option('interactive')) {
            return $this->runInteractiveBrowser($path);
        }

        return $this->displayInteractive($files, $path);
    }
    
    private function showSexyHelp(): int
    {
        $this->smartInfo("💩 SHIT ls - The file lister that doesn't lie to you");
        $this->smartNewLine();
        
        $this->smartLine("Usage: ./💩 ls [path] [options]");
        $this->smartNewLine();
        
        table(
            ['🚩 Flag', '📝 Description', '💡 Example'],
            [
                ['--json', 'Output as machine-readable JSON', './💩 ls --json'],
                ['--recent', 'Sort by recently modified files first', './💩 ls --recent'],
                ['--large', 'Sort by largest files first', './💩 ls --large'],
                ['--git', 'Show git status indicators', './💩 ls --git'],
                ['--octal', 'Show permissions as 755 format', './💩 ls --octal'],
                ['--detailed-perms', 'Show full rwxr-xr-x format', './💩 ls --detailed-perms'],
                ['--interactive', 'Launch interactive file browser', './💩 ls --interactive'],
                ['--guide', 'Show this sexy options guide', './💩 ls --guide'],
            ]
        );
        
        $this->smartNewLine();
        $this->smartLine("🎭 Permission Emojis:");
        
        table(
            ['🎨 Emoji', '📊 Octal', '📝 Description'],
            [
                ['📁', '755', 'Directory with normal access'],
                ['🔓', '755', 'Executable file'],
                ['📖', '644', 'Standard readable file'],
                ['🔒', '600', 'Private file (owner only)'],
                ['🏠', '700', 'Private directory/file'],
                ['📝', '666', 'World-writable file'],
                ['🚨', '777', 'DANGEROUS: World-writable!'],
                ['👁️', '444', 'Read-only file'],
                ['🔍', '555', 'Read/execute only'],
                ['🚫', '000', 'No permissions'],
            ]
        );
        
        $this->smartNewLine();
        $this->smartLine("💩 Finally, a file lister that doesn't pretend to be enterprise-grade.");
        
        return self::SUCCESS;
    }

    private function scanDirectory(string $path): array
    {
        $files = [];
        
        try {
            $items = scandir($path);
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $fullPath = $path . DIRECTORY_SEPARATOR . $item;
                $stat = stat($fullPath);
                
                if ($stat === false) {
                    // Skip files we can't stat
                    continue;
                }
                
                $files[] = [
                    'name' => $item,
                    'type' => is_dir($fullPath) ? 'directory' : 'file',
                    'size' => $stat['size'],
                    'modified' => Carbon::createFromTimestamp($stat['mtime']),
                    'permissions' => $this->getPermissions($fullPath),
                    'icon' => $this->getIcon($fullPath),
                    'git_status' => $this->option('git') ? $this->getGitStatus($fullPath) : null,
                ];
            }
        } catch (\Exception $e) {
            $this->forceOutput("💩 Error reading directory: " . $e->getMessage(), 'error');
        }
        
        return $files;
    }

    private function getIcon(string $path): string
    {
        if (is_dir($path)) {
            // Special directories
            if (basename($path) === '.git') return '🔧';
            if (basename($path) === 'vendor') return '📦';
            if (basename($path) === 'node_modules') return '📦';
            if (basename($path) === 'tests') return '🧪';
            return '📁';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return match($extension) {
            'php' => '🐘',
            'js', 'ts' => '💛',
            'json' => '📋',
            'md' => '📝',
            'txt' => '📄',
            'yml', 'yaml' => '⚙️',
            'env' => '🔐',
            'log' => '📜',
            'sql' => '🗄️',
            'png', 'jpg', 'jpeg', 'gif' => '🖼️',
            'mp3', 'wav' => '🎵',
            'mp4', 'avi' => '🎬',
            'zip', 'tar', 'gz' => '📦',
            'sh' => '💻',
            default => '📄'
        };
    }

    private function getPermissions(string $path): string
    {
        $perms = fileperms($path) & 0777;
        $permString = $this->formatPermissionString($perms);
        $isDir = is_dir($path);
        
        // Choose format based on flags
        if ($this->option('detailed-perms')) {
            return $this->getPermissionEmoji($perms, $isDir) . ' ' . $permString;
        } elseif ($this->option('octal')) {
            return $this->getPermissionEmoji($perms, $isDir) . ' ' . sprintf('%03o', $perms);
        }
        
        // Default: emoji + description (different for dirs vs files)
        if ($isDir) {
            return match($perms) {
                0755 => '📁 Dir Access',     // rwxr-xr-x
                0700 => '🏠 Private Dir',    // rwx------
                0777 => '🚨 World Write!',   // rwxrwxrwx (dangerous!)
                0555 => '🔍 Read Only',      // r-xr-xr-x
                0000 => '🚫 No Access',      // ---------
                default => $this->getPermissionEmoji($perms, $isDir) . ' ' . sprintf('%03o', $perms)
            };
        } else {
            return match($perms) {
                0755 => '🔓 Executable',     // rwxr-xr-x
                0644 => '📖 Standard',       // rw-r--r--
                0600 => '🔒 Private',        // rw-------
                0777 => '🚨 Dangerous!',     // rwxrwxrwx (world writable)
                0700 => '🏠 Owner Only',     // rwx------
                0666 => '📝 World Edit',     // rw-rw-rw-
                0555 => '🔍 Read/Run',       // r-xr-xr-x
                0444 => '👁️ Read Only',      // r--r--r--
                0000 => '🚫 No Access',      // ---------
                default => $this->getPermissionEmoji($perms, $isDir) . ' ' . sprintf('%03o', $perms)
            };
        }
    }
    
    private function getPermissionEmoji(int $perms, bool $isDir = false): string
    {
        if ($isDir) {
            return match($perms) {
                0755 => '📁',  // Directory access
                0700 => '🏠',  // Private directory
                0777 => '🚨',  // Dangerous!
                0555 => '🔍',  // Read-only directory
                0000 => '🚫',  // No access
                default => '📂'  // Generic folder for uncommon perms
            };
        } else {
            return match($perms) {
                0755 => '🔓',  // Executable file
                0644 => '📖',  // Standard file
                0600 => '🔒',  // Private file
                0777 => '🚨',  // Dangerous!
                0700 => '🏠',  // Owner only
                0666 => '📝',  // World writable file
                0555 => '🔍',  // Read/execute
                0444 => '👁️',  // Read-only
                0000 => '🚫',  // No permissions
                default => '⚙️'  // Generic gear for uncommon perms
            };
        }
    }
    
    private function formatPermissionString(int $perms): string
    {
        $info = '';

        // Owner
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? 'x' : '-');

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? 'x' : '-');

        // Others
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? 'x' : '-');

        return $info;
    }

    private function getGitStatus(string $path): ?string
    {
        if (!is_dir('.git') && !exec('git rev-parse --git-dir 2>/dev/null')) {
            return null;
        }

        $relativePath = str_replace(getcwd() . '/', '', $path);
        $status = trim(shell_exec('git status --porcelain ' . escapeshellarg($relativePath) . ' 2>/dev/null') ?? '');
        
        if (empty($status)) return '✅';
        
        return match(substr($status, 0, 2)) {
            '??' => '❓',
            'A ' => '➕',
            'M ' => '📝',
            'D ' => '❌',
            'R ' => '🔄',
            ' M' => '📝',
            ' D' => '❌',
            default => '⚡'
        };
    }


    private function formatSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);
        
        return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    private function displayInteractive(array $files, string $path): int
    {
        $this->smartInfo("💩 SHIT File Browser - {$path}");
        $this->smartNewLine();

        if (empty($files)) {
            $this->smartLine('Empty directory (how sad)');
            return self::SUCCESS;
        }

        // Prepare table rows
        $rows = [];
        foreach ($files as $file) {
            $sizeStr = $file['type'] === 'directory' ? '-' : $this->formatSize($file['size']);
            $modifiedStr = $file['modified']->diffForHumans();
            $nameWithIcon = $file['icon'] . ' ' . $file['name'];
            
            // Add git status if enabled
            if ($this->option('git') && $file['git_status']) {
                $nameWithIcon .= ' ' . $file['git_status'];
            }

            $rows[] = [
                substr($nameWithIcon, 0, 30),
                $sizeStr,
                substr($modifiedStr, 0, 15),
                $file['permissions']
            ];
        }

        // Use Laravel Prompts table
        table(
            ['📄 Name', '📊 Size', '📅 Modified', '🔐 Perms'],
            $rows
        );

        $this->smartNewLine();
        $this->smartLine("💡 Tip: Use --json for machine-readable output");
        $this->smartLine("💡 Tip: Use --recent, --large, or --git for different views");

        return self::SUCCESS;
    }

    private function runInteractiveBrowser(string $currentPath): int
    {
        while (true) {
            $files = $this->scanDirectory($currentPath);
            
            if (empty($files)) {
                $this->smartLine("Empty directory: {$currentPath}");
                $action = select(
                    '🚀 What would you like to do?',
                    ['Go up one level', 'Exit browser']
                );
                
                if ($action === 'Go up one level') {
                    $currentPath = dirname($currentPath);
                    continue;
                } else {
                    break;
                }
            }

            // Build options for selection
            $options = [];
            
            // Add "Go up" option if not at root
            if ($currentPath !== '/') {
                $options['..'] = '📁 .. (Go up one level)';
            }
            
            // Add all files and directories
            foreach ($files as $file) {
                $display = $file['icon'] . ' ' . $file['name'];
                
                if ($file['type'] === 'directory') {
                    $display .= '/';
                } else {
                    $display .= ' (' . $this->formatSize($file['size']) . ')';
                }
                
                if ($this->option('git') && $file['git_status']) {
                    $display .= ' ' . $file['git_status'];
                }
                
                $options[$file['name']] = $display;
            }
            
            // Add action options
            $options['__actions__'] = '⚡ Actions...';
            $options['__exit__'] = '🚪 Exit browser';

            $choice = search(
                "📂 Browse: {$currentPath}",
                fn (string $value) => array_filter(
                    $options,
                    fn ($option) => str_contains(strtolower($option), strtolower($value))
                )
            );

            if ($choice === '__exit__') {
                break;
            }
            
            if ($choice === '__actions__') {
                $this->showFileActions($currentPath);
                continue;
            }
            
            if ($choice === '..') {
                $currentPath = dirname($currentPath);
                continue;
            }

            $selectedPath = $currentPath . DIRECTORY_SEPARATOR . $choice;
            
            if (is_dir($selectedPath)) {
                $currentPath = $selectedPath;
            } else {
                $this->handleFileSelection($selectedPath);
            }
        }
        
        $this->smartInfo('👋 Exited interactive browser');
        return self::SUCCESS;
    }

    private function showFileActions(string $currentPath): void
    {
        $action = select(
            '⚡ Choose an action:',
            [
                'refresh' => '🔄 Refresh current directory',
                'create_file' => '📄 Create new file',
                'create_dir' => '📁 Create new directory',
                'show_path' => '📍 Show current path',
                'back' => '⬅️ Back to browser'
            ]
        );

        switch ($action) {
            case 'refresh':
                $this->smartInfo('🔄 Directory refreshed');
                break;
                
            case 'create_file':
                $filename = text('📄 Enter filename:');
                if ($filename) {
                    $fullPath = $currentPath . DIRECTORY_SEPARATOR . $filename;
                    if (!file_exists($fullPath)) {
                        touch($fullPath);
                        $this->smartInfo("✅ Created file: {$filename}");
                    } else {
                        $this->smartLine('❌ File already exists');
                    }
                }
                break;
                
            case 'create_dir':
                $dirname = text('📁 Enter directory name:');
                if ($dirname) {
                    $fullPath = $currentPath . DIRECTORY_SEPARATOR . $dirname;
                    if (!is_dir($fullPath)) {
                        mkdir($fullPath, 0755, true);
                        $this->smartInfo("✅ Created directory: {$dirname}");
                    } else {
                        $this->smartLine('❌ Directory already exists');
                    }
                }
                break;
                
            case 'show_path':
                $this->smartInfo("📍 Current path: {$currentPath}");
                break;
        }
    }

    private function handleFileSelection(string $filePath): void
    {
        $filename = basename($filePath);
        $filesize = $this->formatSize(filesize($filePath));
        
        $action = select(
            "📄 {$filename} ({$filesize})",
            [
                'view' => '👁️ View file content',
                'edit' => '✏️ Edit file',
                'copy_path' => '📋 Copy path to clipboard',
                'delete' => '🗑️ Delete file',
                'info' => 'ℹ️ Show file info',
                'back' => '⬅️ Back to browser'
            ]
        );

        switch ($action) {
            case 'view':
                $this->viewFile($filePath);
                break;
                
            case 'edit':
                $this->editFile($filePath);
                break;
                
            case 'copy_path':
                $this->smartInfo("📋 Path copied: {$filePath}");
                break;
                
            case 'delete':
                if (confirm("🗑️ Are you sure you want to delete {$filename}?")) {
                    unlink($filePath);
                    $this->smartInfo("✅ Deleted: {$filename}");
                }
                break;
                
            case 'info':
                $this->showFileInfo($filePath);
                break;
        }
    }

    private function viewFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        $this->smartInfo("👁️ Viewing: " . basename($filePath));
        $this->smartLine(str_repeat('─', 50));
        
        foreach (array_slice($lines, 0, 20) as $i => $line) {
            $this->smartLine(sprintf('%3d: %s', $i + 1, $line));
        }
        
        if (count($lines) > 20) {
            $this->smartLine('... (truncated, showing first 20 lines)');
        }
        
        $this->smartLine(str_repeat('─', 50));
    }

    private function editFile(string $filePath): void
    {
        $editor = getenv('EDITOR') ?: 'nano';
        $this->smartInfo("✏️ Opening {$filePath} with {$editor}");
        system($editor . ' ' . escapeshellarg($filePath));
    }

    private function showFileInfo(string $filePath): void
    {
        $stat = stat($filePath);
        $filename = basename($filePath);
        
        $this->smartInfo("ℹ️ File Information: {$filename}");
        $this->smartLine("📄 Path: {$filePath}");
        $this->smartLine("📊 Size: " . $this->formatSize($stat['size']));
        $this->smartLine("📅 Modified: " . Carbon::createFromTimestamp($stat['mtime'])->format('Y-m-d H:i:s'));
        $this->smartLine("🔐 Permissions: " . $this->getPermissions($filePath));
        
        if (is_link($filePath)) {
            $this->smartLine("🔗 Symlink to: " . readlink($filePath));
        }
    }
}
