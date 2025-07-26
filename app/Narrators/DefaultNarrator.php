<?php

declare(strict_types=1);

namespace App\Narrators;

use App\Contracts\VoiceNarratorInterface;
use App\ValueObjects\NarrationContent;
use App\ValueObjects\SpeechConfiguration;

class DefaultNarrator implements VoiceNarratorInterface
{
    public function generate(NarrationContent $content, SpeechConfiguration $config): string
    {
        // Generate professional, neutral narration
        $narration = $this->buildDefaultNarration($content);

        return $this->formatForSpeech($narration);
    }

    public function supports(string $contentType): bool
    {
        // Default narrator supports all content types
        return true;
    }

    private function buildDefaultNarration(NarrationContent $content): string
    {
        $parts = [];

        // Title and basic info
        if ($content->title) {
            $parts[] = "Title: {$content->title}";
        }

        if ($content->author) {
            $parts[] = "Author: {$content->author}";
        }

        if ($content->state) {
            $parts[] = "Status: {$content->state}";
        }

        // Description content
        if ($content->description) {
            $parts[] = 'Description: '.$this->summarizeContent($content->description);
        }

        // Comments if included
        if ($content->comments && $content->comments->isNotEmpty()) {
            $parts[] = $content->getCommentsSummary();
        }

        // Reviews if included
        if ($content->reviews && $content->reviews->isNotEmpty()) {
            $parts[] = $content->getReviewsSummary();
        }

        // Statistics if included (for PRs)
        if ($content->type === 'pull_request') {
            $statsSummary = $content->getStatsSummary();
            if ($statsSummary) {
                $parts[] = $statsSummary;
            }
        }

        return implode('. ', $parts).'.';
    }

    private function summarizeContent(string $content): string
    {
        // Remove markdown formatting
        $content = strip_tags($content);
        $content = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $content);
        $content = preg_replace('/[#*`_~]/', '', $content);

        // Trim to reasonable length for speech
        if (strlen($content) > 200) {
            $content = substr($content, 0, 197).'...';
        }

        return trim($content);
    }

    private function formatForSpeech(string $text): string
    {
        // Format text for better speech synthesis
        $text = str_replace(['PR', 'GitHub', 'API'], ['pull request', 'Git Hub', 'A P I'], $text);
        $text = preg_replace_callback('/\b([A-Z]{2,})\b/', function ($matches) {
            return implode(' ', str_split($matches[1]));
        }, $text);

        // Clean up extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
