<?php

declare(strict_types=1);

namespace App\Services\ComponentScaffolding;

use App\Services\ComponentScaffolding\Builders\ComponentBuilder;
use App\Services\ComponentScaffolding\Templates\TemplateManager;
use App\Services\ComponentScaffolding\Validators\ComponentValidator;
use LaravelZero\Framework\Commands\Command;

/**
 * Enhanced component scaffolding service that addresses critical GitHub issues:
 * - Issue #85: Universal Output Format Interfaces
 * - Issue #84: Laravel Zero Database Patterns
 * - Issue #76: Component Installation System
 * - Issue #65: Slack Integration patterns
 * - Issue #62: Service Architecture
 * - Issue #44: PR Readiness Workflow
 * - Issue #38: Enhanced GitHub Package
 */
class ScaffoldingService
{
    public function __construct(
        private TemplateManager $templateManager,
        private ComponentValidator $validator,
        private ComponentBuilder $builder
    ) {}

    /**
     * Generate component with enhanced scaffolding addressing all critical issues
     */
    public function generateComponent(array $config, ?Command $command = null): ComponentResult
    {
        // Validate configuration
        $validationResult = $this->validator->validate($config);
        if (! $validationResult->isValid()) {
            return ComponentResult::failed($validationResult->getErrors());
        }

        // Determine component type and get appropriate templates
        $componentType = $this->determineComponentType($config);
        $templates = $this->templateManager->getTemplatesForType($componentType);

        // Build component structure
        $buildResult = $this->builder->build($config, $templates, $command);

        if (! $buildResult->isSuccessful()) {
            return ComponentResult::failed($buildResult->getErrors());
        }

        // Generate git-tag based isolation structure (Issue #76)
        $this->generateIsolationStructure($config, $buildResult->getPath());

        // Generate comprehensive CI/CD workflows (Issue #44)
        $this->generateCiCdWorkflows($config, $buildResult->getPath());

        return ComponentResult::success($buildResult->getPath(), $componentType);
    }

    /**
     * Determine component type based on configuration
     */
    private function determineComponentType(array $config): ComponentType
    {
        // Check for explicit type
        if (isset($config['type'])) {
            return ComponentType::from($config['type']);
        }

        // Infer from component name and features
        $name = strtolower($config['name']);
        $features = $config['features'] ?? [];

        // API integration components (Issue #65)
        if (str_contains($name, 'slack') || in_array('slack', $features)) {
            return ComponentType::SLACK_INTEGRATION;
        }

        if (str_contains($name, 'github') || in_array('github', $features)) {
            return ComponentType::GITHUB_ADVANCED;
        }

        // Database components (Issue #84)
        if (in_array('database', $features) || str_contains($name, 'schema') || str_contains($name, 'migration')) {
            return ComponentType::DATABASE;
        }

        // Advanced components with multiple integrations
        if (count($features) > 3 || in_array('advanced', $features)) {
            return ComponentType::ADVANCED;
        }

        return ComponentType::BASIC;
    }

    /**
     * Generate git-tag based isolation structure (addresses Issue #76)
     */
    private function generateIsolationStructure(array $config, string $path): void
    {
        // Create .conduit-isolation config
        $isolationConfig = [
            'version' => '1.0.0',
            'isolation_strategy' => 'git-tag',
            'installation_method' => 'isolated_composer',
            'prevents_main_pollution' => true,
            'supports_rollback' => true,
            'semantic_versioning' => true,
        ];

        file_put_contents(
            $path.'/.conduit-isolation',
            json_encode($isolationConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Create installation script
        $installScript = $this->templateManager->getTemplate('isolation/install.stub', $config);
        file_put_contents($path.'/install.sh', $installScript);
        chmod($path.'/install.sh', 0755);

        // Create rollback script
        $rollbackScript = $this->templateManager->getTemplate('isolation/rollback.stub', $config);
        file_put_contents($path.'/rollback.sh', $rollbackScript);
        chmod($path.'/rollback.sh', 0755);
    }

    /**
     * Generate comprehensive CI/CD workflows (addresses Issue #44)
     */
    private function generateCiCdWorkflows(array $config, string $path): void
    {
        $workflowsDir = $path.'/.github/workflows';
        mkdir($workflowsDir, 0755, true);

        // PR readiness workflow
        $prWorkflow = $this->templateManager->getTemplate('workflows/pr-readiness.yml.stub', $config);
        file_put_contents($workflowsDir.'/pr-readiness.yml', $prWorkflow);

        // Component testing workflow
        $testWorkflow = $this->templateManager->getTemplate('workflows/component-testing.yml.stub', $config);
        file_put_contents($workflowsDir.'/component-testing.yml', $testWorkflow);

        // Release automation workflow
        $releaseWorkflow = $this->templateManager->getTemplate('workflows/release-automation.yml.stub', $config);
        file_put_contents($workflowsDir.'/release-automation.yml', $releaseWorkflow);
    }

    /**
     * Get available component types
     */
    public function getAvailableTypes(): array
    {
        return ComponentType::getDescriptions();
    }

    /**
     * Get template options for a specific component type
     */
    public function getTemplateOptions(ComponentType $type): array
    {
        return $this->templateManager->getTemplateOptions($type);
    }
}
