<?php

declare(strict_types=1);

namespace App\Support;

class BoardCode
{
    public static function fromSystemName(?string $name): string
    {
        $value = trim((string) ($name ?? ''));
        if ($value === '') {
            return 'UNKNOWN';
        }

        // Already a compact code (e.g., TRREB, OREB)
        if (strlen($value) <= 16 && ! str_contains($value, ' ') && preg_match('/^[A-Za-z0-9]+$/', $value)) {
            return strtoupper($value);
        }

        // Build an acronym from words, skipping common stopwords
        $clean = preg_replace('/[^A-Za-z0-9\-\s]/', ' ', $value) ?? $value;
        $tokens = preg_split('/[\s\-]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['of', 'the', 'and', 'for'];
        $letters = '';

        foreach ($tokens as $t) {
            $lower = strtolower($t);
            if (in_array($lower, $stop, true)) {
                continue;
            }
            $letters .= strtoupper(substr($t, 0, 1));
            if (strlen($letters) >= 16) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'UNKNOWN';
    }
}
