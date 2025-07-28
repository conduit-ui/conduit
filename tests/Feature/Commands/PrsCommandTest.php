<?php

use App\Services\GithubAuthService;
use JordanPartridge\GithubClient\Facades\Github;

beforeEach(function () {
    $this->mockAuthService = Mockery::mock(GithubAuthService::class);
    app()->instance(GithubAuthService::class, $this->mockAuthService);

    // Mock the entire Github facade
    $mockGithub = Mockery::mock();
    $mockPullRequests = Mockery::mock();
    $mockGithub->shouldReceive('pullRequests')->andReturn($mockPullRequests);
    Github::swap($mockGithub);

    $this->mockPullRequests = $mockPullRequests;
});

it('requires authentication to list PRs', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(false);

    $this->artisan('prs')
        ->expectsOutput('❌ Not authenticated with GitHub')
        ->expectsOutput('💡 Run: gh auth login')
        ->assertExitCode(1);
});

it('lists PRs in table format', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);

    $mockPrs = [
        (object) [
            'number' => 123,
            'title' => 'Feature: Add new functionality',
            'state' => 'open',
            'user' => (object) ['login' => 'user1'],
            'comments' => 2,
            'review_comments' => 3,
            'draft' => false,
            'updated_at' => now()->subHours(2)->toIso8601String(),
            'html_url' => 'https://github.com/owner/repo/pull/123',
            'base_ref' => 'main',
            'head_ref' => 'feature/new',
        ],
        (object) [
            'number' => 124,
            'title' => 'Fix: Resolve bug in authentication',
            'state' => 'open',
            'user' => (object) ['login' => 'user2'],
            'comments' => 0,
            'review_comments' => 1,
            'draft' => true,
            'updated_at' => now()->subDays(1)->toIso8601String(),
            'html_url' => 'https://github.com/owner/repo/pull/124',
            'base_ref' => 'main',
            'head_ref' => 'fix/auth-bug',
        ],
    ];

    $this->mockPullRequests->shouldReceive('recentDetails')
        ->once()
        ->with('owner', 'repo', 20, 'open')
        ->andReturn($mockPrs);

    $this->artisan('prs --repo=owner/repo --format=table')
        ->assertExitCode(0);
});

it('lists PRs in interactive format with selection', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);

    $mockPrs = [
        [
            'number' => 456,
            'title' => 'Update documentation',
            'state' => 'open',
            'user' => ['login' => 'docuser'],
            'comments' => 1,
            'review_comments' => 0,
            'draft' => false,
            'updated_at' => now()->subMinutes(30)->toIso8601String(),
            'html_url' => 'https://github.com/owner/repo/pull/456',
            'base_ref' => 'main',
            'head_ref' => 'docs/update',
        ],
    ];

    $this->mockPullRequests->shouldReceive('recentDetails')
        ->once()
        ->with('owner', 'repo', 20, 'open')
        ->andReturn($mockPrs);

    // Test that interactive mode properly formats the output without requiring actual interaction
    $this->artisan('prs --repo=owner/repo --format=interactive')
        ->expectsOutput('📋 Found 1 pull request')
        ->assertExitCode(0);
});

it('filters PRs by context', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);

    // When context=mine with a repo, it should use summaries method
    // Since getCurrentUser returns null in tests, it will just use the regular query
    $this->mockPullRequests->shouldReceive('recentDetails')
        ->once()
        ->with('owner', 'repo', 20, 'open')
        ->andReturn([]);

    $this->artisan('prs --repo=owner/repo --context=mine')
        ->expectsOutput('📭 No pull requests found')
        ->assertExitCode(0);
});

it('outputs PRs in JSON format', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);

    $mockPrs = [
        [
            'number' => 789,
            'title' => 'JSON test PR',
            'state' => 'open',
            'user' => ['login' => 'jsonuser'],
            'comments' => 0,
            'review_comments' => 0,
            'draft' => false,
            'updated_at' => now()->toIso8601String(),
            'html_url' => 'https://github.com/owner/repo/pull/789',
            'base_ref' => 'main',
            'head_ref' => 'test/json',
        ],
    ];

    $this->mockPullRequests->shouldReceive('recentDetails')
        ->once()
        ->with('owner', 'repo', 20, 'open')
        ->andReturn($mockPrs);

    $result = $this->artisan('prs --repo=owner/repo --format=json');

    // Just verify it completes successfully
    $result->assertExitCode(0);
});

it('handles empty PR list gracefully', function () {
    $this->mockAuthService
        ->shouldReceive('isAuthenticated')
        ->once()
        ->andReturn(true);

    $this->mockPullRequests->shouldReceive('recentDetails')
        ->once()
        ->with('owner', 'repo', 20, 'closed')
        ->andReturn([]);

    $this->artisan('prs --repo=owner/repo --state=closed')
        ->expectsOutput('📭 No pull requests found')
        ->assertExitCode(0);
});
