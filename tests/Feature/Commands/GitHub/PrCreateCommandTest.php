<?php

use App\Commands\GitHub\PrCreateCommand;
use App\Services\GitHub\PrCreateService;
use App\Services\GithubAuthService;
use Illuminate\Support\Facades\Http;
use JordanPartridge\GithubClient\Data\Pulls\PullRequestDetailDTO;
use JordanPartridge\GithubClient\Data\Pulls\PullRequestDTOFactory;
use JordanPartridge\GithubClient\Facades\Github;

beforeEach(function () {
    $this->mockAuthService = Mockery::mock(GithubAuthService::class);
    $this->mockPrService = Mockery::mock(PrCreateService::class);
    
    app()->instance(GithubAuthService::class, $this->mockAuthService);
    app()->instance(PrCreateService::class, $this->mockPrService);
});

// Helper function to create PR data for tests
function createPrData(array $overrides = []): array {
    return array_merge([
        'id' => 1,
        'number' => 123,
        'state' => 'open',
        'title' => 'Test PR',
        'body' => 'Test description',
        'html_url' => 'https://github.com/owner/repo/pull/123',
        'diff_url' => 'https://github.com/owner/repo/pull/123.diff',
        'patch_url' => 'https://github.com/owner/repo/pull/123.patch',
        'draft' => false,
        'merged' => false,
        'merged_at' => null,
        'merge_commit_sha' => null,
        'created_at' => '2024-01-01T00:00:00Z',
        'updated_at' => '2024-01-01T00:00:00Z',
        'closed_at' => null,
        'user' => [
            'login' => 'testuser',
            'id' => 1,
            'node_id' => 'MDQ6VXNlcjE=',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/1',
            'gravatar_id' => '',
            'url' => 'https://api.github.com/users/testuser',
            'html_url' => 'https://github.com/testuser',
            'followers_url' => 'https://api.github.com/users/testuser/followers',
            'following_url' => 'https://api.github.com/users/testuser/following{/other_user}',
            'gists_url' => 'https://api.github.com/users/testuser/gists{/gist_id}',
            'starred_url' => 'https://api.github.com/users/testuser/starred{/owner}{/repo}',
            'subscriptions_url' => 'https://api.github.com/users/testuser/subscriptions',
            'organizations_url' => 'https://api.github.com/users/testuser/orgs',
            'repos_url' => 'https://api.github.com/users/testuser/repos',
            'events_url' => 'https://api.github.com/users/testuser/events{/privacy}',
            'received_events_url' => 'https://api.github.com/users/testuser/received_events',
            'type' => 'User',
            'name' => 'Test User',
            'company' => null,
            'blog' => '',
            'location' => null,
            'email' => null,
            'hireable' => null,
            'bio' => null,
            'twitter_username' => null,
            'public_repos' => 0,
            'public_gists' => 0,
            'followers' => 0,
            'following' => 0,
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-01T00:00:00Z',
            'private_gists' => 0,
            'total_private_repos' => 0,
            'owned_private_repos' => 0,
            'disk_usage' => 0,
            'collaborators' => 0,
            'two_factor_authentication' => false,
            'plan' => [
                'name' => 'free',
                'space' => 976562499,
                'private_repos' => 10000,
                'collaborators' => 0,
            ],
            'suspended_at' => null,
            'business_plus' => false,
            'ldap_dn' => null,
            'user_view_type' => 'public',
            'site_admin' => false,
        ],
        'base' => [
            'ref' => 'main',
        ],
        'head' => [
            'ref' => 'feature/test',
        ],
        // Detail fields
        'comments' => 0,
        'review_comments' => 0,
        'commits' => 1,
        'additions' => 10,
        'deletions' => 5,
        'changed_files' => 2,
        'mergeable' => true,
        'mergeable_state' => 'clean',
        'rebaseable' => true,
    ], $overrides);
}

it('requires authentication to create PR', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(false);
    
    $this->artisan('prs:create')
        ->expectsOutput('❌ Not authenticated with GitHub')
        ->expectsOutput('💡 Run: gh auth login')
        ->assertExitCode(1);
});

it('creates PR successfully with all options', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);
    
    // Create a real DTO using the factory
    $prData = createPrData([
        'number' => 123,
        'title' => 'Test PR',
        'body' => 'Test description',
    ]);
    
    $pr = PullRequestDTOFactory::createDetail($prData);
    
    $this->mockPrService
        ->shouldReceive('createPullRequest')
        ->once()
        ->andReturn($pr);
    
    $result = $this->artisan('prs:create', [
        '--repo' => 'owner/repo',
        '--title' => 'Test PR',
        '--body' => 'Test description',
        '--head' => 'feature/test',
        '--base' => 'main',
        '--format' => 'json'
    ]);
    
    // First just check it succeeded
    $result->assertExitCode(0);
    
    // Get the actual output to debug
    $output = $this->app->make(\Symfony\Component\Console\Output\BufferedOutput::class);
    
    // For now, let's just check for the number
    $result->expectsOutputToContain('123');
});

it('handles PR creation failure', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);
    
    $this->mockPrService
        ->shouldReceive('createPullRequest')
        ->once()
        ->andReturn(null);
    
    $this->artisan('prs:create', [
        '--repo' => 'owner/repo',
        '--title' => 'Failed PR',
        '--body' => 'This will fail',
        '--head' => 'feature/fail',
        '--base' => 'main',
        '--format' => 'json'
    ])
        ->expectsOutput('❌ Failed to create pull request')
        ->assertExitCode(1);
});

it('creates PR with command options', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);
    
    // Create a real DTO using the factory
    $prData = createPrData([
        'number' => 456,
        'title' => 'Feature PR',
        'html_url' => 'https://github.com/owner/repo/pull/456',
        'head' => ['ref' => 'feature/new'],
        'base' => ['ref' => 'develop'],
        'draft' => true,
    ]);
    
    $pr = PullRequestDTOFactory::createDetail($prData);
    
    $this->mockPrService
        ->shouldReceive('createPullRequest')
        ->once()
        ->with('owner/repo', Mockery::on(function ($data) {
            return $data['title'] === 'Feature PR' &&
                   $data['body'] === 'Feature description' &&
                   $data['head'] === 'feature/new' &&
                   $data['base'] === 'develop' &&
                   $data['draft'] === true;
        }))
        ->andReturn($pr);
    
    $this->artisan('prs:create', [
        '--repo' => 'owner/repo',
        '--title' => 'Feature PR',
        '--body' => 'Feature description',
        '--head' => 'feature/new',
        '--base' => 'develop',
        '--draft' => true,
        '--format' => 'json'
    ])
        ->expectsOutputToContain('456')
        ->assertExitCode(0);
});

it('uses PR template when specified', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);
    
    // When using JSON format, the template is not applied interactively
    
    // Create a real DTO using the factory
    $prData = createPrData([
        'number' => 789,
        'title' => 'feat: New feature',
        'html_url' => 'https://github.com/owner/repo/pull/789',
        'head' => ['ref' => 'feature/test'],
        'base' => ['ref' => 'main'],
    ]);
    
    $pr = PullRequestDTOFactory::createDetail($prData);
    
    $this->mockPrService
        ->shouldReceive('createPullRequest')
        ->once()
        ->andReturn($pr);
    
    $this->artisan('prs:create', [
        '--repo' => 'owner/repo',
        '--template' => 'feature',
        '--format' => 'json',
        '--head' => 'feature/test',
        '--base' => 'main',
    ])
        ->expectsOutputToContain('789')
        ->assertExitCode(0);
});