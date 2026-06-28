<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Sslcommerz = 'sslcommerz';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Stripe = 'stripe';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Cash on Delivery',
            self::Sslcommerz => 'SSLCommerz (Card / Banking)',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Stripe => 'Card (Stripe)',
        };
    }

    public function isOnline(): bool
    {
        return $this !== self::Cod;
    }

    /** @return list<self> */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method) => config("payment.enabled.{$method->value}", true)));
    }
}
