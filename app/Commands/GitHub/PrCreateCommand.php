<?php

namespace App\Commands\GitHub;

use App\Commands\GitHub\Concerns\DetectsRepository;
use App\Commands\GitHub\Concerns\OpensBrowser;
use App\Services\GitHub\PrCreateService;
use App\Services\GithubAuthService;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class PrCreateCommand extends Command
{
    use DetectsRepository;
    use OpensBrowser;

    protected $signature = 'prs:create 
                           {--repo= : Repository (owner/repo)}
                           {--title= : PR title}
                           {--body= : PR body (markdown)}
                           {--head= : Head branch (your changes)}
                           {--base= : Base branch (merge target)}
                           {--template= : Use PR template (feature, bugfix, hotfix, breaking, docs)}
                           {--reviewers=* : Reviewers (usernames)}
                           {--draft : Create as draft PR}
                           {--format=interactive : Output format (interactive, json)}';

    protected $description = 'Create a new GitHub pull request with rich formatting and templates';

    public function handle(GithubAuthService $githubAuth, PrCreateService $prCreateService): int
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

        if ($this->option('format') === 'json') {
            return $this->createPrJson($prCreateService, $repo);
        }

        return $this->createPrInteractive($prCreateService, $repo);
    }

    private function createPrInteractive(PrCreateService $service, string $repo): int
    {
        $this->info("🔀 Creating pull request for {$repo}");
        $this->newLine();

        // Gather PR data
        $prData = $this->gatherPrData($service, $repo);
        if (! $prData) {
            $this->error('❌ PR creation cancelled');
            return 1;
        }

        // Preview the PR
        $this->showPrPreview($service, $prData);

        if (! confirm('Create this pull request?', true)) {
            $this->info('❌ PR creation cancelled');
            return 1;
        }

        // Create the PR
        $this->info('🚀 Creating pull request...');
        $pr = $service->createPullRequest($repo, $prData);

        if (! $pr) {
            $this->error('❌ Failed to create pull request');
            return 1;
        }

        // Display success
        $this->displaySuccessMessage($pr);

        // Ask to open in browser
        if (confirm('🌐 Open PR in browser?', true)) {
            $this->openInBrowser($pr->html_url);
        }

        return 0;
    }

    private function createPrJson(PrCreateService $service, string $repo): int
    {
        $prData = [
            'title' => $this->option('title'),
            'body' => $this->option('body'),
            'head' => $this->option('head'),
            'base' => $this->option('base'),
            'draft' => $this->option('draft'),
        ];

        // Filter out empty values
        $prData = array_filter($prData, fn($value) => $value !== null);

        $pr = $service->createPullRequest($repo, $prData);

        if (! $pr) {
            $this->error('❌ Failed to create pull request');
            return 1;
        }

        $this->line(json_encode($pr->toArray(), JSON_PRETTY_PRINT));
        return 0;
    }

    private function gatherPrData(PrCreateService $service, string $repo): ?array
    {
        $data = [];

        // Handle template-based creation
        if ($template = $this->option('template')) {
            $templateData = $service->applyTemplateInteractively($this, $template);
            if (empty($templateData)) {
                $this->error("❌ Invalid template: {$template}");
                return null;
            }
            $data = array_merge($data, $templateData);
        }

        // Branch configuration
        if (! $this->option('head') || ! $this->option('base')) {
            $branches = $service->selectBranches($this);
            $data = array_merge($data, $branches);
        } else {
            $data['head'] = $this->option('head');
            $data['base'] = $this->option('base');
        }

        // Verify branch setup
        if (! $service->verifyBranchSetup($this, $data)) {
            return null;
        }

        // Interactive title input
        if (empty($data['title'])) {
            $data['title'] = text(
                label: '📝 PR title',
                placeholder: 'Add a descriptive title...',
                required: true
            );
        }

        // Interactive body input with template or editor
        if (empty($data['body'])) {
            if (confirm('📄 Open markdown editor for PR body?', false)) {
                $data['body'] = $service->openEditor($data['body'] ?? '', $this);
            } else {
                $data['body'] = text(
                    label: '📄 PR body (markdown)',
                    placeholder: 'Brief description or use markdown editor above...'
                );
            }
        }

        // Reviewer selection
        if (empty($this->option('reviewers'))) {
            try {
                $availableReviewers = $service->getAvailableReviewers($repo);
                if (! empty($availableReviewers)) {
                    $selectedReviewers = $service->selectReviewers($this, $availableReviewers);
                    $data['reviewers'] = $selectedReviewers;
                }
            } catch (\BadMethodCallException $e) {
                $this->warn($e->getMessage());
            }
        } else {
            $data['reviewers'] = $this->option('reviewers');
        }

        // Draft option
        if ($this->option('draft')) {
            $data['draft'] = true;
        }

        return $data;
    }

    private function showPrPreview(PrCreateService $service, array $prData): void
    {
        $this->newLine();
        $this->line('<comment>📋 Pull Request Preview:</comment>');
        $this->newLine();

        // Display branch configuration
        $service->displayBranchSummary($this, $prData);

        // Title
        $this->line("📝 <fg=cyan;options=bold>{$prData['title']}</fg=cyan;options=bold>");
        $this->newLine();

        // Body preview
        if (!empty($prData['body'])) {
            $this->line('<comment>Body:</comment>');
            $this->newLine();
            $service->renderMarkdownText($this, $prData['body']);
            $this->newLine();
        }

        // Reviewers
        if (!empty($prData['reviewers'])) {
            $service->displayReviewerSummary($this, $prData['reviewers']);
        }

        // Draft status
        if (!empty($prData['draft'])) {
            $this->line('📝 <fg=yellow>Draft PR</fg=yellow>');
            $this->newLine();
        }
    }

    private function displaySuccessMessage(object $pr): void
    {
        $this->newLine();
        $this->info('✅ Pull request created successfully!');
        $this->newLine();

        $this->line("🔀 <fg=cyan;options=bold>PR #{$pr->number}</fg=cyan;options=bold>");
        $this->line("📝 <info>{$pr->title}</info>");
        $this->line("🌿 <comment>{$pr->head->ref} → {$pr->base->ref}</comment>");
        $this->line("🔗 <href={$pr->html_url}>{$pr->html_url}</>");
        $this->newLine();
    }
}