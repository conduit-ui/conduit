<?php

namespace Tests\Unit;

use App\Contracts\GitHub\PrCreateInterface;
use App\Services\GitHub\PrCreateService;
use Tests\TestCase;

class PrCreateServiceTest extends TestCase
{
    private PrCreateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PrCreateService;
    }

    public function test_implements_pr_create_interface()
    {
        expect($this->service)->toBeInstanceOf(PrCreateInterface::class);
    }

    public function test_sanitizes_pr_data()
    {
        $dirtyData = [
            'title' => '  Test PR Title  ',
            'body' => "Line 1\nLine 2\n\n\nLine 3",
            'head' => ' feature/test ',
            'base' => ' main ',
            'draft' => 'true',
            'extra_field' => 'should be removed',
        ];

        $cleanData = $this->service->sanitizePrData($dirtyData);

        expect($cleanData)
            ->toHaveKey('title', 'Test PR Title')
            ->toHaveKey('body', "Line 1\nLine 2\n\n\nLine 3")
            ->toHaveKey('head', 'feature/test')
            ->toHaveKey('base', 'main')
            ->toHaveKey('draft', true)
            ->not->toHaveKey('extra_field');
    }

    public function test_validates_pr_data()
    {
        $validData = [
            'title' => 'Valid PR Title',
            'head' => 'feature/test',
            'base' => 'main',
        ];

        $errors = $this->service->validatePrData($validData);
        expect($errors)->toBeEmpty();
    }

    public function test_validates_missing_required_fields()
    {
        $invalidData = [
            'body' => 'Just a body',
        ];

        $errors = $this->service->validatePrData($invalidData);

        expect($errors)
            ->toContain('Title is required')
            ->toContain('Head branch is required')
            ->toContain('Base branch is required');
    }

    public function test_validates_title_length()
    {
        $data = [
            'title' => str_repeat('a', 300), // Too long
            'head' => 'feature/test',
            'base' => 'main',
        ];

        $errors = $this->service->validatePrData($data);
        expect($errors)->toContain('Title must be 256 characters or less');
    }

    public function test_validates_same_head_and_base()
    {
        $data = [
            'title' => 'Test PR',
            'head' => 'main',
            'base' => 'main',
        ];

        $errors = $this->service->validatePrData($data);
        expect($errors)->toContain('Head and base branches cannot be the same');
    }

    public function test_detects_changes_in_pr_data()
    {
        $original = [
            'title' => 'Original Title',
            'body' => 'Original body',
            'draft' => false,
        ];

        $updated = [
            'title' => 'Updated Title',
            'body' => 'Original body',
            'draft' => false,
        ];

        expect($this->service->hasChanges($original, $updated))->toBeTrue();
        expect($this->service->hasChanges($original, $original))->toBeFalse();
    }

    public function test_create_pull_request_validates_data_first()
    {
        $invalidData = [
            'head' => 'feature/test',
            'base' => 'main',
            // Missing required title
        ];

        expect(fn () => $this->service->createPullRequest('owner/repo', $invalidData))
            ->toThrow(\InvalidArgumentException::class, 'Validation failed: Title is required');
    }

    public function test_should_add_attribution_setting()
    {
        config(['conduit.github.add_attribution' => true]);
        expect($this->service->shouldAddAttribution())->toBeTrue();

        config(['conduit.github.add_attribution' => false]);
        expect($this->service->shouldAddAttribution())->toBeFalse();
    }

    public function test_create_pull_request_handles_validation_errors()
    {
        $invalidData = [
            'head' => 'feature/test',
            'base' => 'main',
            // Missing required title
        ];

        expect(fn () => $this->service->createPullRequest('owner/repo', $invalidData))
            ->toThrow(\InvalidArgumentException::class, 'Validation failed: Title is required');
    }

    public function test_get_available_reviewers_returns_array()
    {
        // Test that method returns array even when API fails
        // The implementation is designed to handle errors gracefully and return empty array
        $reviewers = $this->service->getAvailableReviewers('nonexistent/repo');
        expect($reviewers)->toBeArray();
    }
}
