<?php

use App\Services\GitHub\PrAnalysisService;
use JordanPartridge\GithubClient\Data\Pulls\PullRequestDetailDTO;
use JordanPartridge\GithubClient\Facades\Github;

beforeEach(function () {
    $this->service = new PrAnalysisService();
});

it('analyzes PR ready to merge', function () {
    $mockPr = Mockery::mock(PullRequestDetailDTO::class);
    $mockPr->number = 123;
    $mockPr->title = 'Test PR';
    $mockPr->user = (object) ['login' => 'testuser'];
    $mockPr->state = 'open';
    $mockPr->draft = false;
    $mockPr->mergeable = true;
    $mockPr->mergeable_state = 'clean';
    $mockPr->rebaseable = true;
    $mockPr->changed_files = 5;
    $mockPr->additions = 100;
    $mockPr->deletions = 50;
    $mockPr->comments = 2;
    $mockPr->review_comments = 3;
    $mockPr->commits = 4;
    
    $mockPr->shouldReceive('isReadyToMerge')->andReturn(true);
    $mockPr->shouldReceive('hasMergeConflicts')->andReturn(false);
    $mockPr->shouldReceive('canRebase')->andReturn(true);
    $mockPr->shouldReceive('getMergeStatusDescription')->andReturn('Ready to merge');
    $mockPr->shouldReceive('getTotalLinesChanged')->andReturn(150);
    $mockPr->shouldReceive('getAdditionRatio')->andReturn(0.67);
    
    Github::shouldReceive('pullRequests->detail')
        ->once()
        ->with('owner', 'repo', 123)
        ->andReturn($mockPr);
    
    $result = $this->service->analyzeMergeReadiness('owner/repo', 123);
    
    expect($result)->toHaveKey('pr_info')
        ->and($result['pr_info']['number'])->toBe(123)
        ->and($result['merge_analysis']['ready_to_merge'])->toBeTrue()
        ->and($result['merge_analysis']['has_conflicts'])->toBeFalse()
        ->and($result['code_analysis']['total_changes'])->toBe(150)
        ->and($result['code_analysis']['change_size'])->toBe('medium')
        ->and($result['discussion_analysis']['total_comments'])->toBe(5)
        ->and($result['recommendations'])->toHaveCount(1)
        ->and($result['recommendations'][0]['type'])->toBe('success');
});

it('analyzes PR with conflicts', function () {
    $mockPr = Mockery::mock(PullRequestDetailDTO::class);
    $mockPr->number = 456;
    $mockPr->title = 'Conflicted PR';
    $mockPr->user = (object) ['login' => 'testuser'];
    $mockPr->state = 'open';
    $mockPr->draft = false;
    $mockPr->mergeable = false;
    $mockPr->mergeable_state = 'dirty';
    $mockPr->rebaseable = false;
    $mockPr->changed_files = 10;
    $mockPr->additions = 200;
    $mockPr->deletions = 100;
    $mockPr->comments = 5;
    $mockPr->review_comments = 10;
    $mockPr->commits = 8;
    
    $mockPr->shouldReceive('isReadyToMerge')->andReturn(false);
    $mockPr->shouldReceive('hasMergeConflicts')->andReturn(true);
    $mockPr->shouldReceive('canRebase')->andReturn(false);
    $mockPr->shouldReceive('getMergeStatusDescription')->andReturn('Has merge conflicts');
    $mockPr->shouldReceive('getTotalLinesChanged')->andReturn(300);
    $mockPr->shouldReceive('getAdditionRatio')->andReturn(0.67);
    
    Github::shouldReceive('pullRequests->detail')
        ->once()
        ->andReturn($mockPr);
    
    $result = $this->service->analyzeMergeReadiness('owner/repo', 456);
    
    expect($result['merge_analysis']['has_conflicts'])->toBeTrue()
        ->and($result['merge_analysis']['ready_to_merge'])->toBeFalse()
        ->and($result['recommendations'][0]['type'])->toBe('critical')
        ->and($result['recommendations'][0]['action'])->toBe('resolve_conflicts');
});

it('calculates health score correctly', function () {
    $mockPr = Mockery::mock(PullRequestDetailDTO::class);
    $mockPr->number = 789;
    $mockPr->title = 'Large PR';
    $mockPr->user = (object) ['login' => 'testuser'];
    $mockPr->state = 'open';
    $mockPr->draft = false;
    $mockPr->mergeable = true;
    $mockPr->mergeable_state = 'clean';
    $mockPr->rebaseable = true;
    $mockPr->changed_files = 50;
    $mockPr->additions = 1500;
    $mockPr->deletions = 500;
    $mockPr->comments = 0;
    $mockPr->review_comments = 0;
    $mockPr->commits = 20;
    
    $mockPr->shouldReceive('isReadyToMerge')->andReturn(true);
    $mockPr->shouldReceive('hasMergeConflicts')->andReturn(false);
    $mockPr->shouldReceive('canRebase')->andReturn(true);
    $mockPr->shouldReceive('getMergeStatusDescription')->andReturn('Ready but large');
    $mockPr->shouldReceive('getTotalLinesChanged')->andReturn(2000);
    $mockPr->shouldReceive('getAdditionRatio')->andReturn(0.75);
    
    Github::shouldReceive('pullRequests->detail')
        ->twice()
        ->andReturn($mockPr);
    
    $result = $this->service->getHealthScore('owner/repo', 789);
    
    expect($result)->toHaveKey('health_score')
        ->and($result['health_score'])->toBeLessThan(80) // Large PR with no discussion
        ->and($result['grade'])->toBeIn(['B', 'C', 'D'])
        ->and($result['status'])->toBeIn(['good', 'fair', 'needs_attention']);
});

it('performs batch analysis', function () {
    $mockPr1 = Mockery::mock(PullRequestDetailDTO::class);
    $mockPr1->number = 1;
    $mockPr1->title = 'PR 1';
    $mockPr1->user = (object) ['login' => 'user1'];
    $mockPr1->state = 'open';
    $mockPr1->draft = false;
    $mockPr1->mergeable = true;
    $mockPr1->mergeable_state = 'clean';
    $mockPr1->rebaseable = true;
    $mockPr1->changed_files = 3;
    $mockPr1->additions = 50;
    $mockPr1->deletions = 20;
    $mockPr1->comments = 1;
    $mockPr1->review_comments = 2;
    $mockPr1->commits = 2;
    
    $mockPr1->shouldReceive('isReadyToMerge')->andReturn(true);
    $mockPr1->shouldReceive('hasMergeConflicts')->andReturn(false);
    $mockPr1->shouldReceive('canRebase')->andReturn(true);
    $mockPr1->shouldReceive('getMergeStatusDescription')->andReturn('Ready');
    $mockPr1->shouldReceive('getTotalLinesChanged')->andReturn(70);
    $mockPr1->shouldReceive('getAdditionRatio')->andReturn(0.71);
    
    $mockPr2 = Mockery::mock(PullRequestDetailDTO::class);
    $mockPr2->number = 2;
    $mockPr2->title = 'PR 2';
    $mockPr2->user = (object) ['login' => 'user2'];
    $mockPr2->state = 'open';
    $mockPr2->draft = true;
    $mockPr2->mergeable = false;
    $mockPr2->mergeable_state = 'draft';
    $mockPr2->rebaseable = true;
    $mockPr2->changed_files = 1;
    $mockPr2->additions = 10;
    $mockPr2->deletions = 5;
    $mockPr2->comments = 0;
    $mockPr2->review_comments = 0;
    $mockPr2->commits = 1;
    
    $mockPr2->shouldReceive('isReadyToMerge')->andReturn(false);
    $mockPr2->shouldReceive('hasMergeConflicts')->andReturn(false);
    $mockPr2->shouldReceive('canRebase')->andReturn(true);
    $mockPr2->shouldReceive('getMergeStatusDescription')->andReturn('Draft');
    $mockPr2->shouldReceive('getTotalLinesChanged')->andReturn(15);
    $mockPr2->shouldReceive('getAdditionRatio')->andReturn(0.67);
    
    Github::shouldReceive('pullRequests->detail')
        ->once()
        ->with('owner', 'repo', 1)
        ->andReturn($mockPr1);
    
    Github::shouldReceive('pullRequests->detail')
        ->once()
        ->with('owner', 'repo', 2)
        ->andReturn($mockPr2);
    
    $result = $this->service->batchAnalyze('owner/repo', [1, 2]);
    
    expect($result)->toHaveKey('summary')
        ->and($result['analyzed_prs'])->toBe(2)
        ->and($result['summary']['total_prs'])->toBe(2)
        ->and($result['summary']['ready_to_merge'])->toBe(1)
        ->and($result['summary']['drafts'])->toBe(1)
        ->and($result['summary']['merge_ready_percentage'])->toBe(50.0);
});

it('handles PR not found gracefully', function () {
    Github::shouldReceive('pullRequests->detail')
        ->once()
        ->andReturn(null);
    
    $result = $this->service->analyzeMergeReadiness('owner/repo', 999);
    
    expect($result)->toHaveKey('error')
        ->and($result['error'])->toBe('Pull request not found');
});