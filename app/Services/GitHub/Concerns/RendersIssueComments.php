<?php

namespace App\Services\GitHub\Concerns;

use LaravelZero\Framework\Commands\Command;

trait RendersIssueComments
{
    /**
     * Render a single comment with rich formatting
     */
    protected function renderComment(Command $command, array $comment, int $commentNumber): void
    {
        // Comment header
        $author = $comment['user']['login'];
        $createdAt = $this->formatDate($comment['created_at']);
        $updatedAt = $comment['updated_at'] !== $comment['created_at'] 
            ? " (edited {$this->formatDate($comment['updated_at'])})" 
            : '';
        
        $command->line("<fg=cyan;options=bold>💬 Comment #{$commentNumber}</fg=cyan;options=bold>");
        $command->line("👤 <info>{$author}</info> • 📅 {$createdAt}{$updatedAt}");
        
        // Author association badge
        if (isset($comment['author_association']) && $comment['author_association'] !== 'NONE') {
            $badge = $this->getAuthorAssociationBadge($comment['author_association']);
            $command->line("🏷️  {$badge}");
        }
        
        $command->newLine();
        
        // Comment body
        if (!empty($comment['body'])) {
            $this->renderMarkdownText($command, $comment['body']);
        } else {
            $command->line('<fg=gray>No content</fg=gray>');
        }
        
        // Comment reactions
        if (isset($comment['reactions']) && $comment['reactions']['total_count'] > 0) {
            $this->renderReactions($command, $comment['reactions']);
        }
        
        // Comment URL
        $command->newLine();
        $command->line("🔗 <href={$comment['html_url']}>View on GitHub</href>");
    }
    
    /**
     * Render comment reactions
     */
    protected function renderReactions(Command $command, array $reactions): void
    {
        if ($reactions['total_count'] === 0) {
            return;
        }
        
        $command->newLine();
        $reactionEmojis = [];
        
        $emojiMap = [
            '+1' => '👍',
            '-1' => '👎',
            'laugh' => '😄',
            'hooray' => '🎉',
            'confused' => '😕',
            'heart' => '❤️',
            'rocket' => '🚀',
            'eyes' => '👀'
        ];
        
        foreach ($emojiMap as $reaction => $emoji) {
            if (isset($reactions[$reaction]) && $reactions[$reaction] > 0) {
                $count = $reactions[$reaction];
                $reactionEmojis[] = "{$emoji} {$count}";
            }
        }
        
        if (!empty($reactionEmojis)) {
            $command->line("🎭 Reactions: " . implode(' • ', $reactionEmojis));
        }
    }
    
    /**
     * Get author association badge
     */
    protected function getAuthorAssociationBadge(string $association): string
    {
        return match ($association) {
            'OWNER' => '<fg=red;options=bold>👑 OWNER</fg=red;options=bold>',
            'MEMBER' => '<fg=blue;options=bold>👥 MEMBER</fg=blue;options=bold>',
            'COLLABORATOR' => '<fg=green;options=bold>🤝 COLLABORATOR</fg=green;options=bold>',
            'CONTRIBUTOR' => '<fg=yellow>✨ CONTRIBUTOR</fg=yellow>',
            'FIRST_TIME_CONTRIBUTOR' => '<fg=magenta>🌟 FIRST TIME CONTRIBUTOR</fg=magenta>',
            'FIRST_TIMER' => '<fg=magenta>🆕 FIRST TIMER</fg=magenta>',
            'MANNEQUIN' => '<fg=gray>🤖 MANNEQUIN</fg=gray>',
            default => "<fg=gray>{$association}</fg=gray>"
        };
    }
    
    /**
     * Render comment thread summary
     */
    protected function renderCommentSummary(Command $command, array $comments): void
    {
        if (empty($comments)) {
            return;
        }
        
        $command->newLine();
        $this->renderSeparator($command, 'Comment Summary');
        $command->newLine();
        
        // Participation analysis
        $participants = [];
        $totalComments = count($comments);
        
        foreach ($comments as $comment) {
            $author = $comment['user']['login'];
            $participants[$author] = ($participants[$author] ?? 0) + 1;
        }
        
        $command->line("👥 <comment>Participants:</comment> " . count($participants));
        $command->line("💬 <comment>Total Comments:</comment> {$totalComments}");
        
        // Top contributors
        arsort($participants);
        $topParticipants = array_slice($participants, 0, 3, true);
        
        $command->newLine();
        $command->line("<comment>Most Active:</comment>");
        foreach ($topParticipants as $author => $count) {
            $percentage = round(($count / $totalComments) * 100);
            $command->line("  • <info>{$author}</info>: {$count} comments ({$percentage}%)");
        }
        
        // Timeline
        if ($totalComments > 1) {
            $firstComment = reset($comments);
            $lastComment = end($comments);
            $timespan = strtotime($lastComment['created_at']) - strtotime($firstComment['created_at']);
            
            $command->newLine();
            $command->line("⏱️  <comment>Discussion Timeline:</comment> " . $this->formatTimespan($timespan));
        }
    }
    
    /**
     * Format timespan between comments
     */
    protected function formatTimespan(int $seconds): string
    {
        if ($seconds < 3600) {
            return floor($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return floor($seconds / 3600) . ' hours';
        } elseif ($seconds < 2592000) {
            return floor($seconds / 86400) . ' days';
        } else {
            return floor($seconds / 2592000) . ' months';
        }
    }
    
    /**
     * Render comment navigation
     */
    protected function renderCommentNavigation(Command $command, int $currentComment, int $totalComments): void
    {
        if ($totalComments <= 1) {
            return;
        }
        
        $command->newLine();
        $progress = str_repeat('●', $currentComment) . str_repeat('○', $totalComments - $currentComment);
        $command->line("<fg=gray>Progress: {$progress} ({$currentComment}/{$totalComments})</fg=gray>");
    }
}