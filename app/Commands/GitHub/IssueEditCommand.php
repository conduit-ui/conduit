<?php

namespace App\Commands\GitHub;

use App\Commands\GitHub\Concerns\DetectsRepository;
use App\Services\GitHub\IssueEditService;
use App\Services\GithubAuthService;
use LaravelZero\Framework\Commands\Command;

class IssueEditCommand extends Command
{
    use DetectsRepository;

    protected $signature = 'issues:edit 
                           {issue : Issue number to edit}
                           {--repo= : Repository (owner/repo)}
                           {--title= : New issue title}
                           {--body= : New issue body (markdown)}
                           {--state= : Issue state (open, closed)}
                           {--add-labels=* : Labels to add}
                           {--remove-labels=* : Labels to remove}
                           {--add-assignees=* : Assignees to add}
                           {--remove-assignees=* : Assignees to remove}
                           {--milestone= : Milestone name or number (use "none" to remove)}
                           {--editor : Open editor for title and body}
                           {--format=interactive : Output format (interactive, json)}';

    protected $description = 'Edit GitHub issue with rich editing capabilities';

    public function handle(GithubAuthService $githubAuth, IssueEditService $issueEditService): int
    {
        if (! $githubAuth->isAuthenticated()) {
            $this->error('❌ Not authenticated with GitHub');
            $this->info('💡 Run: gh auth login');

            return 1;
        }

        $issueNumber = (int) $this->argument('issue');
        $repo = $this->option('repo');

        if (! $repo) {
            $repo = $this->detectCurrentRepo();
            if (! $repo) {
                $this->error('📂 No repository specified and none detected from current directory');
                $this->info('💡 Use --repo=owner/repo or run from within a git repository');

                return 1;
            }
        }

        try {
            if ($this->option('format') === 'json') {
                return $this->editIssueJson($issueEditService, $repo, $issueNumber);
            }

            return $this->editIssueInteractive($issueEditService, $repo, $issueNumber);

        } catch (\Exception $e) {
            $this->error("❌ Failed to edit issue: {$e->getMessage()}");

            return 1;
        }
    }

    private function editIssueInteractive(IssueEditService $service, string $repo, int $issueNumber): int
    {
        // First, get current issue
        $currentIssue = $service->getIssue($repo, $issueNumber);
        if (! $currentIssue) {
            $this->error("❌ Issue #{$issueNumber} not found in {$repo}");

            return 1;
        }

        $this->info("📝 Editing issue #{$issueNumber} in {$repo}");
        $this->newLine();

        // Show current issue
        $service->displayCurrentIssue($this, $currentIssue);

        // Gather changes
        $changes = $this->gatherChanges($service, $repo, $currentIssue);

        if (empty($changes)) {
            $this->info('ℹ️  No changes specified');

            return 0;
        }

        // Preview changes
        $this->showChangePreview($service, $currentIssue, $changes);

        if (! $this->confirm('Apply these changes?', true)) {
            $this->info('❌ Edit cancelled');

            return 1;
        }

        // Apply changes
        $this->info('🚀 Updating issue...');
        $updatedIssue = $service->updateIssue($repo, $issueNumber, $changes);

        if (! $updatedIssue) {
            $this->error('❌ Failed to update issue');

            return 1;
        }

        // Display success
        $this->displaySuccessMessage($updatedIssue);

        return 0;
    }

    private function editIssueJson(IssueEditService $service, string $repo, int $issueNumber): int
    {
        $changes = [
            'title' => $this->option('title'),
            'body' => $this->option('body'),
            'state' => $this->option('state'),
            'add_labels' => $this->option('add-labels') ?: [],
            'remove_labels' => $this->option('remove-labels') ?: [],
            'add_assignees' => $this->option('add-assignees') ?: [],
            'remove_assignees' => $this->option('remove-assignees') ?: [],
            'milestone' => $this->option('milestone'),
        ];

        $changes = array_filter($changes, fn ($value) => $value !== null);

        $updatedIssue = $service->updateIssue($repo, $issueNumber, $changes);

        if (! $updatedIssue) {
            $this->error('❌ Failed to update issue');

            return 1;
        }

        $this->line(json_encode($updatedIssue, JSON_PRETTY_PRINT));

        return 0;
    }

    private function gatherChanges(IssueEditService $service, string $repo, array $currentIssue): array
    {
        $changes = [];

        // Handle editor mode
        if ($this->option('editor')) {
            return $this->gatherChangesWithEditor($service, $currentIssue);
        }

        // Handle individual options
        if ($title = $this->option('title')) {
            $changes['title'] = $title;
        }

        if ($body = $this->option('body')) {
            $changes['body'] = $body;
        }

        if ($state = $this->option('state')) {
            $changes['state'] = $state;
        }

        if ($addLabels = $this->option('add-labels')) {
            $changes['add_labels'] = $addLabels;
        }

        if ($removeLabels = $this->option('remove-labels')) {
            $changes['remove_labels'] = $removeLabels;
        }

        if ($addAssignees = $this->option('add-assignees')) {
            $changes['add_assignees'] = $addAssignees;
        }

        if ($removeAssignees = $this->option('remove-assignees')) {
            $changes['remove_assignees'] = $removeAssignees;
        }

        if ($milestone = $this->option('milestone')) {
            $changes['milestone'] = $milestone;
        }

        // Interactive mode if no options specified
        if (empty($changes)) {
            return $this->gatherChangesInteractively($service, $repo, $currentIssue);
        }

        return $changes;
    }

    private function gatherChangesWithEditor(IssueEditService $service, array $currentIssue): array
    {
        $this->info('📝 Opening interactive editor for issue editing...');

        $editData = $service->openIssueEditor($this, $currentIssue);

        $changes = [];
        if ($editData['title'] !== $currentIssue['title']) {
            $changes['title'] = $editData['title'];
        }

        if ($editData['body'] !== ($currentIssue['body'] ?? '')) {
            $changes['body'] = $editData['body'];
        }

        return $changes;
    }

    private function gatherChangesInteractively(IssueEditService $service, string $repo, array $currentIssue): array
    {
        $changes = [];

        $this->line('<comment>What would you like to edit? (Enter to skip each)</comment>');
        $this->newLine();

        // Title
        $newTitle = $this->ask("📝 New title (current: {$currentIssue['title']})");
        if ($newTitle && $newTitle !== $currentIssue['title']) {
            $changes['title'] = $newTitle;
        }

        // Body
        if ($this->confirm('📄 Edit body with markdown editor?', false)) {
            $changes['body'] = $service->openEditor($currentIssue['body'] ?? '', $this);
        }

        // State
        $currentState = $currentIssue['state'];
        $newState = $this->choice("📊 State (current: {$currentState})", ['open', 'closed', 'skip'], 2);
        if ($newState !== 'skip' && $newState !== $currentState) {
            $changes['state'] = $newState;
        }

        // Labels
        if ($this->confirm('🏷️  Manage labels?', false)) {
            $labelChanges = $service->interactiveLabelManagement($this, $repo, $currentIssue['labels']);
            $changes = array_merge($changes, $labelChanges);
        }

        // Assignees
        if ($this->confirm('👥 Manage assignees?', false)) {
            $assigneeChanges = $service->interactiveAssigneeManagement($this, $repo, $currentIssue['assignees']);
            $changes = array_merge($changes, $assigneeChanges);
        }

        // Milestone
        if ($this->confirm('🎯 Change milestone?', false)) {
            $milestoneChange = $service->interactiveMilestoneSelection($this, $repo, $currentIssue['milestone']);
            if ($milestoneChange !== null) {
                $changes['milestone'] = $milestoneChange;
            }
        }

        return $changes;
    }

    private function showChangePreview(IssueEditService $service, array $currentIssue, array $changes): void
    {
        $this->newLine();
        $this->line('<comment>📋 Change Preview:</comment>');
        $this->newLine();

        $service->displayChangePreview($this, $currentIssue, $changes);
    }

    private function displaySuccessMessage(array $issue): void
    {
        $this->newLine();
        $this->info('✅ Issue updated successfully!');
        $this->newLine();

        $this->line("📋 <fg=cyan;options=bold>Issue #{$issue['number']}</fg=cyan;options=bold>");
        $this->line("📝 <info>{$issue['title']}</info>");
        $this->line("🔗 <href={$issue['html_url']}>{$issue['html_url']}</>");
        $this->newLine();
    }

    private function detectCurrentRepo(): ?string
    {
        if (! $this->isGitRepository()) {
            return null;
        }

        $remoteUrl = trim(shell_exec('git config --get remote.origin.url 2>/dev/null') ?: '');
        if (empty($remoteUrl)) {
            return null;
        }

        return $this->parseGitHubRepo($remoteUrl);
    }

    private function isGitRepository(): bool
    {
        $gitDir = shell_exec('git rev-parse --git-dir 2>/dev/null');

        return ! empty(trim($gitDir ?? ''));
    }

    private function parseGitHubRepo(string $remoteUrl): ?string
    {
        $patterns = [
            '/git@github\.com:([^\/]+)\/(.+)\.git$/',
            '/https:\/\/github\.com\/([^\/]+)\/(.+)\.git$/',
            '/https:\/\/github\.com\/([^\/]+)\/(.+)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $remoteUrl, $matches)) {
                return "{$matches[1]}/{$matches[2]}";
            }
        }

        return null;
    }
}
