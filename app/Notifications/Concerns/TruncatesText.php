<?php

namespace App\Notifications\Concerns;

trait TruncatesText
{
    /**
     * Word-based truncation for notification previews. The full text is never
     * mutated at the source (comments/rework reasons keep their original,
     * untruncated value in their own tables) — only the short preview stored
     * on the notification itself is shortened.
     */
    protected function truncateWords(string $text, int $limit = 30): string
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) <= $limit) {
            return trim($text);
        }

        return implode(' ', array_slice($words, 0, $limit)).'...';
    }
}
