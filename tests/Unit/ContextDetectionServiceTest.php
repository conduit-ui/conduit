<?php

use App\Services\ContextDetectionService;

beforeEach(function () {
    // Create a temporary directory for testing
    $this->testDir = sys_get_temp_dir().'/conduit_test_'.uniqid();
    mkdir($this->testDir);
});

afterEach(function () {
    // Clean up test directory
    exec("rm -rf {$this->testDir}");
});

test('detects git repository', function () {
    // Create a git repo
    mkdir($this->testDir.'/.git');

    $service = new ContextDetectionService($this->testDir);
    $context = $service->getContext();

    expect($context['is_git_repo'])->toBeTrue();
});

test('detects non-git directory', function () {
    $service = new ContextDetectionService($this->testDir);
    $context = $service->getContext();

    expect($context['is_git_repo'])->toBeFalse();
    expect($context['git'])->toBeNull();
});

test('detects git branch', function () {
    mkdir($this->testDir.'/.git');
    file_put_contents($this->testDir.'/.git/HEAD', 'ref: refs/heads/main');

    $service = new ContextDetectionService($this->testDir);
    $context = $service->getContext();

    expect($context['git']['current_branch'])->toBe('main');
});

test('detects GitHub repository', function () {
    mkdir($this->testDir.'/.git');
    file_put_contents($this->testDir.'/.git/HEAD', 'ref: refs/heads/main');
    file_put_contents($this->testDir.'/.git/config', <<<'CONFIG'
[remote "origin"]
    url = https://github.com/conduit-ui/conduit.git
    fetch = +refs/heads/*:refs/remotes/origin/*
CONFIG
    );

    $service = new ContextDetectionService($this->testDir);
    $context = $service->getContext();

    expect($context['git']['is_github'])->toBeTrue();
    expect($context['git']['github_owner'])->toBe('conduit-ui');
    expect($context['git']['github_repo'])->toBe('conduit');
});

test('detects Laravel project', function () {
    file_put_contents($this->testDir.'/artisan', '<?php // Laravel artisan file');

    $service = new ContextDetectionService($this->testDir);
    $context = $service->getContext();

    expect($context['project_type'])->toBe('laravel');
});

test('detects programming languages', function () {
    file_put_contents($this->testDir.'/index.php', '<?php echo "Hello";');
    file_put_contents($this->testDir.'/app.js', 'console.log("Hello");');

    $service = new ContextDetectionService($this->testDir);
    $context = $service->getContext();

    expect($context['languages'])->toContain('php');
    expect($context['languages'])->toContain('javascript');
});

test('detects package managers', function () {
    file_put_contents($this->testDir.'/composer.json', '{}');
    file_put_contents($this->testDir.'/package.json', '{}');

    $service = new ContextDetectionService($this->testDir);
    $context = $service->getContext();

    expect($context['package_managers'])->toContain('composer');
    expect($context['package_managers'])->toContain('npm');
});

test('generates correct activation events', function () {
    mkdir($this->testDir.'/.git');
    file_put_contents($this->testDir.'/.git/HEAD', 'ref: refs/heads/main');
    file_put_contents($this->testDir.'/.git/config', <<<'CONFIG'
[remote "origin"]
    url = https://github.com/conduit-ui/conduit.git
CONFIG
    );
    file_put_contents($this->testDir.'/artisan', '<?php // Laravel');
    file_put_contents($this->testDir.'/index.php', '<?php');

    $service = new ContextDetectionService($this->testDir);
    $events = $service->getActivationEvents();

    expect($events)->toContain('context:git');
    expect($events)->toContain('context:github');
    expect($events)->toContain('context:laravel');
    expect($events)->toContain('language:php');
});
