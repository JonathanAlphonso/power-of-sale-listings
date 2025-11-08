<?php

declare(strict_types=1);

namespace App\Support;

class ResoFilters
{
    public static function powerOfSale(): string
    {
        return 'PublicRemarks ne null and '
            ."startswith(TransactionType,'For Sale') and ("
            ."contains(PublicRemarks,'power of sale') or "
            ."contains(PublicRemarks,'Power of Sale') or "
            ."contains(PublicRemarks,'POWER OF SALE') or "
            ."contains(PublicRemarks,'Power-of-Sale') or "
            ."contains(PublicRemarks,'Power-of-sale') or "
            ."contains(PublicRemarks,'P.O.S') or "
            ."contains(PublicRemarks,' POS ') or "
            ."contains(PublicRemarks,' POS,') or "
            ."contains(PublicRemarks,' POS.') or "
            ."contains(PublicRemarks,' POS-')"
            .')';
    }
}
