<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case Waste = 'waste';
    case OrderDeduct = 'order_deduct';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stok Masuk',
            self::Out => 'Stok Keluar',
            self::Adjustment => 'Penyesuaian',
            self::Waste => 'Waste/Rusak',
            self::OrderDeduct => 'Pengurangan Order',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::In => 'emerald',
            self::Out => 'red',
            self::Adjustment => 'amber',
            self::Waste => 'rose',
            self::OrderDeduct => 'sky',
        };
    }
}
