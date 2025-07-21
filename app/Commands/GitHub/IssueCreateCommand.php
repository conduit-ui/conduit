<?php

namespace App\Commands\GitHub;

use App\Commands\GitHub\Concerns\DetectsRepository;
use App\Commands\GitHub\Concerns\OpensBrowser;
use App\Services\GitHub\IssueCreateService;
use App\Services\GithubAuthService;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;

class IssueCreateCommand extends Command
{
    use DetectsRepository;
    use OpensBrowser;
    protected $signature = 'issues:create 
                           {--repo= : Repository (owner/repo)}
                           {--title= : Issue title}
                           {--body= : Issue body (markdown)}
                           {--template= : Use issue template (bug, feature, epic, question)}
                           {--labels=* : Labels to assign}
                           {--assignees=* : Assignees (usernames)}
                           {--milestone= : Milestone name or number}
                           {--draft : Create as draft (if supported)}
                           {--format=interactive : Output format (interactive, json)}';

    protected $description = 'Create a new GitHub issue with rich formatting and templates';

    public function handle(GithubAuthService $githubAuth, IssueCreateService $issueCreateService): int
    {
        if (! $githubAuth->isAuthenticated()) {
            $this->error('❌ Not authenticated with GitHub');
            $this->info('💡 Run: gh auth login');

            return 1;
        }

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
                return $this->createIssueJson($issueCreateService, $repo);
            }

            return $this->createIssueInteractive($issueCreateService, $repo);

        } catch (\Exception $e) {
            $this->error("❌ Failed to create issue: {$e->getMessage()}");

            return 1;
        }
    }

    private function createIssueInteractive(IssueCreateService $service, string $repo): int
    {
        $this->info("✨ Creating new issue in {$repo}");
        $this->newLine();

        // Gather issue data
        $issueData = $this->gatherIssueData($service, $repo);

        if (! $issueData) {
            $this->error('❌ Issue creation cancelled');

            return 1;
        }

        // Preview the issue
        $this->showIssuePreview($service, $issueData);

        if (! confirm('Create this issue?', true)) {
            $this->info('❌ Issue creation cancelled');

            return 1;
        }

        // Create the issue
        $this->info('🚀 Creating issue...');
        $issue = $service->createIssue($repo, $issueData);

        if (! $issue) {
            $this->error('❌ Failed to create issue');

            return 1;
        }

        // Display success
        $this->displaySuccessMessage($issue);

        // Ask to open in browser
        if (confirm('🌐 Open issue in browser?', true)) {
            $this->openInBrowser($issue->html_url);
        }

        return 0;
    }

    private function createIssueJson(IssueCreateService $service, string $repo): int
    {
        $issueData = [
            'title' => $this->option('title'),
            'body' => $this->option('body'),
            'labels' => $this->option('labels') ?: [],
            'assignees' => $this->option('assignees') ?: [],
            'milestone' => $this->option('milestone'),
        ];

        // Apply template if specified
        if ($template = $this->option('template')) {
            $templateData = $service->getTemplate($template);
            if ($templateData) {
                $issueData = array_merge($templateData, array_filter($issueData));
            }
        }

        $issue = $service->createIssue($repo, $issueData);

        if (! $issue) {
            $this->error('❌ Failed to create issue');

            return 1;
        }

        $this->line(json_encode($issue->toArray(), JSON_PRETTY_PRINT));

        return 0;
    }

    private function gatherIssueData(IssueCreateService $service, string $repo): ?array
    {
        // Start with any provided options
        $data = [
            'title' => $this->option('title'),
            'body' => $this->option('body'),
            'labels' => $this->option('labels') ?: [],
            'assignees' => $this->option('assignees') ?: [],
            'milestone' => $this->option('milestone'),
        ];

        // Apply template first if specified
        if ($template = $this->option('template')) {
            $templateData = $service->getTemplate($template);
            if ($templateData) {
                $data = array_merge($templateData, array_filter($data));
                $this->info("📋 Applied {$template} template");
                $this->newLine();
            }
        }

        // Interactive title input
        if (empty($data['title'])) {
            $data['title'] = text(
                label: '📝 Issue title',
                placeholder: 'Enter a descriptive title...',
                required: true
            );
        }

        // Interactive body input with template or editor
        if (empty($data['body'])) {
            if (confirm('📄 Open markdown editor for issue body?', false)) {
                $data['body'] = $service->openEditor($data['body'] ?? '', $this);
            } else {
                $data['body'] = text(
                    label: '📄 Issue body (markdown)',
                    placeholder: 'Brief description or use markdown editor above...'
                );
            }
        }

        // Interactive label selection
        if (empty($data['labels'])) {
            $availableLabels = $service->getAvailableLabels($repo);
            if (! empty($availableLabels)) {
                $selectedLabels = $service->selectLabels($this, $availableLabels);
                $data['labels'] = $selectedLabels;
            }
        }

        // Interactive assignee selection
        if (empty($data['assignees'])) {
            $collaborators = $service->getCollaborators($repo);
            if (! empty($collaborators)) {
                $selectedAssignees = $service->selectAssignees($this, $collaborators);
                $data['assignees'] = $selectedAssignees;
            }
        }

        // Interactive milestone selection
        if (empty($data['milestone'])) {
            $milestones = $service->getMilestones($repo);
            if (! empty($milestones)) {
                $selectedMilestone = $service->selectMilestone($this, $milestones);
                $data['milestone'] = $selectedMilestone;
            }
        }

        return array_filter($data);
    }

    private function showIssuePreview(IssueCreateService $service, array $issueData): void
    {
        $this->newLine();
        $this->line('<comment>📋 Issue Preview:</comment>');
        $this->newLine();

        $service->displayIssuePreview($this, $issueData);
    }

    private function displaySuccessMessage(object $issue): void
    {
        $this->newLine();
        $this->info('✅ Issue created successfully!');
        $this->newLine();

        $this->line("📋 <fg=cyan;options=bold>Issue #{$issue->number}</fg=cyan;options=bold>");
        $this->line("📝 <info>{$issue->title}</info>");
        $this->line("🔗 <href={$issue->html_url}>{$issue->html_url}</>");
        $this->newLine();
    }


}
