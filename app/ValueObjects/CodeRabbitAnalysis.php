<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Collection;

class CodeRabbitAnalysis
{
    public function __construct(
        public readonly int $prNumber,
        public readonly string $repository,
        public readonly int $totalComments,
        public readonly array $commentsByFile,
        public readonly array $commentsByCategory,
        public readonly array $aiSummary,
        public readonly Collection $rawComments,
    ) {}

    public function getVoiceNarration(): string
    {
        if ($this->totalComments === 0) {
            return 'CodeRabbit found no issues with this PR. Clean code, ready to ship!';
        }

        $summary = $this->aiSummary['executive_summary'] ?? 'CodeRabbit provided feedback on this PR.';

        $priorityBreakdown = $this->getPriorityBreakdown();
        $topFiles = $this->getTopFilesWithIssues();
        $keyThemes = $this->aiSummary['key_themes'] ?? [];

        $narration = "{$summary} ";
        $narration .= "Total feedback: {$this->totalComments} comments. ";
        $narration .= $priorityBreakdown.' ';

        if (! empty($topFiles)) {
            $narration .= 'Main files needing attention: '.implode(', ', $topFiles).'. ';
        }

        if (! empty($keyThemes)) {
            $narration .= 'Key themes: '.implode(', ', array_slice($keyThemes, 0, 3)).'. ';
        }

        $actionPriorities = $this->aiSummary['action_priorities'] ?? [];
        if (! empty($actionPriorities)) {
            $narration .= 'Top priority: '.$actionPriorities[0].'.';
        }

        return $narration;
    }

    private function getPriorityBreakdown(): string
    {
        $priorities = $this->rawComments->groupBy('priority')->map->count();

        $parts = [];
        if ($priorities->get('high', 0) > 0) {
            $parts[] = "{$priorities['high']} high priority";
        }
        if ($priorities->get('medium', 0) > 0) {
            $parts[] = "{$priorities['medium']} medium priority";
        }
        if ($priorities->get('low', 0) > 0) {
            $parts[] = "{$priorities['low']} low priority";
        }

        return empty($parts) ? '' : 'Breakdown: '.implode(', ', $parts).'.';
    }

    private function getTopFilesWithIssues(int $limit = 3): array
    {
        return array_keys(
            array_slice(
                array_filter($this->commentsByFile, fn ($file) => $file !== null),
                0,
                $limit,
                true
            )
        );
    }
}
