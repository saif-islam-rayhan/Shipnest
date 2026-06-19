<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Sslcommerz = 'sslcommerz';
    case Bkash = 'bkash';
    case Nagad = 'nagad';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Cash on Delivery',
            self::Sslcommerz => 'SSLCommerz',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
        };
    }

    public function isOnline(): bool
    {
        return $this !== self::Cod;
    }
}
