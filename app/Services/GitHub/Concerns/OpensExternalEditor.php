<?php

namespace App\Services\GitHub\Concerns;

trait OpensExternalEditor
{
    /**
     * Open external editor for markdown editing
     */
    public function openEditor(string $initialContent = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'conduit_edit_') . '.md';
        file_put_contents($tempFile, $initialContent);

        $editor = $_ENV['EDITOR'] ?? $_ENV['VISUAL'] ?? 'vim';
        system("{$editor} {$tempFile}");

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content ?: $initialContent;
    }

    /**
     * Open issue editor with structured format
     */
    public function openIssueEditor(array $currentIssue): array
    {
        $template = $this->generateEditTemplate($currentIssue);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'conduit_issue_edit_') . '.md';
        file_put_contents($tempFile, $template);

        $editor = $_ENV['EDITOR'] ?? $_ENV['VISUAL'] ?? 'vim';
        system("{$editor} {$tempFile}");

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $this->parseEditTemplate($content);
    }

    /**
     * Generate edit template for structured editing
     */
    private function generateEditTemplate(array $issue): string
    {
        return <<<TEMPLATE
# Issue Edit Template
# Lines starting with # are comments and will be ignored
# Edit the title and body below, then save and close

TITLE: {$issue['title']}

BODY:
{$issue['body']}
TEMPLATE;
    }

    /**
     * Parse edit template content
     */
    private function parseEditTemplate(string $content): array
    {
        $lines = explode("\n", $content);
        $result = ['title' => '', 'body' => ''];
        $inBody = false;
        $bodyLines = [];

        foreach ($lines as $line) {
            // Skip comments
            if (preg_match('/^#/', $line)) {
                continue;
            }

            if (preg_match('/^TITLE:\s*(.+)$/', $line, $matches)) {
                $result['title'] = trim($matches[1]);
                continue;
            }

            if (preg_match('/^BODY:\s*$/', $line)) {
                $inBody = true;
                continue;
            }

            if ($inBody) {
                $bodyLines[] = $line;
            }
        }

        $result['body'] = implode("\n", $bodyLines);
        return $result;
    }

    /**
     * Get the preferred editor command
     */
    protected function getEditorCommand(): string
    {
        return $_ENV['EDITOR'] ?? $_ENV['VISUAL'] ?? 'vim';
    }

    /**
     * Create a temporary file for editing
     */
    protected function createTempFile(string $content, string $prefix = 'conduit_', string $extension = '.md'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), $prefix) . $extension;
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    /**
     * Safe cleanup of temporary files
     */
    protected function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}