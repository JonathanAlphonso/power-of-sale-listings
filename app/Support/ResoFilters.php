<?php

declare(strict_types=1);

namespace App\Support;

class ResoFilters
{
    /**
     * @return array<int, string>
     */
    public static function powerOfSaleKeywords(): array
    {
        return [
            'power of sale',
            'Power of Sale',
            'Power Of Sale',
            'POWER OF SALE',
            'Power-of-Sale',
            'Power-of-sale',
            'P.O.S',
            ' POS ',
            ' POS,',
            ' POS.',
            ' POS-',
        ];
    }

    public static function isPowerOfSaleRemarks(?string $remarks): bool
    {
        if (! is_string($remarks) || $remarks === '') {
            return false;
        }

        $haystack = mb_strtolower($remarks);

        foreach (self::powerOfSaleKeywords() as $keyword) {
            if ($keyword === '') {
                continue;
            }

            if (str_contains($haystack, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    public static function powerOfSale(): string
    {
        // Include common Power of Sale phrasing variants. Some RESO feeds are case-sensitive
        // for string functions like contains(), so include multiple case variants.
        $keywords = static::powerOfSaleKeywords();

        $clauses = [];

        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            $escaped = str_replace("'", "''", $keyword);
            $clauses[] = "contains(PublicRemarks,'{$escaped}')";
        }

        $remarksFilter = implode(' or ', $clauses);

        return 'PublicRemarks ne null and '
            ."startswith(TransactionType,'For Sale') and ({$remarksFilter})";
    }
}
