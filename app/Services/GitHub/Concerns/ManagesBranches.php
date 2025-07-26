<?php

namespace App\Services\GitHub\Concerns;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

trait ManagesBranches
{
    /**
     * Get current branch from git
     */
    public function getCurrentBranch(): ?string
    {
        try {
            $result = Process::run('git branch --show-current');

            if ($result->failed()) {
                return null;
            }

            $branch = trim($result->output());

            return ! empty($branch) ? $branch : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get available branches for base selection
     */
    public function getAvailableBranches(): array
    {
        try {
            $result = Process::run('git branch -r --format="%(refname:short)"');

            if ($result->failed()) {
                // Fallback for Git <2.13: parse remote branch list
                $fallback = Process::run(
                    'git branch -r | sed \'s|origin/||\' | grep -v HEAD'
                );
                if (! $fallback->failed()) {
                    return collect(explode("\n", trim($fallback->output())))->all();
                }

                // Last resort: common defaults
                return ['main', 'master', 'develop'];
            }

            $output = array_filter(explode("\n", trim($result->output())));
            $branches = [];

            foreach ($output as $branch) {
                // Clean up origin/ prefix and skip HEAD
                $cleanBranch = preg_replace('/^origin\//', '', trim($branch));
                if ($cleanBranch !== 'HEAD' && ! empty($cleanBranch)) {
                    $branches[] = $cleanBranch;
                }
            }

            return array_unique($branches);
        } catch (\Exception $e) {
            return ['main', 'master', 'develop'];
        }
    }

    /**
     * Interactive branch selection for PR
     */
    public function selectBranches(?Command $command): array
    {
        if (! $command) {
            return [
                'head' => $this->getCurrentBranch() ?? 'feature-branch',
                'base' => 'main',
            ];
        }

        $currentBranch = $this->getCurrentBranch();
        $availableBranches = $this->getAvailableBranches();

        // Head branch (source)
        $headBranch = text(
            label: 'Head branch (your changes)',
            placeholder: 'feature/my-changes',
            default: $currentBranch ?? '',
            hint: 'The branch containing your changes'
        );

        // Base branch (target)
        $defaultBase = $this->detectDefaultBranch($availableBranches);
        if (count($availableBranches) > 1) {
            $baseBranch = select(
                label: 'Base branch (merge target)',
                options: array_combine($availableBranches, $availableBranches),
                default: $defaultBase,
                hint: 'The branch you want to merge into'
            );
        } else {
            $baseBranch = text(
                label: 'Base branch (merge target)',
                default: $defaultBase,
                hint: 'The branch you want to merge into'
            );
        }

        return [
            'head' => $headBranch,
            'base' => $baseBranch,
        ];
    }

    /**
     * Validate branch names
     */
    public function validateBranches(array $branches): array
    {
        $errors = [];

        if (empty($branches['head'])) {
            $errors[] = 'Head branch is required';
        }

        if (empty($branches['base'])) {
            $errors[] = 'Base branch is required';
        }

        if (isset($branches['head'], $branches['base']) && $branches['head'] === $branches['base']) {
            $errors[] = 'Head and base branches cannot be the same';
        }

        foreach (['head', 'base'] as $type) {
            if (isset($branches[$type])) {
                $branch = $branches[$type];
                if (strlen($branch) > 250) {
                    $errors[] = ucfirst($type).' branch name is too long (max 250 characters)';
                }

                // Basic branch name validation
                if (! preg_match('/^[a-zA-Z0-9._\/-]+$/', $branch)) {
                    $errors[] = ucfirst($type).' branch name contains invalid characters';
                }
            }
        }

        return $errors;
    }

    /**
     * Detect the default branch for the repository
     */
    private function detectDefaultBranch(array $availableBranches): string
    {
        // Priority order for default branches
        $priorities = ['main', 'master', 'develop', 'dev'];
        foreach ($priorities as $branch) {
            if (in_array($branch, $availableBranches)) {
                return $branch;
            }
        }

        // Return first available branch or 'main' as fallback
        return $availableBranches[0] ?? 'main';
    }

    /**
     * Check if branches exist and are different
     */
    public function verifyBranchSetup(?Command $command, array $branches): bool
    {
        if (! $command) {
            return true;
        }

        $head = $branches['head'] ?? '';
        $base = $branches['base'] ?? '';

        if (empty($head) || empty($base)) {
            $command->error('❌ Both head and base branches must be specified');
            return false;
        }

        if ($head === $base) {
            $command->error('❌ Head and base branches cannot be the same');
            return false;
        }

        // Check if current branch matches head branch
        $currentBranch = $this->getCurrentBranch();
        if ($currentBranch && $currentBranch !== $head) {
            $switch = confirm(
                "Current branch is '{$currentBranch}' but PR head is '{$head}'. Switch branches?",
                false
            );

            if ($switch) {
                $command->warn("⚠️ Please run: git checkout {$head}");
                return false;
            }
        }

        return true;
    }

    /**
     * Display branch configuration summary
     */
    public function displayBranchSummary(?Command $command, array $branches): void
    {
        if (! $command) {
            return;
        }

        $command->line('<comment>🌿 Branch configuration:</comment>');
        $command->line("  📤 Head: <info>{$branches['head']}</info> (your changes)");
        $command->line("  📥 Base: <info>{$branches['base']}</info> (merge target)");
        $command->newLine();
    }
}
