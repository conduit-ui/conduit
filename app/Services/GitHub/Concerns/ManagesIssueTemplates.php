<?php

namespace App\Services\GitHub\Concerns;

use function Laravel\Prompts\text;

trait ManagesIssueTemplates
{
    /**
     * Get issue template by type
     */
    public function getTemplate(string $templateType): ?array
    {
        $templates = [
            'bug' => [
                'title' => '🐛 Bug: ',
                'body' => $this->getBugTemplate(),
                'labels' => ['bug'],
            ],
            'feature' => [
                'title' => '✨ Feature: ',
                'body' => $this->getFeatureTemplate(),
                'labels' => ['enhancement'],
            ],
            'epic' => [
                'title' => '🚀 Epic: ',
                'body' => $this->getEpicTemplate(),
                'labels' => ['epic'],
            ],
            'question' => [
                'title' => '❓ Question: ',
                'body' => $this->getQuestionTemplate(),
                'labels' => ['question'],
            ],
        ];

        return $templates[$templateType] ?? null;
    }

    /**
     * Get bug report template
     */
    private function getBugTemplate(): string
    {
        return <<<'MARKDOWN'
## 🐛 Bug Description
A clear and concise description of what the bug is.

## 🔄 Steps to Reproduce
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

## ✅ Expected Behavior
A clear and concise description of what you expected to happen.

## ❌ Actual Behavior
A clear and concise description of what actually happened.

## 📸 Screenshots
If applicable, add screenshots to help explain your problem.

## 🌍 Environment
- OS: [e.g. macOS, Ubuntu]
- Version: [e.g. v1.2.3]
- PHP Version: [e.g. 8.1]

## 📋 Additional Context
Add any other context about the problem here.
MARKDOWN;
    }

    /**
     * Get feature request template
     */
    private function getFeatureTemplate(): string
    {
        return <<<'MARKDOWN'
## ✨ Feature Summary
A clear and concise description of the feature you'd like to see.

## 🎯 Problem Statement
What problem does this feature solve? What use case does it address?

## 💡 Proposed Solution
Describe the solution you'd like to see implemented.

## 🔄 User Stories
- As a [type of user], I want [some goal] so that [some reason]
- As a [type of user], I want [some goal] so that [some reason]

## 🎨 Mockups/Examples
If applicable, add mockups, wireframes, or examples.

## ✅ Acceptance Criteria
- [ ] Criterion 1
- [ ] Criterion 2
- [ ] Criterion 3

## 🚀 Additional Context
Add any other context, alternatives considered, or related issues.
MARKDOWN;
    }

    /**
     * Get epic template
     */
    private function getEpicTemplate(): string
    {
        return <<<'MARKDOWN'
## 🚀 Epic Overview
High-level description of the epic and its goals.

## 🎯 Business Value
Why is this epic important? What value does it deliver?

## 👥 Target Users
Who will benefit from this epic?

## 📋 User Stories
Break down the epic into user stories:

### Core Features
- [ ] #[issue-number] Story 1
- [ ] #[issue-number] Story 2
- [ ] #[issue-number] Story 3

### Nice to Have
- [ ] #[issue-number] Story 4
- [ ] #[issue-number] Story 5

## ✅ Definition of Done
- [ ] All user stories completed
- [ ] Tests written and passing
- [ ] Documentation updated
- [ ] Performance requirements met

## 🗓️ Timeline
Target completion: [Date]

## 📊 Success Metrics
How will we measure success?
MARKDOWN;
    }

    /**
     * Get question template
     */
    private function getQuestionTemplate(): string
    {
        return <<<'MARKDOWN'
## ❓ Question
What would you like to know?

## 🔍 Context
Provide context about what you're trying to achieve.

## 🤔 What I've Tried
Describe what you've already attempted or researched.

## 📚 Documentation Checked
- [ ] README
- [ ] API Documentation
- [ ] Examples
- [ ] Related Issues

## 💭 Additional Information
Any other details that might be helpful.
MARKDOWN;
    }

    /**
     * Apply template interactively with user input
     */
    public function applyTemplateInteractively($command, string $templateType): array
    {
        $template = $this->getTemplate($templateType);
        if (! $template) {
            return [];
        }

        if ($command) {
            $command->line("<comment>📝 Using {$templateType} template</comment>");
            $command->newLine();
        }

        // Get title with template prefix
        $title = text(
            label: 'Issue title',
            placeholder: $template['title'],
            default: $template['title']
        );

        // Show template and allow editing
        if ($command) {
            $command->line('<comment>Template body loaded. You can edit or use as-is:</comment>');
        }
        $body = $this->promptForMarkdown($command, 'Issue body', $template['body']);

        return [
            'title' => $title,
            'body' => $body,
            'labels' => $template['labels'] ?? [],
        ];
    }
}
