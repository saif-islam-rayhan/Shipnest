<?php

namespace App\Models\Concerns;

trait FormatsCurrency
{
    protected function formatCurrency(?float $amount): string
    {
        $symbol = config('shipnest.currency_symbol', '৳');

        return $symbol.number_format((float) ($amount ?? 0), 2);
    }

    protected function calculateDiscountPercent(?float $price, ?float $comparePrice): ?int
    {
        if (! $comparePrice || ! $price || $comparePrice <= $price) {
            return null;
        }

        return (int) round((($comparePrice - $price) / $comparePrice) * 100);
    }
}
