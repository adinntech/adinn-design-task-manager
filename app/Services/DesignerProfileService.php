<?php

namespace App\Services;

/**
 * Small shared normalizer for the Designer profile fields (experienced
 * verticals + skills) so Admin\UserController and ProfileController never
 * drift on how duplicates/whitespace are handled.
 */
class DesignerProfileService
{
    public function normalizeVerticals(array $verticals): array
    {
        return array_values(array_unique($verticals));
    }

    /**
     * Trims whitespace and dedupes case-insensitively (so "Photoshop" and
     * "photoshop" collapse to one tag) while preserving first-seen casing.
     */
    public function normalizeSkills(array $skills): array
    {
        return collect($skills)
            ->map(fn ($skill) => trim((string) $skill))
            ->filter()
            ->unique(fn ($skill) => mb_strtolower($skill))
            ->values()
            ->all();
    }
}
