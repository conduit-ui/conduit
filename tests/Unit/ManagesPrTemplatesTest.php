<?php

namespace Tests\Unit;

use App\Services\GitHub\Concerns\ManagesPrTemplates;
use Tests\TestCase;

class ManagesPrTemplatesTest extends TestCase
{
    use ManagesPrTemplates;

    public function test_gets_feature_template()
    {
        $template = $this->getTemplate('feature');
        
        expect($template)
            ->not->toBeNull()
            ->toHaveKey('title')
            ->toHaveKey('body')
            ->toHaveKey('base');
    }

    public function test_gets_bugfix_template()
    {
        $template = $this->getTemplate('bugfix');
        
        expect($template)
            ->not->toBeNull()
            ->toHaveKey('title')
            ->toHaveKey('body')
            ->toHaveKey('base');
    }

    public function test_gets_hotfix_template()
    {
        $template = $this->getTemplate('hotfix');
        
        expect($template)
            ->not->toBeNull()
            ->toHaveKey('title')
            ->toHaveKey('body');
    }

    public function test_gets_breaking_template()
    {
        $template = $this->getTemplate('breaking');
        
        expect($template)
            ->not->toBeNull()
            ->toHaveKey('title')
            ->toHaveKey('body');
    }

    public function test_gets_docs_template()
    {
        $template = $this->getTemplate('docs');
        
        expect($template)
            ->not->toBeNull()
            ->toHaveKey('title')
            ->toHaveKey('body');
    }

    public function test_handles_invalid_template()
    {
        $template = $this->getTemplate('nonexistent');
        expect($template)->toBeNull();
    }

    public function test_feature_template_has_expected_content()
    {
        $template = $this->getTemplate('feature');
        
        expect($template['title'])->toContain('Feature:');
        expect($template['body'])->toContain('What does this PR do?');
    }

    public function test_bugfix_template_has_expected_content()
    {
        $template = $this->getTemplate('bugfix');
        
        expect($template['title'])->toContain('Fix:');
        expect($template['body'])->toContain('Bug Description');
    }

    public function test_all_templates_have_base_branch()
    {
        $templateTypes = ['feature', 'bugfix', 'hotfix', 'breaking', 'docs'];
        
        foreach ($templateTypes as $type) {
            $template = $this->getTemplate($type);
            expect($template)->toHaveKey('base', 'main');
        }
    }
}