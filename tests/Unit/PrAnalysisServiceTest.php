<?php

namespace Tests\Unit;

use App\Services\GitHub\PrAnalysisService;
use Tests\TestCase;

class PrAnalysisServiceTest extends TestCase
{
    private PrAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PrAnalysisService;
    }

    public function test_service_instantiates_correctly()
    {
        expect($this->service)->toBeInstanceOf(PrAnalysisService::class);
    }

    public function test_calculates_health_scores()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateHealthScore');
        $method->setAccessible(true);

        // Perfect PR - ready to merge, small changes, has discussion
        $analysis = [
            'merge_analysis' => [
                'has_conflicts' => false,
                'ready_to_merge' => true,
            ],
            'code_analysis' => [
                'change_size' => 'small',
                'total_changes' => 50,
            ],
            'discussion_analysis' => [
                'has_discussion' => true,
            ],
            'pr_info' => [
                'draft' => false,
            ],
        ];

        $score = $method->invokeArgs($this->service, [$analysis]);
        expect($score)->toBe(100);

        // Problematic PR - has conflicts, massive changes, no discussion, is draft
        $analysis = [
            'merge_analysis' => [
                'has_conflicts' => true,
                'ready_to_merge' => false,
            ],
            'code_analysis' => [
                'change_size' => 'massive',
                'total_changes' => 1500,
            ],
            'discussion_analysis' => [
                'has_discussion' => false,
            ],
            'pr_info' => [
                'draft' => true,
            ],
        ];

        $score = $method->invokeArgs($this->service, [$analysis]);
        expect($score)->toBe(15); // 100 - 40 (conflicts) - 15 (massive) - 20 (no discussion) - 10 (draft)
    }

    public function test_converts_scores_to_grades()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getGrade');
        $method->setAccessible(true);

        expect($method->invokeArgs($this->service, [95]))->toBe('A');
        expect($method->invokeArgs($this->service, [85]))->toBe('B');
        expect($method->invokeArgs($this->service, [75]))->toBe('C');
        expect($method->invokeArgs($this->service, [65]))->toBe('D');
        expect($method->invokeArgs($this->service, [45]))->toBe('F');
    }

    public function test_gets_status_from_score()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getStatus');
        $method->setAccessible(true);

        expect($method->invokeArgs($this->service, [95]))->toBe('excellent');
        expect($method->invokeArgs($this->service, [85]))->toBe('good');
        expect($method->invokeArgs($this->service, [75]))->toBe('fair');
        expect($method->invokeArgs($this->service, [65]))->toBe('needs_attention');
        expect($method->invokeArgs($this->service, [45]))->toBe('problematic');
    }

    public function test_generates_batch_summary()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateBatchSummary');
        $method->setAccessible(true);

        $results = [
            1 => [
                'merge_analysis' => ['ready_to_merge' => true, 'has_conflicts' => false],
                'pr_info' => ['draft' => false],
            ],
            2 => [
                'merge_analysis' => ['ready_to_merge' => false, 'has_conflicts' => true],
                'pr_info' => ['draft' => true],
            ],
            3 => [
                'merge_analysis' => ['ready_to_merge' => false, 'has_conflicts' => false],
                'pr_info' => ['draft' => false],
            ],
        ];

        $summary = $method->invokeArgs($this->service, [$results]);

        expect($summary)
            ->toHaveKey('total_prs', 3)
            ->toHaveKey('ready_to_merge', 1)
            ->toHaveKey('has_conflicts', 1)
            ->toHaveKey('drafts', 1)
            ->toHaveKey('merge_ready_percentage', 33.3);
    }

    public function test_handles_empty_batch_summary()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateBatchSummary');
        $method->setAccessible(true);

        $summary = $method->invokeArgs($this->service, [[]]);

        expect($summary)
            ->toHaveKey('total_prs', 0)
            ->toHaveKey('merge_ready_percentage', 0);
    }

    private function createMockPrDto(int $totalChanges): object
    {
        return new class($totalChanges) {
            public function __construct(private int $totalChanges) {}
            
            public function getTotalLinesChanged(): int
            {
                return $this->totalChanges;
            }
        };
    }
}