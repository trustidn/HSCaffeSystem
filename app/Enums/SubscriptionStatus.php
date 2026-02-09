<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Trial = 'trial';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Expired => 'Kadaluarsa',
            self::Cancelled => 'Dibatalkan',
            self::Trial => 'Masa Percobaan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Expired => 'red',
            self::Cancelled => 'zinc',
            self::Trial => 'amber',
        };
    }
}
