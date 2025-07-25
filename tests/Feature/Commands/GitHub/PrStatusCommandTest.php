<?php

use App\Commands\GitHub\PrStatusCommand;
use App\Services\GitHub\PrAnalysisService;
use App\Services\GithubAuthService;

beforeEach(function () {
    $this->mockAuthService = Mockery::mock(GithubAuthService::class);
    $this->mockAnalysisService = Mockery::mock(PrAnalysisService::class);
    
    app()->instance(GithubAuthService::class, $this->mockAuthService);
    app()->instance(PrAnalysisService::class, $this->mockAnalysisService);
});

it('requires authentication to check PR status', function () {
    $this->mockAuthService
        ->shouldReceive('getToken')
        ->once()
        ->andReturn(null);
    
    $this->artisan('prs:status 123')
        ->expectsOutput('❌ GitHub authentication required. Run: conduit github:auth')
        ->assertExitCode(1);
});

it('shows PR ready to merge status', function () {
    $this->mockAuthService
        ->shouldReceive('getToken')
        ->once()
        ->andReturn('test-token');
    
    $this->mockAnalysisService
        ->shouldReceive('analyzeMergeReadiness')
        ->once()
        ->with('owner/repo', 123)
        ->andReturn([
            'pr_info' => [
                'number' => 123,
                'title' => 'Test PR',
                'author' => 'testuser',
                'state' => 'open',
                'draft' => false,
            ],
            'merge_analysis' => [
                'ready_to_merge' => true,
                'has_conflicts' => false,
                'can_rebase' => true,
                'status_description' => 'Clean and ready',
                'mergeable' => true,
                'mergeable_state' => 'clean',
                'rebaseable' => true,
            ]
        ]);
    
    $this->artisan('prs:status 123 --repo=owner/repo')
        ->expectsOutput('⏳ Checking merge status for PR #123...')
        ->expectsOutputToContain('🔍 PR #123: Test PR')
        ->expectsOutputToContain('✅ Ready to Merge - No conflicts detected')
        ->expectsOutputToContain('🎉 This PR is ready to merge!')
        ->assertExitCode(0);
});

it('shows PR with conflicts status', function () {
    $this->mockAuthService
        ->shouldReceive('getToken')
        ->once()
        ->andReturn('test-token');
    
    $this->mockAnalysisService
        ->shouldReceive('analyzeMergeReadiness')
        ->once()
        ->with('owner/repo', 456)
        ->andReturn([
            'pr_info' => [
                'number' => 456,
                'title' => 'Conflicted PR',
                'author' => 'testuser',
                'state' => 'open',
                'draft' => false,
            ],
            'merge_analysis' => [
                'ready_to_merge' => false,
                'has_conflicts' => true,
                'can_rebase' => false,
                'status_description' => 'Has merge conflicts',
                'mergeable' => false,
                'mergeable_state' => 'dirty',
                'rebaseable' => false,
            ]
        ]);
    
    $this->artisan('prs:status 456 --repo=owner/repo')
        ->expectsOutputToContain('❌ Has Merge Conflicts - Requires resolution')
        ->expectsOutputToContain('🔧 Action needed: Resolve merge conflicts before merging')
        ->assertExitCode(0);
});

it('shows draft PR status', function () {
    $this->mockAuthService
        ->shouldReceive('getToken')
        ->once()
        ->andReturn('test-token');
    
    $this->mockAnalysisService
        ->shouldReceive('analyzeMergeReadiness')
        ->once()
        ->andReturn([
            'pr_info' => [
                'number' => 789,
                'title' => 'Draft PR',
                'author' => 'testuser',
                'state' => 'open',
                'draft' => true,
            ],
            'merge_analysis' => [
                'ready_to_merge' => false,
                'has_conflicts' => false,
                'can_rebase' => true,
                'status_description' => 'Draft PR',
                'mergeable' => true,
                'mergeable_state' => 'draft',
                'rebaseable' => true,
            ]
        ]);
    
    $this->artisan('prs:status 789 --repo=owner/repo')
        ->expectsOutputToContain('📝 Note: This is a draft PR')
        ->assertExitCode(0);
});

it('outputs JSON format when requested', function () {
    $this->mockAuthService
        ->shouldReceive('getToken')
        ->once()
        ->andReturn('test-token');
    
    $analysisData = [
        'pr_info' => [
            'number' => 999,
            'title' => 'JSON PR',
            'author' => 'testuser',
        ],
        'merge_analysis' => [
            'ready_to_merge' => true,
            'has_conflicts' => false,
        ]
    ];
    
    $this->mockAnalysisService
        ->shouldReceive('analyzeMergeReadiness')
        ->once()
        ->andReturn($analysisData);
    
    $this->artisan('prs:status 999 --repo=owner/repo --format=json')
        ->expectsOutputToContain('999')
        ->assertExitCode(0);
});

it('handles PR not found error', function () {
    $this->mockAuthService
        ->shouldReceive('getToken')
        ->once()
        ->andReturn('test-token');
    
    $this->mockAnalysisService
        ->shouldReceive('analyzeMergeReadiness')
        ->once()
        ->andReturn(['error' => 'Pull request not found']);
    
    $this->artisan('prs:status 404 --repo=owner/repo')
        ->expectsOutput('❌ Pull request not found')
        ->assertExitCode(1);
});