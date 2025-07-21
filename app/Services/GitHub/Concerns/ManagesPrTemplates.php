<?php

namespace App\Services\GitHub\Concerns;

use function Laravel\Prompts\text;

trait ManagesPrTemplates
{
    /**
     * Get PR template by type
     */
    public function getTemplate(string $templateType): ?array
    {
        $templates = [
            'feature' => [
                'title' => '✨ Feature: ',
                'body' => $this->getFeatureTemplate(),
                'base' => 'main',
            ],
            'bugfix' => [
                'title' => '🐛 Fix: ',
                'body' => $this->getBugfixTemplate(),
                'base' => 'main',
            ],
            'hotfix' => [
                'title' => '🚨 Hotfix: ',
                'body' => $this->getHotfixTemplate(),
                'base' => 'main',
            ],
            'breaking' => [
                'title' => '💥 Breaking: ',
                'body' => $this->getBreakingTemplate(),
                'base' => 'main',
            ],
            'docs' => [
                'title' => '📚 Docs: ',
                'body' => $this->getDocsTemplate(),
                'base' => 'main',
            ],
        ];

        return $templates[$templateType] ?? null;
    }

    /**
     * Get feature PR template
     */
    private function getFeatureTemplate(): string
    {
        return <<<'MARKDOWN'
## 🎯 What does this PR do?
Brief description of the feature and its value.

## ✨ Key Changes
- [ ] New feature implementation
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] Breaking changes (if any)

## 🧪 Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed

## 📸 Screenshots/GIFs
If applicable, add screenshots or GIFs to showcase the feature.

## 🔗 Related Issues
Closes #issue_number

## 📋 Checklist
- [ ] Code follows project standards
- [ ] Self-review completed
- [ ] Ready for review
MARKDOWN;
    }

    /**
     * Get bugfix PR template
     */
    private function getBugfixTemplate(): string
    {
        return <<<'MARKDOWN'
## 🐛 Bug Description
What bug does this PR fix?

## 🔍 Root Cause
What was causing the issue?

## ✅ Solution
How does this fix address the problem?

## 🧪 Testing
- [ ] Bug reproduction test added
- [ ] Fix verified locally
- [ ] No regression introduced

## 🔗 Related Issues
Fixes #issue_number

## 📋 Checklist
- [ ] Root cause identified
- [ ] Fix implemented and tested
- [ ] No side effects
MARKDOWN;
    }

    /**
     * Get hotfix PR template
     */
    private function getHotfixTemplate(): string
    {
        return <<<'MARKDOWN'
## 🚨 Critical Issue
Describe the critical issue requiring immediate fix.

## ⚡ Urgency
Why this needs to be merged ASAP.

## 🔧 Quick Fix
Minimal changes to resolve the critical issue.

## 🧪 Verification
- [ ] Issue reproduced
- [ ] Fix verified
- [ ] Minimal risk assessment

## 🔗 Related Issues
Emergency fix for #issue_number

## ⚠️ Post-Deploy Actions
- [ ] Monitor deployment
- [ ] Verify fix in production
- [ ] Create follow-up tasks if needed
MARKDOWN;
    }

    /**
     * Get breaking change PR template
     */
    private function getBreakingTemplate(): string
    {
        return <<<'MARKDOWN'
## 💥 Breaking Changes
List all breaking changes introduced.

## 🎯 Motivation
Why are these breaking changes necessary?

## 📝 Migration Guide
How should users/developers adapt to these changes?

## 🔄 Backward Compatibility
What options exist for backward compatibility?

## 🧪 Testing
- [ ] All tests updated for breaking changes
- [ ] Migration scripts tested
- [ ] Documentation updated

## 📢 Communication Plan
- [ ] Release notes prepared
- [ ] Team notified
- [ ] Users will be notified

## 🔗 Related Issues
Implements breaking change from #issue_number
MARKDOWN;
    }

    /**
     * Get documentation PR template
     */
    private function getDocsTemplate(): string
    {
        return <<<'MARKDOWN'
## 📚 Documentation Changes
What documentation is being added/updated?

## 🎯 Purpose
Why is this documentation needed?

## 📝 Content Summary
Brief overview of the content being added.

## ✅ Review Checklist
- [ ] Content is accurate
- [ ] Examples work correctly
- [ ] Links are valid
- [ ] Formatting is consistent

## 🔗 Related Issues
Documents feature/fix from #issue_number

## 📋 Validation
- [ ] Reviewed for clarity
- [ ] Checked for typos
- [ ] Examples tested
MARKDOWN;
    }

    /**
     * Apply template interactively with user input
     */
    public function applyTemplateInteractively($command, string $templateType): array
    {
        $template = $this->getTemplate($templateType);
        if (!$template) {
            return [];
        }

        if ($command) {
            $command->line("<comment>📝 Using {$templateType} PR template</comment>");
            $command->newLine();
        }
        
        // Get title with template prefix
        $title = text(
            label: 'PR title',
            placeholder: $template['title'],
            default: $template['title']
        );
        
        // Show template and allow editing
        if ($command) {
            $command->line('<comment>Template body loaded. You can edit or use as-is:</comment>');
        }
        $body = $this->promptForMarkdown($command, 'PR body', $template['body']);
        
        return [
            'title' => $title,
            'body' => $body,
            'base' => $template['base'],
        ];
    }
}