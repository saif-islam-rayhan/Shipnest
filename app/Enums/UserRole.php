<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Merchant = 'merchant';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Merchant => 'Merchant',
            self::Customer => 'Customer',
        };
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    public function isMerchant(): bool
    {
        return $this === self::Merchant;
    }

    public function isCustomer(): bool
    {
        return $this === self::Customer;
    }
}
